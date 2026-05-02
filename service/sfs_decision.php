<?php
/**
 * AntiSpam Guard - StopForumSpam decision service.
 */

namespace mundophpbb\antispamguard\service;

class sfs_decision
{
    protected $config;
    protected $sfs;
    protected $log;

    public function __construct(\phpbb\config\config $config, stopforumspam_client $sfs, sfs_log $log)
    {
        $this->config = $config;
        $this->sfs = $sfs;
        $this->log = $log;
    }

    public function should_block($ip = '', $email = '', $username = '', $source = 'unknown', $force_log = false)
    {
        $sfs_enabled = !empty($this->config['antispamguard_sfs_enabled']);

        // Normal runtime: if SFS is disabled, do not query the external service and do not log.
        // Manual ACP tests pass $force_log = true, so they still perform the lookup and write
        // an audit row explaining that the live SFS protection is disabled.
        if (!$sfs_enabled && !$force_log)
        {
            return array(
                'block' => false,
                'matched' => false,
                'action_mode' => 'disabled',
                'soft' => false,
                'log_only' => false,
                'listed_count' => 0,
                'strong_hit' => false,
                'results' => array(),
                'status' => 'sfs_disabled',
                'log_written' => false,
            );
        }

        $checks = array(
            'ip' => $ip,
            'email' => $email,
            'username' => $username,
        );

        if ($this->is_whitelisted_value($ip, 'antispamguard_sfs_whitelist_ips')
            || $this->is_whitelisted_value($email, 'antispamguard_sfs_whitelist_emails')
            || $this->is_whitelisted_value($username, 'antispamguard_sfs_whitelist_usernames'))
        {
            $decision = array(
                'block' => false,
                'matched' => false,
                'action_mode' => 'whitelist',
                'soft' => false,
                'log_only' => false,
                'listed_count' => 0,
                'strong_hit' => false,
                'results' => array(
                    'whitelist' => array(
                        'matched' => true,
                    ),
                ),
                'status' => 'whitelisted',
                'sfs_enabled' => $sfs_enabled,
                'debug' => (bool) $force_log,
                'debug_status' => $force_log ? 'manual_force_log_whitelisted' : '',
                'log_written' => false,
            );

            $this->maybe_log($source, $ip, $email, $username, $decision, $force_log);

            return $decision;
        }

        $min_confidence = isset($this->config['antispamguard_sfs_min_confidence']) ? (float) $this->config['antispamguard_sfs_min_confidence'] : 50;
        $min_frequency = isset($this->config['antispamguard_sfs_min_frequency']) ? (int) $this->config['antispamguard_sfs_min_frequency'] : 3;
        $block_multiple_hits = !empty($this->config['antispamguard_sfs_block_multiple_hits']);

        $listed_count = 0;
        $strong_hit = false;
        $results = array();

        foreach ($checks as $type => $value)
        {
            $value = trim((string) $value);

            if ($value === '')
            {
                continue;
            }

            $result = $this->sfs->check($type, $value);

            if (!$result)
            {
                $results[$type] = array(
                    'value' => $value,
                    'checked' => true,
                    'is_listed' => false,
                    'error' => true,
                    'confidence' => 0,
                    'frequency' => 0,
                    'cached' => false,
                );

                continue;
            }

            $confidence = isset($result['confidence']) ? (float) $result['confidence'] : 0;
            $frequency = isset($result['frequency']) ? (int) $result['frequency'] : 0;
            $is_listed = !empty($result['is_listed']);

            $results[$type] = array(
                'value' => $value,
                'checked' => true,
                'is_listed' => $is_listed,
                'confidence' => $confidence,
                'frequency' => $frequency,
                'cached' => !empty($result['cached']),
            );

            if (!$is_listed)
            {
                continue;
            }

            $listed_count++;

            if ($confidence >= $min_confidence || $frequency >= $min_frequency)
            {
                $strong_hit = true;
            }
        }

        $matched = $strong_hit || ($block_multiple_hits && $listed_count >= 2);
        $action_mode = isset($this->config['antispamguard_sfs_action_mode']) ? (string) $this->config['antispamguard_sfs_action_mode'] : 'block';

        if (!in_array($action_mode, array('block', 'soft', 'log_only'), true))
        {
            $action_mode = 'block';
        }

        if (!$sfs_enabled)
        {
            $action_mode = 'disabled';
        }

        $block = ($sfs_enabled && $action_mode === 'block') ? $matched : false;

        $decision = array(
            'block' => $block,
            'matched' => $matched,
            'action_mode' => $action_mode,
            'soft' => ($sfs_enabled && $matched && $action_mode === 'soft'),
            'log_only' => ($matched && ($action_mode === 'log_only' || $action_mode === 'disabled')),
            'listed_count' => $listed_count,
            'strong_hit' => $strong_hit,
            'results' => $results,
            'status' => $sfs_enabled ? 'checked' : 'sfs_disabled_manual_check',
            'sfs_enabled' => $sfs_enabled,
            'log_written' => false,
        );

        $debug_log = $this->should_debug_log_sfs($ip);

        if ($debug_log && $listed_count === 0)
        {
            $decision['debug'] = true;
            $decision['debug_status'] = 'checked_not_listed';
        }

        if ($force_log && $listed_count === 0)
        {
            $decision['debug'] = true;
            $decision['debug_status'] = $sfs_enabled ? 'manual_force_log_checked_not_listed' : 'manual_force_log_sfs_disabled_checked_not_listed';
        }

        if ($force_log && !$sfs_enabled && $listed_count > 0)
        {
            $decision['debug'] = true;
            $decision['debug_status'] = 'manual_force_log_sfs_disabled_listed';
        }

        $this->maybe_log($source, $ip, $email, $username, $decision, $force_log);

        return $decision;
    }

