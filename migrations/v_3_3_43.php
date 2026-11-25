<?php
/**
 * AntiSpam Guard 3.3.43 — repair missing admin permission and module auth.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_43 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
	public function effectively_installed()
	{
		return isset($this->config['antispamguard_version'])
			&& version_compare($this->config['antispamguard_version'], '3.3.43', '>=');
	}

	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_42');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'install_admin_permissions_if_missing'))),
			array('custom', array(array($this, 'repair_module_auth'))),
			array('custom', array(array($this, 'set_version'))),
		);
	}

	public function revert_schema()
	{
		return array();
	}
}