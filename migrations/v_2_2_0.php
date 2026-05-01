<?php
/**
 * AntiSpam Guard 2.2.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_2_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.2.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_1_0');
    }

    public function update_data()
    {
        return array(
            array('permission.add', array('a_antispamguard_manage', true)),
            array('permission.permission_set', array('ROLE_ADMIN_FULL', 'a_antispamguard_manage')),
            array('config.update', array('antispamguard_version', '2.2.0')),
        );
    }
}
