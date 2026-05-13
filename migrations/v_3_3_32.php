<?php
/**
 * AntiSpam Guard 3.3.32 - SFS investigation filters and review counters.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_32 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.32', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_31');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_investigation_indexes'))),
            array('config.update', array('antispamguard_version', '3.3.32')),
        );
    }

    public function repair_sfs_investigation_indexes()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $this->add_index_if_missing($table, 'sfs_ip_created_idx', array('user_ip', 'created_at'));
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
