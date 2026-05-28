<?php
/**
 * AntiSpam Guard consolidated upgrade/repair migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_35 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.35', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_1_0');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_schema'))),
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('config.update', array('antispamguard_version', '3.3.35')),
        );
    }

    public function revert_schema()
    {
        return array();
    }
}
