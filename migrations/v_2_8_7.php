<?php
/**
 * AntiSpam Guard 2.8.7 - Immediate IP rate limit.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_8_7 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.8.7', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_8_6');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_ip_rate' => array(
                    'COLUMNS' => array(
                        'rate_id'   => array('UINT', null, 'auto_increment'),
                        'ip'        => array('VCHAR:45', ''),
                        'hits'      => array('UINT', 0),
                        'first_hit' => array('TIMESTAMP', 0),
                        'last_hit'  => array('TIMESTAMP', 0),
                        'expires_at'=> array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'rate_id',
                    'KEYS' => array(
                        'ip_idx'      => array('UNIQUE', array('ip')),
                        'expires_idx' => array('INDEX', array('expires_at')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_ip_rate_limit_enabled', 1)),
            array('config.add', array('antispamguard_ip_rate_limit_window', 60)),
            array('config.add', array('antispamguard_ip_rate_limit_max_hits', 5)),
            array('config.add', array('antispamguard_ip_rate_limit_action', 'block')),
            array('config.add', array('antispamguard_ip_reputation_weight_rate_limit', 3)),
            array('config.update', array('antispamguard_version', '2.8.7')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_ip_rate',
            ),
        );
    }
}
