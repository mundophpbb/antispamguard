<?php
/**
 * AntiSpam Guard 3.3.42 — repair missing ACP modules in phpbb_modules.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_42 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.42', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_41');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_acp_modules'))),
            array('config.update', array('antispamguard_version', '3.3.42')),
        );
    }

    public function repair_acp_modules()
    {
        if (!$this->module_tool->exists('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_TITLE', true))
        {
            $this->module_tool->add('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_TITLE');
        }

        if (!$this->module_tool->exists('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_SETTINGS', true))
        {
            $this->module_tool->add('acp', 'ACP_ANTISPAMGUARD_TITLE', array(
                'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
                'modes' => array('settings', 'logs', 'stats', 'about', 'sfs'),
            ));
        }
    }

    public function revert_schema()
    {
        return array();
    }
}