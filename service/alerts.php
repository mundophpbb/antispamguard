<?php
/**
 * AntiSpam Guard - Critical alerts service.
 */

namespace mundophpbb\antispamguard\service;

class alerts
{
    protected $db;
    protected $config;
    protected $table;

    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, $table_prefix)
    {
        $this->db = $db;
        $this->config = $config;
        $this->table = $table_prefix . 'antispamguard_alerts';
    }

    public function is_enabled()
    {
        return !empty($this->config['antispamguard_alerts_enabled']);
    }

    public function add($type, $severity, $ip, $user_id, $username, $message, array $details = array())
    {
        if (!$this->is_enabled())
        {
            return;
        }

        $data = array(
            'alert_type' => (string) $type,
            'severity' => (string) $severity,
            'ip' => (string) $ip,
            'user_id' => (int) $user_id,
            'username' => (string) $username,
            'message' => (string) $message,
            'details_json' => json_encode($details),
            'created_at' => time(),
            'is_read' => 0,
        );

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
        $this->db->sql_query($sql);
    }

    public function prune()
    {
        $retention = isset($this->config['antispamguard_alerts_retention']) ? (int) $this->config['antispamguard_alerts_retention'] : 604800;

        if ($retention <= 0)
        {
            return 0;
        }

        $sql = 'DELETE FROM ' . $this->table . '
            WHERE created_at < ' . (time() - $retention);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_affectedrows();
    }

    public function mark_all_read()
    {
        $sql = 'UPDATE ' . $this->table . '
            SET is_read = 1
            WHERE is_read = 0';
        $this->db->sql_query($sql);

        return (int) $this->db->sql_affectedrows();
    }
}
