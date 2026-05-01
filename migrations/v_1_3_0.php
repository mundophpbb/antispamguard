<?php
/**
 * AntiSpam Guard 1.3.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_1_3_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '1.3.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_1_2_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_ip_whitelist', '')),
            array('config.add', array('antispamguard_ip_blacklist', '')),
            array('config.update', array('antispamguard_version', '1.3.0')),
        );
    }
}
