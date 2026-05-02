<?php
/**
 * AntiSpam Guard 2.8.6 - Unified whitelist test tool.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_8_6 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.8.6', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_8_5');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.8.6')),
        );
    }
}
