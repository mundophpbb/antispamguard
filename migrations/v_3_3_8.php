<?php
/**
 * AntiSpam Guard 3.3.8 - Registration log and runtime schema repair.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_8 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.8', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_7');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_runtime_schema'))),
            array('config.update', array('antispamguard_version', '3.3.8')),
        );
    }

    public function repair_runtime_schema()
    {
        $this->repair_main_log_table();
        $this->repair_ip_score_table();
        $this->repair_ip_rate_table();
        $this->repair_activity_log_table();
        $this->repair_alerts_table();
    }

    protected function repair_main_log_table()
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

        $this->add_missing_columns($table, $columns);

        // Some broken builds attempted to use an "ip" column. If it exists,
        // preserve those values by copying them into the canonical user_ip field.
        if ($this->db_tools->sql_column_exists($table, 'ip') && $this->db_tools->sql_column_exists($table, 'user_ip'))
        {
            $sql = 'UPDATE ' . $table . "
                SET user_ip = ip
                WHERE user_ip = '' AND ip <> ''";
            $this->db->sql_query($sql);
        }
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
                    'ip_idx'      => array('INDEX', array('ip')),
                    'score_idx'   => array('INDEX', array('score')),
                    'expires_idx' => array('INDEX', array('expires_at')),
                ),
            ));

            return;
        }

        $this->add_missing_columns($table, $columns, array('score_id'));
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
                    'ip_idx'      => array('INDEX', array('ip')),
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
