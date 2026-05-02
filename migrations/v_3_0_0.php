<?php
namespace mundophpbb\antispamguard\migrations;

class v_3_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.0.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_9_9');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_autoban_enabled', 0)),
            array('config.add', array('antispamguard_autoban_threshold', 120)),
            array('config.add', array('antispamguard_autoban_duration', 86400)),
            array('config.update', array('antispamguard_version', '3.0.0')),
        );
    }
}
