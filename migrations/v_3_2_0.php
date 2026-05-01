<?php
/**
 * AntiSpam Guard 3.2.0 - Slow spam detection.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_2_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.2.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_1_0');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_activity_log' => array(
                    'COLUMNS' => array(
                        'activity_id' => array('UINT', null, 'auto_increment'),
                        'ip'          => array('VCHAR:45', ''),
                        'user_id'     => array('UINT', 0),
                        'action_type' => array('VCHAR:32', ''),
                        'created_at'  => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'activity_id',
                    'KEYS' => array(
                        'ip_time_idx'   => array('INDEX', array('ip', 'created_at')),
                        'user_time_idx' => array('INDEX', array('user_id', 'created_at')),
                        'type_idx'      => array('INDEX', array('action_type')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_slowspam_enabled', 1)),
            array('config.add', array('antispamguard_slowspam_window', 1800)),
            array('config.add', array('antispamguard_slowspam_threshold', 8)),
            array('config.add', array('antispamguard_slowspam_prune_after', 86400)),
            array('config.add', array('antispamguard_decision_weight_slowspam', 35)),
            array('config.update', array('antispamguard_version', '3.2.0')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_activity_log',
            ),
        );
    }
}
