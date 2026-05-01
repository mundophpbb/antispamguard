<?php
/**
 * AntiSpam Guard 3.3.15 - Consolidated migration.
 *
 * This migration replaces the long incremental chain with one idempotent
 * migration. It creates/repairs the final schema, adds missing configuration
 * values without overwriting administrator choices, and updates the extension
 * version marker.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_15 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.15', '>=');
    }

    public static function depends_on()
    {
        return array();
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'install_or_repair_schema'))),
            array('custom', array(array($this, 'install_missing_config'))),
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                    'modes'           => array('logs'),
                ),
            )),
            array('config.update', array('antispamguard_version', '3.3.15')),
        );
    }

    public function install_missing_config()
    {
        $defaults = array(
            'antispamguard_enabled' => array(1, false),
            'antispamguard_hp_name' => array('homepage', false),
            'antispamguard_min_seconds' => array(3, false),
            'antispamguard_max_seconds' => array(1800, false),
            'antispamguard_protect_posts' => array(1, false),
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
            'antispamguard_cron_last_prune' => array(0, false),
            'antispamguard_silent_mode' => array(0, false),
            'antispamguard_protect_contact' => array(0, false),
            'antispamguard_protect_pm' => array(0, false),
            'antispamguard_simulation_mode' => array(0, false),

            'antispamguard_sfs_enabled' => array(0, false),
            'antispamguard_sfs_log_enabled' => array(1, false),
            'antispamguard_sfs_log_only_blocked' => array(0, false),
            'antispamguard_sfs_min_confidence' => array(50, false),
            'antispamguard_sfs_min_frequency' => array(3, false),
            'antispamguard_sfs_block_multiple_hits' => array(1, false),
            'antispamguard_sfs_cleanup_interval' => array(86400, false),
            'antispamguard_sfs_log_retention_days' => array(90, false),
            'antispamguard_sfs_cleanup_last_gc' => array(0, true),
            'antispamguard_sfs_whitelist_ips' => array('', false),
            'antispamguard_sfs_whitelist_emails' => array('', false),
            'antispamguard_sfs_whitelist_usernames' => array('', false),
            'antispamguard_sfs_action_mode' => array('block', false),
            'antispamguard_sfs_cache_ttl' => array(86400, false),
            'antispamguard_sfs_log_all_checks' => array(1, false),
            'antispamguard_sfs_debug_log_all' => array(0, false),
            'antispamguard_sfs_debug_localhost_only' => array(1, false),
            'antispamguard_sfs_api_key' => array('', true),

            'antispamguard_hp_dynamic_enabled' => array(1, false),
            'antispamguard_hp_dynamic_prefix' => array('asg_hp', false),
            'antispamguard_hp_camouflage_enabled' => array(1, false),
            'antispamguard_max_form_age' => array(3600, false),

            'antispamguard_ip_reputation_enabled' => array(1, false),
            'antispamguard_ip_reputation_threshold' => array(5, false),
            'antispamguard_ip_reputation_decay_interval' => array(600, false),
            'antispamguard_ip_reputation_ttl' => array(86400, false),
            'antispamguard_ip_reputation_weight_honeypot' => array(3, false),
            'antispamguard_ip_reputation_weight_timestamp_fast' => array(2, false),
            'antispamguard_ip_reputation_weight_timestamp_expired' => array(1, false),
            'antispamguard_ip_reputation_weight_sfs' => array(5, false),
            'antispamguard_ip_reputation_weight_rate_limit' => array(3, false),
            'antispamguard_ip_reputation_cleanup_interval' => array(86400, false),
            'antispamguard_ip_reputation_cleanup_last_gc' => array(0, true),
            'antispamguard_trusted_ip_whitelist' => array('', false),
            'antispamguard_ip_whitelist_mode' => array('partial', false),

            'antispamguard_ip_rate_limit_enabled' => array(1, false),
            'antispamguard_ip_rate_limit_window' => array(60, false),
            'antispamguard_ip_rate_limit_max_hits' => array(5, false),
            'antispamguard_ip_rate_limit_action' => array('block', false),
            'antispamguard_ip_rate_limit_cleanup_interval' => array(3600, false),
            'antispamguard_ip_rate_limit_cleanup_last_gc' => array(0, true),

            'antispamguard_decision_engine_enabled' => array(1, false),
            'antispamguard_decision_score_log' => array(30, false),
            'antispamguard_decision_score_block' => array(60, false),
            'antispamguard_decision_weight_honeypot' => array(100, false),
            'antispamguard_decision_weight_timestamp_fast' => array(30, false),
            'antispamguard_decision_weight_timestamp_expired' => array(15, false),
            'antispamguard_decision_weight_rate_limit' => array(40, false),
            'antispamguard_decision_weight_sfs' => array(50, false),
            'antispamguard_decision_weight_ip_reputation' => array(1, false),
            'antispamguard_decision_weight_slowspam' => array(35, false),

            'antispamguard_autoban_enabled' => array(0, false),
            'antispamguard_autoban_threshold' => array(120, false),
            'antispamguard_autoban_duration' => array(86400, false),
            'antispamguard_shadowban_enabled' => array(0, false),
            'antispamguard_shadowban_threshold' => array(80, false),

            'antispamguard_slowspam_enabled' => array(1, false),
            'antispamguard_slowspam_window' => array(1800, false),
            'antispamguard_slowspam_threshold' => array(8, false),
            'antispamguard_slowspam_prune_after' => array(86400, false),
            'antispamguard_slowspam_cleanup_interval' => array(86400, false),
            'antispamguard_slowspam_cleanup_last_gc' => array(0, true),

            'antispamguard_alerts_enabled' => array(1, false),
            'antispamguard_alerts_retention' => array(604800, false),
            'antispamguard_alerts_last_gc' => array(0, true),

            'antispamguard_register_notice_enabled' => array(0, false),
            'antispamguard_register_notice_text' => array('', false),
        );

        foreach ($defaults as $key => $data)
        {
            if (!isset($this->config[$key]))
            {
                $this->config->set($key, $data[0], $data[1]);
            }
        }
    }

    public function install_or_repair_schema()
    {
        $this->repair_main_log_table();
        $this->repair_sfs_cache_table();
        $this->repair_sfs_log_table();
        $this->repair_ip_score_table();
        $this->repair_ip_rate_table();
        $this->repair_activity_log_table();
        $this->repair_alerts_table();
    }

    protected function repair_main_log_table()
    {
        $table = $this->table_prefix . 'antispamguard_log';
        $columns = array(
            'log_id'     => array('UINT', null, 'auto_increment'),
            'log_time'   => array('TIMESTAMP', 0),
            'user_ip'    => array('VCHAR:45', ''),
            'username'   => array('VCHAR:255', ''),
            'email'      => array('VCHAR:255', ''),
            'reason'     => array('VCHAR:191', ''),
            'user_agent' => array('VCHAR:255', ''),
            'form_type'  => array('VCHAR:30', 'register'),
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

        if ($this->db_tools->sql_column_exists($table, 'reason'))
        {
            $this->db_tools->sql_column_change($table, 'reason', array('VCHAR:191', ''));
        }
        if ($this->db_tools->sql_column_exists($table, 'user_ip'))
        {
            $this->db_tools->sql_column_change($table, 'user_ip', array('VCHAR:45', ''));
        }
        if ($this->db_tools->sql_column_exists($table, 'form_type'))
        {
            $this->db_tools->sql_column_change($table, 'form_type', array('VCHAR:30', 'register'));
        }
    }

    protected function repair_sfs_cache_table()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_cache';
        $columns = array(
            'cache_id'      => array('UINT', null, 'auto_increment'),
            'lookup_type'   => array('VCHAR:20', ''),
            'lookup_value'  => array('VCHAR:255', ''),
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
                    'lookup_idx'  => array('INDEX', array('lookup_type', 'lookup_value')),
                    'expires_idx' => array('INDEX', array('expires_at')),
                    'listed_idx'  => array('INDEX', array('is_listed')),
                ),
            ));
            return;
        }

        $this->add_missing_columns($table, $columns, array('cache_id'));

        if ($this->db_tools->sql_column_exists($table, 'cache_key'))
        {
            $sql = 'UPDATE ' . $table . "
                SET lookup_type = CASE
                        WHEN lookup_type = '' AND cache_key LIKE 'email:%' THEN 'email'
                        WHEN lookup_type = '' AND cache_key LIKE 'username:%' THEN 'username'
                        WHEN lookup_type = '' AND cache_key LIKE 'ip:%' THEN 'ip'
                        WHEN lookup_type = '' THEN cache_key
                        ELSE lookup_type
                    END,
                    lookup_value = CASE
                        WHEN lookup_value = '' AND cache_key LIKE 'email:%' THEN SUBSTRING(cache_key, 7)
                        WHEN lookup_value = '' AND cache_key LIKE 'username:%' THEN SUBSTRING(cache_key, 10)
                        WHEN lookup_value = '' AND cache_key LIKE 'ip:%' THEN SUBSTRING(cache_key, 4)
                        ELSE lookup_value
                    END";
            $this->db->sql_query($sql);
        }

        if ($this->db_tools->sql_column_exists($table, 'response'))
        {
            $this->db->sql_query('UPDATE ' . $table . " SET response_json = response WHERE response_json = ''");
        }
    }

    protected function repair_sfs_log_table()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';
        $columns = array(
            'log_id'       => array('UINT', null, 'auto_increment'),
            'check_source' => array('VCHAR:50', ''),
            'user_ip'      => array('VCHAR:45', ''),
            'user_email'   => array('VCHAR:255', ''),
            'username'     => array('VCHAR:255', ''),
            'listed_count' => array('UINT', 0),
            'strong_hit'   => array('BOOL', 0),
            'blocked'      => array('BOOL', 0),
            'details_json' => array('MTEXT_UNI', ''),
            'created_at'   => array('TIMESTAMP', 0),
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
                ),
            ));
            return;
        }

        $this->add_missing_columns($table, $columns, array('log_id'));
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
                    'ip_idx'      => array('UNIQUE', array('ip')),
                    'score_idx'   => array('INDEX', array('score')),
                    'expires_idx' => array('INDEX', array('expires_at')),
                ),
            ));
            return;
        }

        $this->add_missing_columns($table, $columns, array('score_id'));
        if ($this->db_tools->sql_column_exists($table, 'last_reason'))
        {
            $this->db_tools->sql_column_change($table, 'last_reason', array('VCHAR:191', ''));
        }
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
                    'ip_idx'      => array('UNIQUE', array('ip')),
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
                    'ip_time_idx'   => array('INDEX', array('ip', 'created_at')),
                    'user_time_idx' => array('INDEX', array('user_id', 'created_at')),
                    'type_idx'      => array('INDEX', array('action_type')),
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
                    'type_idx'    => array('INDEX', array('alert_type')),
                    'read_idx'    => array('INDEX', array('is_read')),
                ),
            ));
            return;
        }

        $this->add_missing_columns($table, $columns, array('alert_id'));
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
}
