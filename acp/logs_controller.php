<?php
/**
 * AntiSpam Guard — ACP logs and statistics controller.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

use mundophpbb\antispamguard\service\activity_pdf;
use mundophpbb\antispamguard\service\country_index;

class logs_controller
{
	/**
	 * Auxiliary audit tables exported from the block log page. Time columns
	 * are written as ISO-8601 UTC; the period filter applies to time_column
	 * and the IP filter to ip_column when the table has one.
	 */
	protected const LOG_EXPORT_COLUMNS = array('log_id', 'log_time_utc', 'first_seen_utc', 'last_seen_utc', 'occurrence_count', 'submission_key', 'user_ip', 'username', 'email', 'form_type', 'reason', 'risk_score', 'risk_level', 'action', 'observed_actions', 'matched_rules', 'decision_details', 'user_agent');

	protected const EXTRA_EXPORTS = array(
		'registration_audit' => array(
			'table' => 'antispamguard_registration_audit',
			'id' => 'audit_id',
			'time_column' => 'last_seen',
			'ip_column' => 'user_ip',
			'columns' => array('audit_id', 'bucket_start', 'first_seen', 'last_seen', 'user_ip', 'user_agent', 'page_views', 'form_submissions', 'phpbb_rejected', 'local_rejected', 'sfs_analyzed', 'last_reason'),
			'time_columns' => array('bucket_start', 'first_seen', 'last_seen'),
		),
		'sfs_submissions' => array(
			'table' => 'antispamguard_sfs_submit_log',
			'id' => 'submit_id',
			'time_column' => 'created_at',
			'ip_column' => 'spammer_ip',
			'columns' => array('submit_id', 'created_at', 'status', 'source', 'source_log_id', 'spammer_ip', 'spammer_email', 'spammer_username', 'evidence', 'response_text', 'user_id', 'admin_username', 'admin_ip'),
			'time_columns' => array('created_at'),
		),
		'sfs_reviews' => array(
			'table' => 'antispamguard_sfs_review_log',
			'id' => 'review_id',
			'time_column' => 'created_at',
			'ip_column' => '',
			'columns' => array('review_id', 'created_at', 'sfs_log_id', 'action', 'old_review_status', 'new_review_status', 'old_local_action', 'new_local_action', 'admin_user_id', 'note'),
			'time_columns' => array('created_at'),
		),
	);

	protected const LOG_REASONS = array(
		'phpbb_rejected', 'honeypot', 'timestamp', 'timestamp_too_fast', 'timestamp_expired',
		'ip_reputation', 'content_filter', 'too_many_urls', 'ip_rate_limit', 'ip_blacklist',
		'sfs_reputation', 'sfs_identity', 'subnet_abuse', 'random_gmail', 'possible_false_positive',
		'combined_decision', 'slow_spam',
		'simulation_honeypot', 'simulation_timestamp', 'simulation_timestamp_too_fast',
		'simulation_timestamp_expired', 'simulation_content_filter',
		'simulation_too_many_urls', 'simulation_ip_rate_limit', 'simulation_ip_blacklist',
		'simulation_sfs_reputation', 'simulation_subnet_abuse', 'simulation_random_gmail',
		'simulation_multiple',
	);

	public $u_action;

	/** @var settings_helper */
	protected $settings_helper;

	/** @var pagination_helper */
	protected $pagination;

	/** @var sfs_controller */
	protected $sfs_controller;

	protected $country_index;

	public function __construct($u_action, ?settings_helper $settings_helper = null, ?pagination_helper $pagination = null, ?sfs_controller $sfs_controller = null, ?country_index $country_index = null)
	{
		$this->u_action = $u_action;
		$this->settings_helper = $settings_helper ?: new settings_helper();
		$this->pagination = $pagination ?: new pagination_helper();
		$this->sfs_controller = $sfs_controller ?: new sfs_controller($u_action, $this->settings_helper, $this->pagination);
		$this->country_index = $country_index;
	}

	public function set_sfs_controller(sfs_controller $sfs_controller)
	{
		$this->sfs_controller = $sfs_controller;
	}
	public function show_stats($db, $template, $table_prefix, $request)
	{
		global $user, $config;

		$table = $table_prefix . 'antispamguard_log';
		$now = time();
		$sfs_log_table = $table_prefix . 'antispamguard_sfs_log';
		$hourly = $request->variable('period', '') === 'hours';
		$export_pdf = $request->is_set_post('export_activity_pdf');
		if ($export_pdf && !check_form_key('mundophpbb_antispamguard'))
		{
			trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$activity = $this->get_activity($db, $table, $sfs_log_table, $now, $hourly);
		if ($export_pdf)
		{
			$this->export_activity_pdf($activity, $user);
			return;
		}

		$total_logs = $this->count_logs($db, $table);
		$total_24h = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 86400));
		$total_7d = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 604800));
		$total_30d = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 2592000));

		$top_reason = $this->get_top_stat($db, $table, 'reason', 'unknown', $user);
		$top_form = $this->get_top_stat($db, $table, 'form_type', 'register', $user);

		$timestamp_total = $this->count_reason_token($db, $table, 'timestamp');
		$timestamp_too_fast = $this->count_reason_token($db, $table, 'timestamp_too_fast');
		$timestamp_expired = $this->count_reason_token($db, $table, 'timestamp_expired');
		$timestamp_combined = $timestamp_total + $timestamp_too_fast + $timestamp_expired;

		$ip_rep_table = $table_prefix . 'antispamguard_ip_score';
		$ip_rep_total = 0;
		$ip_rep_blocked = 0;
		$ip_rep_threshold = isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 20;

		$sql = 'SELECT COUNT(score_id) AS total_scores
			FROM ' . $ip_rep_table;
		$result = $db->sql_query($sql);
		$ip_rep_total = (int) $db->sql_fetchfield('total_scores');
		$db->sql_freeresult($result);

		$sql = 'SELECT COUNT(score_id) AS blocked_scores
			FROM ' . $ip_rep_table . '
			WHERE score >= ' . (int) $ip_rep_threshold;
		$result = $db->sql_query($sql);
		$ip_rep_blocked = (int) $db->sql_fetchfield('blocked_scores');
		$db->sql_freeresult($result);

		$sfs_cache_table = $table_prefix . 'antispamguard_sfs_cache';

		$sfs_total_logs = $this->count_logs($db, $sfs_log_table);
		$sfs_blocked_logs = $this->count_logs($db, $sfs_log_table, 'blocked = 1');
		$sfs_suspect_logs = $this->count_logs($db, $sfs_log_table, 'blocked = 0');
		$sfs_strong_hits = $this->count_logs($db, $sfs_log_table, 'strong_hit = 1');
		$sfs_24h = $this->count_logs($db, $sfs_log_table, 'created_at >= ' . (int) ($now - 86400));
		$sfs_7d = $this->count_logs($db, $sfs_log_table, 'created_at >= ' . (int) ($now - 604800));

		$sfs_cache_total = 0;
		$sfs_cache_positive = 0;
		$sfs_cache_expired = 0;

		$sql = 'SELECT COUNT(cache_id) AS total_cache
			FROM ' . $sfs_cache_table;
		$result = $db->sql_query($sql);
		$sfs_cache_total = (int) $db->sql_fetchfield('total_cache');
		$db->sql_freeresult($result);

		$sql = 'SELECT COUNT(cache_id) AS positive_cache
			FROM ' . $sfs_cache_table . '
			WHERE is_listed = 1';
		$result = $db->sql_query($sql);
		$sfs_cache_positive = (int) $db->sql_fetchfield('positive_cache');
		$db->sql_freeresult($result);

		$sql = 'SELECT COUNT(cache_id) AS expired_cache
			FROM ' . $sfs_cache_table . '
			WHERE expires_at <= ' . (int) $now;
		$result = $db->sql_query($sql);
		$sfs_cache_expired = (int) $db->sql_fetchfield('expired_cache');
		$db->sql_freeresult($result);

		$sfs_block_rate = ($sfs_total_logs > 0) ? (int) round(($sfs_blocked_logs / $sfs_total_logs) * 100) : 0;

		$registration_audit = array(
			'page_views' => 0,
			'form_submissions' => 0,
			'phpbb_rejected' => 0,
			'local_rejected' => 0,
			'sfs_analyzed' => 0,
		);
		$registration_audit_table = $table_prefix . 'antispamguard_registration_audit';
		if ($this->table_exists($registration_audit_table))
		{
			$registration_audit = $this->get_registration_audit_totals(
				$db,
				$registration_audit_table,
				$now - 86400
			);
		}

		$this->assign_group_stats($db, $template, $table, 'form_type', 'stats_forms', 'register', $total_logs, $user);
		$this->assign_group_stats($db, $template, $table, 'reason', 'stats_reasons', 'unknown', $total_logs, $user);
		$this->assign_activity_chart($template, $activity, $hourly);
		$this->assign_country_stats($db, $template, $table, $activity['start'], $activity['end']);
		$this->assign_top_ips($db, $template, $table, $now - 604800);

		$template->assign_vars(array(
			'S_STATS' => true,
			'S_ACTIVITY_HOURLY' => $hourly,
			'U_LOGS' => $this->get_logs_url(),
			'U_ACTIVITY_DAYS' => $this->u_action . '#asg-activity',
			'U_ACTIVITY_HOURS' => $this->sfs_controller->append_url_param($this->u_action, 'period', 'hours') . '#asg-activity',
			'U_HONEYPOT_LOGS' => $this->get_logs_url(array('filter_reason' => 'honeypot')),
			'U_SFS_BLOCKS' => $this->get_logs_url(array('sfs_filter_blocked' => '1')) . '#asg-sfs-logs',
			'ANTISPAMGUARD_STATS_HONEYPOT' => $this->count_reason_token($db, $table, 'honeypot'),
			'U_ACTION' => $this->u_action,
			'ANTISPAMGUARD_STATS_TOTAL' => $total_logs,
			'ANTISPAMGUARD_STATS_24H' => $total_24h,
			'ANTISPAMGUARD_STATS_7D' => $total_7d,
			'ANTISPAMGUARD_STATS_30D' => $total_30d,
			'ANTISPAMGUARD_STATS_TOP_REASON' => $top_reason['label'],
			'ANTISPAMGUARD_STATS_TOP_REASON_TOTAL' => $top_reason['total'],
			'ANTISPAMGUARD_STATS_TOP_FORM' => $top_form['label'],
			'ANTISPAMGUARD_STATS_TOP_FORM_TOTAL' => $top_form['total'],
			'ANTISPAMGUARD_STATS_TIMESTAMP_TOTAL' => $timestamp_total,
			'ANTISPAMGUARD_STATS_TIMESTAMP_TOO_FAST' => $timestamp_too_fast,
			'ANTISPAMGUARD_STATS_TIMESTAMP_EXPIRED' => $timestamp_expired,
			'ANTISPAMGUARD_STATS_TIMESTAMP_COMBINED' => $timestamp_combined,
			'ANTISPAMGUARD_STATS_IP_REP_TOTAL' => $ip_rep_total,
			'ANTISPAMGUARD_STATS_IP_REP_BLOCKED' => $ip_rep_blocked,
			'ANTISPAMGUARD_SFS_STATS_TOTAL_LOGS' => $sfs_total_logs,
			'ANTISPAMGUARD_SFS_STATS_BLOCKED_LOGS' => $sfs_blocked_logs,
			'ANTISPAMGUARD_SFS_STATS_SUSPECT_LOGS' => $sfs_suspect_logs,
			'ANTISPAMGUARD_SFS_STATS_STRONG_HITS' => $sfs_strong_hits,
			'ANTISPAMGUARD_SFS_STATS_24H' => $sfs_24h,
			'ANTISPAMGUARD_SFS_STATS_7D' => $sfs_7d,
			'ANTISPAMGUARD_SFS_STATS_CACHE_TOTAL' => $sfs_cache_total,
			'ANTISPAMGUARD_SFS_STATS_CACHE_POSITIVE' => $sfs_cache_positive,
			'ANTISPAMGUARD_SFS_STATS_CACHE_EXPIRED' => $sfs_cache_expired,
			'ANTISPAMGUARD_SFS_STATS_BLOCK_RATE' => $sfs_block_rate,
			'ANTISPAMGUARD_REG_AUDIT_PAGE_VIEWS' => $registration_audit['page_views'],
			'ANTISPAMGUARD_REG_AUDIT_SUBMISSIONS' => $registration_audit['form_submissions'],
			'ANTISPAMGUARD_REG_AUDIT_PHPBB_REJECTED' => $registration_audit['phpbb_rejected'],
			'ANTISPAMGUARD_REG_AUDIT_LOCAL_REJECTED' => $registration_audit['local_rejected'],
			'ANTISPAMGUARD_REG_AUDIT_SFS_ANALYZED' => $registration_audit['sfs_analyzed'],
			'S_HAS_SFS_STATS' => ($sfs_total_logs > 0 || $sfs_cache_total > 0),
			'S_HAS_STATS' => ($total_logs > 0),
		));
	}

	public function get_registration_audit_totals($db, $table, $since)
	{
		$totals = array(
			'page_views' => 0,
			'form_submissions' => 0,
			'phpbb_rejected' => 0,
			'local_rejected' => 0,
			'sfs_analyzed' => 0,
		);

		$sql = 'SELECT
				SUM(page_views) AS page_views,
				SUM(form_submissions) AS form_submissions,
				SUM(phpbb_rejected) AS phpbb_rejected,
				SUM(local_rejected) AS local_rejected,
				SUM(sfs_analyzed) AS sfs_analyzed
			FROM ' . $table . '
			WHERE bucket_start >= ' . (int) $since;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if ($row)
		{
			foreach ($totals as $key => $value)
			{
				$totals[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
			}
		}

		return $totals;
	}

	protected function table_exists($table)
	{
		global $phpbb_container;

		return $phpbb_container->get('dbal.tools')->sql_table_exists($table);
	}

	public function count_logs($db, $table, $where = '')
	{
		$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table;
		if ($where !== '')
		{
			$sql .= ' WHERE ' . $where;
		}

		$result = $db->sql_query($sql);
		$total = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		return $total;
	}

	public function assign_group_stats($db, $template, $table, $column, $block_name, $fallback_label, $grand_total = 0, $user = null)
	{
		if ($column === 'reason')
		{
			foreach ($this->get_reason_counts($db, $table) as $label => $total)
			{
				$template->assign_block_vars($block_name, array(
					'LABEL' => $this->format_log_value($label !== '' ? $label : $fallback_label, $column, $user),
					'U_LOGS' => in_array($label, self::LOG_REASONS, true) ? $this->get_logs_url(array('filter_reason' => $label)) : '',
					'TOTAL' => $total,
					'PERCENT' => ($grand_total > 0) ? (int) round(($total / $grand_total) * 100) : 0,
				));
			}
			return;
		}

		$sql = 'SELECT ' . $column . ' AS stat_label, COUNT(log_id) AS stat_total
			FROM ' . $table . '
			GROUP BY ' . $column . '
			ORDER BY stat_total DESC, stat_label ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$total = (int) $row['stat_total'];
			$percent = ($grand_total > 0) ? (int) round(($total / $grand_total) * 100) : 0;
			$label = isset($row['stat_label']) && $row['stat_label'] !== '' ? $row['stat_label'] : $fallback_label;
			$label = $this->format_log_value($label, $column, $user);
			$template->assign_block_vars($block_name, array(
				'LABEL' => $label,
				'TOTAL' => $total,
				'PERCENT' => $percent,
			));
		}

		$db->sql_freeresult($result);
	}

	public function get_top_stat($db, $table, $column, $fallback_label, $user = null)
	{
		if ($column === 'reason')
		{
			$counts = $this->get_reason_counts($db, $table);
			if (empty($counts))
			{
				return array('label' => '-', 'total' => 0);
			}
			$label = key($counts);
			return array('label' => $this->format_log_value($label !== '' ? $label : $fallback_label, $column, $user), 'total' => (int) current($counts));
		}

		$sql = 'SELECT ' . $column . ' AS stat_label, COUNT(log_id) AS stat_total
			FROM ' . $table . '
			GROUP BY ' . $column . '
			ORDER BY stat_total DESC, stat_label ASC';
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			return array('label' => '-', 'total' => 0);
		}

		return array(
			'label' => $this->format_log_value((isset($row['stat_label']) && $row['stat_label'] !== '') ? $row['stat_label'] : $fallback_label, $column, $user),
			'total' => (int) $row['stat_total'],
		);
	}

	protected function get_reason_counts($db, $table)
	{
		$counts = array();
		$result = $db->sql_query('SELECT reason FROM ' . $table);
		while ($row = $db->sql_fetchrow($result))
		{
			$parts = array_unique(array_filter(array_map('trim', explode(',', isset($row['reason']) ? (string) $row['reason'] : ''))));
			if (empty($parts))
			{
				$parts = array('unknown');
			}
			foreach ($parts as $part)
			{
				$counts[$part] = isset($counts[$part]) ? $counts[$part] + 1 : 1;
			}
		}
		$db->sql_freeresult($result);
		arsort($counts, SORT_NUMERIC);

		return $counts;
	}

	protected function count_reason_token($db, $table, $reason)
	{
		return $this->count_logs($db, $table, $this->reason_condition($db, $reason));
	}

	protected function reason_condition($db, $reason)
	{
		$reason = $db->sql_escape($reason);
		$any = $db->get_any_char();
		return "(reason = '" . $reason . "' OR reason " . $db->sql_like_expression($reason . ',' . $any)
			. " OR reason " . $db->sql_like_expression($any . ',' . $reason)
			. " OR reason " . $db->sql_like_expression($any . ',' . $reason . ',' . $any) . ')';
	}

	protected function get_logs_url(array $filters = array())
	{
		$url = preg_replace('/([?&;]mode=)[^&;]+/', '${1}logs', $this->u_action, 1, $replaced);
		if (!$replaced)
		{
			$url = $this->sfs_controller->append_url_param($url, 'mode', 'logs');
		}
		return $url . log_scope::query_string($filters);
	}

	protected function get_activity($db, $table, $sfs_table, $now, $hourly)
	{
		$interval = $hourly ? 3600 : 86400;
		$count = $hourly ? 24 : 7;
		$end = (intdiv($now, $interval) + 1) * $interval;
		$start = $end - ($count * $interval);

		return array(
			'start' => $start,
			'end' => $end,
			'interval' => $interval,
			'general' => $this->get_activity_counts($db, $table, 'log_time', $start, $interval, $count),
			'sfs' => $this->get_activity_counts($db, $sfs_table, 'created_at', $start, $interval, $count),
		);
	}

	protected function assign_activity_chart($template, array $activity, $hourly)
	{
		$general = $activity['general'];
		$sfs = $activity['sfs'];
		$interval = $activity['interval'];
		$scale = max(1, max($general), max($sfs));

		foreach ($general as $index => $total)
		{
			$bucket_start = $activity['start'] + ($index * $interval);
			$url = $this->get_logs_url(array(
				'filter_from' => gmdate('Y-m-d\TH:i', $bucket_start),
				'filter_until' => gmdate('Y-m-d\TH:i', $bucket_start + $interval),
			));
			$template->assign_block_vars('activity', array(
				'LABEL' => gmdate($hourly ? 'H:i' : 'd/m', $bucket_start),
				'DATE' => gmdate('Y-m-d H:i', $bucket_start),
				'TOTAL' => $total,
				'SFS_TOTAL' => $sfs[$index],
				'PERCENT' => (int) round(100 * $total / $scale),
				'SFS_PERCENT' => (int) round(100 * $sfs[$index] / $scale),
				'U_LOGS' => $url . '#asg-main-logs',
				'U_SFS' => $url . '#asg-sfs-logs',
			));
		}
	}

	protected function get_activity_counts($db, $table, $column, $start, $interval, $count)
	{
		$expressions = array();
		for ($index = 0; $index < $count; $index++)
		{
			$from = $start + ($index * $interval);
			$expressions[] = 'SUM(CASE WHEN ' . $column . ' >= ' . (int) $from
				. ' AND ' . $column . ' < ' . (int) ($from + $interval)
				. ' THEN 1 ELSE 0 END) AS bucket_' . $index;
		}

		$sql = 'SELECT ' . implode(', ', $expressions) . '
			FROM ' . $table . '
			WHERE ' . $column . ' >= ' . (int) $start . '
				AND ' . $column . ' < ' . (int) ($start + ($interval * $count));
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return array_map('intval', array_values($row));
	}

	protected function assign_country_stats($db, $template, $table, $start, $end)
	{
		$index = $this->country_index;
		if ($index === null || !$index->available())
		{
			return;
		}

		$sql = 'SELECT user_ip, COUNT(log_id) AS total
			FROM ' . $table . "
			WHERE user_ip <> '' AND log_time >= " . (int) $start . ' AND log_time < ' . (int) $end . '
			GROUP BY user_ip
			ORDER BY total DESC, user_ip ASC';
		$result = $db->sql_query_limit($sql, 1000);
		$totals = array();
		$investigated_ips = 0;
		while ($row = $db->sql_fetchrow($result))
		{
			$country = $index->country($row['user_ip']);
			$totals[$country] = ($totals[$country] ?? 0) + (int) $row['total'];
			$investigated_ips++;
		}
		$db->sql_freeresult($result);

		$unknown = $totals[''] ?? 0;
		unset($totals['']);
		arsort($totals, SORT_NUMERIC);
		foreach (array_slice($totals, 0, 8, true) as $country => $total)
		{
			$template->assign_block_vars('activity_countries', array('CODE' => $country, 'TOTAL' => $total));
		}
		$template->assign_vars(array(
			'S_COUNTRY_INDEX' => true,
			'COUNTRY_UNKNOWN_TOTAL' => $unknown,
			'COUNTRY_ANALYZED_IPS' => $investigated_ips,
			'COUNTRY_UPDATED' => gmdate('Y-m-d H:i', $index->updated_at()),
		));
	}

	protected function export_activity_pdf(array $activity, $user)
	{
		$rows = array();
		foreach ($activity['general'] as $index => $general)
		{
			$start = $activity['start'] + $index * $activity['interval'];
			$rows[] = array(gmdate('Y-m-d H:i', $start), $general, $activity['sfs'][$index]);
		}

		$pdf = new activity_pdf();
		$content = $pdf->render(
			$user->lang('ACP_ANTISPAMGUARD_PDF_TITLE'),
			$user->lang('ACP_ANTISPAMGUARD_PDF_PERIOD', gmdate('Y-m-d H:i', $activity['start']), gmdate('Y-m-d H:i', $activity['end'])),
			$user->lang('ACP_ANTISPAMGUARD_PDF_NOTE'),
			array(
				$user->lang('ACP_ANTISPAMGUARD_PDF_INTERVAL'),
				$user->lang('ACP_ANTISPAMGUARD_ACTIVITY_GENERAL'),
				$user->lang('ACP_ANTISPAMGUARD_SFS_LOGS'),
			),
			$rows,
			array($user->lang('ACP_ANTISPAMGUARD_PDF_TOTAL'), array_sum($activity['general']), array_sum($activity['sfs']))
		);

		$response = new \Symfony\Component\HttpFoundation\Response($content, 200, array(
			'Content-Type' => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="antispamguard-activity-' . gmdate('Y-m-d') . '.pdf"',
			'Cache-Control' => 'private, no-store',
		));
		$response->send();
		garbage_collection();
		exit_handler();
	}

	protected function assign_top_ips($db, $template, $table, $start)
	{
		$sql = 'SELECT user_ip, COUNT(log_id) AS total
			FROM ' . $table . "
			WHERE user_ip <> '' AND log_time >= " . (int) $start . '
			GROUP BY user_ip
			ORDER BY total DESC, user_ip ASC';
		$result = $db->sql_query_limit($sql, 8);
		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('activity_ips', array(
				'IP' => $row['user_ip'],
				'TOTAL' => (int) $row['total'],
				'U_LOGS' => filter_var($row['user_ip'], FILTER_VALIDATE_IP)
					? $this->get_logs_url(array('filter_ip' => $row['user_ip'])) : '',
			));
		}
		$db->sql_freeresult($result);
	}

	public function show_logs($db, $request, $template, $user, $table_prefix)
	{
		global $config;

		$table = $table_prefix . 'antispamguard_log';

		$filter_form = $request->variable('filter_form', '');
		$filter_reason = $request->variable('filter_reason', '');
		$filters = log_scope::read($request);
		$filters['filter_username'] = $this->settings_helper->truncate_for_storage(trim($request->variable('filter_username', '', true)), 255);
		$filters['filter_min_score'] = $request->variable('filter_min_score', '');
		$filters['filter_max_score'] = $request->variable('filter_max_score', '');
		$filters['filter_action'] = $request->variable('filter_action', '');
		$start = max(0, $request->variable('start', 0));
		$per_page = 25;

		if (!in_array($filter_form, array('', 'register', 'post', 'contact', 'pm'), true))
		{
			$filter_form = '';
		}
		if (!in_array($filter_reason, self::LOG_REASONS, true))
		{
			$filter_reason = '';
		}
		if (!in_array($filters['filter_action'], array('', 'blocked', 'review', 'simulation'), true))
		{
			$filters['filter_action'] = '';
		}

		$where_sql = $this->build_log_filter_where($db, $filter_form, $filter_reason, $filters);
		$valid_filters = $where_sql !== false;
		$where_sql = $valid_filters ? $where_sql : ' WHERE 1 = 0';

		if ($request->is_set_post('export_csv'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->export_logs_csv($db, $table, $filter_form, $filter_reason, $filters);
		}

		if ($request->is_set_post('export_json'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->export_logs_json($db, $table, $filter_form, $filter_reason, $filters);
		}

		foreach (array_keys(self::EXTRA_EXPORTS) as $export_key)
		{
			if ($request->is_set_post('export_extra_' . $export_key))
			{
				if (!check_form_key('mundophpbb_antispamguard'))
				{
					trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$this->export_extra_csv($db, $user, $table_prefix, $export_key, $filters);
			}
		}

		if ($request->is_set_post('delete_marked') || $request->is_set_post('delete_filtered') || $request->is_set_post('clear_logs'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if ($request->is_set_post('clear_logs'))
			{
				$db->sql_query('DELETE FROM ' . $table);
				trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_CLEARED') . adm_back_link($this->u_action));
			}

			if ($request->is_set_post('delete_filtered'))
			{
				if (!$valid_filters || $where_sql === '')
				{
					trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTER_REQUIRED') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . $where_sql;
				$result = $db->sql_query($sql);
				$deleted = (int) $db->sql_fetchfield('total_logs');
				$db->sql_freeresult($result);

				if ($deleted > 0)
				{
					$db->sql_query('DELETE FROM ' . $table . $where_sql);
				}

				trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTERED_LOGS_DELETED', $deleted) . adm_back_link($this->u_action));
			}

			$marked = $request->variable('mark', array(0));
			$log_ids = array();

			foreach ($marked as $log_id)
			{
				$log_id = (int) $log_id;
				if ($log_id > 0)
				{
					$log_ids[] = $log_id;
				}
			}

			if (empty($log_ids))
			{
				trigger_error($user->lang('ACP_ANTISPAMGUARD_NO_LOG_SELECTED') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$sql = 'DELETE FROM ' . $table . ' WHERE ' . $db->sql_in_set('log_id', $log_ids);
			$db->sql_query($sql);

			trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_DELETED') . adm_back_link($this->u_action));
		}

		if ($request->is_set_post('prune_old_logs'))
		{
			if (!check_form_key('mundophpbb_antispamguard'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			global $config;

			$retention_days = isset($config['antispamguard_log_retention_days']) ? max(1, (int) $config['antispamguard_log_retention_days']) : 30;
			$deleted = $this->prune_old_logs($db, $table, $retention_days);

			trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_PRUNED', $deleted, $retention_days) . adm_back_link($this->u_action));
		}

		$url_filters = $filters;
		$url_filters['filter_username'] = htmlspecialchars_decode($filters['filter_username'], ENT_QUOTES);
		$filter_params = log_scope::query_string($url_filters + array('filter_form' => $filter_form, 'filter_reason' => $filter_reason));
		$sfs_params = log_scope::query_string($this->sfs_controller->read_log_filters($request));

		$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . $where_sql;
		$result = $db->sql_query($sql);
		$total_logs = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		if ($start >= $total_logs && $total_logs > 0)
		{
			$start = max(0, floor(($total_logs - 1) / $per_page) * $per_page);
		}

		$sql = 'SELECT * FROM ' . $table . $where_sql . ' ORDER BY log_time DESC, log_id DESC';
		$result = $db->sql_query_limit($sql, $per_page, $start);

		$has_logs = false;
		while ($row = $db->sql_fetchrow($result))
		{
			$has_logs = true;

			$template->assign_block_vars('logs', array(
				'ID'         => (int) $row['log_id'],
				'TIME'       => $user->format_date((int) $row['log_time']),
				'IP'         => $row['user_ip'],
				'U_IP' => filter_var($row['user_ip'], FILTER_VALIDATE_IP)
					? $this->get_logs_url(array('filter_ip' => $row['user_ip'])) : '',
				'USERNAME'   => $row['username'],
				'EMAIL'      => $row['email'],
				'FORM_TYPE'  => $this->format_log_value(isset($row['form_type']) ? $row['form_type'] : 'register', 'form_type', $user),
				'REASON'        => $this->format_log_value($row['reason'], 'reason', $user),
				'REASON_BADGES' => $this->build_reason_badges(isset($row['matched_rules']) && $row['matched_rules'] !== '' ? $row['matched_rules'] : $row['reason'], $user),
				'USER_AGENT'    => $row['user_agent'],
				'RISK_SCORE' => isset($row['risk_score']) ? (int) $row['risk_score'] : 0,
				'RISK_LEVEL' => isset($row['risk_level']) ? $row['risk_level'] : '',
				'ACTION' => isset($row['action']) ? $row['action'] : '',
				'OBSERVED_ACTIONS' => isset($row['observed_actions']) ? $row['observed_actions'] : '',
				'OCCURRENCE_COUNT' => isset($row['occurrence_count']) ? max(1, (int) $row['occurrence_count']) : 1,
				'MATCHED_RULES' => isset($row['matched_rules']) ? $row['matched_rules'] : '',
			));
		}
		$db->sql_freeresult($result);

		foreach (self::LOG_REASONS as $reason)
		{
			$template->assign_block_vars('log_reasons', array(
				'VALUE' => $reason,
				'LABEL' => $this->format_log_value($reason, 'reason', $user),
			));
		}

		$base_url = $this->u_action . $filter_params . $sfs_params;
		$this->sfs_controller->assign_sfs_logs($db, $request, $template, $user, $table_prefix, $base_url);

		$ip = $filters['filter_ip'];
		$investigate_ip = filter_var($ip, FILTER_VALIDATE_IP) !== false;
		if ($investigate_ip)
		{
			$this->assign_ip_investigation($db, $template, $user, $table_prefix, $ip, $table);
		}

		$pagination = $this->pagination->build_pagination($base_url, $total_logs, $per_page, $start);
		$page_number = $this->pagination->build_page_number($user, $total_logs, $per_page, $start);

		$template->assign_vars(array(
			'S_LOGS' => true,
			'S_INVESTIGATE_IP' => $investigate_ip,
			'S_HAS_LOGS' => $has_logs,
			'S_FILTER_ACTIVE' => $valid_filters && $where_sql !== '',
			'S_FILTER_INVALID' => !$valid_filters,
			'FILTERS' => $filters,
			'FILTER_USERNAME' => $url_filters['filter_username'],
			'U_BLOCK_HISTORY' => $this->get_logs_url(array('filter_ip' => $ip, 'filter_action' => 'blocked', 'sfs_filter_blocked' => '1')) . '#asg-main-logs',
			'U_ACTION' => $this->u_action,
			'FILTER_FORM' => $filter_form,
			'FILTER_REASON' => $filter_reason,
			'TOTAL_LOGS' => $total_logs,
			'PAGE_NUMBER' => $page_number,
			'PAGINATION' => $pagination,
			'ANTISPAMGUARD_LOG_RETENTION_DAYS' => isset($config['antispamguard_log_retention_days']) ? (int) $config['antispamguard_log_retention_days'] : 30,
		));
	}
	protected function assign_ip_investigation($db, $template, $user, $table_prefix, $ip, $table)
	{
		global $phpbb_container;

		$service = $phpbb_container->get('mundophpbb.antispamguard.ip_reputation');
		$sql = 'SELECT expires_at FROM ' . $table_prefix . "antispamguard_ip_score
			WHERE ip = '" . $db->sql_escape($ip) . "'";
		$result = $db->sql_query_limit($sql, 1);
		$expires_at = (int) $db->sql_fetchfield('expires_at');
		$db->sql_freeresult($result);

		$country = $this->country_index !== null ? $this->country_index->country($ip) : '';
		$template->assign_vars(array(
			'IP_COUNTRY_CODE' => $country,
			'S_IP_REPUTATION_ENABLED' => $service->is_enabled(),
			'IP_BLOCK_HISTORY_TOTAL' => $this->count_logs($db, $table, "action = 'blocked' AND user_ip = '" . $db->sql_escape($ip) . "'"),
		));
		if (!$service->is_enabled() || $expires_at <= time())
		{
			return;
		}

		$reputation = $service->get($ip);
		$template->assign_vars(array(
			'S_HAS_IP_REPUTATION' => true,
			'S_IP_REPUTATION_BLOCKED' => $reputation['blocked'],
			'IP_REPUTATION_SCORE' => $reputation['score'],
			'IP_REPUTATION_THRESHOLD' => $reputation['threshold'],
			'IP_REPUTATION_LAST_REASON' => $this->format_ip_reputation_reason($reputation['last_reason'], $user),
		));
	}

	public function format_log_value($value, $type, $user = null)
	{
		if ($user === null)
		{
			global $user;
		}

		$map = array(
			'form_type' => array(
				'register' => 'ACP_ANTISPAMGUARD_FORM_REGISTER',
				'post' => 'ACP_ANTISPAMGUARD_FORM_POST',
					'contact' => 'ACP_ANTISPAMGUARD_FORM_CONTACT',
					'pm' => 'ACP_ANTISPAMGUARD_FORM_PM',
					'diagnostic' => 'ACP_ANTISPAMGUARD_FORM_DIAGNOSTIC',
				),
				'reason' => array(
					'diagnostic_test' => 'ACP_ANTISPAMGUARD_REASON_DIAGNOSTIC_TEST',
					'phpbb_rejected' => 'ACP_ANTISPAMGUARD_REASON_PHPBB_REJECTED',
				'honeypot' => 'ACP_ANTISPAMGUARD_REASON_HONEYPOT',
				'timestamp' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP',
					'timestamp_too_fast' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_TOO_FAST',
					'timestamp_expired' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_EXPIRED',
				'content_filter' => 'ACP_ANTISPAMGUARD_REASON_CONTENT_FILTER',
				'too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_TOO_MANY_URLS',
				'ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_IP_RATE_LIMIT',
				'subnet_abuse' => 'ACP_ANTISPAMGUARD_REASON_SUBNET_ABUSE',
				'random_gmail' => 'ACP_ANTISPAMGUARD_REASON_RANDOM_GMAIL',
				'possible_false_positive' => 'ACP_ANTISPAMGUARD_REASON_POSSIBLE_FALSE_POSITIVE',
				'ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_IP_BLACKLIST',
				'ip_reputation' => 'ACP_ANTISPAMGUARD_REASON_IP_REPUTATION',
				'combined_decision' => 'ACP_ANTISPAMGUARD_REASON_COMBINED_DECISION',
				'slow_spam' => 'ACP_ANTISPAMGUARD_REASON_SLOW_SPAM',
				'sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
				'sfs_identity' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
				'simulation_honeypot' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_HONEYPOT',
				'simulation_timestamp' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP',
				'simulation_timestamp_too_fast' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP_TOO_FAST',
				'simulation_timestamp_expired' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP_EXPIRED',
				'simulation_content_filter' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_CONTENT_FILTER',
				'simulation_too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TOO_MANY_URLS',
				'simulation_ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_RATE_LIMIT',
				'simulation_subnet_abuse' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_SUBNET_ABUSE',
				'simulation_random_gmail' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_RANDOM_GMAIL',
				'simulation_ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_BLACKLIST',
				'simulation_sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_SFS_REPUTATION',
				'simulation_multiple' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_MULTIPLE',
				'unknown' => 'ACP_ANTISPAMGUARD_REASON_UNKNOWN',
			),
		);

		if (isset($map[$type][$value]))
		{
			return $user->lang($map[$type][$value]);
		}

		return $value;
	}

	protected function enrich_log_identity_from_sfs($db, $table_prefix, array $row)
	{
		$username = isset($row['username']) ? (string) $row['username'] : '';
		$email = isset($row['email']) ? (string) $row['email'] : '';

		if ($username !== '' && $email !== '')
		{
			return $row;
		}

		$ip = isset($row['user_ip']) ? (string) $row['user_ip'] : '';
		if ($ip === '')
		{
			return $row;
		}

		$log_time = isset($row['log_time']) ? (int) $row['log_time'] : 0;
		$window_start = max(0, $log_time - 600);
		$window_end = $log_time + 600;
		$sfs_table = $table_prefix . 'antispamguard_sfs_log';

		$sql = 'SELECT username, user_email, created_at
			FROM ' . $sfs_table . "
			WHERE user_ip = '" . $db->sql_escape($ip) . "'
				AND created_at >= " . (int) $window_start . "
				AND created_at <= " . (int) $window_end . "
				AND (username <> '' OR user_email <> '')
			ORDER BY created_at DESC, log_id DESC";
		$result = $db->sql_query_limit($sql, 10);

		while ($sfs_row = $db->sql_fetchrow($result))
		{
			if ($username === '' && isset($sfs_row['username']) && (string) $sfs_row['username'] !== '')
			{
				$username = (string) $sfs_row['username'];
			}

			if ($email === '' && isset($sfs_row['user_email']) && (string) $sfs_row['user_email'] !== '')
			{
				$email = (string) $sfs_row['user_email'];
			}

			if ($username !== '' && $email !== '')
			{
				break;
			}
		}
		$db->sql_freeresult($result);

		$row['username'] = $username;
		$row['email'] = $email;

		return $row;
	}

	public function build_log_filter_where($db, $filter_form = '', $filter_reason = '', array $filters = array())
	{
		$where = log_scope::conditions($db, $filters);
		if ($where === false)
		{
			return false;
		}

		if ($filter_form !== '')
		{
			$where[] = "form_type = '" . $db->sql_escape($filter_form) . "'";
		}
		if ($filter_reason !== '')
		{
			$where[] = $this->reason_condition($db, $filter_reason);
		}
		if (isset($filters['filter_username']) && $filters['filter_username'] !== '')
		{
			$where[] = "username = '" . $db->sql_escape($filters['filter_username']) . "'";
		}
		if (!empty($filters['filter_action']))
		{
			$where[] = "action = '" . $db->sql_escape($filters['filter_action']) . "'";
		}

		$min = isset($filters['filter_min_score']) ? $filters['filter_min_score'] : '';
		$max = isset($filters['filter_max_score']) ? $filters['filter_max_score'] : '';
		if (($min !== '' && (!ctype_digit($min) || (int) $min > 255))
			|| ($max !== '' && (!ctype_digit($max) || (int) $max > 255))
			|| ($min !== '' && $max !== '' && (int) $min > (int) $max))
		{
			return false;
		}
		if ($min !== '')
		{
			$where[] = 'risk_score >= ' . (int) $min;
		}
		if ($max !== '')
		{
			$where[] = 'risk_score <= ' . (int) $max;
		}
		return empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
	}

	public function export_logs_csv($db, $table, $filter_form = '', $filter_reason = '', array $filters = array())
	{
		$where_sql = $this->build_log_filter_where($db, $filter_form, $filter_reason, $filters);
		if ($where_sql === false)
		{
			global $user;
			trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTER_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$filename = 'antispamguard_logs_' . gmdate('Y-m-d_H-i-s') . '.csv';

		while (ob_get_level())
		{
			@ob_end_clean();
		}

		$response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($table, $where_sql, $db)
		{
			$output = fopen('php://output', 'w');
			fwrite($output, "\xEF\xBB\xBF");
			fputcsv($output, self::LOG_EXPORT_COLUMNS);

			$sql = 'SELECT * FROM ' . $table . $where_sql . ' ORDER BY log_time DESC, log_id DESC';
			$offset = 0;
			$batch_size = 500;
			do
			{
				$result = $db->sql_query_limit($sql, $batch_size, $offset);
				$batch_count = 0;
				while ($row = $db->sql_fetchrow($result))
				{
					$batch_count++;
					$first_seen = !empty($row['first_seen']) ? (int) $row['first_seen'] : (int) $row['log_time'];
					$last_seen = !empty($row['last_seen']) ? (int) $row['last_seen'] : (int) $row['log_time'];
					fputcsv($output, $this->settings_helper->sanitize_csv_row(array_values($this->build_log_export_row($row))));
				}
				$db->sql_freeresult($result);
				$offset += $batch_count;
			} while ($batch_count === $batch_size);
			fclose($output);
		});
		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Pragma', 'no-cache');
		$response->headers->set('Expires', '0');
		$response->send();

		garbage_collection();
		exit_handler();
	}

	/**
	 * One main-log row in export order; shared by the CSV and JSON exports.
	 */
	protected function build_log_export_row(array $row)
	{
		$first_seen = !empty($row['first_seen']) ? (int) $row['first_seen'] : (int) $row['log_time'];
		$last_seen = !empty($row['last_seen']) ? (int) $row['last_seen'] : (int) $row['log_time'];

		return array(
			'log_id' => (int) $row['log_id'],
			'log_time_utc' => gmdate('Y-m-d\TH:i:s\Z', (int) $row['log_time']),
			'first_seen_utc' => gmdate('Y-m-d\TH:i:s\Z', $first_seen),
			'last_seen_utc' => gmdate('Y-m-d\TH:i:s\Z', $last_seen),
			'occurrence_count' => isset($row['occurrence_count']) ? max(1, (int) $row['occurrence_count']) : 1,
			'submission_key' => isset($row['submission_key']) ? (string) $row['submission_key'] : '',
			'user_ip' => isset($row['user_ip']) ? (string) $row['user_ip'] : '',
			'username' => isset($row['username']) ? (string) $row['username'] : '',
			'email' => isset($row['email']) ? (string) $row['email'] : '',
			'form_type' => isset($row['form_type']) ? (string) $row['form_type'] : 'register',
			'reason' => isset($row['reason']) ? (string) $row['reason'] : '',
			'risk_score' => isset($row['risk_score']) ? (int) $row['risk_score'] : 0,
			'risk_level' => isset($row['risk_level']) ? (string) $row['risk_level'] : '',
			'action' => isset($row['action']) ? (string) $row['action'] : '',
			'observed_actions' => isset($row['observed_actions']) ? (string) $row['observed_actions'] : '',
			'matched_rules' => isset($row['matched_rules']) ? (string) $row['matched_rules'] : '',
			'decision_details' => isset($row['decision_details']) ? (string) $row['decision_details'] : '',
			'user_agent' => isset($row['user_agent']) ? (string) $row['user_agent'] : '',
		);
	}

	/**
	 * Same rows and filters as the CSV export, as a JSON document. Decision
	 * details stored as JSON are embedded as objects instead of strings.
	 */
	public function export_logs_json($db, $table, $filter_form = '', $filter_reason = '', array $filters = array())
	{
		$where_sql = $this->build_log_filter_where($db, $filter_form, $filter_reason, $filters);
		if ($where_sql === false)
		{
			global $user;
			trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTER_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$filename = 'antispamguard_logs_' . gmdate('Y-m-d_H-i-s') . '.json';
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if (defined('JSON_INVALID_UTF8_SUBSTITUTE'))
		{
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$exported_filters = array_filter(array_merge(array('filter_form' => $filter_form, 'filter_reason' => $filter_reason), $filters), function ($value)
		{
			return $value !== '' && $value !== null;
		});

		while (ob_get_level())
		{
			@ob_end_clean();
		}

		$response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($table, $where_sql, $db, $flags, $exported_filters)
		{
			echo '{"generated_at_utc":' . json_encode(gmdate('Y-m-d\TH:i:s\Z')) . ',"filters":' . json_encode((object) $exported_filters, $flags) . ',"logs":[';

			$sql = 'SELECT * FROM ' . $table . $where_sql . ' ORDER BY log_time DESC, log_id DESC';
			$offset = 0;
			$batch_size = 500;
			$first = true;
			do
			{
				$result = $db->sql_query_limit($sql, $batch_size, $offset);
				$batch_count = 0;
				while ($row = $db->sql_fetchrow($result))
				{
					$batch_count++;
					$item = $this->build_log_export_row($row);
					$details = $item['decision_details'] !== '' ? json_decode($item['decision_details'], true) : null;
					if (is_array($details))
					{
						$item['decision_details'] = $details;
					}
					$encoded = json_encode($item, $flags);
					if ($encoded === false)
					{
						continue;
					}
					echo ($first ? '' : ',') . "\n" . $encoded;
					$first = false;
				}
				$db->sql_freeresult($result);
				$offset += $batch_count;
			} while ($batch_count === $batch_size);

			echo "\n]}\n";
		});
		$response->headers->set('Content-Type', 'application/json; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Pragma', 'no-cache');
		$response->headers->set('Expires', '0');
		$response->send();

		garbage_collection();
		exit_handler();
	}

	/**
	 * CSV export of an auxiliary audit table (registration audit, SFS
	 * submissions, SFS review actions). Honours the period and IP filters.
	 */
	public function export_extra_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix, $export_key, array $filters = array())
	{
		if (!isset(self::EXTRA_EXPORTS[$export_key]))
		{
			trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$definition = self::EXTRA_EXPORTS[$export_key];
		$table = $table_prefix . $definition['table'];

		if (!$this->extra_table_exists($table))
		{
			trigger_error($user->lang('ACP_ANTISPAMGUARD_EXPORT_TABLE_MISSING') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$conditions = log_scope::column_conditions($db, $filters, $definition['time_column'], $definition['ip_column']);
		if ($conditions === false)
		{
			trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTER_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			return;
		}

		$where_sql = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);
		$columns = $definition['columns'];
		$time_columns = $definition['time_columns'];
		$id_column = $definition['id'];
		$filename = 'antispamguard_' . $export_key . '_' . gmdate('Y-m-d_H-i-s') . '.csv';

		$header = array();
		foreach ($columns as $column)
		{
			$header[] = in_array($column, $time_columns, true) ? $column . '_utc' : $column;
		}

		while (ob_get_level())
		{
			@ob_end_clean();
		}

		$response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($db, $table, $where_sql, $columns, $time_columns, $id_column, $header)
		{
			$output = fopen('php://output', 'w');
			fwrite($output, "\xEF\xBB\xBF");
			fputcsv($output, $header);

			$sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $table . $where_sql . ' ORDER BY ' . $id_column . ' DESC';
			$offset = 0;
			$batch_size = 500;
			do
			{
				$result = $db->sql_query_limit($sql, $batch_size, $offset);
				$batch_count = 0;
				while ($row = $db->sql_fetchrow($result))
				{
					$batch_count++;
					$line = array();
					foreach ($columns as $column)
					{
						$value = isset($row[$column]) ? $row[$column] : '';
						if (in_array($column, $time_columns, true))
						{
							$value = (int) $value > 0 ? gmdate('Y-m-d\TH:i:s\Z', (int) $value) : '';
						}
						$line[] = $value;
					}
					fputcsv($output, $this->settings_helper->sanitize_csv_row($line));
				}
				$db->sql_freeresult($result);
				$offset += $batch_count;
			} while ($batch_count === $batch_size);
			fclose($output);
		});
		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Pragma', 'no-cache');
		$response->headers->set('Expires', '0');
		$response->send();

		garbage_collection();
		exit_handler();
	}

	protected function extra_table_exists($table)
	{
		global $phpbb_container;

		if (!isset($phpbb_container) || !$phpbb_container->has('dbal.tools'))
		{
			return true;
		}

		return (bool) $phpbb_container->get('dbal.tools')->sql_table_exists($table);
	}

	public function prune_old_logs($db, $table, $retention_days)
	{
		$cutoff = time() - ((int) $retention_days * 86400);

		$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . ' WHERE log_time < ' . (int) $cutoff;
		$result = $db->sql_query($sql);
		$deleted = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		if ($deleted > 0)
		{
			$db->sql_query('DELETE FROM ' . $table . ' WHERE log_time < ' . (int) $cutoff);
		}

		return $deleted;
	}
	public function export_ip_reputation_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
	{
		$filename = 'antispamguard_ip_reputation_' . gmdate('Ymd_His') . '.csv';

		$response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($db, $table_prefix, $user)
		{
			$output = fopen('php://output', 'w');
			fwrite($output, "\xEF\xBB\xBF");

			fputcsv($output, array(
				'ip',
				'score',
				'hits',
				'last_reason',
				'first_seen',
				'last_update',
				'expires_at',
				'blocked_by_threshold',
			));

			$threshold_sql = 'SELECT config_value
				FROM ' . CONFIG_TABLE . "
				WHERE config_name = 'antispamguard_ip_reputation_threshold'";
			$threshold_result = $db->sql_query($threshold_sql);
			$threshold = (int) $db->sql_fetchfield('config_value');
			$db->sql_freeresult($threshold_result);

			if ($threshold <= 0)
			{
				$threshold = 20;
			}

			$sql = 'SELECT *
				FROM ' . $table_prefix . 'antispamguard_ip_score
				ORDER BY score DESC, last_update DESC';
			$result = $db->sql_query_limit($sql, 5000);

			while ($row = $db->sql_fetchrow($result))
			{
				fputcsv($output, $this->settings_helper->sanitize_csv_row(array(
					$row['ip'],
					(int) $row['score'],
					(int) $row['hits'],
					$row['last_reason'],
					!empty($row['first_seen']) ? $user->format_date((int) $row['first_seen']) : '',
					!empty($row['last_update']) ? $user->format_date((int) $row['last_update']) : '',
					!empty($row['expires_at']) ? $user->format_date((int) $row['expires_at']) : '',
					((int) $row['score'] >= $threshold) ? 1 : 0,
				)));
			}

			$db->sql_freeresult($result);
			fclose($output);
		});
		$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Pragma', 'no-cache');
		$response->headers->set('Expires', '0');
		$response->send();

		garbage_collection();
		exit_handler();
	}

	public function format_ip_reputation_reason($reason, \phpbb\user $user)
	{
		$map = array(
			'honeypot' => 'ACP_ANTISPAMGUARD_REASON_HONEYPOT',
			'timestamp' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP',
			'timestamp_too_fast' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_TOO_FAST',
			'timestamp_expired' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_EXPIRED',
			'content_filter' => 'ACP_ANTISPAMGUARD_REASON_CONTENT_FILTER',
			'too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_TOO_MANY_URLS',
			'ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_IP_RATE_LIMIT',
			'ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_IP_BLACKLIST',
			'ip_reputation' => 'ACP_ANTISPAMGUARD_REASON_IP_REPUTATION',
			'sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
			'sfs_identity' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
		);

		if (isset($map[$reason]) && isset($user->lang[$map[$reason]]))
		{
			return $user->lang($map[$reason]);
		}

		return (string) $reason;
	}

	/**
	 * Builds colored reason badges HTML for the logs table.
	 * Supports comma-separated reasons from matched_rules or reason column.
	 */
	public function build_reason_badges($reasons_string, $user = null)
	{
		if ($user === null)
		{
			global $user;
		}

		$reasons_string = trim((string) $reasons_string);
		if ($reasons_string === '')
		{
			return '';
		}

		$parts = array_map('trim', explode(',', $reasons_string));
		$html  = '<div class="asg-reason-badges">';

		foreach ($parts as $reason)
		{
			if ($reason === '')
			{
				continue;
			}

			$class = 'asg-reason-other';
			$label = $this->format_log_value($reason, 'reason', $user);

			if (strpos($reason, 'honeypot') !== false)
			{
				$class = 'asg-reason-honeypot';
			}
			elseif (strpos($reason, 'timestamp') !== false)
			{
				$class = 'asg-reason-timestamp';
			}
			elseif (strpos($reason, 'sfs_reputation') !== false || strpos($reason, 'sfs_identity') !== false)
			{
				$class = 'asg-reason-sfs';
			}
			elseif (strpos($reason, 'combined_decision') !== false)
			{
				$class = 'asg-reason-combined';
			}
			elseif (strpos($reason, 'ip_reputation') !== false)
			{
				$class = 'asg-reason-ip-reputation';
			}
			elseif (strpos($reason, 'ip_rate_limit') !== false || strpos($reason, 'rate_limit') !== false)
			{
				$class = 'asg-reason-rate-limit';
			}
			elseif (strpos($reason, 'content_filter') !== false || strpos($reason, 'too_many_urls') !== false)
			{
				$class = 'asg-reason-content-filter';
			}
			elseif (strpos($reason, 'simulation_') !== false)
			{
				$class = 'asg-reason-simulation';
			}

			$html .= '<span class="asg-reason-badge ' . $class . '">' . utf8_htmlspecialchars($label) . '</span>';
		}

		$html .= '</div>';
		return $html;
	}
}
