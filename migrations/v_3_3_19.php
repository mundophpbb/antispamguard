<?php
/**
 * AntiSpam Guard 3.3.19 - Log de-duplication and logs pagination maintenance.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_19 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.19', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_18');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'remove_exact_duplicate_logs'))),
            array('config.update', array('antispamguard_version', '3.3.19')),
        );
    }

    public function remove_exact_duplicate_logs()
    {
        $table = $this->table_prefix . 'antispamguard_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $seen = array();
        $delete_ids = array();

        $sql = 'SELECT log_id, log_time, user_ip, username, email, form_type, reason, user_agent
            FROM ' . $table . '
            ORDER BY log_time DESC, log_id DESC';
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result))
        {
            $key = implode("\x1F", array(
                (string) $row['log_time'],
                (string) $row['user_ip'],
                (string) $row['username'],
                (string) $row['email'],
                (string) $row['form_type'],
                (string) $row['reason'],
                (string) $row['user_agent'],
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
