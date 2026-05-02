<?php
/**
 * AntiSpam Guard 2.8.4 - Global trusted IP whitelist.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_8_4 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.8.4', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_8_3');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_trusted_ip_whitelist', '')),
            array('config.update', array('antispamguard_version', '2.8.4')),
        );
    }
}
