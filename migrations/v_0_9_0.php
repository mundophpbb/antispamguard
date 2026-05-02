<?php
/**
 * AntiSpam Guard 0.9.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_9_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.9.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_8_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_content_filter_enabled', 0)),
            array('config.add', array('antispamguard_blocked_keywords', '')),
            array('config.add', array('antispamguard_max_urls', 0)),
            array('config.update', array('antispamguard_version', '0.9.0')),
        );
    }
}
