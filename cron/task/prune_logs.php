<?php
/**
 * AntiSpam Guard automatic log pruning cron task.
 */

namespace mundophpbb\antispamguard\cron\task;

class prune_logs extends \phpbb\cron\task\base
{
    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var string */
    protected $table_prefix;

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
    }

    public function get_name()
    {
        return 'mundophpbb.antispamguard.cron.prune_logs';
    }

    public function run()
    {
        $retention_days = isset($this->config['antispamguard_log_retention_days']) ? max(1, (int) $this->config['antispamguard_log_retention_days']) : 30;
        $cutoff = time() - ($retention_days * 86400);
        $table = $this->table_prefix . 'antispamguard_log';

        $this->db->sql_query('DELETE FROM ' . $table . ' WHERE log_time < ' . (int) $cutoff);
        $this->config->set('antispamguard_cron_last_prune', time(), false);
    }

    public function should_run()
    {
        if (empty($this->config['antispamguard_log_retention_enabled']))
        {
            return false;
        }

        $last_run = isset($this->config['antispamguard_cron_last_prune']) ? (int) $this->config['antispamguard_cron_last_prune'] : 0;

        return $last_run < (time() - 86400);
    }
}
