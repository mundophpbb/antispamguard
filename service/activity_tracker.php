<?php
/**
 * AntiSpam Guard - Activity tracker for slow spam detection.
 */

namespace mundophpbb\antispamguard\service;

class activity_tracker
{
    protected $db;
    protected $config;
    protected $table;

    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, $table_prefix)
    {
        $this->db = $db;
        $this->config = $config;
        $this->table = $table_prefix . 'antispamguard_activity_log';
    }

    public function is_enabled()
    {
        return !empty($this->config['antispamguard_slowspam_enabled']);
    }

    public function log($ip, $user_id, $action_type)
    {
        if (!$this->is_enabled())
        {
            return;
        }

        $ip = trim((string) $ip);
        $action_type = trim((string) $action_type);

        if ($ip === '' || $action_type === '')
        {
            return;
        }

        $data = array(
            'ip' => $ip,
            'user_id' => (int) $user_id,
            'action_type' => $action_type,
            'created_at' => time(),
        );

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
        $this->db->sql_query($sql);
    }

    public function count_recent_by_ip($ip, $seconds, $action_type = '')
    {
        $ip = trim((string) $ip);

        if ($ip === '')
        {
            return 0;
        }

        $where = "ip = '" . $this->db->sql_escape($ip) . "'
            AND created_at >= " . (time() - max(1, (int) $seconds));

        if ($action_type !== '')
        {
            $where .= " AND action_type = '" . $this->db->sql_escape($action_type) . "'";
        }

        $sql = 'SELECT COUNT(activity_id) AS total
            FROM ' . $this->table . '
            WHERE ' . $where;
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        return $total;
    }

    public function is_slow_spam($ip, $action_type = '')
    {
        if (!$this->is_enabled())
        {
            return false;
        }

        $window = isset($this->config['antispamguard_slowspam_window']) ? (int) $this->config['antispamguard_slowspam_window'] : 1800;
        $threshold = isset($this->config['antispamguard_slowspam_threshold']) ? (int) $this->config['antispamguard_slowspam_threshold'] : 8;

        if ($threshold <= 0)
        {
            return false;
        }

        return $this->count_recent_by_ip($ip, $window, $action_type) >= $threshold;
    }

    public function prune()
    {
        $after = isset($this->config['antispamguard_slowspam_prune_after']) ? (int) $this->config['antispamguard_slowspam_prune_after'] : 86400;

        if ($after <= 0)
        {
            return 0;
        }

        $sql = 'DELETE FROM ' . $this->table . '
            WHERE created_at < ' . (time() - $after);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_affectedrows();
    }
}
