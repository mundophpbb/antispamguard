<?php
/**
 * AntiSpam Guard 3.3.12 - Optional public anti-spam notice on registration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_12 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.12', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_11');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_register_notice_enabled', 0)),
            array('config.add', array('antispamguard_register_notice_text', '')),
            array('config.update', array('antispamguard_version', '3.3.12')),
        );
    }
}
