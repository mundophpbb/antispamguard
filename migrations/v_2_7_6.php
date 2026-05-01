<?php
/**
 * AntiSpam Guard 2.7.6 - StopForumSpam cleanup cron settings.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_6 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.6', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_5');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_cleanup_interval', 86400)),
            array('config.add', array('antispamguard_sfs_log_retention_days', 90)),
            array('config.add', array('antispamguard_sfs_cleanup_last_gc', 0, true)),
            array('config.update', array('antispamguard_version', '2.7.6')),
        );
    }
}
