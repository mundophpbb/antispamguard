<?php
/**
 * AntiSpam Guard 2.0.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.0.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_1_8_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_simulation_mode', 0)),
            array('config.update', array('antispamguard_version', '2.0.0')),
        );
    }
}
