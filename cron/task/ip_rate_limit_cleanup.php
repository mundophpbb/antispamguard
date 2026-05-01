<?php
/**
 * AntiSpam Guard - IP rate limit cleanup cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class ip_rate_limit_cleanup extends \phpbb\cron\task\base
{
    protected $config;
    protected $ip_rate_limit;

    public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\ip_rate_limit $ip_rate_limit)
    {
        $this->config = $config;
        $this->ip_rate_limit = $ip_rate_limit;
    }

    public function run()
    {
        $this->ip_rate_limit->prune();
        $this->config->set('antispamguard_ip_rate_limit_cleanup_last_gc', time(), false);
    }

    public function should_run()
    {
        if (empty($this->config['antispamguard_ip_rate_limit_enabled']))
        {
            return false;
        }

        $last_gc = isset($this->config['antispamguard_ip_rate_limit_cleanup_last_gc']) ? (int) $this->config['antispamguard_ip_rate_limit_cleanup_last_gc'] : 0;
        $interval = isset($this->config['antispamguard_ip_rate_limit_cleanup_interval']) ? (int) $this->config['antispamguard_ip_rate_limit_cleanup_interval'] : 3600;

        return $last_gc + $interval < time();
    }
}
