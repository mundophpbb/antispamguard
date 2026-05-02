<?php
namespace mundophpbb\antispamguard\migrations;

class v_3_1_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.1.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_0_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_shadowban_enabled', 0)),
            array('config.add', array('antispamguard_shadowban_threshold', 80)),
            array('config.update', array('antispamguard_version', '3.1.0')),
        );
    }
}
