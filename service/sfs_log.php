<?php
/**
 * AntiSpam Guard - StopForumSpam log service.
 */

namespace mundophpbb\antispamguard\service;

class sfs_log
{
    protected $db;
    protected $table;

    public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db = $db;
        $this->table = $table_prefix . 'antispamguard_sfs_log';
    }

    public function add($source, $ip, $email, $username, array $decision)
    {
        $details = isset($decision['results']) ? $decision['results'] : array();
        $details['_decision'] = array(
            'action_mode' => isset($decision['action_mode']) ? $decision['action_mode'] : 'block',
            'matched' => !empty($decision['matched']),
            'soft' => !empty($decision['soft']),
            'log_only' => !empty($decision['log_only']),
            'debug' => !empty($decision['debug']),
            'debug_status' => isset($decision['debug_status']) ? $decision['debug_status'] : '',
            'status' => isset($decision['status']) ? $decision['status'] : '',
            'sfs_enabled' => !isset($decision['sfs_enabled']) || !empty($decision['sfs_enabled']),
        );

        $data = array(
            'check_source' => (string) $source,
            'user_ip' => (string) $ip,
            'user_email' => (string) $email,
            'username' => (string) $username,
            'listed_count' => isset($decision['listed_count']) ? (int) $decision['listed_count'] : 0,
            'strong_hit' => !empty($decision['strong_hit']) ? 1 : 0,
            'blocked' => !empty($decision['block']) ? 1 : 0,
            'details_json' => json_encode($details),
            'created_at' => time(),
        );

        $existing_log_id = $this->recent_duplicate_log_exists($data);
        if ($existing_log_id)
        {
            return (int) $existing_log_id;
        }

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_nextid();
    }

    protected function recent_duplicate_log_exists(array $data)
    {
        $window_start = max(0, (int) $data['created_at'] - 5);

        $sql = 'SELECT log_id
            FROM ' . $this->table . '
            WHERE created_at >= ' . (int) $window_start . "
                AND check_source = '" . $this->db->sql_escape($data['check_source']) . "'
                AND user_ip = '" . $this->db->sql_escape($data['user_ip']) . "'
                AND user_email = '" . $this->db->sql_escape($data['user_email']) . "'
                AND username = '" . $this->db->sql_escape($data['username']) . "'
                AND listed_count = " . (int) $data['listed_count'] . "
                AND strong_hit = " . (int) $data['strong_hit'] . "
                AND blocked = " . (int) $data['blocked'] . "
                AND details_json = '" . $this->db->sql_escape($data['details_json']) . "'
            ORDER BY log_id DESC";
        $result = $this->db->sql_query_limit($sql, 1);
        $log_id = (int) $this->db->sql_fetchfield('log_id');
        $this->db->sql_freeresult($result);

        return $log_id;
    }
}
