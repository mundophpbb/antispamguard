<?php
/**
 * AntiSpam Guard 2.7.4 - StopForumSpam settings.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_4 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.4', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_3');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_enabled', 0)),
            array('config.add', array('antispamguard_sfs_log_enabled', 1)),
            array('config.add', array('antispamguard_sfs_log_only_blocked', 0)),
            array('config.add', array('antispamguard_sfs_min_confidence', 50)),
            array('config.add', array('antispamguard_sfs_min_frequency', 3)),
            array('config.add', array('antispamguard_sfs_block_multiple_hits', 1)),
            array('config.update', array('antispamguard_version', '2.7.4')),
        );
    }
}
