<?php
/**
 * AntiSpam Guard - Slow spam activity cleanup cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class slowspam_cleanup extends \phpbb\cron\task\base
{
    protected $config;
    protected $activity_tracker;

    public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\activity_tracker $activity_tracker)
    {
        $this->config = $config;
        $this->activity_tracker = $activity_tracker;
    }

    public function run()
    {
        $this->activity_tracker->prune();
        $this->config->set('antispamguard_slowspam_cleanup_last_gc', time(), false);
    }

    public function should_run()
    {
        if (empty($this->config['antispamguard_slowspam_enabled']))
        {
            return false;
        }

        $last_gc = isset($this->config['antispamguard_slowspam_cleanup_last_gc']) ? (int) $this->config['antispamguard_slowspam_cleanup_last_gc'] : 0;
        $interval = isset($this->config['antispamguard_slowspam_cleanup_interval']) ? (int) $this->config['antispamguard_slowspam_cleanup_interval'] : 86400;

        return $last_gc + $interval < time();
    }
}
