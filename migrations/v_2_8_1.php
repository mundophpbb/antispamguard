<?php
/**
 * AntiSpam Guard 2.8.1 - Local IP reputation audit panel.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_8_1 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.8.1', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_8_0');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.8.1')),
        );
    }
}
