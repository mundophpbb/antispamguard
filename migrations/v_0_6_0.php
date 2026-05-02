<?php
/**
 * AntiSpam Guard 0.6.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_6_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.6.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_4_0');
    }

    public function update_data()
    {
        return array(
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                    'modes'           => array('stats'),
                ),
            )),
            array('config.update', array('antispamguard_version', '0.6.0')),
        );
    }
}
