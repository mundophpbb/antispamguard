<?php
/**
 * AntiSpam Guard 3.3.77 - Telegram alerts, local geolocation and PDF export.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_77 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_76');
	}

	public function update_data()
	{
		return array(
			array('config_text.add', array('antispamguard_telegram_settings', '{}')),
			array('config.add', array('antispamguard_telegram_enabled', 0, true)),
			array('config.add', array('antispamguard_telegram_last_poll', 0, true)),
			array('config.add', array('antispamguard_telegram_alert_checked', 0, true)),
			array('config.add', array('antispamguard_telegram_pending_alert', '', true)),
			array('config.add', array('antispamguard_telegram_alert_sent', 0, true)),
			array('config.add', array('antispamguard_telegram_alert_failed', 0, true)),
			array('config.update', array('antispamguard_version', '3.3.77')),
		);
	}
}
