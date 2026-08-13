<?php
/**
 * AntiSpam Guard - StopForumSpam decision service.
 */

namespace mundophpbb\antispamguard\service;

class sfs_decision
{
	protected $config;
	protected $sfs;
	protected $log;

	public function __construct(\phpbb\config\config $config, stopforumspam_client $sfs, sfs_log $log)
	{
		$this->config = $config;
		$this->sfs = $sfs;
		$this->log = $log;
	}

	public function should_block($ip = '', $email = '', $username = '', $source = 'unknown', $force_log = false, $submission_key = '')
	{
		$sfs_enabled = !empty($this->config['antispamguard_sfs_enabled']);

		// Normal runtime: if SFS is disabled, do not query the external service and do not log.
		// Manual ACP tests pass $force_log = true, so they still perform the lookup and write
		// an audit row explaining that the live SFS protection is disabled.
		if (!$sfs_enabled && !$force_log)
		{
			return array(
				'block' => false,
				'matched' => false,
				'action_mode' => 'disabled',
				'soft' => false,
				'log_only' => false,
				'listed_count' => 0,
				'strong_hit' => false,
				'results' => array(),
				'status' => 'sfs_disabled',
				'log_written' => false,
			);
		}

		$checks = array(
			'ip' => $ip,
			'email' => $email,
			'username' => $username,
		);

		// Whitelists apply to their own identity dimension only.  A common
		// whitelisted username must not bypass a listed IP or e-mail address.
		$whitelist_keys = array(
			'ip' => 'antispamguard_sfs_whitelist_ips',
			'email' => 'antispamguard_sfs_whitelist_emails',
			'username' => 'antispamguard_sfs_whitelist_usernames',
		);
		$active_checks = array();
		$whitelisted_results = array();

		foreach ($checks as $type => $value)
		{
			$value = trim((string) $value);
			if ($value === '')
			{
				continue;
			}

			if ($this->is_whitelisted_value($value, $whitelist_keys[$type]))
			{
				$whitelisted_results[$type] = array(
					'value' => $value,
					'checked' => false,
					'whitelisted' => true,
					'is_listed' => false,
					'confidence' => 0,
					'frequency' => 0,
					'cached' => false,
				);
				continue;
			}

			$active_checks[$type] = $value;
		}

		if (empty($active_checks) && !empty($whitelisted_results))
		{
			$decision = array(
				'block' => false,
				'matched' => false,
				'action_mode' => 'whitelist',
				'soft' => false,
				'log_only' => false,
				'listed_count' => 0,
				'strong_hit' => false,
				'results' => $whitelisted_results,
				'status' => 'whitelisted',
				'sfs_enabled' => $sfs_enabled,
				'submission_key' => (string) $submission_key,
				'debug' => (bool) $force_log,
				'debug_status' => $force_log ? 'manual_force_log_whitelisted' : '',
				'log_written' => false,
			);

			$this->maybe_log($source, $ip, $email, $username, $decision, $force_log);

			return $decision;
		}

		$min_confidence = isset($this->config['antispamguard_sfs_min_confidence']) ? (float) $this->config['antispamguard_sfs_min_confidence'] : 80;
		$min_frequency = isset($this->config['antispamguard_sfs_min_frequency']) ? (int) $this->config['antispamguard_sfs_min_frequency'] : 5;
		$block_multiple_hits = !empty($this->config['antispamguard_sfs_block_multiple_hits']);

		$listed_count = 0;
		$strong_hit = false;
		$listed_identifiers = array();
		$strong_identifiers = array();
		$results = $whitelisted_results;
		$lookup_results = $this->sfs->check_many($active_checks);
		$had_error = false;

		foreach ($active_checks as $type => $value)
		{
			$value = trim((string) $value);

			if ($value === '')
			{
				continue;
			}

			$result = isset($lookup_results[$type]) ? $lookup_results[$type] : false;

			if (!$result || !empty($result['error']))
			{
				$had_error = true;
				$results[$type] = array(
					'value' => $value,
					'checked' => true,
					'is_listed' => false,
					'error' => true,
					'confidence' => 0,
					'frequency' => 0,
					'cached' => !empty($result['cached']),
					'error_status' => isset($result['error_status']) ? (string) $result['error_status'] : 'request_failed',
				);

				continue;
			}

			$confidence = isset($result['confidence']) ? (float) $result['confidence'] : 0;
			$frequency = isset($result['frequency']) ? (int) $result['frequency'] : 0;
			$is_listed = !empty($result['is_listed']);

			$results[$type] = array(
				'value' => $value,
				'checked' => true,
				'is_listed' => $is_listed,
				'confidence' => $confidence,
				'frequency' => $frequency,
				'cached' => !empty($result['cached']),
			);

			if (!$is_listed)
			{
				continue;
			}

			$listed_count++;
			$listed_identifiers[] = $type;

			if ($confidence >= $min_confidence || $frequency >= $min_frequency)
			{
				$strong_hit = true;
				$strong_identifiers[] = $type;
			}
		}

		$matched = $strong_hit || ($block_multiple_hits && $listed_count >= 2);
		$hard_identity_match = in_array('email', $strong_identifiers, true)
			|| (in_array('username', $strong_identifiers, true) && in_array('ip', $strong_identifiers, true));
		$review_only = $matched && !$hard_identity_match;
		$action_mode = isset($this->config['antispamguard_sfs_action_mode']) ? (string) $this->config['antispamguard_sfs_action_mode'] : 'block';

		if (!in_array($action_mode, array('block', 'soft', 'log_only'), true))
		{
			$action_mode = 'block';
		}

		if (!$sfs_enabled)
		{
			$action_mode = 'disabled';
		}

		$block = ($sfs_enabled && $action_mode === 'block') ? ($matched && $hard_identity_match) : false;

		$decision = array(
			'block' => $block,
			'matched' => $matched,
			'action_mode' => $action_mode,
			'soft' => ($sfs_enabled && $matched && $hard_identity_match && $action_mode === 'soft'),
			'log_only' => ($matched && ($review_only || $action_mode === 'log_only' || $action_mode === 'disabled')),
			'review_only' => $review_only,
			'hard_identity_match' => $hard_identity_match,
			'listed_count' => $listed_count,
			'strong_hit' => $strong_hit,
			'listed_identifiers' => array_values(array_unique($listed_identifiers)),
			'strong_identifiers' => array_values(array_unique($strong_identifiers)),
			'results' => $results,
			'status' => $sfs_enabled ? ($had_error ? 'partial_error' : 'checked') : 'sfs_disabled_manual_check',
			'had_error' => $had_error,
			'sfs_enabled' => $sfs_enabled,
			'submission_key' => (string) $submission_key,
			'log_written' => false,
		);

		$debug_log = $this->should_debug_log_sfs($ip);

		if ($debug_log && $listed_count === 0)
		{
			$decision['debug'] = true;
			$decision['debug_status'] = 'checked_not_listed';
		}

		if ($force_log && $listed_count === 0)
		{
			$decision['debug'] = true;
			$decision['debug_status'] = $sfs_enabled ? 'manual_force_log_checked_not_listed' : 'manual_force_log_sfs_disabled_checked_not_listed';
		}

		if ($force_log && !$sfs_enabled && $listed_count > 0)
		{
			$decision['debug'] = true;
			$decision['debug_status'] = 'manual_force_log_sfs_disabled_listed';
		}

		$this->maybe_log($source, $ip, $email, $username, $decision, $force_log);

		return $decision;
	}

