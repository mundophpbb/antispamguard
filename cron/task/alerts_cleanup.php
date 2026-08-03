<?php
/**
 * AntiSpam Guard - Critical alerts cleanup cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class alerts_cleanup extends \phpbb\cron\task\base
{
    protected $config;
    protected $alerts;

    public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\alerts $alerts)
    {
        $this->config = $config;
        $this->alerts = $alerts;
    }

    public function run()
    {
        $this->alerts->prune();
        $this->config->set('antispamguard_alerts_last_gc', time(), false);
    }

    public function should_run()
    {
        $last_gc = isset($this->config['antispamguard_alerts_last_gc']) ? (int) $this->config['antispamguard_alerts_last_gc'] : 0;

        return $last_gc + 86400 < time();
    }
}
