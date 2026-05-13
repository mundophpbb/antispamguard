<?php
/**
 * AntiSpam Guard 3.3.30 - SFS action-mode indexing, debug timeout and optional register Gmail heuristic.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_30 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.30', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_29');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_action_mode_column'))),
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('config.update', array('antispamguard_version', '3.3.30')),
        );
    }

    public function repair_sfs_action_mode_column()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        if (!$this->db_tools->sql_column_exists($table, 'action_mode'))
        {
            $this->db_tools->sql_column_add($table, 'action_mode', array('VCHAR:20', ''));
        }

        $this->backfill_sfs_action_mode($table);
        $this->add_index_if_missing($table, 'action_created_idx', array('action_mode', 'created_at'));
        $this->add_index_if_missing($table, 'blocked_action_created_idx', array('blocked', 'action_mode', 'created_at'));
    }

    public function ensure_config_defaults()
    {
        if (!isset($this->config['antispamguard_sfs_debug_until']))
        {
            $this->config->set('antispamguard_sfs_debug_until', 0);
        }

        if (!isset($this->config['antispamguard_random_gmail_register_enabled']))
        {
            $this->config->set('antispamguard_random_gmail_register_enabled', 0);
        }
    }

    protected function backfill_sfs_action_mode($table)
    {
        $sql = 'SELECT log_id, details_json
            FROM ' . $table . "
            WHERE action_mode = '' OR action_mode IS NULL";
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

            $sql_update = 'UPDATE ' . $table . '
                SET action_mode = \'' . $this->db->sql_escape($action_mode) . '\'
                WHERE log_id = ' . (int) $row['log_id'];
            $this->db->sql_query($sql_update);
        }

        $this->db->sql_freeresult($result);
    }

    protected function add_index_if_missing($table, $index_name, array $columns)
    {
        $indexes = $this->db_tools->sql_list_index($table);

        if (!in_array($index_name, $indexes, true))
        {
            $this->db_tools->sql_create_index($table, $index_name, $columns);
        }
    }
}
