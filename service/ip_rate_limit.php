<?php
/**
 * AntiSpam Guard - Immediate IP rate limit service.
 */

namespace mundophpbb\antispamguard\service;

class ip_rate_limit
{
	protected $config;
	protected $db;
	protected $table;
	protected $atomic_store;

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix, atomic_store $atomic_store = null)
	{
		$this->config = $config;
		$this->db = $db;
		$this->table = $table_prefix . 'antispamguard_ip_rate';
		$this->atomic_store = $atomic_store ?: new atomic_store($db);
	}

	public function is_enabled()
	{
		return !empty($this->config['antispamguard_ip_rate_limit_enabled']);
	}

	public function hit($ip)
	{
		$ip = trim((string) $ip);

		if (!$this->is_enabled() || $ip === '')
		{
			return $this->empty_result();
		}

		$window = $this->get_window();
		$max_hits = $this->get_max_hits();
		$hits = $this->hit_key($ip, $window);

		return array(
			'hits' => $hits,
			'max_hits' => $max_hits,
			'window' => $window,
			'limited' => ($hits > $max_hits),
			'action' => $this->get_action(),
		);
	}


	public function hit_subnet($ip)
	{
		$ip = trim((string) $ip);

		if (empty($this->config['antispamguard_subnet_rate_limit_enabled']) || $ip === '')
		{
			return $this->empty_subnet_result($ip);
		}

		$subnet = $this->get_ip_subnet($ip);
		if ($subnet === '')
		{
			return $this->empty_subnet_result($ip);
		}

		$key = (strpos($subnet, ':') !== false)
			? 'subnet6:' . substr(hash('sha256', $subnet), 0, 32)
			: 'subnet:' . str_replace('/24', '', $subnet);
		$window = $this->get_subnet_window();
		$max_hits = $this->get_subnet_max_hits();
		$hits = $this->hit_key($key, $window);

		return array(
			'subnet' => $subnet,
			'hits' => $hits,
			'max_hits' => $max_hits,
			'window' => $window,
			'limited' => ($hits > $max_hits),
			'action' => $this->get_subnet_action(),
		);
	}

	public function prune()
	{
		$sql = 'DELETE FROM ' . $this->table . '
			WHERE expires_at <= ' . time();
		$this->db->sql_query($sql);

		return (int) $this->db->sql_affectedrows();
	}

	public function reset_all()
	{
		$this->db->sql_query('DELETE FROM ' . $this->table);

		return (int) $this->db->sql_affectedrows();
	}

	protected function get_row($ip)
	{
		$sql = 'SELECT *
			FROM ' . $this->table . "
			WHERE ip = '" . $this->db->sql_escape($ip) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function hit_key($key, $window)
	{
		$now = time();
		$window = max(1, (int) $window);
		$key = (string) $key;

		// One atomic UPDATE owns both increment and expired-window reset.
		// Concurrent requests serialize on the unique IP row instead of
		// performing DELETE + INSERT and creating duplicate counters.
		$condition = 'first_hit + ' . $window . ' >= ' . $now;
		$sql = 'UPDATE ' . $this->table . '
			SET hits = CASE WHEN ' . $condition . ' THEN hits + 1 ELSE 1 END,
				first_hit = CASE WHEN ' . $condition . ' THEN first_hit ELSE ' . $now . ' END,
				last_hit = ' . $now . ',
				expires_at = ' . ($now + $window) . "
			WHERE ip = '" . $this->db->sql_escape($key) . "'";
		$this->db->sql_query($sql);

		if ((int) $this->db->sql_affectedrows() === 0)
		{
			$this->atomic_store->insert_if_missing($this->table, array(
				'ip' => $key,
				'hits' => 0,
				'first_hit' => $now,
				'last_hit' => $now,
				'expires_at' => $now + $window,
			), 'ip');
			$this->db->sql_query($sql);
		}

		$row = $this->get_row($key);

		return $row ? (int) $row['hits'] : 1;
	}

	protected function get_window()
	{
		return isset($this->config['antispamguard_ip_rate_limit_window']) ? max(1, (int) $this->config['antispamguard_ip_rate_limit_window']) : 60;
	}

	protected function get_max_hits()
	{
		return isset($this->config['antispamguard_ip_rate_limit_max_hits']) ? max(1, (int) $this->config['antispamguard_ip_rate_limit_max_hits']) : 5;
	}

	protected function get_action()
	{
		$action = isset($this->config['antispamguard_ip_rate_limit_action']) ? (string) $this->config['antispamguard_ip_rate_limit_action'] : 'block';

		return in_array($action, array('block', 'score', 'log_only'), true) ? $action : 'block';
	}


	protected function get_subnet_window()
	{
		return isset($this->config['antispamguard_subnet_rate_limit_window']) ? max(60, (int) $this->config['antispamguard_subnet_rate_limit_window']) : 600;
	}

	protected function get_subnet_max_hits()
	{
		return isset($this->config['antispamguard_subnet_rate_limit_max_hits']) ? max(1, (int) $this->config['antispamguard_subnet_rate_limit_max_hits']) : 10;
	}

	protected function get_subnet_action()
	{
		$action = isset($this->config['antispamguard_subnet_rate_limit_action']) ? (string) $this->config['antispamguard_subnet_rate_limit_action'] : 'score';

		return in_array($action, array('block', 'score', 'log_only'), true) ? $action : 'score';
	}

	protected function empty_subnet_result($ip)
	{
		return array(
			'subnet' => $this->get_ip_subnet($ip),
			'hits' => 0,
			'max_hits' => $this->get_subnet_max_hits(),
			'window' => $this->get_subnet_window(),
			'limited' => false,
			'action' => $this->get_subnet_action(),
		);
	}

	protected function get_ip_subnet($ip)
	{
		$ip = trim((string) $ip);

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			$parts = explode('.', $ip);
			if (count($parts) !== 4)
			{
				return '';
			}

			return (int) $parts[0] . '.' . (int) $parts[1] . '.' . (int) $parts[2] . '.0/24';
		}

		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			return '';
		}

		$packed = @inet_pton($ip);
		if ($packed === false || strlen($packed) !== 16)
		{
			return '';
		}

		$network = substr($packed, 0, 8) . str_repeat("\0", 8);
		$formatted = @inet_ntop($network);

		return ($formatted === false) ? '' : $formatted . '/64';
	}

	protected function empty_result()
	{
		return array(
			'hits' => 0,
			'max_hits' => $this->get_max_hits(),
			'window' => $this->get_window(),
			'limited' => false,
			'action' => $this->get_action(),
		);
	}
}
