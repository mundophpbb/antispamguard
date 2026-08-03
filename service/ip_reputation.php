<?php
/**
 * AntiSpam Guard - Local IP reputation service.
 */

namespace mundophpbb\antispamguard\service;

class ip_reputation
{
    protected $config;
    protected $db;
    protected $table;
    protected $request_cache = array();
    protected $atomic_store;

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix, atomic_store $atomic_store = null)
    {
        $this->config = $config;
        $this->db = $db;
        $this->table = $table_prefix . 'antispamguard_ip_score';
        $this->atomic_store = $atomic_store ?: new atomic_store($db);
    }

    public function is_enabled()
    {
        return !empty($this->config['antispamguard_ip_reputation_enabled']);
    }

    public function add_event($ip, $reason)
    {
        $ip = trim((string) $ip);

        if (!$this->is_enabled() || $ip === '')
        {
            return $this->get_empty_result();
        }

        $weight = $this->get_reason_weight($reason);

        if ($weight <= 0)
        {
            return $this->get($ip);
        }

        $now = time();
        $ttl = $this->get_ttl();

        $initial_row = $this->get_row($ip);
        if (!$initial_row)
        {
            $this->atomic_store->insert_if_missing($this->table, array(
                'ip' => $ip,
                'score' => 0,
                'hits' => 0,
                'last_reason' => '',
                'first_seen' => $now,
                'last_update' => $now,
                'expires_at' => $now + $ttl,
            ), 'ip');
        }

        $updated = false;
        $score = 0;
        $hits = 0;

        // Compare-and-swap preserves decay and prevents concurrent events from
        // overwriting one another. A changed row is re-read and retried.
        for ($attempt = 0; $attempt < 5; $attempt++)
        {
            $row = ($attempt === 0 && $initial_row) ? $initial_row : $this->get_row($ip);
            if (!$row)
            {
                continue;
            }

            $old_score = (int) $row['score'];
            $old_hits = (int) $row['hits'];
            $old_update = (int) $row['last_update'];
            $score = $this->apply_decay($old_score, $old_update, $now) + $weight;
            $hits = $old_hits + 1;

            $data = array(
                'score' => $score,
                'hits' => $hits,
                'last_reason' => (string) $reason,
                'last_update' => $now,
                'expires_at' => $now + $ttl,
            );

            $sql = 'UPDATE ' . $this->table . '
                SET ' . $this->db->sql_build_array('UPDATE', $data) . "
                WHERE ip = '" . $this->db->sql_escape($ip) . "'
                    AND score = " . $old_score . '
                    AND hits = ' . $old_hits . '
                    AND last_update = ' . $old_update;
            $this->db->sql_query($sql);

            if ((int) $this->db->sql_affectedrows() > 0)
            {
                $updated = true;
                break;
            }
        }

        if (!$updated)
        {
            // Extremely high contention fallback: never lose the event. Decay
            // is deferred for this one write, but score/hits still increment
            // atomically on the unique row.
            $sql = 'UPDATE ' . $this->table . '
                SET score = score + ' . (int) $weight . ',
                    hits = hits + 1,
                    last_reason = \'' . $this->db->sql_escape((string) $reason) . '\',
                    last_update = ' . $now . ',
                    expires_at = ' . ($now + $ttl) . "
                WHERE ip = '" . $this->db->sql_escape($ip) . "'";
            $this->db->sql_query($sql);

            $row = $this->get_row($ip);
            $score = $row ? (int) $row['score'] : $weight;
            $hits = $row ? (int) $row['hits'] : 1;
        }

        $result = array(
            'score' => $score,
            'threshold' => $this->get_threshold(),
            'blocked' => ($score >= $this->get_threshold()),
            'hits' => $hits,
            'last_reason' => (string) $reason,
        );

        $this->request_cache[$ip] = $result;

        return $result;
    }

    public function get($ip)
    {
        $ip = trim((string) $ip);

        if ($ip === '')
        {
            return $this->get_empty_result();
        }

        if (isset($this->request_cache[$ip]))
        {
            return $this->request_cache[$ip];
        }

        $row = $this->get_row($ip);

        if (!$row)
        {
            $this->request_cache[$ip] = $this->get_empty_result();
            return $this->request_cache[$ip];
        }

        $score = $this->apply_decay((int) $row['score'], (int) $row['last_update']);

        $this->request_cache[$ip] = array(
            'score' => $score,
            'threshold' => $this->get_threshold(),
            'blocked' => ($score >= $this->get_threshold()),
            'hits' => (int) $row['hits'],
            'last_reason' => (string) $row['last_reason'],
        );

        return $this->request_cache[$ip];
    }

    public function prune()
    {
        $sql = 'DELETE FROM ' . $this->table . '
            WHERE expires_at <= ' . time();
        $this->db->sql_query($sql);
        $this->request_cache = array();

        return (int) $this->db->sql_affectedrows();
    }

    public function reset_all()
    {
        $this->db->sql_query('DELETE FROM ' . $this->table);
        $this->request_cache = array();

        return (int) $this->db->sql_affectedrows();
    }

    public function count_all()
    {
        $sql = 'SELECT COUNT(score_id) AS total_scores
            FROM ' . $this->table;
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total_scores');
        $this->db->sql_freeresult($result);

        return $total;
    }

    public function count_blocked()
    {
        $threshold = $this->get_threshold();

        $sql = 'SELECT COUNT(score_id) AS total_scores
            FROM ' . $this->table . '
            WHERE score >= ' . (int) $threshold;
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total_scores');
        $this->db->sql_freeresult($result);

        return $total;
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

    protected function apply_decay($score, $last_update, $now = null)
    {
        $interval = isset($this->config['antispamguard_ip_reputation_decay_interval']) ? (int) $this->config['antispamguard_ip_reputation_decay_interval'] : 600;

        if ($interval <= 0 || $last_update <= 0)
        {
            return max(0, (int) $score);
        }

        $now = $now === null ? time() : (int) $now;
        $steps = (int) floor(($now - $last_update) / $interval);

        if ($steps <= 0)
        {
            return max(0, (int) $score);
        }

        return max(0, ((int) $score) - $steps);
    }

    protected function get_reason_weight($reason)
    {
        switch ($reason)
        {
            case 'honeypot':
                return isset($this->config['antispamguard_ip_reputation_weight_honeypot']) ? (int) $this->config['antispamguard_ip_reputation_weight_honeypot'] : 3;
            case 'timestamp_too_fast':
                return isset($this->config['antispamguard_ip_reputation_weight_timestamp_fast']) ? (int) $this->config['antispamguard_ip_reputation_weight_timestamp_fast'] : 2;
            case 'timestamp_expired':
                return isset($this->config['antispamguard_ip_reputation_weight_timestamp_expired']) ? (int) $this->config['antispamguard_ip_reputation_weight_timestamp_expired'] : 1;
            case 'sfs_reputation':
            case 'sfs_identity':
                return isset($this->config['antispamguard_ip_reputation_weight_sfs']) ? (int) $this->config['antispamguard_ip_reputation_weight_sfs'] : 2;
            case 'ip_rate_limit':
                return isset($this->config['antispamguard_ip_reputation_weight_rate_limit']) ? (int) $this->config['antispamguard_ip_reputation_weight_rate_limit'] : 3;
            case 'subnet_abuse':
                return isset($this->config['antispamguard_ip_reputation_weight_subnet_abuse']) ? (int) $this->config['antispamguard_ip_reputation_weight_subnet_abuse'] : 1;
            case 'random_gmail':
                return isset($this->config['antispamguard_ip_reputation_weight_random_gmail']) ? (int) $this->config['antispamguard_ip_reputation_weight_random_gmail'] : 1;
            default:
                return 1;
        }
    }

    protected function get_threshold()
    {
        return isset($this->config['antispamguard_ip_reputation_threshold']) ? max(1, (int) $this->config['antispamguard_ip_reputation_threshold']) : 20;
    }

    protected function get_ttl()
    {
        return isset($this->config['antispamguard_ip_reputation_ttl']) ? max(3600, (int) $this->config['antispamguard_ip_reputation_ttl']) : 86400;
    }

    protected function get_empty_result()
    {
        return array(
            'score' => 0,
            'threshold' => $this->get_threshold(),
            'blocked' => false,
            'hits' => 0,
            'last_reason' => '',
        );
    }
}
