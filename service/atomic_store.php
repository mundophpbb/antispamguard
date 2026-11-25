<?php
/**
 * AntiSpam Guard - Cross-database insert-if-missing helper.
 */

namespace mundophpbb\antispamguard\service;

class atomic_store
{
	protected $db;

	public function __construct(\phpbb\db\driver\driver_interface $db)
	{
		$this->db = $db;
	}

	/**
	 * Insert a seed row without replacing an existing unique-key row.
	 *
	 * The target table must already have a UNIQUE index on $unique_column.
	 */
	public function insert_if_missing($table, array $data, $unique_column)
	{
		$table = (string) $table;
		$unique_column = (string) $unique_column;

		if ($table === '' || $unique_column === '' || !array_key_exists($unique_column, $data))
		{
			return;
		}

		$layer = method_exists($this->db, 'get_sql_layer') ? (string) $this->db->get_sql_layer() : '';
		$insert = $this->db->sql_build_array('INSERT', $data);

		if (in_array($layer, array('mysql', 'mysql4', 'mysqli'), true))
		{
			$sql = 'INSERT IGNORE INTO ' . $table . ' ' . $insert;
		}
		else if ($layer === 'postgres')
		{
			$sql = 'INSERT INTO ' . $table . ' ' . $insert . '
				ON CONFLICT (' . $unique_column . ') DO NOTHING';
		}
		else if ($layer === 'sqlite3' || $layer === 'sqlite')
		{
			$sql = 'INSERT OR IGNORE INTO ' . $table . ' ' . $insert;
		}
		else if ($layer === 'mssqlnative' || $layer === 'mssql_odbc' || $layer === 'mssql')
		{
			$value = $this->sql_literal($data[$unique_column]);
			$sql = 'IF NOT EXISTS (SELECT 1 FROM ' . $table . ' WITH (UPDLOCK, HOLDLOCK)
					WHERE ' . $unique_column . ' = ' . $value . ')
				BEGIN
					INSERT INTO ' . $table . ' ' . $insert . '
				END';
		}
		else if ($layer === 'oracle')
		{
			$columns = array_keys($data);
			$values = array();
			foreach ($data as $value)
			{
				$values[] = $this->sql_literal($value);
			}

			$unique_value = $this->sql_literal($data[$unique_column]);
			$sql = 'MERGE INTO ' . $table . ' target
				USING (SELECT ' . $unique_value . ' AS unique_value FROM dual) source
				ON (target.' . $unique_column . ' = source.unique_value)
				WHEN NOT MATCHED THEN
					INSERT (' . implode(', ', $columns) . ')
					VALUES (' . implode(', ', $values) . ')';
		}
		else
		{
			// phpBB-supported drivers are handled above. This fallback keeps
			// compatibility for custom drivers, while the UNIQUE index remains
			// the final protection against duplicate rows.
			$value = $this->sql_literal($data[$unique_column]);
			$sql = 'INSERT INTO ' . $table . ' ' . $insert;
			$check = 'SELECT ' . $unique_column . ' FROM ' . $table . '
				WHERE ' . $unique_column . ' = ' . $value;
			$result = $this->db->sql_query_limit($check, 1);
			$exists = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($exists)
			{
				return;
			}
		}

		$this->db->sql_query($sql);
	}

	protected function sql_literal($value)
	{
		if ($value === null)
		{
			return 'NULL';
		}

		if (is_int($value) || is_float($value))
		{
			return (string) $value;
		}

		return "'" . $this->db->sql_escape((string) $value) . "'";
	}
}
