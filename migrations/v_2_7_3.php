<?php
/**
 * AntiSpam Guard 2.7.3 - StopForumSpam decision log.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_3 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.3', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_2');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_log' => array(
                    'COLUMNS' => array(
                        'log_id'       => array('UINT', null, 'auto_increment'),
                        'check_source' => array('VCHAR:50', ''),
                        'user_ip'      => array('VCHAR:45', ''),
                        'user_email'   => array('VCHAR:255', ''),
                        'username'     => array('VCHAR:255', ''),
                        'listed_count' => array('UINT', 0),
                        'strong_hit'   => array('BOOL', 0),
                        'blocked'      => array('BOOL', 0),
                        'details_json' => array('MTEXT_UNI', ''),
                        'created_at'   => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => array(
                        'created_idx' => array('INDEX', array('created_at')),
                        'blocked_idx' => array('INDEX', array('blocked')),
                        'source_idx'  => array('INDEX', array('check_source')),
                        'ip_idx'      => array('INDEX', array('user_ip')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.7.3')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_log',
            ),
        );
    }
}
