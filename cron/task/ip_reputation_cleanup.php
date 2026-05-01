<?php
namespace mundophpbb\antispamguard\cron\task;

class ip_reputation_cleanup extends \phpbb\cron\task\base
{
    protected $config;
    protected $ip_reputation;

    public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\ip_reputation $ip_reputation)
    {
        $this->config = $config;
        $this->ip_reputation = $ip_reputation;
    }

    public function run()
    {
        $this->ip_reputation->prune();
        $this->config->set('antispamguard_ip_reputation_cleanup_last_gc', time(), false);
    }

    public function should_run()
    {
        if (empty($this->config['antispamguard_ip_reputation_enabled']))
        {
            return false;
        }

        $last_gc = isset($this->config['antispamguard_ip_reputation_cleanup_last_gc']) ? (int) $this->config['antispamguard_ip_reputation_cleanup_last_gc'] : 0;
        $interval = isset($this->config['antispamguard_ip_reputation_cleanup_interval']) ? (int) $this->config['antispamguard_ip_reputation_cleanup_interval'] : 86400;

        return $last_gc + $interval < time();
    }
}
