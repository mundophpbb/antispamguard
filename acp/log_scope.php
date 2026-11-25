<?php
/**
 * Shared investigation filters for the ACP logs and exports.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

class log_scope
{
	public static function read($request)
	{
		return array(
			'filter_ip' => trim($request->variable('filter_ip', '')),
			'filter_from' => $request->variable('filter_from', ''),
			'filter_until' => $request->variable('filter_until', ''),
		);
	}

	public static function conditions($db, array $filters, $sfs = false)
	{
		$ip = isset($filters['filter_ip']) ? $filters['filter_ip'] : '';
		$from = self::parse_time(isset($filters['filter_from']) ? $filters['filter_from'] : '');
		$until = self::parse_time(isset($filters['filter_until']) ? $filters['filter_until'] : '');
		if (($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) || $from === false || $until === false
			|| ($from !== null && $until !== null && $from >= $until))
		{
			return false;
		}

		$where = array();
		$time_column = $sfs ? 'created_at' : 'log_time';
		if ($ip !== '')
		{
			$where[] = "user_ip = '" . $db->sql_escape($ip) . "'";
		}
		if ($from !== null)
		{
			$where[] = $time_column . ' >= ' . (int) $from;
		}
		if ($until !== null)
		{
			$where[] = $time_column . ' < ' . (int) $until;
		}
		return $where;
	}

	/**
	 * Period/IP conditions for tables whose columns differ from the main log.
	 *
	 * @return array|false SQL conditions, or false when the filters are invalid.
	 */
	public static function column_conditions($db, array $filters, $time_column, $ip_column = '')
	{
		$ip = isset($filters['filter_ip']) ? $filters['filter_ip'] : '';
		$from = self::parse_time(isset($filters['filter_from']) ? $filters['filter_from'] : '');
		$until = self::parse_time(isset($filters['filter_until']) ? $filters['filter_until'] : '');
		if (($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) || $from === false || $until === false
			|| ($from !== null && $until !== null && $from >= $until))
		{
			return false;
		}

		$where = array();
		if ($ip !== '' && $ip_column !== '')
		{
			$where[] = $ip_column . " = '" . $db->sql_escape($ip) . "'";
		}
		if ($from !== null)
		{
			$where[] = $time_column . ' >= ' . (int) $from;
		}
		if ($until !== null)
		{
			$where[] = $time_column . ' < ' . (int) $until;
		}
		return $where;
	}

	public static function query_string(array $filters)
	{
		$filters = array_filter($filters, function ($value)
		{
			return $value !== '';
		});
		return empty($filters) ? '' : '&amp;' . http_build_query($filters, '', '&amp;', PHP_QUERY_RFC3986);
	}

	public static function parse_date($value)
	{
		if ($value === '')
		{
			return null;
		}

		if (substr($value, 0, 4) === '0000')
		{
			return false;
		}

		try
		{
			return self::parse_time($value . 'T00:00');
		}
		catch (\Throwable $exception)
		{
			return false;
		}
	}

	protected static function parse_time($value)
	{
		if ($value === '')
		{
			return null;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value))
		{
			return false;
		}

		$time = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone('UTC'));
		if (!$time || $time->format('Y-m-d\TH:i') !== $value)
		{
			return false;
		}
		return $time->getTimestamp();
	}
}
