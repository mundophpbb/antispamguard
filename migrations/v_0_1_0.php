<?php
/**
 * AntiSpam Guard initial migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_1_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.1.0', '>=');
    }

    public static function depends_on()
    {
        return array('\phpbb\db\migration\data\v310\dev');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_version', '0.1.0')),
            array('config.add', array('antispamguard_enabled', 1)),
            array('config.add', array('antispamguard_hp_name', 'homepage')),
            array('config.add', array('antispamguard_min_seconds', 3)),
            array('config.add', array('antispamguard_max_seconds', 1800)),
            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_ANTISPAMGUARD_TITLE'
            )),
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                    'modes'           => array('settings'),
                ),
            )),
        );
    }
}
