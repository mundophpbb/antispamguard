<?php
/**
 * AntiSpam Guard - StopForumSpam cache service.
 */

namespace mundophpbb\antispamguard\service;

class sfs_cache
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var string */
	protected $table;

	/** @var atomic_store */
	protected $atomic_store;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, $table_prefix, atomic_store $atomic_store = null)
	{
		$this->db = $db;
		$this->config = $config;
		$this->table = $table_prefix . 'antispamguard_sfs_cache';
		$this->atomic_store = $atomic_store ?: new atomic_store($db);
	}

	public function get($type, $value = null)
	{
		if ($value === null)
		{
			$parts = explode(':', (string) $type, 2);
			$type = $parts[0];
			$value = isset($parts[1]) ? $parts[1] : '';
		}

		$rows = $this->get_many(array((string) $type => (string) $value));

		return isset($rows[(string) $type]) ? $rows[(string) $type] : false;
	}

	/**
	 * Load IP, e-mail and username cache entries in one database query.
	 *
	 * @param array<string,string> $checks
	 * @return array<string,array>
	 */
	public function get_many(array $checks)
	{
		$hashes = array();
		$expected = array();
		foreach ($checks as $type => $value)
		{
			$type = (string) $type;
			$value = (string) $value;
			if ($type === '' || $value === '')
			{
				continue;
			}

			$hash = $this->build_lookup_hash($type, $value);
			$hashes[] = $hash;
			$expected[$hash] = array('type' => $type, 'value' => $value);
		}

		if (empty($hashes))
		{
			return array();
		}

		$sql = 'SELECT *
			FROM ' . $this->table . '
			WHERE ' . $this->db->sql_in_set('lookup_hash', array_values(array_unique($hashes))) . '
			ORDER BY created_at DESC, cache_id DESC';
		$result = $this->db->sql_query($sql);
		$cached = array();
		$now = time();

		while ($row = $this->db->sql_fetchrow($result))
		{
			$type = isset($row['lookup_type']) ? (string) $row['lookup_type'] : '';
			$value = isset($row['lookup_value']) ? (string) $row['lookup_value'] : '';
			$hash = isset($row['lookup_hash']) ? (string) $row['lookup_hash'] : '';
			if ($type === '' || !isset($expected[$hash]) || $expected[$hash]['type'] !== $type || $expected[$hash]['value'] !== $value || isset($cached[$type]) || (!empty($row['expires_at']) && (int) $row['expires_at'] < $now))
			{
				continue;
			}

			$data = json_decode(isset($row['response_json']) ? $row['response_json'] : '', true);
			if (!is_array($data))
			{
				$data = array();
			}

			$cached[$type] = array(
				'cached' => true,
				'data' => $data,
				'is_listed' => !empty($row['is_listed']),
				'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0,
				'frequency' => isset($row['frequency']) ? (int) $row['frequency'] : 0,
				'error' => !empty($data['error']),
				'error_status' => isset($data['status']) ? (string) $data['status'] : '',
			);
		}
		$this->db->sql_freeresult($result);

		return $cached;
	}

	public function set($type, $value = null, $data = null, $is_listed = false, $confidence = 0, $frequency = 0, $ttl_override = null)
	{
		if ($data === null)
		{
			$data = $value;
			$value = '';
		}

		$ttl = ($ttl_override !== null)
			? (int) $ttl_override
			: (isset($this->config['antispamguard_sfs_cache_ttl']) ? (int) $this->config['antispamguard_sfs_cache_ttl'] : 86400);
		$ttl = max(60, $ttl);

		$data_row = array(
			'lookup_type' => (string) $type,
			'lookup_value' => (string) $value,
			'lookup_hash' => $this->build_lookup_hash($type, $value),
			'response_json' => json_encode($data),
			'is_listed' => (int) (bool) $is_listed,
			'confidence' => (float) $confidence,
			'frequency' => (int) $frequency,
			'created_at' => time(),
			'expires_at' => time() + $ttl,
		);

		$this->atomic_store->insert_if_missing($this->table, array(
			'lookup_type' => (string) $type,
			'lookup_value' => (string) $value,
			'lookup_hash' => $data_row['lookup_hash'],
			'response_json' => '',
			'is_listed' => 0,
			'confidence' => 0,
			'frequency' => 0,
			'created_at' => time(),
			'expires_at' => time() + $ttl,
		), 'lookup_hash');

		// Atomic last-write-wins refresh on one unique cache row.
		$sql = 'UPDATE ' . $this->table . '
			SET ' . $this->db->sql_build_array('UPDATE', $data_row) . "
			WHERE lookup_hash = '" . $this->db->sql_escape($data_row['lookup_hash']) . "'";
		$this->db->sql_query($sql);
	}

	public function set_error($type, $value = null, $status = 'remote_error')
	{
		$ttl = isset($this->config['antispamguard_sfs_error_cache_ttl'])
			? max(60, (int) $this->config['antispamguard_sfs_error_cache_ttl'])
			: 300;

		$status = trim((string) $status);
		if ($status === '')
		{
			$status = 'remote_error';
		}

		$this->set($type, $value, array('error' => true, 'status' => $status), false, 0, 0, $ttl);
	}

	public function delete($type, $value = null)
	{
		if ($value === null)
		{
			$parts = explode(':', (string) $type, 2);
			$type = $parts[0];
			$value = isset($parts[1]) ? $parts[1] : '';
		}

		$hash = $this->build_lookup_hash($type, $value);
		$sql = 'DELETE FROM ' . $this->table . "
			WHERE lookup_hash = '" . $this->db->sql_escape($hash) . "'";
		$this->db->sql_query($sql);
	}

	public function prune()
	{
		$sql = 'DELETE FROM ' . $this->table . '
			WHERE expires_at <= ' . time();
		$this->db->sql_query($sql);
	}

	protected function build_lookup_hash($type, $value)
	{
		return hash('sha256', (string) $type . "\0" . (string) $value);
	}
}
