<?php
/**
 * AntiSpam Guard 3.3.64 - safer SFS identity decisions and atomic stores.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_64 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
	public function effectively_installed()
	{
		return isset($this->config['antispamguard_version'])
			&& version_compare($this->config['antispamguard_version'], '3.3.64', '>=');
	}

	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_63');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'repair_schema'))),
			array('custom', array(array($this, 'deduplicate_atomic_tables'))),
			array('custom', array(array($this, 'install_atomic_unique_indexes'))),
			array('custom', array(array($this, 'set_version'))),
		);
	}

	public function deduplicate_atomic_tables()
	{
		$this->deduplicate_sfs_cache();
		$this->deduplicate_by_latest(
			$this->table_prefix . 'antispamguard_ip_rate',
			'rate_id',
			'ip',
			'last_hit'
		);
		$this->deduplicate_ip_scores();
	}

	public function install_atomic_unique_indexes()
	{
		$cache_table = $this->table_prefix . 'antispamguard_sfs_cache';
		$score_table = $this->table_prefix . 'antispamguard_ip_score';
		$rate_table = $this->table_prefix . 'antispamguard_ip_rate';

		$this->add_unique_index_if_missing($cache_table, 'lookup_hash_unique', array('lookup_hash'));
		$this->add_unique_index_if_missing($score_table, 'ip_unique', array('ip'));
		$this->add_unique_index_if_missing($rate_table, 'ip_unique', array('ip'));

		// The unique indexes cover these previous lookup paths too, avoiding
		// redundant index writes on every request.
		$this->drop_index_if_present($cache_table, 'lookup_idx');
		$this->drop_index_if_present($score_table, 'ip_idx');
		$this->drop_index_if_present($rate_table, 'ip_idx');
	}

	protected function deduplicate_sfs_cache()
	{
		$table = $this->table_prefix . 'antispamguard_sfs_cache';
		$sql = 'SELECT cache_id, lookup_type, lookup_value
			FROM ' . $table . '
			ORDER BY created_at DESC, cache_id DESC';
		$result = $this->db->sql_query($sql);
		$seen = array();
		$delete_ids = array();
		$hash_updates = array();

		while ($row = $this->db->sql_fetchrow($result))
		{
			$type = isset($row['lookup_type']) ? (string) $row['lookup_type'] : '';
			$value = isset($row['lookup_value']) ? (string) $row['lookup_value'] : '';
			$hash = hash('sha256', $type . "\0" . $value);
			$cache_id = (int) $row['cache_id'];

			if (isset($seen[$hash]))
			{
				$delete_ids[] = $cache_id;
				continue;
			}

			$seen[$hash] = $cache_id;
			$hash_updates[$cache_id] = $hash;
		}
		$this->db->sql_freeresult($result);

		foreach ($hash_updates as $cache_id => $hash)
		{
			$this->db->sql_query('UPDATE ' . $table . "
				SET lookup_hash = '" . $this->db->sql_escape($hash) . "'
				WHERE cache_id = " . (int) $cache_id);
		}

		$this->delete_ids_in_batches($table, 'cache_id', $delete_ids);
	}

	protected function deduplicate_by_latest($table, $id_column, $key_column, $order_column)
	{
		$sql = 'SELECT ' . $key_column . ', COUNT(' . $id_column . ') AS duplicate_count
			FROM ' . $table . '
			GROUP BY ' . $key_column . '
			HAVING COUNT(' . $id_column . ') > 1';
		$result = $this->db->sql_query($sql);
		$keys = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$keys[] = isset($row[$key_column]) ? (string) $row[$key_column] : '';
		}
		$this->db->sql_freeresult($result);

		foreach ($keys as $key)
		{
			$sql = 'SELECT ' . $id_column . '
				FROM ' . $table . "
				WHERE " . $key_column . " = '" . $this->db->sql_escape($key) . "'
				ORDER BY " . $order_column . ' DESC, ' . $id_column . ' DESC';
			$result = $this->db->sql_query($sql);
			$keep = 0;
			$delete_ids = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$id = (int) $row[$id_column];
				if ($keep === 0)
				{
					$keep = $id;
				}
				else
				{
					$delete_ids[] = $id;
				}
			}
			$this->db->sql_freeresult($result);
			$this->delete_ids_in_batches($table, $id_column, $delete_ids);
		}
	}

	protected function deduplicate_ip_scores()
	{
		$table = $this->table_prefix . 'antispamguard_ip_score';
		$sql = 'SELECT ip, COUNT(score_id) AS duplicate_count
			FROM ' . $table . '
			GROUP BY ip
			HAVING COUNT(score_id) > 1';
		$result = $this->db->sql_query($sql);
		$ips = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ips[] = isset($row['ip']) ? (string) $row['ip'] : '';
		}
		$this->db->sql_freeresult($result);

		foreach ($ips as $ip)
		{
			$sql = 'SELECT * FROM ' . $table . "
				WHERE ip = '" . $this->db->sql_escape($ip) . "'
				ORDER BY last_update DESC, score_id DESC";
			$result = $this->db->sql_query($sql);
			$keep = false;
			$delete_ids = array();
			$max_score = 0;
			$max_hits = 0;
			$first_seen = 0;
			$expires_at = 0;

			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($keep === false)
				{
					$keep = $row;
				}
				else
				{
					$delete_ids[] = (int) $row['score_id'];
				}

				$max_score = max($max_score, (int) $row['score']);
				$max_hits = max($max_hits, (int) $row['hits']);
				$row_first = (int) $row['first_seen'];
				$first_seen = ($first_seen === 0 || ($row_first > 0 && $row_first < $first_seen)) ? $row_first : $first_seen;
				$expires_at = max($expires_at, (int) $row['expires_at']);
			}
			$this->db->sql_freeresult($result);

			if ($keep !== false)
			{
				$data = array(
					'score' => $max_score,
					'hits' => $max_hits,
					'first_seen' => $first_seen,
					'expires_at' => $expires_at,
				);
				$this->db->sql_query('UPDATE ' . $table . '
					SET ' . $this->db->sql_build_array('UPDATE', $data) . '
					WHERE score_id = ' . (int) $keep['score_id']);
			}

			$this->delete_ids_in_batches($table, 'score_id', $delete_ids);
		}
	}

	protected function delete_ids_in_batches($table, $id_column, array $ids)
	{
		foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $batch)
		{
			if (!empty($batch))
			{
				$this->db->sql_query('DELETE FROM ' . $table . '
					WHERE ' . $this->db->sql_in_set($id_column, $batch));
			}
		}
	}

	protected function add_unique_index_if_missing($table, $index_name, array $columns)
	{
		$indexes = $this->db_tools->sql_list_index($table);
		if (!in_array($index_name, $indexes, true))
		{
			$this->db_tools->sql_create_unique_index($table, $index_name, $columns);
		}
	}

	protected function drop_index_if_present($table, $index_name)
	{
		$indexes = $this->db_tools->sql_list_index($table);
		if (in_array($index_name, $indexes, true))
		{
			$this->db_tools->sql_index_drop($table, $index_name);
		}
	}

	public function revert_schema()
	{
		return array();
	}
}
