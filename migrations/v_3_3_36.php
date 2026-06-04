<?php
/**
 * AntiSpam Guard schema repair migration.
 *
 * Some local/dev installs can have antispamguard_version already set while
 * one or more tables are missing. This migration deliberately re-runs the
 * defensive schema repair and only considers itself installed when the main
 * log table exists.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_36 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        $main_log_table = $this->table_prefix . 'antispamguard_log';

        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.36', '>=')
            && $this->db_tools->sql_table_exists($main_log_table);
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_35');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_schema'))),
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('custom', array(array($this, 'set_version'))),
        );
    }

    public function revert_schema()
    {
        return array();
    }
}
