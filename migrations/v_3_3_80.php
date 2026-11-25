<?php
/**
 * AntiSpam Guard 3.3.80 - Delivery diagnostics.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_80 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_79');
	}

	public function update_schema()
	{
		return array(
			'add_columns' => array(
				$this->table_prefix . 'antispamguard_delivery' => array(
					'delivery_detail' => array('VCHAR:32', ''),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array('drop_columns' => array($this->table_prefix . 'antispamguard_delivery' => array('delivery_detail')));
	}

	public function update_data()
	{
		return array(array('config.update', array('antispamguard_version', '3.3.80')));
	}
}