    public function has_api_key()
    {
        return $this->sfs->has_api_key();
    }

    public function get_api_key_masked()
    {
        return $this->sfs->get_api_key_masked();
    }

    public function submit_spammer($ip, $email, $username, $evidence = '')
    {
        return $this->sfs->submit_spammer($ip, $email, $username, $evidence);
    }

    protected function maybe_log($source, $ip, $email, $username, array &$decision, $force_log = false)
    {
        $log_enabled = !isset($this->config['antispamguard_sfs_log_enabled']) || !empty($this->config['antispamguard_sfs_log_enabled']);
        $log_only_blocked = !empty($this->config['antispamguard_sfs_log_only_blocked']);
        $debug_log = $this->should_debug_log_sfs($ip);
        $log_all_checks = !isset($this->config['antispamguard_sfs_log_all_checks']) || !empty($this->config['antispamguard_sfs_log_all_checks']);
        $listed_count = isset($decision['listed_count']) ? (int) $decision['listed_count'] : 0;
        $block = !empty($decision['block']);

        $should_log = false;

        if ($force_log)
        {
            $should_log = true;
        }
        else if ($log_enabled && (!$log_only_blocked || $block || $debug_log) && ($listed_count > 0 || $debug_log || $log_all_checks))
        {
            $should_log = true;
        }

        if (!$should_log)
        {
            $decision['log_written'] = false;
            return;
        }

        $log_id = $this->log->add($source, $ip, $email, $username, $decision);
        $decision['log_id'] = (int) $log_id;
        $decision['logged'] = true;
        $decision['log_written'] = true;
    }

    protected function is_whitelisted_value($value, $config_key)
    {
        $value = trim((string) $value);

        if ($value === '' || empty($this->config[$config_key]))
        {
            return false;
        }

        $entries = preg_split('/\r\n|\r|\n/', (string) $this->config[$config_key]);

        foreach ($entries as $entry)
        {
            $entry = trim($entry);

            if ($entry === '')
            {
                continue;
            }

            if (strcasecmp($value, $entry) === 0)
            {
                return true;
            }
        }

        return false;
    }

    protected function is_localhost_ip($ip)
    {
        $ip = trim((string) $ip);

        return in_array($ip, array('127.0.0.1', '::1', 'localhost'), true);
    }

    protected function should_debug_log_sfs($ip)
    {
        if (empty($this->config['antispamguard_sfs_debug_log_all']))
        {
            return false;
        }

        if (!empty($this->config['antispamguard_sfs_debug_localhost_only']))
        {
            return $this->is_localhost_ip($ip);
        }

        return true;
    }
}
