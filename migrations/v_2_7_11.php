<?php
/**
 * AntiSpam Guard 2.7.11 - StopForumSpam action mode.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_11 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.11', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_10');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_action_mode', 'block')),
            array('config.update', array('antispamguard_version', '2.7.11')),
        );
    }
}
