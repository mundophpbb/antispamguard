<?php
/**
 * AntiSpam Guard 3.3.5 - Repair StopForumSpam services and cache schema.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_5 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.5', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_4');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_cache_schema'))),
            array('config.add', array('antispamguard_sfs_cache_ttl', 86400)),
            array('config.update', array('antispamguard_version', '3.3.5')),
        );
    }

    public function repair_sfs_cache_schema()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_cache';

        if (!$this->db_tools->sql_table_exists($table))
        {
            $this->db_tools->sql_create_table($table, array(
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
            ));

            return;
        }

        $columns = array(
            'lookup_type'   => array('VCHAR:20', ''),
            'lookup_value'  => array('VCHAR:255', ''),
            'response_json' => array('MTEXT_UNI', ''),
            'is_listed'     => array('BOOL', 0),
            'confidence'    => array('DECIMAL:5', 0),
            'frequency'     => array('UINT', 0),
            'created_at'    => array('TIMESTAMP', 0),
            'expires_at'    => array('TIMESTAMP', 0),
        );

        foreach ($columns as $column => $definition)
        {
            if (!$this->db_tools->sql_column_exists($table, $column))
            {
                $this->db_tools->sql_column_add($table, $column, $definition);
            }
        }

        if ($this->db_tools->sql_column_exists($table, 'cache_key'))
        {
            $sql = 'UPDATE ' . $table . "
                SET lookup_type = CASE
                        WHEN lookup_type = '' AND cache_key LIKE 'email:%' THEN 'email'
                        WHEN lookup_type = '' AND cache_key LIKE 'username:%' THEN 'username'
                        WHEN lookup_type = '' AND cache_key LIKE 'ip:%' THEN 'ip'
                        WHEN lookup_type = '' THEN cache_key
                        ELSE lookup_type
                    END,
                    lookup_value = CASE
                        WHEN lookup_value = '' AND cache_key LIKE 'email:%' THEN SUBSTRING(cache_key, 7)
                        WHEN lookup_value = '' AND cache_key LIKE 'username:%' THEN SUBSTRING(cache_key, 10)
                        WHEN lookup_value = '' AND cache_key LIKE 'ip:%' THEN SUBSTRING(cache_key, 4)
                        ELSE lookup_value
                    END";
            $this->db->sql_query($sql);
        }

        if ($this->db_tools->sql_column_exists($table, 'response'))
        {
            $sql = 'UPDATE ' . $table . "
                SET response_json = response
                WHERE response_json = ''";
            $this->db->sql_query($sql);
        }
    }
}
