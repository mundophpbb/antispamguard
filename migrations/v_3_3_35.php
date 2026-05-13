<?php
/**
 * AntiSpam Guard 3.3.35 - SFS review audit log and reviewed-log retention preservation.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_35 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.35', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_32');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_sfs_review_audit_table'))),
            array('custom', array(array($this, 'ensure_config_defaults'))),
            array('config.update', array('antispamguard_version', '3.3.35')),
        );
    }

    public function repair_sfs_review_audit_table()
    {
        $table = $this->table_prefix . 'antispamguard_sfs_review_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            $this->db_tools->sql_create_table($table, array(
                'COLUMNS' => array(
                    'review_id' => array('UINT', null, 'auto_increment'),
                    'sfs_log_id' => array('UINT', 0),
                    'action' => array('VCHAR:32', ''),
                    'old_review_status' => array('VCHAR:32', ''),
                    'new_review_status' => array('VCHAR:32', ''),
                    'old_local_action' => array('VCHAR:32', ''),
                    'new_local_action' => array('VCHAR:32', ''),
                    'admin_user_id' => array('UINT', 0),
                    'created_at' => array('TIMESTAMP', 0),
                    'note' => array('TEXT_UNI', ''),
                ),
                'PRIMARY_KEY' => 'review_id',
                'KEYS' => array(
                    'sfs_log_created_idx' => array('INDEX', array('sfs_log_id', 'created_at')),
                    'action_created_idx' => array('INDEX', array('action', 'created_at')),
                    'admin_created_idx' => array('INDEX', array('admin_user_id', 'created_at')),
                ),
            ));
        }
    }

    public function ensure_config_defaults()
    {
        if (!isset($this->config['antispamguard_sfs_log_preserve_reviewed']))
        {
            $this->config->set('antispamguard_sfs_log_preserve_reviewed', 1);
        }
    }
}
