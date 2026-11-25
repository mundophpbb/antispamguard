<?php
/**
 * AntiSpam Guard 3.3.76 - Scheduled email alerts and activity summaries.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_76 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_75');
	}

	public function update_data()
	{
		return array(
			array('config_text.add', array('antispamguard_email_settings', '{}')),
			array('config.add', array('antispamguard_email_poll_interval', 0, true)),
			array('config.add', array('antispamguard_email_last_poll', 0, true)),
			array('config.add', array('antispamguard_email_alert_checked', 0, true)),
			array('config.add', array('antispamguard_email_pending_alert', '', true)),
			array('config.add', array('antispamguard_email_alert_sent', 0, true)),
			array('config.add', array('antispamguard_email_alert_failed', 0, true)),
			array('config.add', array('antispamguard_email_digest_end', 0, true)),
			array('config.add', array('antispamguard_email_digest_sent', 0, true)),
			array('config.add', array('antispamguard_email_digest_failed', 0, true)),
			array('module.add', array('acp', 'ACP_ANTISPAMGUARD_TITLE', array(
				'module_basename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
				'modes' => array('email_reports'),
			))),
			array('config.update', array('antispamguard_version', '3.3.76')),
		);
	}
}
