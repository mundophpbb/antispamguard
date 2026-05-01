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

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->db = $db;
        $this->table = $table_prefix . 'antispamguard_ip_rate';
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

        $now = time();
        $window = $this->get_window();
        $max_hits = $this->get_max_hits();

        $row = $this->get_row($ip);

        if ($row && (int) $row['first_hit'] + $window >= $now)
        {
            $hits = ((int) $row['hits']) + 1;

            $data = array(
                'hits' => $hits,
                'last_hit' => $now,
                'expires_at' => $now + $window,
            );

            $sql = 'UPDATE ' . $this->table . '
                SET ' . $this->db->sql_build_array('UPDATE', $data) . "
                WHERE ip = '" . $this->db->sql_escape($ip) . "'";
            $this->db->sql_query($sql);
        }
        else
        {
            $hits = 1;
            $this->delete($ip);

            $data = array(
                'ip' => $ip,
                'hits' => $hits,
                'first_hit' => $now,
                'last_hit' => $now,
                'expires_at' => $now + $window,
            );

            $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
            $this->db->sql_query($sql);
        }

        return array(
            'hits' => $hits,
            'max_hits' => $max_hits,
            'window' => $window,
            'limited' => ($hits > $max_hits),
            'action' => $this->get_action(),
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

    protected function delete($ip)
    {
        $sql = 'DELETE FROM ' . $this->table . "
            WHERE ip = '" . $this->db->sql_escape($ip) . "'";
        $this->db->sql_query($sql);
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
