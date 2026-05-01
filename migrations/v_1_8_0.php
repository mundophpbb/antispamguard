<?php
/**
 * AntiSpam Guard 1.8.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_1_8_0 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_1_7_0');
    }

    public function effectively_installed()
    {
        return isset($this->config['antispamguard_protect_pm']);
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_protect_pm', 0)),
        );
    }
}
