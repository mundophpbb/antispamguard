<?php
/**
 * AntiSpam Guard consolidated migration.
 *
 * This migration is intentionally defensive: it creates/repairs the final
 * schema and adds missing config defaults without overwriting existing values.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_1_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.1.0', '>=');
	}

	public static function depends_on()
	{
		return array('\\phpbb\\db\\migration\\data\\v310\\dev');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'repair_schema'))),
			array('custom', array(array($this, 'ensure_config_defaults'))),
			array('custom', array(array($this, 'install_acp_modules_if_missing'))),
			array('custom', array(array($this, 'install_admin_permissions_if_missing'))),
			array('custom', array(array($this, 'set_version'))),
		);
	}

	/**
	 * Idempotent ACP module install (avoids MODULE_EXISTS + broken rollback).
	 */
	public function install_acp_modules_if_missing()
	{
		$module_tool = $this->get_module_tool();

		if (!$module_tool->exists('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_TITLE', true))
		{
			$module_tool->add('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_TITLE');
		}

		$basename = '\\mundophpbb\\antispamguard\\acp\\main_module';

		foreach ($this->get_acp_module_modes() as $mode => $langname)
		{
			if (!$module_tool->exists('acp', 'ACP_ANTISPAMGUARD_TITLE', $langname, true))
			{
				$module_tool->add('acp', 'ACP_ANTISPAMGUARD_TITLE', array(
					'module_basename' => $basename,
					'modes' => array($mode),
				));
			}
		}

		$this->repair_module_auth();
	}

	public function install_admin_permissions_if_missing()
	{
		$permission_tool = $this->get_permission_tool();

		if (!$permission_tool->exists('a_antispamguard_manage', true))
		{
			$permission_tool->add('a_antispamguard_manage', true);
		}

		$permission_tool->permission_set('ROLE_ADMIN_FULL', 'a_antispamguard_manage');
		$permission_tool->permission_set('ROLE_ADMIN_STANDARD', 'a_antispamguard_manage');
	}

	public function repair_module_auth()
	{
		$auth = 'ext_mundophpbb/antispamguard && (acl_a_board || acl_a_antispamguard_manage)';

		$sql = 'UPDATE ' . $this->table_prefix . "modules
			SET module_auth = '" . $this->db->sql_escape($auth) . "'
			WHERE module_langname LIKE 'ACP_ANTISPAMGUARD_%'
				OR module_langname = 'ACP_ANTISPAMGUARD_TITLE'";
		$this->db->sql_query($sql);
	}

	protected function get_acp_module_modes()
	{
		return array(
			'settings' => 'ACP_ANTISPAMGUARD_SETTINGS',
			'sfs'      => 'ACP_ANTISPAMGUARD_PANEL_SFS',
			'logs'     => 'ACP_ANTISPAMGUARD_LOGS',
			'stats'    => 'ACP_ANTISPAMGUARD_STATS',
			'about'    => 'ACP_ANTISPAMGUARD_ABOUT',
		);
	}

	public function repair_schema()
	{
		$this->repair_main_log_table();
		$this->repair_sfs_cache_table();
		$this->repair_sfs_log_table();
		$this->repair_ip_score_table();
		$this->repair_ip_rate_table();
		$this->repair_activity_log_table();
		$this->repair_alerts_table();
		$this->repair_sfs_submit_log_table();
		$this->repair_sfs_review_log_table();
		$this->repair_registration_audit_table();
	}

	protected function get_config_defaults()
	{
		return array(
			'antispamguard_enabled' => array(1, false),
			'antispamguard_hp_name' => array('homepage', false),
			'antispamguard_min_seconds' => array(3, false),
			'antispamguard_max_seconds' => array(1800, false),
			'antispamguard_protect_posts' => array(0, false),
			'antispamguard_posts_guests_only' => array(1, false),
			'antispamguard_bypass_group_ids' => array('', false),
			'antispamguard_content_filter_enabled' => array(0, false),
			'antispamguard_blocked_keywords' => array('', false),
			'antispamguard_max_urls' => array(0, false),
			'antispamguard_rate_limit_enabled' => array(0, false),
			'antispamguard_rate_limit_max_attempts' => array(5, false),
			'antispamguard_rate_limit_window' => array(3600, false),
			'antispamguard_log_retention_enabled' => array(0, false),
			'antispamguard_log_retention_days' => array(30, false),
			'antispamguard_ip_whitelist' => array('', false),
			'antispamguard_ip_blacklist' => array('', false),
			'antispamguard_cron_last_prune' => array(0, true),
			'antispamguard_silent_mode' => array(0, false),
			'antispamguard_protect_contact' => array(0, false),
			'antispamguard_protect_pm' => array(0, false),
			'antispamguard_simulation_mode' => array(0, false),
			'antispamguard_sfs_enabled' => array(0, false),
			'antispamguard_sfs_log_enabled' => array(1, false),
			'antispamguard_sfs_log_only_blocked' => array(0, false),
			'antispamguard_sfs_min_confidence' => array(80, false),
			'antispamguard_sfs_min_frequency' => array(5, false),
			'antispamguard_sfs_block_multiple_hits' => array(0, false),
			'antispamguard_sfs_cleanup_interval' => array(86400, false),
			'antispamguard_sfs_log_retention_days' => array(90, false),
			'antispamguard_sfs_cleanup_last_gc' => array(0, true),
			'antispamguard_sfs_whitelist_ips' => array('', false),
			'antispamguard_sfs_whitelist_emails' => array('', false),
			'antispamguard_sfs_whitelist_usernames' => array('', false),
			'antispamguard_sfs_action_mode' => array('block', false),
			'antispamguard_hp_dynamic_enabled' => array(1, false),
			'antispamguard_hp_dynamic_prefix' => array('asg_hp', false),
			'antispamguard_hp_camouflage_enabled' => array(1, false),
			'antispamguard_max_form_age' => array(3600, false),
			'antispamguard_sfs_cache_ttl' => array(86400, false),
			'antispamguard_ip_reputation_enabled' => array(1, false),
			'antispamguard_ip_reputation_threshold' => array(20, false),
			'antispamguard_ip_reputation_decay_interval' => array(600, false),
			'antispamguard_ip_reputation_ttl' => array(86400, false),
			'antispamguard_ip_reputation_weight_honeypot' => array(3, false),
			'antispamguard_ip_reputation_weight_timestamp_fast' => array(2, false),
			'antispamguard_ip_reputation_weight_timestamp_expired' => array(1, false),
			'antispamguard_ip_reputation_weight_sfs' => array(2, false),
			'antispamguard_ip_reputation_cleanup_interval' => array(86400, false),
			'antispamguard_ip_reputation_cleanup_last_gc' => array(0, true),
			'antispamguard_trusted_ip_whitelist' => array('', false),
			'antispamguard_ip_whitelist_mode' => array('partial', false),
			'antispamguard_ip_rate_limit_enabled' => array(0, false),
			'antispamguard_ip_rate_limit_window' => array(60, false),
			'antispamguard_ip_rate_limit_max_hits' => array(5, false),
			'antispamguard_ip_rate_limit_action' => array('block', false),
			'antispamguard_ip_reputation_weight_rate_limit' => array(3, false),
			'antispamguard_ip_rate_limit_cleanup_interval' => array(3600, false),
			'antispamguard_ip_rate_limit_cleanup_last_gc' => array(0, true),
			'antispamguard_decision_engine_enabled' => array(1, false),
			'antispamguard_decision_score_log' => array(25, false),
			'antispamguard_decision_score_block' => array(80, false),
			'antispamguard_decision_weight_honeypot' => array(100, false),
			'antispamguard_decision_weight_timestamp_fast' => array(15, false),
			'antispamguard_decision_weight_timestamp_expired' => array(5, false),
			'antispamguard_decision_weight_rate_limit' => array(25, false),
			'antispamguard_decision_weight_sfs' => array(80, false),
			'antispamguard_decision_weight_ip_reputation' => array(0, false),
			'antispamguard_autoban_enabled' => array(0, false),
			'antispamguard_autoban_threshold' => array(120, false),
			'antispamguard_autoban_duration' => array(86400, false),
			'antispamguard_slowspam_enabled' => array(1, false),
			'antispamguard_slowspam_window' => array(1800, false),
			'antispamguard_slowspam_threshold' => array(8, false),
			'antispamguard_slowspam_prune_after' => array(86400, false),
			'antispamguard_decision_weight_slowspam' => array(15, false),
			'antispamguard_sfs_log_all_checks' => array(0, false),
			'antispamguard_sfs_debug_log_all' => array(0, false),
			'antispamguard_sfs_debug_localhost_only' => array(1, false),
			'antispamguard_sfs_debug_until' => array(0, false),
			'antispamguard_slowspam_cleanup_interval' => array(86400, false),
			'antispamguard_slowspam_cleanup_last_gc' => array(0, true),
			'antispamguard_alerts_enabled' => array(1, false),
			'antispamguard_alerts_retention' => array(604800, false),
			'antispamguard_alerts_last_gc' => array(0, true),
			'antispamguard_sfs_api_key' => array('', true),
			'antispamguard_register_notice_enabled' => array(0, false),
			'antispamguard_register_notice_text' => array('', false),
			'antispamguard_register_audit_soft_signals' => array(1, false),
			'antispamguard_subnet_rate_limit_enabled' => array(0, false),
			'antispamguard_subnet_rate_limit_window' => array(600, false),
			'antispamguard_subnet_rate_limit_max_hits' => array(10, false),
			'antispamguard_subnet_rate_limit_action' => array('score', false),
			'antispamguard_random_gmail_enabled' => array(1, false),
			'antispamguard_random_gmail_register_enabled' => array(0, false),
			'antispamguard_decision_weight_subnet_abuse' => array(30, false),
			'antispamguard_decision_weight_random_gmail' => array(10, false),
			'antispamguard_ip_reputation_weight_subnet_abuse' => array(1, false),
			'antispamguard_ip_reputation_weight_random_gmail' => array(1, false),
			'antispamguard_sfs_log_preserve_reviewed' => array(1, false),
			'antispamguard_token_secret' => array('', true),
			'antispamguard_sfs_error_cache_ttl' => array(300, false),
			'antispamguard_sfs_http_timeout' => array(2, false),
			'antispamguard_sfs_http_retries' => array(1, false),
			'antispamguard_sfs_http_max_response_bytes' => array(262144, false),
			'antispamguard_sfs_circuit_threshold' => array(3, false),
			'antispamguard_sfs_circuit_cooldown' => array(300, false),
			'antispamguard_sfs_failure_count' => array(0, true),
			'antispamguard_sfs_circuit_until' => array(0, true),
			'antispamguard_registration_audit_enabled' => array(1, false),
			'antispamguard_registration_track_page_views' => array(0, false),
			'antispamguard_registration_audit_window' => array(300, false),
			'antispamguard_registration_audit_retention_days' => array(7, false),
			'antispamguard_registration_audit_last_gc' => array(0, true),
		);
	}

	public function ensure_config_defaults()
	{
		foreach ($this->get_config_defaults() as $key => $data)
		{
			if (!isset($this->config[$key]))
			{
				$this->config->set($key, $data[0], $data[1]);
			}
		}

		if (empty($this->config['antispamguard_token_secret']))
		{
			try
			{
				$secret = bin2hex(random_bytes(32));
			}
			catch (\Exception $e)
			{
				$secret = hash('sha256', uniqid((string) mt_rand(), true));
			}

			$this->config->set('antispamguard_token_secret', $secret, true);
		}
	}

	/**
	 * Safely set the extension migration version.
	 *
	 * phpBB's config.update migration tool expects the config key to
	 * already exist. Using config->set() here makes fresh installs and
	 * repaired/partial installs idempotent because the key is created when
	 * missing and updated when present.
	 */
	public function set_version()
	{
		$version = '0.1.0';
		$class = get_class($this);

		if (preg_match('/v_(\d+)_(\d+)_(\d+)$/', $class, $matches))
		{
			$version = $matches[1] . '.' . $matches[2] . '.' . $matches[3];
		}

		$this->config->set('antispamguard_version', $version);
	}

	protected function repair_main_log_table()
	{
		$table = $this->table_prefix . 'antispamguard_log';
		$columns = array(
			'log_id'        => array('UINT', null, 'auto_increment'),
			'log_time'      => array('TIMESTAMP', 0),
			'user_ip'       => array('VCHAR:45', ''),
			'username'      => array('VCHAR:255', ''),
			'email'         => array('VCHAR:255', ''),
			'reason'        => array('VCHAR:191', ''),
			'user_agent'    => array('VCHAR:255', ''),
			'form_type'     => array('VCHAR:30', 'register'),
			'risk_score'    => array('UINT:8', 0),
			'risk_level'    => array('VCHAR:20', ''),
			'action'        => array('VCHAR:20', ''),
			'matched_rules' => array('VCHAR:191', ''),
		);

		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'log_id',
				'KEYS' => array(
					'log_time'  => array('INDEX', array('log_time')),
					'user_ip'   => array('INDEX', array('user_ip')),
					'reason'    => array('INDEX', array('reason')),
					'form_type' => array('INDEX', array('form_type')),
				),
			));
			return;
		}

		$this->add_missing_columns($table, $columns, array('log_id'));
		if ($this->db_tools->sql_column_exists($table, 'ip') && $this->db_tools->sql_column_exists($table, 'user_ip'))
		{
			$this->db->sql_query('UPDATE ' . $table . " SET user_ip = ip WHERE user_ip = '' AND ip <> ''");
		}
		$this->safe_column_change($table, 'reason', array('VCHAR:191', ''));
		$this->safe_column_change($table, 'user_ip', array('VCHAR:45', ''));
		$this->safe_column_change($table, 'form_type', array('VCHAR:30', 'register'));
		$this->add_index_if_missing($table, 'user_ip', array('user_ip'));
		$this->add_index_if_missing($table, 'reason', array('reason'));
		$this->add_index_if_missing($table, 'form_type', array('form_type'));
	}

	public function repair_registration_audit_table()
	{
		$table = $this->table_prefix . 'antispamguard_registration_audit';
		$columns = array(
			'audit_id'         => array('UINT', null, 'auto_increment'),
			'bucket_key'       => array('VCHAR:64', ''),
			'bucket_start'     => array('TIMESTAMP', 0),
			'user_ip'          => array('VCHAR:45', ''),
			'user_agent'       => array('VCHAR:255', ''),
			'page_views'       => array('UINT', 0),
			'form_submissions' => array('UINT', 0),
			'phpbb_rejected'   => array('UINT', 0),
			'local_rejected'   => array('UINT', 0),
			'sfs_analyzed'     => array('UINT', 0),
			'last_reason'      => array('VCHAR:191', ''),
			'first_seen'       => array('TIMESTAMP', 0),
			'last_seen'        => array('TIMESTAMP', 0),
		);

		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'audit_id',
				'KEYS' => array(
					'bucket_key_unique' => array('UNIQUE', array('bucket_key')),
					'bucket_start_idx'  => array('INDEX', array('bucket_start')),
					'user_ip_idx'       => array('INDEX', array('user_ip')),
				),
			));
			return;
		}

		$this->add_missing_columns($table, $columns, array('audit_id'));
		$this->add_unique_index_if_missing($table, 'bucket_key_unique', array('bucket_key'));
		$this->add_index_if_missing($table, 'bucket_start_idx', array('bucket_start'));
		$this->add_index_if_missing($table, 'user_ip_idx', array('user_ip'));
	}

	protected function repair_sfs_cache_table()
	{
		$table = $this->table_prefix . 'antispamguard_sfs_cache';
		$columns = array(
			'cache_id'      => array('UINT', null, 'auto_increment'),
			'lookup_type'   => array('VCHAR:20', ''),
			'lookup_value'  => array('VCHAR:255', ''),
			'lookup_hash'   => array('VCHAR:64', ''),
			'response_json' => array('MTEXT_UNI', ''),
			'is_listed'     => array('BOOL', 0),
			'confidence'    => array('DECIMAL:5', 0),
			'frequency'     => array('UINT', 0),
			'created_at'    => array('TIMESTAMP', 0),
			'expires_at'    => array('TIMESTAMP', 0),
		);

		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'cache_id',
				'KEYS' => array(
					'lookup_hash_unique' => array('UNIQUE', array('lookup_hash')),
					'expires_idx' => array('INDEX', array('expires_at')),
					'listed_idx'  => array('INDEX', array('is_listed')),
				),
			));
			return;
		}

		$this->add_missing_columns($table, $columns, array('cache_id'));
		if ($this->db_tools->sql_column_exists($table, 'cache_key'))
		{
			$this->backfill_legacy_sfs_cache_key($table);
		}
		if ($this->db_tools->sql_column_exists($table, 'response'))
		{
			$this->db->sql_query('UPDATE ' . $table . " SET response_json = response WHERE response_json = ''");
		}
	}

	protected function backfill_legacy_sfs_cache_key($table)
	{
		$updates = array();
		$sql = 'SELECT cache_id, cache_key, lookup_type, lookup_value
			FROM ' . $table;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$cache_key = (string) $row['cache_key'];
			$lookup_type = (string) $row['lookup_type'];
			$lookup_value = (string) $row['lookup_value'];
			$prefixes = array(
				'email:' => 'email',
				'username:' => 'username',
				'ip:' => 'ip',
			);

			foreach ($prefixes as $prefix => $type)
			{
				if (strpos($cache_key, $prefix) !== 0)
				{
					continue;
				}

				if ($lookup_type === '')
				{
					$lookup_type = $type;
				}
				if ($lookup_value === '')
				{
					$lookup_value = substr($cache_key, strlen($prefix));
				}
				break;
			}

			if ($lookup_type === '')
			{
				$lookup_type = $cache_key;
			}

			if ($lookup_type === (string) $row['lookup_type'] && $lookup_value === (string) $row['lookup_value'])
			{
				continue;
			}

			$updates[] = array(
				'cache_id' => (int) $row['cache_id'],
				'data' => array(
					'lookup_type' => $lookup_type,
					'lookup_value' => $lookup_value,
				),
			);
		}

		$this->db->sql_freeresult($result);

		foreach ($updates as $update)
		{
			$sql = 'UPDATE ' . $table . '
				SET ' . $this->db->sql_build_array('UPDATE', $update['data']) . '
				WHERE cache_id = ' . (int) $update['cache_id'];
			$this->db->sql_query($sql);
		}
	}

	protected function repair_sfs_log_table()
	{
		$table = $this->table_prefix . 'antispamguard_sfs_log';
		$columns = array(
			'log_id'        => array('UINT', null, 'auto_increment'),
			'check_source'  => array('VCHAR:50', ''),
			'user_ip'       => array('VCHAR:45', ''),
			'user_email'    => array('VCHAR:255', ''),
			'username'      => array('VCHAR:255', ''),
			'listed_count'  => array('UINT', 0),
			'strong_hit'    => array('BOOL', 0),
			'blocked'       => array('BOOL', 0),
			'details_json'  => array('MTEXT_UNI', ''),
			'created_at'    => array('TIMESTAMP', 0),
			'review_status' => array('VCHAR:32', ''),
			'reviewed_at'   => array('TIMESTAMP', 0),
			'reviewed_by'   => array('UINT', 0),
			'local_action'  => array('VCHAR:32', ''),
			'action_mode'   => array('VCHAR:20', ''),
			'submission_key'=> array('VCHAR:64', ''),
		);

		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'log_id',
				'KEYS' => array(
					'created_idx' => array('INDEX', array('created_at')),
					'blocked_idx' => array('INDEX', array('blocked')),
					'source_idx'  => array('INDEX', array('check_source')),
					'ip_idx'      => array('INDEX', array('user_ip')),
					'review_status_idx' => array('INDEX', array('review_status')),
					'local_action_idx' => array('INDEX', array('local_action')),
					'blocked_created_idx' => array('INDEX', array('blocked', 'created_at')),
					'action_created_idx' => array('INDEX', array('action_mode', 'created_at')),
					'blocked_action_created_idx' => array('INDEX', array('blocked', 'action_mode', 'created_at')),
					'sfs_ip_created_idx' => array('INDEX', array('user_ip', 'created_at')),
					'submission_key_idx' => array('INDEX', array('submission_key')),
				),
			));
			return;
		}

		$this->add_missing_columns($table, $columns, array('log_id'));
		$this->backfill_sfs_action_mode($table);
		$this->add_index_if_missing($table, 'review_status_idx', array('review_status'));
		$this->add_index_if_missing($table, 'local_action_idx', array('local_action'));
		$this->add_index_if_missing($table, 'blocked_created_idx', array('blocked', 'created_at'));
		$this->add_index_if_missing($table, 'action_created_idx', array('action_mode', 'created_at'));
		$this->add_index_if_missing($table, 'blocked_action_created_idx', array('blocked', 'action_mode', 'created_at'));
		$this->add_index_if_missing($table, 'sfs_ip_created_idx', array('user_ip', 'created_at'));
		$this->add_index_if_missing($table, 'submission_key_idx', array('submission_key'));
	}

	protected function repair_ip_score_table()
	{
		$table = $this->table_prefix . 'antispamguard_ip_score';
		$columns = array(
			'score_id'    => array('UINT', null, 'auto_increment'),
			'ip'          => array('VCHAR:45', ''),
			'score'       => array('UINT', 0),
			'hits'        => array('UINT', 0),
			'last_reason' => array('VCHAR:191', ''),
			'first_seen'  => array('TIMESTAMP', 0),
			'last_update' => array('TIMESTAMP', 0),
			'expires_at'  => array('TIMESTAMP', 0),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'score_id',
				'KEYS' => array(
					'ip_unique' => array('UNIQUE', array('ip')),
					'score_idx' => array('INDEX', array('score')),
					'expires_idx' => array('INDEX', array('expires_at')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('score_id'));
		$this->safe_column_change($table, 'last_reason', array('VCHAR:191', ''));
	}

	protected function repair_ip_rate_table()
	{
		$table = $this->table_prefix . 'antispamguard_ip_rate';
		$columns = array(
			'rate_id'    => array('UINT', null, 'auto_increment'),
			'ip'         => array('VCHAR:45', ''),
			'hits'       => array('UINT', 0),
			'first_hit'  => array('TIMESTAMP', 0),
			'last_hit'   => array('TIMESTAMP', 0),
			'expires_at' => array('TIMESTAMP', 0),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'rate_id',
				'KEYS' => array(
					'ip_unique' => array('UNIQUE', array('ip')),
					'expires_idx' => array('INDEX', array('expires_at')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('rate_id'));
	}

	protected function repair_activity_log_table()
	{
		$table = $this->table_prefix . 'antispamguard_activity_log';
		$columns = array(
			'activity_id' => array('UINT', null, 'auto_increment'),
			'ip'          => array('VCHAR:45', ''),
			'user_id'     => array('UINT', 0),
			'action_type' => array('VCHAR:32', ''),
			'created_at'  => array('TIMESTAMP', 0),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'activity_id',
				'KEYS' => array(
					'ip_time_idx' => array('INDEX', array('ip', 'created_at')),
					'user_time_idx' => array('INDEX', array('user_id', 'created_at')),
					'type_idx' => array('INDEX', array('action_type')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('activity_id'));
	}

	protected function repair_alerts_table()
	{
		$table = $this->table_prefix . 'antispamguard_alerts';
		$columns = array(
			'alert_id'     => array('UINT', null, 'auto_increment'),
			'alert_type'   => array('VCHAR:64', ''),
			'severity'     => array('VCHAR:16', 'info'),
			'ip'           => array('VCHAR:45', ''),
			'user_id'      => array('UINT', 0),
			'username'     => array('VCHAR:255', ''),
			'message'      => array('TEXT', ''),
			'details_json' => array('MTEXT_UNI', ''),
			'created_at'   => array('TIMESTAMP', 0),
			'is_read'      => array('BOOL', 0),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'alert_id',
				'KEYS' => array(
					'created_idx' => array('INDEX', array('created_at')),
					'type_idx' => array('INDEX', array('alert_type')),
					'read_idx' => array('INDEX', array('is_read')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('alert_id'));
	}

	protected function repair_sfs_submit_log_table()
	{
		$table = $this->table_prefix . 'antispamguard_sfs_submit_log';
		$columns = array(
			'submit_id'        => array('UINT', null, 'auto_increment'),
			'user_id'          => array('UINT', 0),
			'admin_username'   => array('VCHAR:255', ''),
			'admin_ip'         => array('VCHAR:45', ''),
			'spammer_ip'       => array('VCHAR:45', ''),
			'spammer_email'    => array('VCHAR:255', ''),
			'spammer_username' => array('VCHAR:255', ''),
			'evidence'         => array('MTEXT_UNI', ''),
			'source'           => array('VCHAR:64', 'manual_acp'),
			'source_log_id'    => array('UINT', 0),
			'status'           => array('VCHAR:64', ''),
			'response_text'    => array('MTEXT_UNI', ''),
			'created_at'       => array('TIMESTAMP', 0),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'submit_id',
				'KEYS' => array(
					'created_idx' => array('INDEX', array('created_at')),
					'source_idx' => array('INDEX', array('source', 'source_log_id')),
					'status_idx' => array('INDEX', array('status')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('submit_id'));
	}

	protected function repair_sfs_review_log_table()
	{
		$table = $this->table_prefix . 'antispamguard_sfs_review_log';
		$columns = array(
			'review_id' => array('UINT', null, 'auto_increment'),
			'sfs_log_id' => array('UINT', 0),
			'action' => array('VCHAR:32', ''),
			'old_review_status' => array('VCHAR:32', ''),
			'new_review_status' => array('VCHAR:32', ''),
			'old_local_action' => array('VCHAR:32', ''),
			'new_local_action' => array('VCHAR:32', ''),
			'admin_user_id' => array('UINT', 0),
			'created_at' => array('TIMESTAMP', 0),
			'note' => array('TEXT_UNI', ''),
		);
		if (!$this->db_tools->sql_table_exists($table))
		{
			$this->db_tools->sql_create_table($table, array(
				'COLUMNS' => $columns,
				'PRIMARY_KEY' => 'review_id',
				'KEYS' => array(
					'sfs_log_created_idx' => array('INDEX', array('sfs_log_id', 'created_at')),
					'action_created_idx' => array('INDEX', array('action', 'created_at')),
					'admin_created_idx' => array('INDEX', array('admin_user_id', 'created_at')),
				),
			));
			return;
		}
		$this->add_missing_columns($table, $columns, array('review_id'));
	}

	protected function backfill_sfs_action_mode($table)
	{
		if (!$this->db_tools->sql_column_exists($table, 'action_mode') || !$this->db_tools->sql_column_exists($table, 'details_json'))
		{
			return;
		}

		$sql = 'SELECT log_id, details_json FROM ' . $table . " WHERE action_mode = '' OR action_mode IS NULL";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$action_mode = 'block';
			$details = json_decode((string) $row['details_json'], true);
			if (is_array($details) && isset($details['_decision']) && is_array($details['_decision']) && !empty($details['_decision']['action_mode']))
			{
				$candidate = (string) $details['_decision']['action_mode'];
				if (in_array($candidate, array('block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
				{
					$action_mode = $candidate;
				}
			}
			$this->db->sql_query('UPDATE ' . $table . " SET action_mode = '" . $this->db->sql_escape($action_mode) . "' WHERE log_id = " . (int) $row['log_id']);
		}
		$this->db->sql_freeresult($result);
	}

	protected function add_missing_columns($table, array $columns, array $skip = array())
	{
		foreach ($columns as $column => $definition)
		{
			if (in_array($column, $skip, true))
			{
				continue;
			}
			if (!$this->db_tools->sql_column_exists($table, $column))
			{
				$this->db_tools->sql_column_add($table, $column, $definition);
			}
		}
	}

	protected function add_index_if_missing($table, $index_name, array $columns)
	{
		$indexes = $this->db_tools->sql_list_index($table);
		if (!in_array($index_name, $indexes, true))
		{
			$this->db_tools->sql_create_index($table, $index_name, $columns);
		}
	}

	protected function add_unique_index_if_missing($table, $index_name, array $columns)
	{
		$indexes = $this->db_tools->sql_list_index($table);
		if (!in_array($index_name, $indexes, true))
		{
			$this->db_tools->sql_create_unique_index($table, $index_name, $columns);
		}
	}

	protected function safe_column_change($table, $column, array $definition)
	{
		if ($this->db_tools->sql_column_exists($table, $column))
		{
			$this->db_tools->sql_column_change($table, $column, $definition);
		}
	}

	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				$this->table_prefix . 'antispamguard_sfs_review_log',
				$this->table_prefix . 'antispamguard_registration_audit',
				$this->table_prefix . 'antispamguard_sfs_submit_log',
				$this->table_prefix . 'antispamguard_alerts',
				$this->table_prefix . 'antispamguard_activity_log',
				$this->table_prefix . 'antispamguard_ip_rate',
				$this->table_prefix . 'antispamguard_ip_score',
				$this->table_prefix . 'antispamguard_sfs_log',
				$this->table_prefix . 'antispamguard_sfs_cache',
				$this->table_prefix . 'antispamguard_log',
			),
		);
	}

	/**
	 * @return \phpbb\db\migration\tool\module
	 */
	protected function get_module_tool()
	{
		global $phpbb_container;

		return $phpbb_container->get('migrator.tool.module');
	}

	/**
	 * @return \phpbb\db\migration\tool\permission
	 */
	protected function get_permission_tool()
	{
		global $phpbb_container;

		return $phpbb_container->get('migrator.tool.permission');
	}
}
