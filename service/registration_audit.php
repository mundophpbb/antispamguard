<?php
/**
 * AntiSpam Guard - Low-volume registration telemetry.
 *
 * Page visits are stored once per IP/time bucket. Form outcomes are counters
 * on the same compact row, so automated traffic cannot create one row per hit.
 */

namespace mundophpbb\antispamguard\service;

class registration_audit
{
	protected $db;
	protected $config;
	protected $table_prefix;
	protected $atomic_store;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, $table_prefix, atomic_store $atomic_store)
	{
		$this->db = $db;
		$this->config = $config;
		$this->table_prefix = (string) $table_prefix;
		$this->atomic_store = $atomic_store;
	}

	public function record_page_view($ip, $user_agent = '', $now = null)
	{
		if (!$this->is_enabled() || empty($this->config['antispamguard_registration_track_page_views']))
		{
			return false;
		}

		$now = $now === null ? time() : (int) $now;
		$row = $this->build_seed_row($ip, $user_agent, $now);
		$table = $this->get_table();

		// A page view means one unique IP in the configured time bucket. A bot
		// refreshing the page repeatedly therefore causes reads, not log rows.
		$sql = 'SELECT audit_id FROM ' . $table . "
			WHERE bucket_key = '" . $this->db->sql_escape($row['bucket_key']) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($exists)
		{
			return false;
		}

		$row['page_views'] = 1;
		$row['last_reason'] = 'page_view';
		$this->atomic_store->insert_if_missing($table, $row, 'bucket_key');

		return true;
	}

	public function record_submission($ip, $user_agent = '', array $outcome = array(), $now = null)
	{
		if (!$this->is_enabled())
		{
			return false;
		}

		$now = $now === null ? time() : (int) $now;
		$row = $this->build_seed_row($ip, $user_agent, $now);
		$table = $this->get_table();
		$this->atomic_store->insert_if_missing($table, $row, 'bucket_key');

		$increments = array('form_submissions = form_submissions + 1');
		foreach (array('phpbb_rejected', 'local_rejected', 'sfs_analyzed') as $metric)
		{
			if (!empty($outcome[$metric]))
			{
				$increments[] = $metric . ' = ' . $metric . ' + 1';
			}
		}

		$reason = isset($outcome['reason']) ? $this->normalize_reason($outcome['reason']) : 'submitted';
		$sql = 'UPDATE ' . $table . '
			SET ' . implode(', ', $increments) . ",
				last_reason = '" . $this->db->sql_escape($reason) . "',
				last_seen = " . (int) $now . "
			WHERE bucket_key = '" . $this->db->sql_escape($row['bucket_key']) . "'";
		$this->db->sql_query($sql);

		return true;
	}

	public function prune($now = null)
	{
		$now = $now === null ? time() : (int) $now;
		$days = isset($this->config['antispamguard_registration_audit_retention_days'])
			? max(1, (int) $this->config['antispamguard_registration_audit_retention_days'])
			: 7;
		$cutoff = $now - ($days * 86400);

		$this->db->sql_query('DELETE FROM ' . $this->get_table() . ' WHERE bucket_start < ' . (int) $cutoff);

		return (int) $this->db->sql_affectedrows();
	}

	protected function build_seed_row($ip, $user_agent, $now)
	{
		$ip = substr(trim((string) $ip), 0, 45);
		$window = isset($this->config['antispamguard_registration_audit_window'])
			? max(60, min(3600, (int) $this->config['antispamguard_registration_audit_window']))
			: 300;
		$bucket_start = (int) (floor(((int) $now) / $window) * $window);

		return array(
			'bucket_key'       => hash('sha256', $bucket_start . '|' . $ip),
			'bucket_start'     => $bucket_start,
			'user_ip'          => $ip,
			'user_agent'       => substr((string) $user_agent, 0, 255),
			'page_views'       => 0,
			'form_submissions' => 0,
			'phpbb_rejected'   => 0,
			'local_rejected'   => 0,
			'sfs_analyzed'     => 0,
			'last_reason'      => '',
			'first_seen'       => (int) $now,
			'last_seen'        => (int) $now,
		);
	}

	protected function normalize_reason($reason)
	{
		$reason = preg_replace('/[^a-z0-9_,:-]+/i', '_', (string) $reason);
		$reason = trim($reason, '_,:-');

		return substr($reason !== '' ? $reason : 'submitted', 0, 191);
	}

	protected function is_enabled()
	{
		return !isset($this->config['antispamguard_registration_audit_enabled'])
			|| !empty($this->config['antispamguard_registration_audit_enabled']);
	}

	protected function get_table()
	{
		return $this->table_prefix . 'antispamguard_registration_audit';
	}
}
