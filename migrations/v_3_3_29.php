<?php
/**
 * AntiSpam Guard 3.3.29 - SFS moderation actions and review status.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_29 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.29', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_28');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_moderation_columns'))),
            array('config.update', array('antispamguard_version', '3.3.29')),
        );
    }

    public function repair_sfs_moderation_columns()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $columns = array(
            'review_status' => array('VCHAR:32', ''),
            'reviewed_at'   => array('TIMESTAMP', 0),
            'reviewed_by'   => array('UINT', 0),
            'local_action'  => array('VCHAR:32', ''),
        );

        foreach ($columns as $column => $definition)
        {
            if (!$this->db_tools->sql_column_exists($table, $column))
            {
                $this->db_tools->sql_column_add($table, $column, $definition);
            }
        }

        $this->add_index_if_missing($table, 'review_status_idx', array('review_status'));
        $this->add_index_if_missing($table, 'local_action_idx', array('local_action'));
        $this->add_index_if_missing($table, 'blocked_created_idx', array('blocked', 'created_at'));
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
