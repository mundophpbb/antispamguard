<?php
/**
 * AntiSpam Guard 2.7.2 - StopForumSpam cache.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_2 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.2', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_3_5');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_cache' => array(
                    'COLUMNS' => array(
                        'cache_id'      => array('UINT', null, 'auto_increment'),
                        'lookup_type'   => array('VCHAR:20', ''),
                        'lookup_value'  => array('VCHAR:255', ''),
                        'response_json' => array('MTEXT_UNI', ''),
                        'is_listed'     => array('BOOL', 0),
                        'confidence'    => array('DECIMAL:5', 0),
                        'frequency'     => array('UINT', 0),
                        'created_at'    => array('TIMESTAMP', 0),
                        'expires_at'    => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'cache_id',
                    'KEYS' => array(
                        'lookup_idx'  => array('INDEX', array('lookup_type', 'lookup_value')),
                        'expires_idx' => array('INDEX', array('expires_at')),
                        'listed_idx'  => array('INDEX', array('is_listed')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.7.2')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_cache',
            ),
        );
    }
}
