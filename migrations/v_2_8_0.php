<?php
/**
 * AntiSpam Guard 2.8.0 - Local IP reputation.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_8_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.8.0', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_7_21');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_ip_score' => array(
                    'COLUMNS' => array(
                        'score_id'       => array('UINT', null, 'auto_increment'),
                        'ip'             => array('VCHAR:45', ''),
                        'score'          => array('UINT', 0),
                        'hits'           => array('UINT', 0),
                        'last_reason'    => array('VCHAR:191', ''),
                        'first_seen'     => array('TIMESTAMP', 0),
                        'last_update'    => array('TIMESTAMP', 0),
                        'expires_at'     => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'score_id',
                    'KEYS' => array(
                        'ip_idx'      => array('UNIQUE', array('ip')),
                        'score_idx'   => array('INDEX', array('score')),
                        'expires_idx' => array('INDEX', array('expires_at')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_ip_reputation_enabled', 1)),
            array('config.add', array('antispamguard_ip_reputation_threshold', 5)),
            array('config.add', array('antispamguard_ip_reputation_decay_interval', 600)),
            array('config.add', array('antispamguard_ip_reputation_ttl', 86400)),
            array('config.add', array('antispamguard_ip_reputation_weight_honeypot', 3)),
            array('config.add', array('antispamguard_ip_reputation_weight_timestamp_fast', 2)),
            array('config.add', array('antispamguard_ip_reputation_weight_timestamp_expired', 1)),
            array('config.add', array('antispamguard_ip_reputation_weight_sfs', 5)),
            array('config.update', array('antispamguard_version', '2.8.0')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_ip_score',
            ),
        );
    }
}
