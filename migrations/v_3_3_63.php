<?php
/**
 * AntiSpam Guard 3.3.63 - registration and traffic hardening.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_63 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.63', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_48');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_schema'))),
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('custom', array(array($this, 'migrate_legacy_rate_limiter'))),
            array('config.update', array('antispamguard_sfs_log_all_checks', 0)),
            array('custom', array(array($this, 'set_version'))),
        );
    }

    public function migrate_legacy_rate_limiter()
    {
        if (empty($this->config['antispamguard_rate_limit_enabled']))
        {
            return;
        }

        if (empty($this->config['antispamguard_ip_rate_limit_enabled']))
        {
            $max_hits = isset($this->config['antispamguard_rate_limit_max_attempts'])
                ? max(1, (int) $this->config['antispamguard_rate_limit_max_attempts'])
                : 5;
            $window = isset($this->config['antispamguard_rate_limit_window'])
                ? max(60, (int) $this->config['antispamguard_rate_limit_window'])
                : 3600;

            $this->config->set('antispamguard_ip_rate_limit_enabled', 1);
            $this->config->set('antispamguard_ip_rate_limit_max_hits', $max_hits);
            $this->config->set('antispamguard_ip_rate_limit_window', $window);
            $this->config->set('antispamguard_ip_rate_limit_action', 'block');
        }

        $this->config->set('antispamguard_rate_limit_enabled', 0);
    }

    public function revert_schema()
    {
        return array();
    }
}
