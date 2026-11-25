<?php
/**
 * AntiSpam Guard Telegram alert cron task.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\cron\task;

class telegram_alerts extends \phpbb\cron\task\base
{
	protected $alerts;

	public function __construct(\mundophpbb\antispamguard\service\telegram_alerts $alerts)
	{
		$this->alerts = $alerts;
	}

	public function is_runnable()
	{
		return $this->alerts->is_runnable();
	}

	public function should_run()
	{
		return $this->alerts->should_run(time());
	}

	public function run()
	{
		$this->alerts->run(time());
	}
}
