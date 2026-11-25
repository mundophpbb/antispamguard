<?php
/**
 * AntiSpam Guard - Critical alerts cleanup cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class alerts_cleanup extends \phpbb\cron\task\base
{
	protected $config;
	protected $alerts;
	protected $history;

	public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\alerts $alerts,
		?\mundophpbb\antispamguard\service\delivery_history $history = null
	)
	{
		$this->config = $config;
		$this->alerts = $alerts;
		$this->history = $history;
	}

	public function run()
	{
		$now = time();
		$this->alerts->prune();
		if ($this->history !== null)
		{
			$this->history->prune($now);
		}
		$this->config->set('antispamguard_alerts_last_gc', $now, false);
	}

	public function should_run()
	{
		$last_gc = isset($this->config['antispamguard_alerts_last_gc']) ? (int) $this->config['antispamguard_alerts_last_gc'] : 0;

		return $last_gc + 86400 < time();
	}
}
