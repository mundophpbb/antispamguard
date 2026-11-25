<?php
/**
 * Describe the health of a notification task using its stored timestamps.
 */

namespace mundophpbb\antispamguard\service;

class cron_status
{
	const LATE_AFTER_MINUTES = 15;

	public static function describe($enabled, $runnable, $last_poll, $enabled_at, $now)
	{
		if (!$enabled)
		{
			$state = 'disabled';
		}
		else if (!$runnable)
		{
			$state = 'unavailable';
		}
		else if (max($last_poll, $enabled_at) + self::LATE_AFTER_MINUTES * 60 <= $now)
		{
			$state = 'late';
		}
		else
		{
			$state = ($last_poll > 0 && $last_poll >= $enabled_at) ? 'recent' : 'waiting';
		}

		return array('state' => $state, 'last_poll' => $last_poll);
	}
}
