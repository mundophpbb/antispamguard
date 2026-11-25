<?php
/**
 * Store notification delivery results without destinations or message data.
 */

namespace mundophpbb\antispamguard\service;

class delivery_history
{
	const RETENTION_DAYS = 30;
	const FILTERS = array(
		'channel' => array('email', 'telegram'),
		'kind' => array('test', 'spike', 'digest'),
		'outcome' => array('accepted', 'failed', 'unconfirmed'),
	);

	const UNCONFIRMED_DETAILS = array('email_unconfirmed', 'telegram_timeout', 'telegram_unconfirmed');
	const FAILURE_DETAILS = array(
		'email_transport_failure', 'telegram_token_rejected', 'telegram_chat_unavailable',
		'telegram_access_denied', 'telegram_rate_limited', 'telegram_request_rejected',
	);

	protected $db;
	protected $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
	{
		$this->db = $db;
		$this->table = $table_prefix . 'antispamguard_delivery';
	}

	public function record($channel, $kind, $accepted, $now, $detail = '')
	{
		$sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', array(
			'created_at' => (int) $now,
			'channel' => $channel,
			'kind' => $kind,
			'accepted' => (int) (bool) $accepted,
			'delivery_detail' => $accepted ? '' : self::valid_detail($detail),
		));
		$this->db->sql_query($sql);
	}

	public static function valid_detail($detail)
	{
		return in_array($detail, self::UNCONFIRMED_DETAILS, true) || in_array($detail, self::FAILURE_DETAILS, true) ? $detail : '';
	}

	public static function outcome($accepted, $detail)
	{
		if ($accepted)
		{
			return 'accepted';
		}
		return in_array($detail, self::UNCONFIRMED_DETAILS, true) ? 'unconfirmed' : 'failed';
	}

	public function page(array $filters, $limit, $offset, $now)
	{
		$predicate = $this->filter_predicate($filters, $now);
		$summary = $this->summarize($predicate, $now);
		$total = $summary['channels']['all']['total'];

		$limit = max(1, (int) $limit);
		$offset = min(intdiv(max(0, (int) $offset), $limit), intdiv(max(0, $total - 1), $limit)) * $limit;
		$result = $this->db->sql_query_limit(
			'SELECT created_at, channel, kind, accepted, delivery_detail FROM ' . $this->table . ' WHERE ' . $predicate
				. ' ORDER BY created_at DESC, delivery_id DESC',
			$limit,
			$offset
		);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return array('total' => $total, 'offset' => $offset, 'rows' => $rows, 'summary' => $summary['channels'], 'diagnostics' => $summary['diagnostics'], 'daily' => $summary['daily']);
	}

	protected function summarize($predicate, $now)
	{
		$empty = array('total' => 0, 'accepted' => 0, 'failed' => 0, 'unconfirmed' => 0);
		$summary = array('email' => $empty, 'telegram' => $empty, 'other' => $empty, 'all' => $empty);
		$diagnostics = array();
		$daily = array();
		$day_expression = $this->utc_day_expression($now);
		$sql = 'SELECT channel, accepted, delivery_detail, ' . $day_expression . ' AS delivery_day, COUNT(*) AS total
			FROM ' . $this->table . ' WHERE ' . $predicate . '
			GROUP BY channel, accepted, delivery_detail, ' . $day_expression;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$channel = in_array($row['channel'], self::FILTERS['channel'], true) ? $row['channel'] : 'other';
			$detail = self::valid_detail($row['delivery_detail']);
			$outcome = self::outcome($row['accepted'], $detail);
			$count = (int) $row['total'];
			$summary[$channel][$outcome] += $count;
			$summary[$channel]['total'] += $count;
			$summary['all'][$outcome] += $count;
			$summary['all']['total'] += $count;
			$day = intdiv((int) $row['delivery_day'], 86400) * 86400;
			if (!isset($daily[$day]))
			{
				$daily[$day] = $empty;
			}
			$daily[$day][$outcome] += $count;
			$daily[$day]['total'] += $count;
			if ($outcome !== 'accepted' && $detail !== '')
			{
				$diagnostics[$detail] = ($diagnostics[$detail] ?? 0) + $count;
			}
		}
		$this->db->sql_freeresult($result);

		uksort($diagnostics, function ($left, $right) use ($diagnostics)
		{
			return ($diagnostics[$right] <=> $diagnostics[$left]) ?: strcmp($left, $right);
		});

		krsort($daily, SORT_NUMERIC);
		return array('channels' => $summary, 'diagnostics' => $diagnostics, 'daily' => $daily);
	}

	protected function utc_day_expression($now)
	{
		$first = intdiv($this->cutoff($now), 86400) * 86400;
		$today = intdiv($now, 86400) * 86400;
		$expression = 'CASE';
		for ($day = $first; $day <= $today; $day += 86400)
		{
			$expression .= ' WHEN created_at < ' . (int) ($day + 86400) . ' THEN ' . (int) $day;
		}
		// PHP also buckets timestamps beyond today's UTC boundary when clocks differ.
		return $expression . ' ELSE created_at END';
	}

	/**
	 * Yield export rows in descending time and ID order without paging by offset.
	 */
	public function export_rows(array $filters, $now)
	{
		$batch_size = 500;
		$predicate = $this->filter_predicate($filters, $now);
		$result = $this->db->sql_query('SELECT MAX(delivery_id) AS max_id FROM ' . $this->table);
		$max_id = (int) $this->db->sql_fetchfield('max_id');
		$this->db->sql_freeresult($result);
		$cursor = '';

		do
		{
			$result = $this->db->sql_query_limit(
				'SELECT delivery_id, created_at, channel, kind, accepted, delivery_detail
					FROM ' . $this->table . ' WHERE ' . $predicate . ' AND delivery_id <= ' . (int) $max_id
					. $cursor . ' ORDER BY created_at DESC, delivery_id DESC',
				$batch_size
			);
			$count = 0;
			try
			{
				while ($row = $this->db->sql_fetchrow($result))
				{
					$count++;
					$time = (int) $row['created_at'];
					$id = (int) $row['delivery_id'];
					$detail = self::valid_detail($row['delivery_detail']);
					yield array(
						gmdate('Y-m-d\TH:i:s\Z', $time),
						in_array($row['channel'], self::FILTERS['channel'], true) ? $row['channel'] : '',
						in_array($row['kind'], self::FILTERS['kind'], true) ? $row['kind'] : '',
						self::outcome($row['accepted'], $detail),
						$detail,
					);
				}
			}
			finally
			{
				$this->db->sql_freeresult($result);
			}

			if ($count === $batch_size)
			{
				$cursor = ' AND (created_at < ' . (int) $time . ' OR (created_at = ' . (int) $time
					. ' AND delivery_id < ' . (int) $id . '))';
			}
		}
		while ($count === $batch_size);
	}

	protected function filter_predicate(array $filters, $now)
	{
		$cutoff = $this->cutoff($now);
		$from = max($cutoff, $filters['from_time'] ?? $cutoff);
		$where = array('created_at >= ' . (int) $from);
		if (isset($filters['until_time']))
		{
			$where[] = 'created_at < ' . (int) $filters['until_time'];
		}
		foreach (array('channel', 'kind') as $field)
		{
			if (!empty($filters[$field]) && in_array($filters[$field], self::FILTERS[$field], true))
			{
				$where[] = $field . " = '" . $this->db->sql_escape($filters[$field]) . "'";
			}
		}
		if (!empty($filters['outcome']) && in_array($filters['outcome'], self::FILTERS['outcome'], true))
		{
			$accepted = $filters['outcome'] === 'accepted';
			$where[] = 'accepted = ' . (int) $accepted;
			if (!$accepted)
			{
				$where[] = $this->db->sql_in_set('delivery_detail', self::UNCONFIRMED_DETAILS, $filters['outcome'] === 'failed');
			}
		}
		$diagnostic = self::valid_detail($filters['diagnostic'] ?? '');
		if ($diagnostic !== '')
		{
			$where[] = 'accepted = 0';
			$where[] = "delivery_detail = '" . $this->db->sql_escape($diagnostic) . "'";
		}
		return implode(' AND ', $where);
	}

	public function prune($now)
	{
		$this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE created_at < ' . (int) $this->cutoff($now));
	}

	protected function cutoff($now)
	{
		return $now - self::RETENTION_DAYS * 86400;
	}
}
