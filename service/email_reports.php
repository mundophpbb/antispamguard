<?php
/**
 * Scheduled email alerts and activity summaries.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class email_reports
{
	const SETTINGS_KEY = 'antispamguard_email_settings';
	protected const PENDING_KEY = 'antispamguard_email_pending_alert';

	const LIMITS = array(
		'window' => array(2, 1440),
		'blocks' => array(1, 1000000),
		'ips' => array(1, 1000000),
		'cooldown' => array(1, 10080),
	);

	const DEFAULTS = array(
		'recipient' => '',
		'alert_enabled' => 0,
		'window' => 10,
		'blocks' => 30,
		'ips' => 5,
		'cooldown' => 60,
		'digest' => 'off',
		'digest_start' => 0,
	);

	protected const RETRY_SECONDS = 300;

	protected $config;
	protected $config_text;
	protected $db;
	protected $table_prefix;
	protected $root_path;
	protected $php_ext;
	protected $history;
	protected $delivery_detail = '';

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		\phpbb\db\driver\driver_interface $db,
		$table_prefix,
		$root_path,
		$php_ext,
		?delivery_history $history = null
	)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->history = $history;
	}

	public function get_settings()
	{
		$settings = json_decode((string) $this->config_text->get(self::SETTINGS_KEY), true);
		return is_array($settings) ? $settings + self::DEFAULTS : self::DEFAULTS;
	}

	public function save_settings(array $settings, array $previous, $now)
	{
		$settings['digest_start'] = (int) $previous['digest_start'];
		if ($settings['digest'] !== $previous['digest'] || $settings['recipient'] !== $previous['recipient'])
		{
			$settings['digest_start'] = $this->period_end($settings['digest'], $now);
		}

		$this->config_text->set(self::SETTINGS_KEY, json_encode($settings));
		if (!$settings['alert_enabled'])
		{
			$this->config->set(self::PENDING_KEY, '', false);
		}
		$poll_interval = $settings['alert_enabled'] ? 60 : ($settings['digest'] !== 'off' ? 300 : 0);
		if ($poll_interval > 0 && $this->get_state('poll_interval') === 0)
		{
			$this->set_state('cron_enabled_at', $now);
		}
		$this->set_state('poll_interval', $poll_interval);
	}

	public function board_email_enabled()
	{
		return !empty($this->config['email_enable']);
	}

	public function test_delivery($now)
	{
		if (!$this->board_email_enabled())
		{
			return 'email_disabled';
		}

		$recipient = $this->get_settings()['recipient'];
		if (!filter_var($recipient, FILTER_VALIDATE_EMAIL))
		{
			return 'email_config';
		}
		if (!delivery_test::reserve($this->config, 'antispamguard_email_test_attempt', $now))
		{
			return 'wait';
		}

		$sent = $this->send('antispamguard_delivery_test', $recipient, array());
		if ($this->history !== null)
		{
			$this->history->record('email', 'test', $sent, $now, $this->delivery_detail);
		}
		return $sent ? 'email_sent' : 'email_' . delivery_history::outcome(false, $this->delivery_detail);
	}

	public function get_delivery_detail()
	{
		return $this->delivery_detail;
	}

	public function is_runnable()
	{
		return $this->board_email_enabled() && $this->get_state('poll_interval') > 0;
	}

	public function should_run($now)
	{
		return $this->is_runnable() && $this->get_state('last_poll') + $this->get_state('poll_interval') <= $now;
	}

	public function get_state($field)
	{
		$key = 'antispamguard_email_' . $field;
		return isset($this->config[$key]) ? (int) $this->config[$key] : 0;
	}

	protected function set_state($field, $value)
	{
		$this->config->set('antispamguard_email_' . $field, (int) $value, false);
	}

	public function run($now)
	{
		if (!$this->should_run($now))
		{
			return;
		}

		$this->set_state('last_poll', $now);
		$settings = $this->get_settings();
		if (!filter_var($settings['recipient'], FILTER_VALIDATE_EMAIL))
		{
			return;
		}

		if ($settings['alert_enabled'])
		{
			$spike = new spike_alert($this->config, $this->db, $this->table_prefix, 'antispamguard_email_');
			$sent = $spike->check($settings, $settings['recipient'], $now, function (array $vars) use ($settings)
			{
				return $this->send('antispamguard_volume_alert', $settings['recipient'], $vars);
			});
			if ($sent !== null && $this->history !== null)
			{
				$this->history->record('email', 'spike', $sent, $now, $this->delivery_detail);
			}
		}
		if (in_array($settings['digest'], array('daily', 'weekly'), true))
		{
			$this->check_digest($settings, $now);
		}
	}

	public function get_cron_status(array $settings, $now)
	{
		$enabled = !empty($settings['alert_enabled']) || in_array($settings['digest'], array('daily', 'weekly'), true);
		$runnable = $this->is_runnable() && filter_var($settings['recipient'], FILTER_VALIDATE_EMAIL);

		return cron_status::describe(
			$enabled, $runnable, $this->get_state('last_poll'), $this->get_state('cron_enabled_at'), $now
		);
	}

	public function get_digest_schedule(array $settings, $now)
	{
		if (!in_array($settings['digest'], array('daily', 'weekly'), true))
		{
			return array();
		}

		$period = $settings['digest'] === 'weekly' ? 604800 : 86400;
		$until = $this->period_end($settings['digest'], $now);
		$last = max((int) $settings['digest_start'], $this->get_state('digest_end'));
		$pending = $until > $last;
		if (!$pending)
		{
			$until = $this->period_end($settings['digest'], max($until, $last)) + $period;
		}

		return array(
			'start' => $until - $period,
			'end' => $until,
			'pending' => $pending,
			'retry_at' => $pending ? max($until, $this->get_state('digest_failed') + self::RETRY_SECONDS) : 0,
		);
	}

	protected function check_digest(array $settings, $now)
	{
		$schedule = $this->get_digest_schedule($settings, $now);
		if (!$schedule['pending'] || $schedule['retry_at'] > $now)
		{
			return;
		}

		$from = $schedule['start'];
		$until = $schedule['end'];
		$vars = $this->get_digest_counts($from, $until);
		$vars['S_WEEKLY'] = $settings['digest'] === 'weekly';
		$vars['PERIOD_START'] = gmdate('Y-m-d H:i:s', $from);
		$vars['PERIOD_END'] = gmdate('Y-m-d H:i:s', $until);
		$sent = $this->send('antispamguard_activity_digest', $settings['recipient'], $vars);
		if ($sent)
		{
			$this->set_state('digest_end', $until);
			$this->set_state('digest_sent', $now);
		}
		else
		{
			$this->set_state('digest_failed', $now);
		}
		if ($this->history !== null)
		{
			$this->history->record('email', 'digest', $sent, $now, $this->delivery_detail);
		}
	}

	protected function period_end($digest, $now)
	{
		$day_start = intdiv($now, 86400) * 86400;
		return $digest === 'weekly' ? $day_start - (((int) gmdate('N', $now) - 1) * 86400) : $day_start;
	}

	protected function get_digest_counts($from, $until)
	{
		$sql = 'SELECT COUNT(log_id) AS total,
				SUM(CASE WHEN action = \'blocked\' THEN 1 ELSE 0 END) AS blocked,
				SUM(CASE WHEN action = \'review\' THEN 1 ELSE 0 END) AS review_count,
				SUM(CASE WHEN action = \'simulation\' THEN 1 ELSE 0 END) AS simulation_count
			FROM ' . $this->table_prefix . "antispamguard_log
			WHERE reason <> 'diagnostic_test'
				AND log_time >= " . (int) $from . ' AND log_time < ' . (int) $until;
		$result = $this->db->sql_query($sql);
		$general = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql = 'SELECT COUNT(log_id) AS total,
				SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked
			FROM ' . $this->table_prefix . 'antispamguard_sfs_log
			WHERE created_at >= ' . (int) $from . ' AND created_at < ' . (int) $until;
		$result = $this->db->sql_query($sql);
		$sfs = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return array(
			'GENERAL_TOTAL' => (int) $general['total'],
			'GENERAL_BLOCKED' => (int) $general['blocked'],
			'GENERAL_REVIEW' => (int) $general['review_count'],
			'GENERAL_SIMULATION' => (int) $general['simulation_count'],
			'SFS_TOTAL' => (int) $sfs['total'],
			'SFS_BLOCKED' => (int) $sfs['blocked'],
		);
	}

	protected function send($template, $recipient, array $vars)
	{
		$this->delivery_detail = 'email_unconfirmed';
		try
		{
			if (!class_exists('messenger', false))
			{
				include_once($this->root_path . 'includes/functions_messenger.' . $this->php_ext);
			}

			$lang = (string) $this->config['default_lang'];
			if (!in_array($lang, array('en', 'pt_br', 'fr'), true))
			{
				$lang = 'en';
			}

			$messenger = new \messenger(false);
			$messenger->template($template, $lang, dirname(__DIR__) . '/language/' . $lang . '/email');
			$messenger->to($recipient);
			$messenger->assign_vars($vars);
			$sent = (bool) $messenger->send(NOTIFY_EMAIL);
			$this->delivery_detail = $sent ? '' : 'email_transport_failure';
			return $sent;
		}
		catch (\Exception $exception)
		{
			return false;
		}
	}
}
