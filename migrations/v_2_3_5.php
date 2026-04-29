<?php
/**
 * AntiSpam Guard 2.3.5 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_3_5 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.3.5', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_3_4');
    }

    protected function table_exists($table)
    {
        $sql = "SHOW TABLES LIKE '" . $this->db->sql_escape($table) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return !empty($row);
    }

    public function update_schema()
    {
        $table = $this->table_prefix . 'antispamguard_log';

        if ($this->table_exists($table))
        {
            return array();
        }

        return array(
            'add_tables' => array(
                $table => array(
                    'COLUMNS' => array(
                        'log_id'     => array('UINT', null, 'auto_increment'),
                        'log_time'   => array('TIMESTAMP', 0),
                        'user_ip'    => array('VCHAR:40', ''),
                        'username'   => array('VCHAR:255', ''),
                        'email'      => array('VCHAR:255', ''),
                        'reason'     => array('VCHAR:50', ''),
                        'user_agent' => array('VCHAR:255', ''),
                        'form_type'  => array('VCHAR:20', ''),
                    ),
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => array(
                        'log_time'  => array('INDEX', 'log_time'),
                        'form_type' => array('INDEX', 'form_type'),
                        'reason'    => array('INDEX', 'reason'),
                        'user_ip'   => array('INDEX', 'user_ip'),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.3.5')),
        );
    }
}
