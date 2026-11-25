<?php
/**
 * AntiSpam Guard — ACP settings sanitization and import/export.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

use mundophpbb\antispamguard\service\ip_matcher;

class settings_helper
{
	/** @var ip_matcher */
	protected $ip_matcher;

	public function __construct(?ip_matcher $ip_matcher = null)
	{
		$this->ip_matcher = $ip_matcher ?: new ip_matcher();
	}

	public function normalize_blocked_keywords($raw_keywords)
	{
		$items = preg_split('/[\r\n,]+/', (string) $raw_keywords);
		$keywords = array();

		foreach ($items as $item)
		{
			$keyword = trim($item);

			if ($keyword !== '')
			{
				$keywords[$keyword] = $keyword;
			}
		}

		ksort($keywords);

		return implode("\n", $keywords);
	}

	public function normalize_ip_list($raw_ips)
	{
		return $this->ip_matcher->normalize_list($raw_ips);
	}

	public function normalize_group_ids($raw_group_ids)
	{
		$items = preg_split('/[^0-9]+/', (string) $raw_group_ids);
		$group_ids = array();

		foreach ($items as $item)
		{
			$group_id = (int) $item;

			if ($group_id > 0)
			{
				$group_ids[$group_id] = $group_id;
			}
		}

		sort($group_ids);

		return implode(',', $group_ids);
	}

	public function sanitize_secret($value, $max_length)
	{
		$value = trim((string) $value);
		$value = preg_replace('/[\x00-\x20\x7F]/', '', $value);

		return $this->truncate_for_storage($value, (int) $max_length);
	}

	public function mask_secret($value)
	{
		$value = trim((string) $value);
		$length = strlen($value);

		if ($length === 0)
		{
			return '';
		}

		if ($length <= 4)
		{
			return str_repeat('*', $length);
		}

		return str_repeat('*', max(4, $length - 4)) . substr($value, -4);
	}

	public function truncate_for_storage($value, $max_length)
	{
		$value = (string) $value;
		$max_length = (int) $max_length;

		if ($max_length <= 0)
		{
			return '';
		}

		if (function_exists('mb_strlen') && function_exists('mb_substr'))
		{
			if (mb_strlen($value, 'UTF-8') > $max_length)
			{
				return mb_substr($value, 0, $max_length, 'UTF-8');
			}

			return $value;
		}

		return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
	}

	public function sanitize_register_notice_text($value, $default = '')
	{
		$notice_text = trim((string) $value);

		if ($notice_text === '')
		{
			$notice_text = trim((string) $default);
		}

		$notice_text = trim(strip_tags($notice_text));
		$notice_text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $notice_text);
		$notice_text = preg_replace('/\s+/u', ' ', $notice_text);

		return $this->truncate_for_storage($notice_text, 255);
	}

	public function get_settings_keys()
	{
		return array_keys($this->get_settings_schema());
	}

	protected function get_settings_schema()
	{
		$schema = array();
		foreach (array(
			'antispamguard_enabled',
			'antispamguard_register_notice_enabled',
			'antispamguard_register_audit_soft_signals',
			'antispamguard_protect_posts',
			'antispamguard_protect_contact',
			'antispamguard_protect_pm',
			'antispamguard_posts_guests_only',
			'antispamguard_content_filter_enabled',
			'antispamguard_rate_limit_enabled',
			'antispamguard_log_retention_enabled',
			'antispamguard_registration_audit_enabled',
			'antispamguard_registration_track_page_views',
			'antispamguard_silent_mode',
			'antispamguard_simulation_mode',
			'antispamguard_hp_dynamic_enabled',
			'antispamguard_hp_camouflage_enabled',
			'antispamguard_sfs_enabled',
			'antispamguard_sfs_log_enabled',
			'antispamguard_sfs_log_only_blocked',
			'antispamguard_sfs_block_multiple_hits',
			'antispamguard_sfs_log_all_checks',
			'antispamguard_sfs_debug_log_all',
			'antispamguard_sfs_debug_localhost_only',
			'antispamguard_sfs_log_preserve_reviewed',
			'antispamguard_ip_reputation_enabled',
			'antispamguard_ip_rate_limit_enabled',
			'antispamguard_decision_engine_enabled',
			'antispamguard_autoban_enabled',
			'antispamguard_slowspam_enabled',
			'antispamguard_alerts_enabled',
			'antispamguard_subnet_rate_limit_enabled',
			'antispamguard_random_gmail_enabled',
			'antispamguard_random_gmail_register_enabled'
		) as $key)
		{
			$schema[$key] = array('type' => 'bool');
		}

		$schema['antispamguard_register_notice_text'] = array('type' => 'notice');
		$schema['antispamguard_hp_name'] = array('type' => 'hp_name');
		$schema['antispamguard_hp_dynamic_prefix'] = array('type' => 'hp_prefix');
		$schema['antispamguard_bypass_group_ids'] = array('type' => 'groups');
		$schema['antispamguard_blocked_keywords'] = array('type' => 'keywords');
		foreach (array('antispamguard_ip_whitelist', 'antispamguard_ip_blacklist', 'antispamguard_trusted_ip_whitelist', 'antispamguard_sfs_whitelist_ips') as $key)
		{
			$schema[$key] = array('type' => 'ips');
		}
		$schema['antispamguard_sfs_whitelist_emails'] = array('type' => 'lines_lower');
		$schema['antispamguard_sfs_whitelist_usernames'] = array('type' => 'lines');
		$schema['antispamguard_ip_whitelist_mode'] = array('type' => 'enum', 'values' => array('partial', 'total'));
		$schema['antispamguard_ip_rate_limit_action'] = array('type' => 'enum', 'values' => array('block', 'score', 'log_only'));
		$schema['antispamguard_subnet_rate_limit_action'] = array('type' => 'enum', 'values' => array('block', 'score', 'log_only'));
		$schema['antispamguard_sfs_action_mode'] = array('type' => 'enum', 'values' => array('block', 'soft', 'log_only'));
		$schema['antispamguard_sfs_partial_action'] = array('type' => 'enum', 'values' => array('review', 'score', 'block'));

		$integer_ranges = array(
			'antispamguard_max_urls' => array(0, 1000),
			'antispamguard_rate_limit_max_attempts' => array(1, 10000),
			'antispamguard_rate_limit_window' => array(60, 604800),
			'antispamguard_log_retention_days' => array(1, 3650),
			'antispamguard_registration_audit_window' => array(60, 3600),
			'antispamguard_registration_audit_retention_days' => array(1, 365),
			'antispamguard_min_seconds' => array(0, 3600),
			'antispamguard_max_seconds' => array(10, 86400),
			'antispamguard_max_form_age' => array(60, 86400),
			'antispamguard_sfs_min_confidence' => array(0, 100),
			'antispamguard_sfs_min_frequency' => array(1, 1000000),
			'antispamguard_sfs_cleanup_interval' => array(3600, 604800),
			'antispamguard_sfs_log_retention_days' => array(1, 3650),
			'antispamguard_sfs_cache_ttl' => array(60, 604800),
			'antispamguard_sfs_error_cache_ttl' => array(60, 86400),
			'antispamguard_sfs_http_timeout' => array(1, 10),
			'antispamguard_sfs_http_retries' => array(0, 2),
			'antispamguard_sfs_http_max_response_bytes' => array(4096, 1048576),
			'antispamguard_sfs_circuit_threshold' => array(1, 20),
			'antispamguard_sfs_circuit_cooldown' => array(60, 86400),
			'antispamguard_sfs_debug_until' => array(0, 2147483647),
			'antispamguard_ip_reputation_threshold' => array(1, 10000),
			'antispamguard_ip_reputation_decay_interval' => array(60, 86400),
			'antispamguard_ip_reputation_ttl' => array(3600, 31536000),
			'antispamguard_ip_reputation_cleanup_interval' => array(3600, 604800),
			'antispamguard_ip_rate_limit_window' => array(1, 86400),
			'antispamguard_ip_rate_limit_max_hits' => array(1, 100000),
			'antispamguard_ip_rate_limit_cleanup_interval' => array(60, 604800),
			'antispamguard_decision_score_log' => array(0, 65535),
			'antispamguard_decision_score_block' => array(1, 65535),
			'antispamguard_autoban_threshold' => array(1, 65535),
			'antispamguard_autoban_duration' => array(0, 31536000),
			'antispamguard_slowspam_window' => array(60, 604800),
			'antispamguard_slowspam_threshold' => array(1, 100000),
			'antispamguard_slowspam_prune_after' => array(3600, 31536000),
			'antispamguard_slowspam_cleanup_interval' => array(3600, 604800),
			'antispamguard_alerts_retention' => array(3600, 31536000),
			'antispamguard_subnet_rate_limit_window' => array(60, 604800),
			'antispamguard_subnet_rate_limit_max_hits' => array(1, 100000)
		);
		foreach ($integer_ranges as $key => $range)
		{
			$schema[$key] = array('type' => 'int', 'min' => $range[0], 'max' => $range[1]);
		}

		foreach (array(
			'antispamguard_ip_reputation_weight_honeypot', 'antispamguard_ip_reputation_weight_timestamp_fast',
			'antispamguard_ip_reputation_weight_timestamp_expired', 'antispamguard_ip_reputation_weight_sfs',
			'antispamguard_ip_reputation_weight_rate_limit', 'antispamguard_ip_reputation_weight_subnet_abuse',
			'antispamguard_ip_reputation_weight_random_gmail', 'antispamguard_decision_weight_honeypot',
			'antispamguard_decision_weight_timestamp_fast', 'antispamguard_decision_weight_timestamp_expired',
			'antispamguard_decision_weight_rate_limit', 'antispamguard_decision_weight_sfs',
			'antispamguard_decision_weight_ip_reputation', 'antispamguard_decision_weight_slowspam',
			'antispamguard_decision_weight_subnet_abuse', 'antispamguard_decision_weight_random_gmail'
		) as $key)
		{
			$schema[$key] = array('type' => 'int', 'min' => 0, 'max' => 255);
		}

		return $schema;
	}

	public function get_extension_version($config = null, $composer_dir = null)
	{
		$composer_file = ($composer_dir ?: dirname(__DIR__)) . '/composer.json';

		if (is_file($composer_file) && is_readable($composer_file))
		{
			$composer = json_decode((string) file_get_contents($composer_file), true);

			if (is_array($composer) && !empty($composer['version']))
			{
				return (string) $composer['version'];
			}
		}

		return 'unknown';
	}

	/**
	 * @param \phpbb\config\config $config
	 */
	public function import_settings_json($config, $raw_settings)
	{
		$raw_settings = trim((string) $raw_settings);

		if ($raw_settings === '')
		{
			return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_EMPTY', 'count' => 0);
		}

		$data = json_decode($raw_settings, true);

		if (!is_array($data) || !isset($data['settings']) || !is_array($data['settings']))
		{
			return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_INVALID', 'count' => 0);
		}

		$schema = $this->get_settings_schema();
		$imported = 0;

		foreach ($data['settings'] as $key => $value)
		{
			if (!isset($schema[$key]))
			{
				continue;
			}

			if (is_array($value) || is_object($value))
			{
				continue;
			}

			$value = $this->sanitize_import_value($schema[$key], $value);

			$config->set($key, $value);
			$imported++;
		}

		return array('success' => true, 'message' => '', 'count' => $imported);
	}

	protected function sanitize_import_value(array $definition, $value)
	{
		$type = isset($definition['type']) ? (string) $definition['type'] : 'lines';
		switch ($type)
		{
			case 'bool':
				if (is_bool($value))
				{
					return $value ? 1 : 0;
				}
				$normalized = strtolower(trim((string) $value));
				return in_array($normalized, array('1', 'true', 'yes', 'on'), true) ? 1 : 0;
			case 'int':
				return max((int) $definition['min'], min((int) $definition['max'], (int) $value));
			case 'enum':
				$value = trim((string) $value);
				return in_array($value, $definition['values'], true) ? $value : $definition['values'][0];
			case 'notice':
				return $this->sanitize_register_notice_text($value);
			case 'hp_name':
				$value = trim((string) $value);
				return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $value) ? $value : 'homepage';
			case 'hp_prefix':
				$value = trim((string) $value);
				return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,20}$/', $value) ? $value : 'asg_hp';
			case 'groups':
				return $this->normalize_group_ids($value);
			case 'keywords':
				return $this->normalize_blocked_keywords($value);
			case 'ips':
				return $this->normalize_ip_list($value);
			case 'lines_lower':
				return $this->normalize_text_list($value, true);
			case 'lines':
			default:
				return $this->normalize_text_list($value, false);
		}
	}

	protected function normalize_text_list($raw, $lowercase)
	{
		$values = array();
		foreach (preg_split('/[\r\n,]+/', (string) $raw) as $value)
		{
			$value = trim((string) $value);
			if ($value === '')
			{
				continue;
			}
			$key = strtolower($value);
			$values[$key] = $lowercase ? $key : $value;
		}
		ksort($values);

		return implode("\n", $values);
	}

	/**
	 * @param \phpbb\config\config $config
	 */
	public function export_settings_payload($config)
	{
		$data = array(
			'extension' => 'mundophpbb/antispamguard',
			'schema_version' => 2,
			'version' => $this->get_extension_version($config),
			'exported_at' => gmdate('c'),
			'settings' => array(),
		);

		foreach ($this->get_settings_keys() as $key)
		{
			if (isset($config[$key]))
			{
				$data['settings'][$key] = (string) $config[$key];
			}
		}

		return $data;
	}

	/**
	 * Prevent spreadsheet applications from interpreting exported user input
	 * as a formula while preserving the displayed cell value.
	 */
	public function sanitize_csv_row(array $row)
	{
		foreach ($row as $key => $value)
		{
			if (!is_string($value))
			{
				continue;
			}

			$test_value = ltrim($value, "\x00\x09\x0A\x0B\x0C\x0D\x20");
			if ($test_value !== '' && strpos('=+-@', $test_value[0]) !== false)
			{
				$row[$key] = "'" . $value;
			}
		}

		return $row;
	}
}