	public function has_api_key()
	{
		return $this->sfs->has_api_key();
	}

	public function get_api_key_masked()
	{
		return $this->sfs->get_api_key_masked();
	}

	public function submit_spammer($ip, $email, $username, $evidence = '')
	{
		return $this->sfs->submit_spammer($ip, $email, $username, $evidence);
	}

	protected function maybe_log($source, $ip, $email, $username, array &$decision, $force_log = false)
	{
		$log_enabled = !isset($this->config['antispamguard_sfs_log_enabled']) || !empty($this->config['antispamguard_sfs_log_enabled']);
		$log_only_blocked = !empty($this->config['antispamguard_sfs_log_only_blocked']);
		$debug_log = $this->should_debug_log_sfs($ip);
		$log_all_checks = !empty($this->config['antispamguard_sfs_log_all_checks']);
		$listed_count = isset($decision['listed_count']) ? (int) $decision['listed_count'] : 0;
		$block = !empty($decision['block']);
		$explicit_log_only = !empty($decision['matched']) && !empty($decision['log_only']);
		$has_error = !empty($decision['had_error']) || (isset($decision['status']) && (string) $decision['status'] === 'partial_error');

		if (!$has_error && !empty($decision['results']) && is_array($decision['results']))
		{
			foreach ($decision['results'] as $result)
			{
				if (is_array($result) && !empty($result['error']))
				{
					$has_error = true;
					break;
				}
			}
		}

		$should_log = false;

		if ($force_log)
		{
			$should_log = true;
		}
		else if ($log_enabled && ($has_error || $explicit_log_only || !$log_only_blocked || $block || $debug_log) && ($has_error || $listed_count > 0 || $debug_log || $log_all_checks))
		{
			$should_log = true;
		}

		if (!$should_log)
		{
			$decision['log_written'] = false;
			return;
		}

		$log_id = $this->log->add($source, $ip, $email, $username, $decision);
		$decision['log_id'] = (int) $log_id;
		$decision['logged'] = true;
		$decision['log_written'] = true;
	}

	protected function is_whitelisted_value($value, $config_key)
	{
		$value = trim((string) $value);

		if ($value === '' || empty($this->config[$config_key]))
		{
			return false;
		}

		$entries = preg_split('/\r\n|\r|\n/', (string) $this->config[$config_key]);

		foreach ($entries as $entry)
		{
			$entry = trim($entry);

			if ($entry === '')
			{
				continue;
			}

			if (strcasecmp($value, $entry) === 0)
			{
				return true;
			}
		}

		return false;
	}

	protected function is_localhost_ip($ip)
	{
		$ip = trim((string) $ip);

		return in_array($ip, array('127.0.0.1', '::1', 'localhost'), true);
	}

	protected function should_debug_log_sfs($ip)
	{
		if (empty($this->config['antispamguard_sfs_debug_log_all']))
		{
			return false;
		}

		$debug_until = isset($this->config['antispamguard_sfs_debug_until']) ? (int) $this->config['antispamguard_sfs_debug_until'] : 0;
		if ($debug_until > 0 && time() > $debug_until)
		{
			return false;
		}

		if (!empty($this->config['antispamguard_sfs_debug_localhost_only']))
		{
			return $this->is_localhost_ip($ip);
		}

		return true;
	}
}
