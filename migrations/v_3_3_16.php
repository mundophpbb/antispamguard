<?php
/**
 * AntiSpam Guard 3.3.16 - Add dedicated StopForumSpam ACP module.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_16 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.16', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_3_3_15');
    }

    public function update_data()
    {
        return array(
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                    'modes'           => array('sfs'),
                ),
            )),
            array('config.update', array('antispamguard_version', '3.3.16')),
        );
    }
}
