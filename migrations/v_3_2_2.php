<?php
/**
 * AntiSpam Guard 3.2.2 - Log all StopForumSpam checks.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_2_2 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.2.2', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_2_1');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_log_all_checks', 1)),
            array('config.update', array('antispamguard_version', '3.2.2')),
        );
    }
}
