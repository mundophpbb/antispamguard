<?php
/**
 * AntiSpam Guard - Registration telemetry cleanup task.
 */

namespace mundophpbb\antispamguard\cron\task;

class registration_audit_cleanup extends \phpbb\cron\task\base
{
	protected $config;
	protected $registration_audit;

	public function __construct(\phpbb\config\config $config, \mundophpbb\antispamguard\service\registration_audit $registration_audit)
	{
		$this->config = $config;
		$this->registration_audit = $registration_audit;
	}

	public function run()
	{
		$this->registration_audit->prune();
		$this->config->set('antispamguard_registration_audit_last_gc', time(), false);
	}

	public function should_run()
	{
		$last_gc = isset($this->config['antispamguard_registration_audit_last_gc'])
			? (int) $this->config['antispamguard_registration_audit_last_gc']
			: 0;

		return $last_gc + 86400 < time();
	}
}
