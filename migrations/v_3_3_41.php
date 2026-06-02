<?php
/**
 * AntiSpam Guard 3.3.41 — lenient registration audit for autofill/timing false positives.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_41 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.41', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_40');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'ensure_register_audit_config'))),
            array('config.update', array('antispamguard_version', '3.3.41')),
        );
    }

    public function ensure_register_audit_config()
    {
        if (!isset($this->config['antispamguard_register_audit_soft_signals']))
        {
            $this->config->set('antispamguard_register_audit_soft_signals', 1);
        }
    }

    public function revert_schema()
    {
        return array();
    }
}