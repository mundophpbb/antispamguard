<?php
/**
 * AntiSpam Guard 3.3.69 - Portable cleanup and migration metadata.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_69 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_68');
	}

	public function update_data()
	{
		return array(
			array('config.remove', array('antispamguard_version')),
			array('config.remove', array('antispamguard_shadowban_enabled')),
			array('config.remove', array('antispamguard_shadowban_threshold')),
		);
	}

	/**
	 * Remove data created by the historical custom migration callbacks.
	 *
	 * The phpBB migrator cannot automatically reverse custom callbacks, so the
	 * terminal migration explicitly removes every ACP module, permission and
	 * configuration key created by earlier versions.
	 */
	public function revert_data()
	{
		$steps = array();

		foreach ($this->get_acp_module_modes() as $langname)
		{
			$steps[] = array('module.remove', array('acp', 'ACP_ANTISPAMGUARD_TITLE', $langname));
		}

		$steps[] = array('module.remove', array('acp', 'ACP_CAT_DOT_MODS', 'ACP_ANTISPAMGUARD_TITLE'));
		$steps[] = array('permission.remove', array('a_antispamguard_manage', true));

		$config_names = array_keys($this->get_config_defaults());
		$config_names[] = 'antispamguard_version';
		$config_names[] = 'antispamguard_shadowban_enabled';
		$config_names[] = 'antispamguard_shadowban_threshold';

		foreach (array_unique($config_names) as $config_name)
		{
			$steps[] = array('config.remove', array($config_name));
		}

		return $steps;
	}

	public function revert_schema()
	{
		return array();
	}
}
