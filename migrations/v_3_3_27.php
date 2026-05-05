<?php
/**
 * AntiSpam Guard 3.3.27 - Subnet abuse, random Gmail heuristic and risk log fields.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_27 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.27', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_3_3_26');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_risk_log_columns'))),
            array('config.add', array('antispamguard_subnet_rate_limit_enabled', 1)),
            array('config.add', array('antispamguard_subnet_rate_limit_window', 600)),
            array('config.add', array('antispamguard_subnet_rate_limit_max_hits', 10)),
            array('config.add', array('antispamguard_subnet_rate_limit_action', 'score')),
            array('config.add', array('antispamguard_random_gmail_enabled', 1)),
            array('config.add', array('antispamguard_decision_weight_subnet_abuse', 45)),
            array('config.add', array('antispamguard_decision_weight_random_gmail', 20)),
            array('config.add', array('antispamguard_ip_reputation_weight_subnet_abuse', 4)),
            array('config.add', array('antispamguard_ip_reputation_weight_random_gmail', 2)),
            array('config.update', array('antispamguard_version', '3.3.27')),
        );
    }

    public function repair_risk_log_columns()
    {
        $table = $this->table_prefix . 'antispamguard_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $columns = array(
            'risk_score' => array('UINT:8', 0),
            'risk_level' => array('VCHAR:20', ''),
            'action' => array('VCHAR:20', ''),
            'matched_rules' => array('VCHAR:191', ''),
        );

        foreach ($columns as $column => $definition)
        {
            if (!$this->db_tools->sql_column_exists($table, $column))
            {
                $this->db_tools->sql_column_add($table, $column, $definition);
            }
        }
    }
}
