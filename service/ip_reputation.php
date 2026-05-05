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

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->db = $db;
        $this->table = $table_prefix . 'antispamguard_ip_score';
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

        $row = $this->get_row($ip);
        $now = time();

        if ($row)
        {
            $score = $this->apply_decay((int) $row['score'], (int) $row['last_update']);
            $score += $weight;

            $data = array(
                'score'       => $score,
                'hits'        => ((int) $row['hits']) + 1,
                'last_reason' => (string) $reason,
                'last_update' => $now,
                'expires_at'  => $now + $this->get_ttl(),
            );

            $sql = 'UPDATE ' . $this->table . '
                SET ' . $this->db->sql_build_array('UPDATE', $data) . "
                WHERE ip = '" . $this->db->sql_escape($ip) . "'";
            $this->db->sql_query($sql);
        }
        else
        {
            $score = $weight;

            $data = array(
                'ip'          => $ip,
                'score'       => $score,
                'hits'        => 1,
                'last_reason' => (string) $reason,
                'first_seen'  => $now,
                'last_update' => $now,
                'expires_at'  => $now + $this->get_ttl(),
            );

            $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
            $this->db->sql_query($sql);
        }

        return array(
            'score' => $score,
            'threshold' => $this->get_threshold(),
            'blocked' => ($score >= $this->get_threshold()),
        );
    }

    public function get($ip)
    {
        $ip = trim((string) $ip);

        if ($ip === '')
        {
            return $this->get_empty_result();
        }

        $row = $this->get_row($ip);

        if (!$row)
        {
            return $this->get_empty_result();
        }

        $score = $this->apply_decay((int) $row['score'], (int) $row['last_update']);

        return array(
            'score' => $score,
            'threshold' => $this->get_threshold(),
            'blocked' => ($score >= $this->get_threshold()),
            'hits' => (int) $row['hits'],
            'last_reason' => (string) $row['last_reason'],
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

    protected function apply_decay($score, $last_update)
    {
        $interval = isset($this->config['antispamguard_ip_reputation_decay_interval']) ? (int) $this->config['antispamguard_ip_reputation_decay_interval'] : 600;

        if ($interval <= 0 || $last_update <= 0)
        {
            return max(0, (int) $score);
        }

        $steps = (int) floor((time() - $last_update) / $interval);

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
                return isset($this->config['antispamguard_ip_reputation_weight_sfs']) ? (int) $this->config['antispamguard_ip_reputation_weight_sfs'] : 5;
            case 'ip_rate_limit':
                return isset($this->config['antispamguard_ip_reputation_weight_rate_limit']) ? (int) $this->config['antispamguard_ip_reputation_weight_rate_limit'] : 3;
            case 'subnet_abuse':
                return isset($this->config['antispamguard_ip_reputation_weight_subnet_abuse']) ? (int) $this->config['antispamguard_ip_reputation_weight_subnet_abuse'] : 4;
            case 'random_gmail':
                return isset($this->config['antispamguard_ip_reputation_weight_random_gmail']) ? (int) $this->config['antispamguard_ip_reputation_weight_random_gmail'] : 2;
            default:
                return 1;
        }
    }

    protected function get_threshold()
    {
        return isset($this->config['antispamguard_ip_reputation_threshold']) ? max(1, (int) $this->config['antispamguard_ip_reputation_threshold']) : 5;
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
