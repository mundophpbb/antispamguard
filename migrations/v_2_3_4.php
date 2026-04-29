<?php
/**
 * AntiSpam Guard 2.3.4 migration.
 */
namespace mundophpbb\antispamguard\migrations;

class v_2_3_4 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_3_3');
    }

    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.3.4', '>=');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.3.4')),
        );
    }
}
