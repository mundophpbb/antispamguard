<?php
/**
 * AntiSpam Guard 3.3.20 - StopForumSpam log de-duplication maintenance.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_20 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.20', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_19');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'remove_exact_duplicate_sfs_logs'))),
            array('config.update', array('antispamguard_version', '3.3.20')),
        );
    }

    public function remove_exact_duplicate_sfs_logs()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $seen = array();
        $delete_ids = array();

        $sql = 'SELECT log_id, check_source, user_ip, user_email, username, listed_count, strong_hit, blocked, details_json, created_at
            FROM ' . $table . '
            ORDER BY created_at DESC, log_id DESC';
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result))
        {
            $key = implode("\x1F", array(
                (string) $row['created_at'],
                (string) $row['check_source'],
                (string) $row['user_ip'],
                (string) $row['user_email'],
                (string) $row['username'],
                (string) $row['listed_count'],
                (string) $row['strong_hit'],
                (string) $row['blocked'],
                (string) $row['details_json'],
            ));

            if (isset($seen[$key]))
            {
                $delete_ids[] = (int) $row['log_id'];
            }
            else
            {
                $seen[$key] = true;
            }
        }
        $this->db->sql_freeresult($result);

        if (empty($delete_ids))
        {
            return;
        }

        foreach (array_chunk($delete_ids, 250) as $chunk)
        {
            $sql = 'DELETE FROM ' . $table . '
                WHERE ' . $this->db->sql_in_set('log_id', array_map('intval', $chunk));
            $this->db->sql_query($sql);
        }
    }
}
