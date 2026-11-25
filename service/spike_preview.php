<?php
/**
 * Simulate the spike rule against retained block records.
 */

namespace mundophpbb\antispamguard\service;

class spike_preview
{
	const DAYS = 7;
	const DISPLAY_LIMIT = 10;

	protected const MINUTE = 60;
	protected const BATCH_SIZE = 1000;

	protected $db;
	protected $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
	{
		$this->db = $db;
		$this->table = $table_prefix . 'antispamguard_log';
	}

	public function simulate(array $rule, $now)
	{
		$end = intdiv($now, self::MINUTE) * self::MINUTE;
		$start = $end - self::DAYS * 86400;
		$rows = $this->blocked_logs($start, $end);
		$window_seconds = $rule['window'] * self::MINUTE;
		$cooldown_seconds = $rule['cooldown'] * self::MINUTE;

		$active_minutes = array();
		$active_ips = array();
		$active_blocks = 0;
		$next_check = $start;
		$result = array('start' => $start, 'end' => $end, 'blocks' => 0, 'alerts' => 0, 'latest' => array());

		for ($check = $start + self::MINUTE; $check <= $end; $check += self::MINUTE)
		{
			// Minute-aligned checks use the same inclusive start and exclusive end as live alerts.
			$expired = $check - $window_seconds - self::MINUTE;
			if (isset($active_minutes[$expired]))
			{
				foreach ($active_minutes[$expired] as $ip => $count)
				{
					if ($ip !== '' && ($active_ips[$ip] -= $count) === 0)
					{
						unset($active_ips[$ip]);
					}
					$active_blocks -= $count;
				}
				unset($active_minutes[$expired]);
			}

			$minute = array();
			while ($rows->valid() && (int) $rows->current()['log_time'] < $check)
			{
				$ip = (string) $rows->current()['user_ip'];
				$minute[$ip] = ($minute[$ip] ?? 0) + 1;
				$active_blocks++;
				$result['blocks']++;
				if ($ip !== '')
				{
					$active_ips[$ip] = ($active_ips[$ip] ?? 0) + 1;
				}
				$rows->next();
			}
			if ($minute)
			{
				$active_minutes[$check - self::MINUTE] = $minute;
			}

			if ($check < $next_check || $active_blocks < $rule['blocks'] || count($active_ips) < $rule['ips'])
			{
				continue;
			}

			$result['alerts']++;
			$result['latest'][] = array('time' => $check, 'blocks' => $active_blocks, 'ips' => count($active_ips));
			if (count($result['latest']) > self::DISPLAY_LIMIT)
			{
				array_shift($result['latest']);
			}

			// The live rule counts only fresh records after a successful alert.
			$next_check = $check + $cooldown_seconds;
			$active_minutes = array();
			$active_ips = array();
			$active_blocks = 0;
		}

		$result['latest'] = array_reverse($result['latest']);
		return $result;
	}

	protected function blocked_logs($start, $end)
	{
		$last_time = $start;
		$last_id = 0;
		do
		{
			$sql = 'SELECT log_id, log_time, user_ip FROM ' . $this->table . '
				WHERE ' . spike_alert::BLOCKED_FILTER . '
					AND log_time >= ' . (int) $last_time . '
					AND log_time < ' . (int) $end . '
					AND (log_time > ' . (int) $last_time . ' OR log_id > ' . (int) $last_id . ')
				ORDER BY log_time ASC, log_id ASC';
			$result = $this->db->sql_query_limit($sql, self::BATCH_SIZE);
			$count = 0;
			try
			{
				while ($row = $this->db->sql_fetchrow($result))
				{
					$last_time = (int) $row['log_time'];
					$last_id = (int) $row['log_id'];
					$count++;
					yield $row;
				}
			}
			finally
			{
				$this->db->sql_freeresult($result);
			}
		}
		while ($count === self::BATCH_SIZE);
	}
}
