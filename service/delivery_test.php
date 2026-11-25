<?php
/**
 * Prevent overlapping manual notifications for the same channel.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class delivery_test
{
	public static function reserve(\phpbb\config\config $config, $key, $now)
	{
		$last_attempt = isset($config[$key]) ? $config[$key] : '0';

		return (int) $last_attempt + 15 <= $now && $config->set_atomic($key, $last_attempt, $now, false);
	}
}
