<?php
/**
 * AntiSpam Guard - StopForumSpam cleanup cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class sfs_cleanup extends \phpbb\cron\task\base
{
    protected $config;
    protected $db;
    protected $table_prefix;

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
    }

    public function run()
    {
        $now = time();

        $cache_table = $this->table_prefix . 'antispamguard_sfs_cache';
        $log_table = $this->table_prefix . 'antispamguard_sfs_log';

        $sql = 'DELETE FROM ' . $cache_table . '
            WHERE expires_at <= ' . (int) $now;
        $this->db->sql_query($sql);

        $retention_days = isset($this->config['antispamguard_sfs_log_retention_days']) ? (int) $this->config['antispamguard_sfs_log_retention_days'] : 90;

        if ($retention_days > 0)
        {
            $cutoff = $now - ($retention_days * 86400);

            $sql = 'DELETE FROM ' . $log_table . '
                WHERE created_at < ' . (int) $cutoff;
            $this->db->sql_query($sql);
        }

        $this->config->set('antispamguard_sfs_cleanup_last_gc', $now, false);
    }

    public function should_run()
    {
        if (empty($this->config['antispamguard_sfs_enabled']))
        {
            return false;
        }

        $last_gc = isset($this->config['antispamguard_sfs_cleanup_last_gc']) ? (int) $this->config['antispamguard_sfs_cleanup_last_gc'] : 0;
        $interval = isset($this->config['antispamguard_sfs_cleanup_interval']) ? (int) $this->config['antispamguard_sfs_cleanup_interval'] : 86400;

        return $last_gc + $interval < time();
    }
}
