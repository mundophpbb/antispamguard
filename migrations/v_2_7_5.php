<?php
/**
 * AntiSpam Guard 2.7.5 - StopForumSpam ACP integration marker.
 *
 * No ACP module is added here because the stable extension already registers
 * settings, stats, logs and about modes in earlier migrations.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_5 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.5', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_4');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.7.5')),
        );
    }
}
