<?php
/**
 * AntiSpam Guard 3.3.79 - Notification delivery history.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_79 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_78');
	}

	public function update_schema()
	{
		return array(
			'add_tables' => array(
				$this->table_prefix . 'antispamguard_delivery' => array(
					'COLUMNS' => array(
						'delivery_id' => array('UINT', null, 'auto_increment'),
						'created_at' => array('TIMESTAMP', 0),
						'channel' => array('VCHAR:12', ''),
						'kind' => array('VCHAR:12', ''),
						'accepted' => array('BOOL', 0),
					),
					'PRIMARY_KEY' => 'delivery_id',
					'KEYS' => array('created_id' => array('INDEX', array('created_at', 'delivery_id'))),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array('drop_tables' => array($this->table_prefix . 'antispamguard_delivery'));
	}

	public function update_data()
	{
		return array(array('config.update', array('antispamguard_version', '3.3.79')));
	}
}
