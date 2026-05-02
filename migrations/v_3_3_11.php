<?php
/**
 * AntiSpam Guard 3.3.11 - Optional StopForumSpam API key and submission controls.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_11 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.11', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_10');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_api_key', '', true)),
            array('config.update', array('antispamguard_version', '3.3.11')),
        );
    }
}
