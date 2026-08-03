<?php
/**
 * AntiSpam Guard 3.3.66 - HTTP client container compatibility.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_66 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.66', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_65');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('custom', array(array($this, 'set_version'))),
        );
    }

    public function revert_schema()
    {
        return array();
    }
}
