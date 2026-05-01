<?php
/**
 * AntiSpam Guard 2.7.16 - Honeypot camouflage.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_16 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.16', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_15');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_hp_camouflage_enabled', 1)),
            array('config.update', array('antispamguard_version', '2.7.16')),
        );
    }
}
