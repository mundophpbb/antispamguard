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

    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, $table_prefix)
    {
        $this->db = $db;
        $this->config = $config;
        $this->table = $table_prefix . 'antispamguard_sfs_cache';
    }

    public function get($type, $value = null)
    {
        if ($value === null)
        {
            $parts = explode(':', (string) $type, 2);
            $type = $parts[0];
            $value = isset($parts[1]) ? $parts[1] : '';
        }

        $sql = 'SELECT *
            FROM ' . $this->table . "
            WHERE lookup_type = '" . $this->db->sql_escape((string) $type) . "'
                AND lookup_value = '" . $this->db->sql_escape((string) $value) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return false;
        }

        if (!empty($row['expires_at']) && (int) $row['expires_at'] < time())
        {
            $this->delete($type, $value);
            return false;
        }

        $data = json_decode(isset($row['response_json']) ? $row['response_json'] : '', true);
        if (!is_array($data))
        {
            $data = array();
        }

        return array(
            'cached' => true,
            'data' => $data,
            'is_listed' => !empty($row['is_listed']),
            'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0,
            'frequency' => isset($row['frequency']) ? (int) $row['frequency'] : 0,
        );
    }

    public function set($type, $value = null, $data = null, $is_listed = false, $confidence = 0, $frequency = 0)
    {
        if ($data === null)
        {
            $data = $value;
            $value = '';
        }

        $ttl = isset($this->config['antispamguard_sfs_cache_ttl']) ? (int) $this->config['antispamguard_sfs_cache_ttl'] : 86400;
        $ttl = max(60, $ttl);

        $this->delete($type, $value);

        $insert = array(
            'lookup_type' => (string) $type,
            'lookup_value' => (string) $value,
            'response_json' => json_encode($data),
            'is_listed' => (int) (bool) $is_listed,
            'confidence' => (float) $confidence,
            'frequency' => (int) $frequency,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        );

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $insert);
        $this->db->sql_query($sql);
    }

    public function set_error($type, $value = null)
    {
        $this->set($type, $value, array('error' => true), false, 0, 0);
    }

    public function delete($type, $value = null)
    {
        if ($value === null)
        {
            $parts = explode(':', (string) $type, 2);
            $type = $parts[0];
            $value = isset($parts[1]) ? $parts[1] : '';
        }

        $sql = 'DELETE FROM ' . $this->table . "
            WHERE lookup_type = '" . $this->db->sql_escape((string) $type) . "'
                AND lookup_value = '" . $this->db->sql_escape((string) $value) . "'";
        $this->db->sql_query($sql);
    }

    public function prune()
    {
        $sql = 'DELETE FROM ' . $this->table . '
            WHERE expires_at <= ' . time();
        $this->db->sql_query($sql);
    }
}
