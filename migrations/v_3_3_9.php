<?php
/**
 * AntiSpam Guard 3.3.9 - Harden log reason storage.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_9 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.9', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_8');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_reason_columns'))),
            array('config.update', array('antispamguard_version', '3.3.9')),
        );
    }

    public function repair_reason_columns()
    {
        $this->repair_main_log_reason();
        $this->repair_ip_score_reason();
    }

    protected function repair_main_log_reason()
    {
        $table = $this->table_prefix . 'antispamguard_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            $this->db_tools->sql_create_table($table, array(
                'COLUMNS' => array(
                    'log_id'     => array('UINT', null, 'auto_increment'),
                    'log_time'   => array('TIMESTAMP', 0),
                    'user_ip'    => array('VCHAR:45', ''),
                    'username'   => array('VCHAR:255', ''),
                    'email'      => array('VCHAR:255', ''),
                    'reason'     => array('VCHAR:191', ''),
                    'user_agent' => array('VCHAR:255', ''),
                    'form_type'  => array('VCHAR:30', 'register'),
                ),
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

        $columns = array(
            'log_time'   => array('TIMESTAMP', 0),
            'user_ip'    => array('VCHAR:45', ''),
            'username'   => array('VCHAR:255', ''),
            'email'      => array('VCHAR:255', ''),
            'reason'     => array('VCHAR:191', ''),
            'user_agent' => array('VCHAR:255', ''),
            'form_type'  => array('VCHAR:30', 'register'),
        );

        foreach ($columns as $column => $definition)
        {
            if (!$this->db_tools->sql_column_exists($table, $column))
            {
                $this->db_tools->sql_column_add($table, $column, $definition);
            }
        }

        $this->db_tools->sql_column_change($table, 'reason', array('VCHAR:191', ''));
        $this->db_tools->sql_column_change($table, 'user_ip', array('VCHAR:45', ''));
        $this->db_tools->sql_column_change($table, 'form_type', array('VCHAR:30', 'register'));
    }

    protected function repair_ip_score_reason()
    {
        $table = $this->table_prefix . 'antispamguard_ip_score';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        if ($this->db_tools->sql_column_exists($table, 'last_reason'))
        {
            $this->db_tools->sql_column_change($table, 'last_reason', array('VCHAR:191', ''));
        }
    }
}
