<?php
/**
 * AntiSpam Guard consolidated upgrade/repair migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_35 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.35', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_0_1_0');
    }

    public function revert_schema()
    {
        return array();
    }
}
