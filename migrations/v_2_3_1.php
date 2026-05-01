<?php
/**
 * AntiSpam Guard 2.3.1 migration.
 *
 * Registers the ACP About / Diagnostics mode that was introduced in 2.3.0.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_3_1 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.3.1', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_3_0');
    }

    public function update_data()
    {
        return array(
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                    'modes'           => array('about'),
                ),
            )),
            array('config.update', array('antispamguard_version', '2.3.1')),
        );
    }
}
