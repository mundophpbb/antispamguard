<?php
/**
 * AntiSpam Guard 2.7.10 - StopForumSpam whitelist settings.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_10 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.10', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_9');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_whitelist_ips', '')),
            array('config.add', array('antispamguard_sfs_whitelist_emails', '')),
            array('config.add', array('antispamguard_sfs_whitelist_usernames', '')),
            array('config.update', array('antispamguard_version', '2.7.10')),
        );
    }
}
