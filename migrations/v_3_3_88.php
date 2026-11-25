<?php
/**
 * AntiSpam Guard 3.3.88 - Release candidate.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_88 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_87');
	}

	public function update_data()
	{
		return array(array('config.update', array('antispamguard_version', '3.3.88')));
	}
}
