<?php
/**
 * AntiSpam Guard 0.3.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_3_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.3.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_2_0');
    }

    public function update_schema()
    {
        return array(
            'add_columns' => array(
                $this->table_prefix . 'antispamguard_log' => array(
                    'form_type' => array('VCHAR:30', 'register'),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_protect_posts', 1)),
            array('config.update', array('antispamguard_version', '0.3.0')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_columns' => array(
                $this->table_prefix . 'antispamguard_log' => array('form_type'),
            ),
        );
    }
}
