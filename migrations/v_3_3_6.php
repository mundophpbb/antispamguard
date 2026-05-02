<?php
/**
 * AntiSpam Guard 3.3.6 - Manual SFS log hardening.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_6 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.6', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_5');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_log_schema'))),
            array('custom', array(array($this, 'ensure_sfs_config_defaults'))),
            array('config.update', array('antispamguard_version', '3.3.6')),
        );
    }

    public function repair_sfs_log_schema()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            $this->db_tools->sql_create_table($table, array(
                'COLUMNS' => array(
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
                ),
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

        $columns = array(
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

        foreach ($columns as $column => $definition)
        {
            if (!$this->db_tools->sql_column_exists($table, $column))
            {
                $this->db_tools->sql_column_add($table, $column, $definition);
            }
        }
    }

    public function ensure_sfs_config_defaults()
    {
        $defaults = array(
            'antispamguard_sfs_enabled' => 0,
            'antispamguard_sfs_log_enabled' => 1,
            'antispamguard_sfs_log_only_blocked' => 0,
            'antispamguard_sfs_log_all_checks' => 1,
            'antispamguard_sfs_action_mode' => 'block',
        );

        foreach ($defaults as $key => $value)
        {
            if (!isset($this->config[$key]))
            {
                $this->config->set($key, $value);
            }
        }
    }
}
