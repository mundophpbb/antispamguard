<?php
/**
 * Optional Telegram delivery for block spike alerts.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class telegram_alerts
{
	protected const SETTINGS = 'antispamguard_telegram_settings';
	protected const PREFIX = 'antispamguard_telegram_';
	protected const ENABLED = 'antispamguard_telegram_enabled';
	protected const LAST_POLL = 'antispamguard_telegram_last_poll';

	protected $config;
	protected $config_text;
	protected $rules;
	protected $spike;
	protected $http;
	protected $history;
	protected $delivery_detail = '';

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		\phpbb\db\driver\driver_interface $db,
		$table_prefix,
		email_reports $rules,
		$http = null,
		?delivery_history $history = null
	)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->rules = $rules;
		$this->spike = new spike_alert($config, $db, $table_prefix, self::PREFIX);
		$this->http = $http;
		$this->history = $history;
	}

	public function get_settings()
	{
		$settings = json_decode((string) $this->config_text->get(self::SETTINGS), true);
		return (is_array($settings) ? $settings : array()) + array('enabled' => 0, 'token' => '', 'chat_id' => '');
	}

	public function valid_token($token)
	{
		return preg_match('/^[0-9]{5,16}:[A-Za-z0-9_-]{20,100}$/D', $token) === 1;
	}

	public function valid_chat($chat_id)
	{
		return preg_match('/^-?[1-9][0-9]{0,18}$/D', $chat_id) === 1;
	}

	public function save_settings(array $settings, $now = null)
	{
		$this->config_text->set(self::SETTINGS, json_encode($settings));
		if (!$settings['enabled'])
		{
			$this->config->set(self::PREFIX . 'pending_alert', '', false);
		}
		if ($settings['enabled'] && !$this->is_runnable())
		{
			$this->config->set(self::PREFIX . 'cron_enabled_at', $now ?? time(), false);
		}
		$this->config->set(self::ENABLED, (int) $settings['enabled'], false);
	}

	public function test_delivery($now)
	{
		$settings = $this->get_settings();
		if (!$this->valid_token($settings['token']) || !$this->valid_chat($settings['chat_id']))
		{
			return 'telegram_config';
		}
		if (!delivery_test::reserve($this->config, self::PREFIX . 'test_attempt', $now))
		{
			return 'wait';
		}

		$sent = $this->deliver($settings['token'], $settings['chat_id'], null);
		if ($this->history !== null)
		{
			$this->history->record('telegram', 'test', $sent, $now, $this->delivery_detail);
		}
		return $sent ? 'telegram_sent' : 'telegram_' . delivery_history::outcome(false, $this->delivery_detail);
	}

	public function get_delivery_detail()
	{
		return $this->delivery_detail;
	}

	public function is_runnable()
	{
		return !empty($this->config[self::ENABLED]);
	}

	public function should_run($now)
	{
		return $this->is_runnable() && $this->get_state('last_poll') + 60 <= $now;
	}

	public function get_cron_status(array $settings, $now)
	{
		$enabled = !empty($settings['enabled']);
		$runnable = $this->is_runnable() && $this->valid_token($settings['token']) && $this->valid_chat($settings['chat_id']);

		return cron_status::describe(
			$enabled, $runnable, $this->get_state('last_poll'), $this->get_state('cron_enabled_at'), $now
		);
	}

	public function get_state($name)
	{
		return $this->spike->get_state($name);
	}

	public function run($now)
	{
		if (!$this->should_run($now))
		{
			return;
		}
		$this->config->set(self::LAST_POLL, $now, false);

		$settings = $this->get_settings();
		if (!$settings['enabled'] || !$this->valid_token($settings['token']) || !$this->valid_chat($settings['chat_id']))
		{
			return;
		}

		$destination = hash('sha256', $settings['token'] . '/' . $settings['chat_id']);
		$sent = $this->spike->check($this->rules->get_settings(), $destination, $now, function (array $vars) use ($settings)
		{
			return $this->deliver($settings['token'], $settings['chat_id'], $vars);
		});
		if ($sent !== null && $this->history !== null)
		{
			$this->history->record('telegram', 'spike', $sent, $now, $this->delivery_detail);
		}
	}

	protected function deliver($token, $chat_id, ?array $vars)
	{
		$this->delivery_detail = 'telegram_unconfirmed';
		$language = (string) $this->config['default_lang'];
		$language = in_array($language, array('en', 'fr', 'pt_br'), true) ? $language : 'en';
		$lang = array();
		include dirname(__DIR__) . '/language/' . $language . '/telegram.php';

		$board = html_entity_decode((string) $this->config['sitename'], ENT_QUOTES, 'UTF-8');
		$body = $vars === null
			? sprintf($lang['ANTISPAMGUARD_TELEGRAM_TEST'], $board)
			: sprintf(
				$lang['ANTISPAMGUARD_TELEGRAM_MESSAGE'], $board, $vars['PERIOD_START'], $vars['PERIOD_END'],
				$vars['BLOCKED_RECORDS'], $vars['DISTINCT_IPS']
			);

		try
		{
			if ($this->http === null)
			{
				$this->http = new \GuzzleHttp\Client();
			}
			$response = $this->http->request('POST', 'https://api.telegram.org/bot' . $token . '/sendMessage', array(
				'connect_timeout' => 4,
				'timeout' => 7,
				'http_errors' => false,
				'allow_redirects' => false,
				'verify' => true,
				'json' => array('chat_id' => $chat_id, 'text' => $body),
			));
			$data = json_decode($response->getBody()->read(8192), true);
			$status = (int) $response->getStatusCode();
			if ($status === 200 && isset($data['ok']) && $data['ok'] === true)
			{
				$this->delivery_detail = '';
				return true;
			}

			$this->delivery_detail = $this->classify_response($status, is_array($data) ? $data : array());
			return false;
		}
		catch (\Exception $exception)
		{
			// The exception may contain the bot token in the request URL.
			if (method_exists($exception, 'getHandlerContext'))
			{
				$context = $exception->getHandlerContext();
				if (isset($context['errno']) && (int) $context['errno'] === 28)
				{
					$this->delivery_detail = 'telegram_timeout';
				}
			}
			return false;
		}
	}
	protected function classify_response($status, array $data)
	{
		if ($status >= 200 && $status < 300 && isset($data['ok']) && $data['ok'] === false)
		{
			$status = $data['error_code'] ?? 0;
			if (!is_int($status) || $status < 400 || $status > 599)
			{
				return 'telegram_request_rejected';
			}
		}
		switch ($status)
		{
			case 401:
				return 'telegram_token_rejected';

			case 403:
				return 'telegram_access_denied';

			case 408:
				return 'telegram_timeout';

			case 429:
				return 'telegram_rate_limited';

			case 400:
				if (isset($data['description']) && is_string($data['description']) && stripos($data['description'], 'chat not found') !== false)
				{
					return 'telegram_chat_unavailable';
				}
				break;
		}
		return ($status >= 400 && $status < 500) ? 'telegram_request_rejected' : 'telegram_unconfirmed';
	}
}
