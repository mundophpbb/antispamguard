<?php
/**
 * AntiSpam Guard 3.3.72 - Request-safe logging and SFS hardening.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_72 extends \mundophpbb\antispamguard\migrations\v_3_3_64
{
	public function effectively_installed()
	{
		return isset($this->config['antispamguard_version'])
			&& version_compare($this->config['antispamguard_version'], '3.3.72', '>=');
	}

	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_71');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'repair_schema'))),
			array('custom', array(array($this, 'deduplicate_atomic_tables'))),
			array('custom', array(array($this, 'install_atomic_unique_indexes'))),
			array('custom', array(array($this, 'minimize_sfs_cache_identity'))),
			array('custom', array(array($this, 'ensure_config_defaults'))),
			array('custom', array(array($this, 'set_version'))),
		);
	}

	public function revert_schema()
	{
		return array();
	}
}
