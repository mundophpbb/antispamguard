<?php
/**
 * AntiSpam Guard 0.2.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_2_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '0.2.0', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_0_1_0');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'antispamguard_log' => array(
                    'COLUMNS' => array(
                        'log_id'     => array('UINT', null, 'auto_increment'),
                        'log_time'   => array('TIMESTAMP', 0),
                        'user_ip'    => array('VCHAR:40', ''),
                        'username'   => array('VCHAR:255', ''),
                        'email'      => array('VCHAR:255', ''),
                        'reason'     => array('VCHAR:50', ''),
                        'user_agent' => array('VCHAR:255', ''),
                    ),
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => array(
                        'log_time' => array('INDEX', 'log_time'),
                    ),
                ),
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('module.add', array(
                'acp',
                'ACP_ANTISPAMGUARD_TITLE',
                array(
                    'module_basename' => '\mundophpbb\antispamguard\acp\main_module',
                    'modes'           => array('logs'),
                ),
            )),
            array('config.update', array('antispamguard_version', '0.2.0')),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'antispamguard_log',
            ),
        );
    }
}
