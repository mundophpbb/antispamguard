<?php
/**
 * Channel-independent block spike detection and delivery state.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class spike_alert
{
	const BLOCKED_FILTER = "action = 'blocked' AND reason <> 'diagnostic_test'";

	protected const RETRY_SECONDS = 300;
	protected $config;
	protected $db;
	protected $table_prefix;
	protected $prefix;

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix, $prefix)
	{
		$this->config = $config;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->prefix = $prefix;
	}

	public function get_state($name)
	{
		$key = $this->prefix . $name;
		return isset($this->config[$key]) ? (int) $this->config[$key] : 0;
	}

	protected function set_state($name, $value)
	{
		$this->config->set($this->prefix . $name, (int) $value, false);
	}

	public function check(array $settings, $destination, $now, callable $send)
	{
		$last_sent = $this->get_state('alert_sent');
		if ($last_sent + ((int) $settings['cooldown'] * 60) > $now
			|| $this->get_state('alert_failed') + self::RETRY_SECONDS > $now)
		{
			return;
		}

		$rule = hash('sha256', json_encode(array(
			$destination, $settings['window'], $settings['blocks'], $settings['ips'], $settings['cooldown'],
		)));
		$raw = isset($this->config[$this->prefix . 'pending_alert']) ? $this->config[$this->prefix . 'pending_alert'] : '';
		$pending = json_decode((string) $raw, true);
		if (!isset($pending['rule'], $pending['until']) || $pending['rule'] !== $rule || (int) $pending['until'] <= $last_sent)
		{
			if ($raw !== '')
			{
				$this->config->set($this->prefix . 'pending_alert', '', false);
			}
			$pending = $this->find_alert($settings, $last_sent, $now);
			if (!$pending)
			{
				return;
			}
			$pending['rule'] = $rule;
			$this->config->set($this->prefix . 'pending_alert', json_encode($pending), false);
		}

		$sent = $send(array(
			'PERIOD_START' => gmdate('Y-m-d H:i:s', $pending['from']),
			'PERIOD_END' => gmdate('Y-m-d H:i:s', $pending['until']),
			'BLOCKED_RECORDS' => $pending['blocks'],
			'DISTINCT_IPS' => $pending['ips'],
			'MIN_BLOCKS' => (int) $settings['blocks'],
			'MIN_IPS' => (int) $settings['ips'],
		));
		$this->set_state($sent ? 'alert_sent' : 'alert_failed', $now);
		if ($sent)
		{
			$this->config->set($this->prefix . 'pending_alert', '', false);
		}
		return (bool) $sent;
	}

	protected function find_alert(array $settings, $last_sent, $now)
	{
		$this->set_state('alert_checked', $now);
		// The end is exclusive. A subsequent alert only counts fresh records.
		$from = max($last_sent, $now - ((int) $settings['window'] * 60));
		$sql = 'SELECT COUNT(log_id) AS blocked_records, COUNT(DISTINCT NULLIF(user_ip, \'\')) AS distinct_ips
			FROM ' . $this->table_prefix . 'antispamguard_log
			WHERE ' . self::BLOCKED_FILTER . '
				AND log_time >= ' . (int) $from . ' AND log_time < ' . (int) $now;
		$result = $this->db->sql_query($sql);
		$counts = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ((int) $counts['blocked_records'] < (int) $settings['blocks']
			|| (int) $counts['distinct_ips'] < (int) $settings['ips'])
		{
			return array();
		}

		return array(
			'from' => $from,
			'until' => $now,
			'blocks' => (int) $counts['blocked_records'],
			'ips' => (int) $counts['distinct_ips'],
		);
	}
}
