<?php
/**
 * AntiSpam Guard 3.3.71 - Early registration telemetry and log repair.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_71 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_69');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'repair_schema'))),
			array('custom', array(array($this, 'ensure_config_defaults'))),
			array('custom', array(array($this, 'install_acp_modules_if_missing'))),
			array('custom', array(array($this, 'install_admin_permissions_if_missing'))),
		);
	}

	public function revert_schema()
	{
		return array();
	}
}
