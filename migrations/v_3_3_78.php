<?php
/**
 * AntiSpam Guard 3.3.78 - Manual delivery tests.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_78 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_77');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('antispamguard_email_test_attempt', 0, true)),
			array('config.add', array('antispamguard_telegram_test_attempt', 0, true)),
			array('config.update', array('antispamguard_version', '3.3.78')),
		);
	}
}
