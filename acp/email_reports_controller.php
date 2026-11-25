<?php
/**
 * ACP settings and manual tests for alert delivery.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

use mundophpbb\antispamguard\service\cron_status;
use mundophpbb\antispamguard\service\delivery_history;
use mundophpbb\antispamguard\service\email_reports;
use mundophpbb\antispamguard\service\spike_preview;
use mundophpbb\antispamguard\service\telegram_alerts;

class email_reports_controller
{
	protected $reports;
	protected $telegram;
	protected $history;
	protected $preview;

	public function __construct(
		email_reports $reports,
		?telegram_alerts $telegram = null,
		?delivery_history $history = null,
		?spike_preview $preview = null
	)
	{
		$this->reports = $reports;
		$this->telegram = $telegram;
		$this->history = $history;
		$this->preview = $preview;
	}

	public function handle($request, $template, $user, $url)
	{
		if ($this->history !== null && $request->is_set_post('export_delivery_history'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($url), E_USER_WARNING);
				return;
			}
			$filters = $this->history_filters($request);
			$bounds = $this->history_date_bounds($filters);
			if ($bounds === false)
			{
				trigger_error($user->lang('ACP_ANTISPAMGUARD_DELIVERY_DATE_RANGE_INVALID') . adm_back_link($url), E_USER_WARNING);
				return;
			}

			$this->export_delivery_csv($filters + $bounds, time());
			return;
		}

		$preview = $this->preview !== null && $request->is_set_post('preview_spike_rule');
		$test_email = !$preview && $request->is_set_post('test_email_delivery');
		$test_telegram = !$preview && $this->telegram !== null && $request->is_set_post('test_telegram_delivery');
		if ($test_email || $test_telegram)
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($url), E_USER_WARNING);
				return;
			}

			$channel = $test_email ? $this->reports : $this->telegram;
			$result = $channel->test_delivery(time());
			$level = ($result === 'email_sent' || $result === 'telegram_sent') ? E_USER_NOTICE : E_USER_WARNING;
			$message = $user->lang('ACP_ANTISPAMGUARD_TEST_' . strtoupper($result));
			if (in_array($result, array('email_failed', 'email_unconfirmed', 'telegram_failed', 'telegram_unconfirmed'), true))
			{
				$detail = delivery_history::valid_detail($channel->get_delivery_detail());
				if ($detail !== '')
				{
					$outcome = delivery_history::outcome(false, $detail);
					$message = $user->lang(
						'ACP_ANTISPAMGUARD_TEST_DELIVERY_RESULT',
						$user->lang('ACP_ANTISPAMGUARD_DELIVERY_' . strtoupper($outcome)),
						$user->lang('ACP_ANTISPAMGUARD_DELIVERY_DETAIL_' . strtoupper($detail))
					);
				}
			}
			trigger_error($message . adm_back_link($url), $level);
			return;
		}

		$settings = $this->reports->get_settings();
		$saved_settings = $settings;
		$error = '';
		if ($preview || $request->is_set_post('save_email_reports'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($url), E_USER_WARNING);
				return;
			}

			$previous = $settings;
			$settings = array(
				'recipient' => trim(htmlspecialchars_decode($request->variable('recipient', ''), ENT_QUOTES)),
				'alert_enabled' => (int) $request->variable('alert_enabled', false),
				'digest' => $request->variable('digest', 'off'),
			);
			foreach (email_reports::LIMITS as $name => $limits)
			{
				$settings[$name] = $request->variable($name, 0);
			}

			if ($preview)
			{
				if (!$this->valid_rule($settings))
				{
					$template->assign_var('S_SPIKE_PREVIEW_INVALID', true);
				}
				else
				{
					$this->assign_preview($template, $settings);
				}
			}
			else
			{
				$error = $this->validate($settings);
				if ($error === '')
				{
					$this->reports->save_settings($settings, $previous, time());
					trigger_error($user->lang('ACP_ANTISPAMGUARD_EMAIL_SAVED') . adm_back_link($url));
					return;
				}
			}
		}

		if ($this->telegram !== null)
		{
			$this->assign_telegram($request, $template, $user, $url, !$preview);
		}

		$this->assign_cron_status($template, $saved_settings, time());
		if ($this->history !== null)
		{
			$this->assign_history($request, $template, $user, $url);
		}

		$template->assign_vars(array(
			'S_EMAIL_REPORTS' => true,
			'S_SPIKE_PREVIEW_AVAILABLE' => $this->preview !== null,
			'SPIKE_PREVIEW_DAYS' => spike_preview::DAYS,
			'S_BOARD_EMAIL_ENABLED' => $this->reports->board_email_enabled(),
			'MAIL_SETTINGS' => $settings,
			'MAIL_LIMITS' => email_reports::LIMITS,
			'MAIL_ERROR' => $error,
			'U_ACTION' => $url,
		));

		foreach (array('alert_checked', 'alert_sent', 'alert_failed', 'digest_sent', 'digest_failed') as $field)
		{
			$timestamp = $this->reports->get_state($field);
			$template->assign_var('MAIL_' . strtoupper($field), $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '');
		}
	}

	protected function assign_cron_status($template, array $settings, $now)
	{
		$email = $this->reports->get_cron_status($settings, $now);
		$vars = array('CRON_LATE_MINUTES' => cron_status::LATE_AFTER_MINUTES);
		$channels = array('EMAIL' => $email);
		if ($this->telegram !== null)
		{
			$channels['TELEGRAM'] = $this->telegram->get_cron_status($this->telegram->get_settings(), $now);
		}
		foreach ($channels as $channel => $status)
		{
			$vars['CRON_' . $channel . '_STATE'] = $status['state'];
			$vars['CRON_' . $channel . '_LAST'] = $status['last_poll'] ? gmdate('Y-m-d H:i:s', $status['last_poll']) : '';
		}

		if ($email['state'] !== 'disabled' && $email['state'] !== 'unavailable')
		{
			$schedule = $this->reports->get_digest_schedule($settings, $now);
			if ($schedule)
			{
				$vars += array(
					'CRON_DIGEST_START' => gmdate('Y-m-d H:i', $schedule['start']),
					'CRON_DIGEST_END' => gmdate('Y-m-d H:i', $schedule['end']),
					'S_CRON_DIGEST_PENDING' => $schedule['pending'],
					'CRON_DIGEST_RETRY' => $schedule['retry_at'] > $now ? gmdate('Y-m-d H:i:s', $schedule['retry_at']) : '',
				);
			}
		}

		$template->assign_vars($vars);
	}

	protected function assign_telegram($request, $template, $user, $url, $allow_save = true)
	{
		$settings = $this->telegram->get_settings();
		$has_saved_token = $settings['token'] !== '';
		$error = '';
		if ($allow_save && $request->is_set_post('save_telegram_alerts'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($url), E_USER_WARNING);
				return;
			}

			$token = trim($request->variable('telegram_token', ''));
			if ($request->variable('telegram_clear_token', false))
			{
				$settings['token'] = '';
			}
			else if ($token !== '')
			{
				$settings['token'] = $token;
			}
			$settings['enabled'] = (int) $request->variable('telegram_enabled', false);
			$settings['chat_id'] = trim($request->variable('telegram_chat_id', ''));

			if (($settings['enabled'] || $settings['token'] !== '') && !$this->telegram->valid_token($settings['token']))
			{
				$error = 'ACP_ANTISPAMGUARD_TELEGRAM_INVALID_TOKEN';
			}
			else if (($settings['enabled'] || $settings['chat_id'] !== '') && !$this->telegram->valid_chat($settings['chat_id']))
			{
				$error = 'ACP_ANTISPAMGUARD_TELEGRAM_INVALID_CHAT';
			}

			if ($error === '')
			{
				$this->telegram->save_settings($settings);
				trigger_error($user->lang('ACP_ANTISPAMGUARD_TELEGRAM_SAVED') . adm_back_link($url));
				return;
			}
		}

		$template->assign_vars(array(
			'S_TELEGRAM_SETTINGS' => true,
			'S_TELEGRAM_ENABLED' => !empty($settings['enabled']),
			'S_TELEGRAM_HAS_TOKEN' => $has_saved_token,
			'TELEGRAM_CHAT_ID' => $settings['chat_id'],
			'TELEGRAM_ERROR' => $error,
		));
		foreach (array('alert_checked', 'alert_sent', 'alert_failed') as $field)
		{
			$timestamp = $this->telegram->get_state($field);
			$template->assign_var('TELEGRAM_' . strtoupper($field), $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '');
		}
	}

	protected function history_filters($request)
	{
		$filters = array();
		foreach (delivery_history::FILTERS as $field => $options)
		{
			$value = $request->variable('delivery_' . $field, '');
			$filters[$field] = in_array($value, $options, true) ? $value : '';
		}
		$filters['from'] = $request->variable('delivery_from', '');
		$filters['through'] = $request->variable('delivery_through', '');
		$filters['diagnostic'] = delivery_history::valid_detail($request->variable('delivery_diagnostic', ''));
		return $filters;
	}

	protected function history_date_bounds(array $filters)
	{
		$from = log_scope::parse_date($filters['from']);
		$through = log_scope::parse_date($filters['through']);
		if ($from === false || $through === false
			|| ($through !== null && $through > PHP_INT_MAX - 86400)
			|| ($from !== null && $through !== null && $from > $through))
		{
			return false;
		}

		return array(
			'from_time' => $from,
			// The end date includes its full UTC calendar day.
			'until_time' => $through === null ? null : $through + 86400,
		);
	}

	protected function export_delivery_csv(array $filters, $now)
	{
		while (ob_get_level() && @ob_end_clean())
		{
		}

		$response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filters, $now)
		{
			$output = fopen('php://output', 'wb');
			try
			{
				fwrite($output, "\xEF\xBB\xBF");
				fputcsv($output, array('created_at_utc', 'channel', 'kind', 'outcome', 'diagnostic_code'), ',', '"', '\\');
				foreach ($this->history->export_rows($filters, $now) as $row)
				{
					fputcsv($output, $row, ',', '"', '\\');
				}
			}
			finally
			{
				fclose($output);
			}
		});
		$filename = 'antispamguard_delivery_' . gmdate('Y-m-d_H-i-s', $now) . '.csv';
		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Cache-Control', 'private, no-store');
		$response->send();

		garbage_collection();
		exit_handler();
	}

	protected function assign_history($request, $template, $user, $url)
	{
		$filters = $this->history_filters($request);
		$query = array();
		foreach ($filters as $field => $value)
		{
			$query['delivery_' . $field] = $value;
		}

		$limit = 25;
		$bounds = $this->history_date_bounds($filters);
		$page = $bounds === false
			? array('total' => 0, 'offset' => 0, 'rows' => array())
			: $this->history->page($filters + $bounds, $limit, $request->variable('delivery_start', 0), time());
		$pagination = new pagination_helper();
		$template->assign_vars(array(
			'S_DELIVERY_HISTORY' => true,
			'S_DELIVERY_DATE_INVALID' => $bounds === false,
			'DELIVERY_FILTERS' => $filters,
			'DELIVERY_DIAGNOSTIC_LABEL' => $filters['diagnostic'] === '' ? '' : $user->lang('ACP_ANTISPAMGUARD_DELIVERY_DIAGNOSTIC_' . strtoupper($filters['diagnostic'])),
			'U_CLEAR_DELIVERY_DIAGNOSTIC' => $url . log_scope::query_string(array_replace($query, array('delivery_diagnostic' => ''))) . '#asg-delivery-history',
			'DELIVERY_OPTIONS' => delivery_history::FILTERS,
			'DELIVERY_DAYS' => delivery_history::RETENTION_DAYS,
			'DELIVERY_TOTAL' => $page['total'],
			'DELIVERY_PAGE_NUMBER' => $pagination->build_page_number($user, $page['total'], $limit, $page['offset']),
			'DELIVERY_PAGINATION' => $pagination->build_pagination($url . log_scope::query_string($query), $page['total'], $limit, $page['offset'], 'delivery_start', '#asg-delivery-history'),
		));
		if ($page['total'] > 0)
		{
			$template->assign_var('DELIVERY_SUMMARY', $page['summary']);
			foreach ($page['daily'] as $day => $counts)
			{
				$date = gmdate('Y-m-d', $day);
				$day_query = array_replace($query, array('delivery_from' => $date, 'delivery_through' => $date));
				$template->assign_block_vars('delivery_daily', array(
					'DATE' => $date,
					'U_FILTER' => $url . log_scope::query_string($day_query) . '#asg-delivery-history',
					'COUNTS' => $counts,
				));
			}
			foreach ($page['diagnostics'] as $code => $count)
			{
				$link = $url . log_scope::query_string(array_replace($query, array('delivery_diagnostic' => $code))) . '#asg-delivery-history';
				$template->assign_block_vars('delivery_diagnostics', array(
					'LABEL' => $user->lang('ACP_ANTISPAMGUARD_DELIVERY_DIAGNOSTIC_' . strtoupper($code)),
					'COUNT' => $count,
					'U_FILTER' => $link,
				));
			}
		}
		foreach ($page['rows'] as $row)
		{
			$channel = in_array($row['channel'], delivery_history::FILTERS['channel'], true) ? $row['channel'] : 'other';
			$detail = delivery_history::valid_detail($row['delivery_detail']);
			$outcome = delivery_history::outcome($row['accepted'], $detail);
			$template->assign_block_vars('delivery_history', array(
				'TIME' => gmdate('Y-m-d H:i:s', (int) $row['created_at']),
				'CHANNEL' => $user->lang('ACP_ANTISPAMGUARD_DELIVERY_' . strtoupper($channel)),
				'KIND' => $user->lang('ACP_ANTISPAMGUARD_DELIVERY_' . strtoupper($row['kind'])),
				'S_ACCEPTED' => (bool) $row['accepted'],
				'OUTCOME' => $user->lang('ACP_ANTISPAMGUARD_DELIVERY_' . strtoupper($outcome)),
				'DETAIL' => $detail !== '' ? $user->lang('ACP_ANTISPAMGUARD_DELIVERY_DETAIL_' . strtoupper($detail)) : '',
			));
		}
	}

	protected function assign_preview($template, array $settings)
	{
		$preview = $this->preview->simulate($settings, time());
		$template->assign_vars(array(
			'S_SPIKE_PREVIEW' => true,
			'SPIKE_PREVIEW_START' => gmdate('Y-m-d H:i', $preview['start']),
			'SPIKE_PREVIEW_END' => gmdate('Y-m-d H:i', $preview['end']),
			'SPIKE_PREVIEW_TOTAL' => $preview['alerts'],
			'SPIKE_PREVIEW_BLOCKS' => $preview['blocks'],
			'SPIKE_PREVIEW_LIMIT' => spike_preview::DISPLAY_LIMIT,
		));
		foreach ($preview['latest'] as $alert)
		{
			$template->assign_block_vars('spike_preview', array(
				'TIME' => gmdate('Y-m-d H:i', $alert['time']),
				'BLOCKS' => $alert['blocks'],
				'IPS' => $alert['ips'],
			));
		}
	}

	protected function validate(array $settings)
	{
		if (($settings['recipient'] !== '' || $settings['alert_enabled'] || $settings['digest'] !== 'off')
			&& (strlen($settings['recipient']) > 254 || !filter_var($settings['recipient'], FILTER_VALIDATE_EMAIL)))
		{
			return 'ACP_ANTISPAMGUARD_EMAIL_RECIPIENT_INVALID';
		}

		if (!in_array($settings['digest'], array('off', 'daily', 'weekly'), true))
		{
			return 'ACP_ANTISPAMGUARD_EMAIL_VALUES_INVALID';
		}

		return $this->valid_rule($settings) ? '' : 'ACP_ANTISPAMGUARD_EMAIL_VALUES_INVALID';
	}

	protected function valid_rule(array $settings)
	{
		foreach (email_reports::LIMITS as $name => $limits)
		{
			if ($settings[$name] < $limits[0] || $settings[$name] > $limits[1])
			{
				return false;
			}
		}
		return true;
	}
}
