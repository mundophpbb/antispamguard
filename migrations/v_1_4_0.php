<?php
/**
 * AntiSpam Guard 1.4.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_1_4_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '1.4.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_1_3_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_cron_last_prune', 0, false)),
            array('config.update', array('antispamguard_version', '1.4.0')),
        );
    }
}
