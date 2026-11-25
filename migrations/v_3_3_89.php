<?php
/**
 * AntiSpam Guard 3.3.89 - Contact form fix, submit-only validation,
 * StopForumSpam partial-match action and additional log exports.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_89 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['antispamguard_sfs_partial_action'])
			&& isset($this->config['antispamguard_version'])
			&& version_compare($this->config['antispamguard_version'], '3.3.89', '>=');
	}

	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_88');
	}

	public function update_data()
	{
		return array(
			// "review" preserves the behaviour of earlier releases.
			array('config.add', array('antispamguard_sfs_partial_action', 'review')),
			array('config.update', array('antispamguard_version', '3.3.89')),
		);
	}
}
