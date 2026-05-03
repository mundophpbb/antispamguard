<?php
/**
 * AntiSpam Guard 3.3.22 - StopForumSpam manual reporting audit.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_22 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.22', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_20');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_submit_log' => array(
                    'COLUMNS' => array(
                        'submit_id'        => array('UINT', null, 'auto_increment'),
                        'user_id'          => array('UINT', 0),
                        'admin_username'   => array('VCHAR:255', ''),
                        'admin_ip'         => array('VCHAR:45', ''),
                        'spammer_ip'       => array('VCHAR:45', ''),
                        'spammer_email'    => array('VCHAR:255', ''),
                        'spammer_username' => array('VCHAR:255', ''),
                        'evidence'         => array('MTEXT_UNI', ''),
                        'source'           => array('VCHAR:64', 'manual_acp'),
                        'source_log_id'    => array('UINT', 0),
                        'status'           => array('VCHAR:64', ''),
                        'response_text'    => array('MTEXT_UNI', ''),
                        'created_at'       => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'submit_id',
                    'KEYS' => array(
                        'created_idx' => array('INDEX', array('created_at')),
                        'source_idx'  => array('INDEX', array('source', 'source_log_id')),
                        'status_idx'  => array('INDEX', array('status')),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '3.3.22')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_sfs_submit_log',
            ),
        );
    }
}
