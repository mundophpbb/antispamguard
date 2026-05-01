<?php
/**
 * AntiSpam Guard 3.3.0 - Critical alerts.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_2_5');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_alerts' => array(
                    'COLUMNS' => array(
                        'alert_id'    => array('UINT', null, 'auto_increment'),
                        'alert_type'  => array('VCHAR:64', ''),
                        'severity'    => array('VCHAR:16', 'info'),
                        'ip'          => array('VCHAR:45', ''),
                        'user_id'     => array('UINT', 0),
                        'username'    => array('VCHAR:255', ''),
                        'message'     => array('TEXT', ''),
                        'details_json'=> array('MTEXT_UNI', ''),
                        'created_at'  => array('TIMESTAMP', 0),
                        'is_read'     => array('BOOL', 0),
                    ),
                    'PRIMARY_KEY' => 'alert_id',
                    'KEYS' => array(
                        'created_idx' => array('INDEX', array('created_at')),
                        'type_idx'    => array('INDEX', array('alert_type')),
                        'read_idx'    => array('INDEX', array('is_read')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_alerts_enabled', 1)),
            array('config.add', array('antispamguard_alerts_retention', 604800)),
            array('config.add', array('antispamguard_alerts_last_gc', 0, true)),
            array('config.update', array('antispamguard_version', '3.3.0')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_alerts',
            ),
        );
    }
}
