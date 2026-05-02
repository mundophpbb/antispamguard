<?php
/**
 * AntiSpam Guard 2.9.1-clean - IP rate limit cleanup.
 *
 * Built on top of 2.8.7 without ACP layout changes.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_9_1 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.9.1', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_8_7');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_ip_rate_limit_cleanup_interval', 3600)),
            array('config.add', array('antispamguard_ip_rate_limit_cleanup_last_gc', 0, true)),
            array('config.update', array('antispamguard_version', '2.9.1')),
        );
    }
}
