<?php
/**
 * AntiSpam Guard 2.7.20 - Manual StopForumSpam cleanup.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_20 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.20', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_7_19');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.7.20')),
        );
    }
}
