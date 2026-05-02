<?php
/**
 * AntiSpam Guard 1.0.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_1_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '1.0.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_9_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_rate_limit_enabled', 0)),
            array('config.add', array('antispamguard_rate_limit_max_attempts', 5)),
            array('config.add', array('antispamguard_rate_limit_window', 3600)),
            array('config.update', array('antispamguard_version', '1.0.0')),
        );
    }
}
