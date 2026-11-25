<?php
/**
 * AntiSpam Guard email reports cron task.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\cron\task;

class email_reports extends \phpbb\cron\task\base
{
	protected $reports;

	public function __construct(\mundophpbb\antispamguard\service\email_reports $reports)
	{
		$this->reports = $reports;
	}

	public function is_runnable()
	{
		return $this->reports->is_runnable();
	}

	public function should_run()
	{
		return $this->reports->should_run(time());
	}

	public function run()
	{
		$this->reports->run(time());
	}
}
