<?php
/**
 * AntiSpam Guard 3.3.82 - Notification cron status.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_82 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_81');
	}

	public function update_data()
	{
		$now = time();
		return array(
			array('config.add', array('antispamguard_email_cron_enabled_at', empty($this->config['antispamguard_email_last_poll']) ? $now : 0, true)),
			array('config.add', array('antispamguard_telegram_cron_enabled_at', empty($this->config['antispamguard_telegram_last_poll']) ? $now : 0, true)),
			array('config.update', array('antispamguard_version', '3.3.82')),
		);
	}
}
