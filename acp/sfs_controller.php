<?php
/**
 * AntiSpam Guard — ACP StopForumSpam panel controller.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

class sfs_controller
{
	public $u_action;

	/** @var settings_helper */
	protected $settings_helper;

	/** @var pagination_helper */
	protected $pagination;

	public function __construct($u_action, settings_helper $settings_helper = null, pagination_helper $pagination = null)
	{
		$this->u_action = $u_action;
		$this->settings_helper = $settings_helper ?: new settings_helper();
		$this->pagination = $pagination ?: new pagination_helper();
	}
	public function show_sfs($db, $request, $template, $user, $config, $table_prefix)
	{
		$sfs_cache_total = 0;
		$sfs_cache_expired = 0;
		$sfs_logs_total = 0;
		$circuit_until = isset($config['antispamguard_sfs_circuit_until']) ? (int) $config['antispamguard_sfs_circuit_until'] : 0;
		$circuit_open = $circuit_until > time();

		$sql = 'SELECT COUNT(cache_id) AS total_cache FROM ' . $table_prefix . 'antispamguard_sfs_cache';
		$result = $db->sql_query($sql);
		$sfs_cache_total = (int) $db->sql_fetchfield('total_cache');
		$db->sql_freeresult($result);

		$sql = 'SELECT COUNT(cache_id) AS expired_cache FROM ' . $table_prefix . 'antispamguard_sfs_cache WHERE expires_at <= ' . time();
		$result = $db->sql_query($sql);
		$sfs_cache_expired = (int) $db->sql_fetchfield('expired_cache');
		$db->sql_freeresult($result);

		$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table_prefix . 'antispamguard_sfs_log';
		$result = $db->sql_query($sql);
		$sfs_logs_total = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		$this->assign_sfs_logs($db, $request, $template, $user, $table_prefix);
		$this->assign_sfs_submission_logs($db, $template, $user, $table_prefix);
		$sfs_submit_prefill = $this->get_sfs_submit_prefill($db, $request, $user, $table_prefix);

		$template->assign_vars(array(
			'S_SFS' => true,
			'U_ACTION' => $this->u_action,
			'SFS_DEBUG_LOG_ALL' => !empty($config['antispamguard_sfs_debug_log_all']),
			'SFS_DEBUG_LOCALHOST_ONLY' => !isset($config['antispamguard_sfs_debug_localhost_only']) || !empty($config['antispamguard_sfs_debug_localhost_only']),
			'SFS_DEBUG_UNTIL' => isset($config['antispamguard_sfs_debug_until']) ? (int) $config['antispamguard_sfs_debug_until'] : 0,
			'SFS_DEBUG_UNTIL_FORMATTED' => !empty($config['antispamguard_sfs_debug_until']) ? $user->format_date((int) $config['antispamguard_sfs_debug_until']) : '',
			'SFS_LOG_ALL_CHECKS' => !empty($config['antispamguard_sfs_log_all_checks']),
			'SFS_CACHE_TTL' => isset($config['antispamguard_sfs_cache_ttl']) ? (int) $config['antispamguard_sfs_cache_ttl'] : 86400,
			'SFS_CACHE_TOTAL' => $sfs_cache_total,
			'SFS_CACHE_EXPIRED' => $sfs_cache_expired,
			'SFS_LOGS_TOTAL' => $sfs_logs_total,
			'SFS_CLEANUP_INTERVAL' => isset($config['antispamguard_sfs_cleanup_interval']) ? (int) $config['antispamguard_sfs_cleanup_interval'] : 86400,
			'SFS_LOG_RETENTION_DAYS' => isset($config['antispamguard_sfs_log_retention_days']) ? (int) $config['antispamguard_sfs_log_retention_days'] : 90,
			'SFS_LOG_PRESERVE_REVIEWED' => !empty($config['antispamguard_sfs_log_preserve_reviewed']),
			'SFS_CLEANUP_LAST_GC' => !empty($config['antispamguard_sfs_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_sfs_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
			'ANTISPAMGUARD_SFS_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
			'SFS_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? (string) $config['antispamguard_sfs_action_mode'] : 'block',
			'RANDOM_GMAIL_CONTACT_ENABLED' => !isset($config['antispamguard_random_gmail_enabled']) || !empty($config['antispamguard_random_gmail_enabled']),
			'RANDOM_GMAIL_REGISTER_ENABLED' => !empty($config['antispamguard_random_gmail_register_enabled']),
			'SFS_API_KEY_CONFIGURED' => !empty($config['antispamguard_sfs_api_key']),
			'SFS_API_KEY_MASKED' => !empty($config['antispamguard_sfs_api_key']) ? $this->settings_helper->mask_secret((string) $config['antispamguard_sfs_api_key']) : '',
			'SFS_SUBMIT_PREFILL_IP' => $sfs_submit_prefill['ip'],
			'SFS_SUBMIT_PREFILL_EMAIL' => $sfs_submit_prefill['email'],
			'SFS_SUBMIT_PREFILL_USERNAME' => $sfs_submit_prefill['username'],
			'SFS_SUBMIT_PREFILL_EVIDENCE' => $sfs_submit_prefill['evidence'],
			'SFS_SUBMIT_PREFILL_SOURCE' => $sfs_submit_prefill['source'],
			'SFS_SUBMIT_PREFILL_SOURCE_LOG_ID' => $sfs_submit_prefill['source_log_id'],
			'S_SFS_SUBMIT_PREFILLED' => !empty($sfs_submit_prefill['prefilled']),
			'ANTISPAMGUARD_SFS_LOG_ENABLED' => !isset($config['antispamguard_sfs_log_enabled']) || !empty($config['antispamguard_sfs_log_enabled']),
			'ANTISPAMGUARD_SFS_LOG_ONLY_BLOCKED' => !empty($config['antispamguard_sfs_log_only_blocked']),
			'ANTISPAMGUARD_SFS_MIN_CONFIDENCE' => isset($config['antispamguard_sfs_min_confidence']) ? (int) $config['antispamguard_sfs_min_confidence'] : 80,
			'ANTISPAMGUARD_SFS_MIN_FREQUENCY' => isset($config['antispamguard_sfs_min_frequency']) ? (int) $config['antispamguard_sfs_min_frequency'] : 5,
			'ANTISPAMGUARD_SFS_BLOCK_MULTIPLE_HITS' => !empty($config['antispamguard_sfs_block_multiple_hits']),
			'SFS_WHITELIST_IPS' => isset($config['antispamguard_sfs_whitelist_ips']) ? (string) $config['antispamguard_sfs_whitelist_ips'] : '',
			'SFS_WHITELIST_EMAILS' => isset($config['antispamguard_sfs_whitelist_emails']) ? (string) $config['antispamguard_sfs_whitelist_emails'] : '',
			'SFS_WHITELIST_USERNAMES' => isset($config['antispamguard_sfs_whitelist_usernames']) ? (string) $config['antispamguard_sfs_whitelist_usernames'] : '',
			'IP_REPUTATION_WEIGHT_SFS' => isset($config['antispamguard_ip_reputation_weight_sfs']) ? (int) $config['antispamguard_ip_reputation_weight_sfs'] : 2,
			'DECISION_WEIGHT_SFS' => isset($config['antispamguard_decision_weight_sfs']) ? (int) $config['antispamguard_decision_weight_sfs'] : 80,
			'SFS_DIAG_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
			'SFS_DIAG_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? $config['antispamguard_sfs_action_mode'] : 'block',
			'SFS_DIAG_CACHE_TOTAL' => $sfs_cache_total,
			'SFS_DIAG_LOGS_TOTAL' => $sfs_logs_total,
			'SFS_CIRCUIT_OPEN' => $circuit_open,
			'SFS_CIRCUIT_UNTIL' => $circuit_until,
			'SFS_CIRCUIT_UNTIL_FORMATTED' => $circuit_until > 0 ? $user->format_date($circuit_until) : '',
			'SFS_FAILURE_COUNT' => isset($config['antispamguard_sfs_failure_count']) ? (int) $config['antispamguard_sfs_failure_count'] : 0,
			'SFS_ERROR_CACHE_TTL' => isset($config['antispamguard_sfs_error_cache_ttl']) ? (int) $config['antispamguard_sfs_error_cache_ttl'] : 300,
			'SFS_HTTP_TIMEOUT' => isset($config['antispamguard_sfs_http_timeout']) ? (int) $config['antispamguard_sfs_http_timeout'] : 2,
			'SFS_HTTP_RETRIES' => isset($config['antispamguard_sfs_http_retries']) ? (int) $config['antispamguard_sfs_http_retries'] : 1,
			'SFS_HTTP_MAX_RESPONSE_BYTES' => isset($config['antispamguard_sfs_http_max_response_bytes']) ? (int) $config['antispamguard_sfs_http_max_response_bytes'] : 262144,
			'SFS_CIRCUIT_THRESHOLD' => isset($config['antispamguard_sfs_circuit_threshold']) ? (int) $config['antispamguard_sfs_circuit_threshold'] : 3,
			'SFS_CIRCUIT_COOLDOWN' => isset($config['antispamguard_sfs_circuit_cooldown']) ? (int) $config['antispamguard_sfs_circuit_cooldown'] : 300,
		));
	}

	/**
	 * @param string|null $pagination_base_url  Full ACP URL prefix for SFS pagination (e.g. logs page with filters).
	 */
	public function assign_sfs_logs($db, $request, $template, $user, $table_prefix, $pagination_base_url = null)
	{
		$sfs_filter_action = $request->variable('sfs_filter_action', '');
		$sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');
		$sfs_filter_review = $request->variable('sfs_filter_review', '');
		$sfs_filter_query = trim($request->variable('sfs_filter_query', '', true));
		$sfs_filter_query = $this->settings_helper->truncate_for_storage($sfs_filter_query, 100);
		$sfs_start = max(0, $request->variable('sfs_start', 0));
		$sfs_per_page = 25;

		if (!in_array($sfs_filter_action, array('', 'block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
		{
			$sfs_filter_action = '';
		}

		if (!in_array($sfs_filter_blocked, array('', '1', '0'), true))
		{
			$sfs_filter_blocked = '';
		}

		if (!in_array($sfs_filter_review, array('', 'pending', 'reported', 'blocked', 'reported_blocked', 'allowed'), true))
		{
			$sfs_filter_review = '';
		}

		$sfs_table = $table_prefix . 'antispamguard_sfs_log';
		$sfs_where = array();

		if ($sfs_filter_blocked !== '')
		{
			$sfs_where[] = 'blocked = ' . (int) $sfs_filter_blocked;
		}

		if ($sfs_filter_action !== '')
		{
			$sfs_where[] = "action_mode = '" . $db->sql_escape($sfs_filter_action) . "'";
		}

		if ($sfs_filter_review !== '')
		{
			if ($sfs_filter_review === 'pending')
			{
				$sfs_where[] = "(review_status = '' OR review_status IS NULL)";
			}
			else
			{
				$sfs_where[] = "review_status = '" . $db->sql_escape($sfs_filter_review) . "'";
			}
		}

		if ($sfs_filter_query !== '')
		{
			$sfs_like = $db->sql_like_expression($db->get_any_char() . $db->sql_escape($sfs_filter_query) . $db->get_any_char());
			$sfs_where[] = '(user_ip ' . $sfs_like . ' OR user_email ' . $sfs_like . ' OR username ' . $sfs_like . ')';
		}

		$sfs_where_sql = !empty($sfs_where) ? ' WHERE ' . implode(' AND ', $sfs_where) : '';

		$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table;
		$result = $db->sql_query($sql);
		$total_sfs_logs = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		if ($sfs_where_sql === '')
		{
			$total_sfs_logs_filtered = $total_sfs_logs;
		}
		else
		{
			$sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table . $sfs_where_sql;
			$result = $db->sql_query($sql);
			$total_sfs_logs_filtered = (int) $db->sql_fetchfield('total_logs');
			$db->sql_freeresult($result);
		}

		$has_sfs_logs = false;
		$sfs_rows_rendered = 0;

		if ($sfs_start >= $total_sfs_logs_filtered && $total_sfs_logs_filtered > 0)
		{
			$sfs_start = max(0, floor(($total_sfs_logs_filtered - 1) / $sfs_per_page) * $sfs_per_page);
		}

		// New submissions are correlated and merged at write time by
		// submission_key.  Page in SQL instead of loading and O(n²)-grouping
		// the complete audit table in PHP.
		$sql = 'SELECT * FROM ' . $sfs_table . $sfs_where_sql . ' ORDER BY created_at DESC, log_id DESC';
		$result = $db->sql_query_limit($sql, $sfs_per_page, $sfs_start);
		$sfs_page_rows = array();
		$page_log_ids = array();
		while ($sfs_row = $db->sql_fetchrow($result))
		{
			$sfs_page_rows[] = array(
				'row' => $this->normalize_sfs_group_row($sfs_row),
				'rows' => array($sfs_row),
				'ids' => array((int) $sfs_row['log_id']),
				'count' => 1,
			);
			$page_log_ids[] = (int) $sfs_row['log_id'];
		}
		$db->sql_freeresult($result);

		$reported_log_ids = $this->get_successful_submission_log_ids($db, $table_prefix, $page_log_ids);

		foreach ($sfs_page_rows as $sfs_group)
		{
			$sfs_row = $sfs_group['row'];
			$details = json_decode($sfs_row['details_json'], true);
			if (!is_array($details))
			{
				$details = array();
			}

			$detail_parts = array();
			$decision_meta = isset($details['_decision']) && is_array($details['_decision']) ? $details['_decision'] : array();
			$action_mode = (isset($sfs_row['action_mode']) && (string) $sfs_row['action_mode'] !== '') ? (string) $sfs_row['action_mode'] : (isset($decision_meta['action_mode']) ? (string) $decision_meta['action_mode'] : 'block');
			$matched = !empty($decision_meta['matched']);

			if (!empty($decision_meta['review_only']))
			{
				$detail_parts[] = $user->lang('ACP_ANTISPAMGUARD_SFS_POLICY_REVIEW_ONLY');
			}
			else if (!empty($decision_meta['hard_identity_match']))
			{
				$detail_parts[] = $user->lang('ACP_ANTISPAMGUARD_SFS_POLICY_HARD_IDENTITY');
			}

			if ($sfs_rows_rendered >= $sfs_per_page)
			{
				break;
			}

			$has_sfs_logs = true;
			$sfs_rows_rendered++;

			foreach ($details as $detail_type => $detail_data)
			{
				if ($detail_type === '_decision' || !is_array($detail_data))
				{
					continue;
				}

				if (!empty($detail_data['error']))
				{
					$detail_parts[] = strtoupper($detail_type) . ': '
						. 'error=' . (!empty($detail_data['error_status']) ? (string) $detail_data['error_status'] : 'remote_error')
						. ', cached=' . (!empty($detail_data['cached']) ? $user->lang('YES') : $user->lang('NO'));
					continue;
				}

				$detail_parts[] = strtoupper($detail_type) . ': '
					. 'confidence=' . (isset($detail_data['confidence']) ? $detail_data['confidence'] : 0)
					. ', frequency=' . (isset($detail_data['frequency']) ? $detail_data['frequency'] : 0)
					. ', cached=' . (!empty($detail_data['cached']) ? $user->lang('YES') : $user->lang('NO'));
			}

			$already_reported = isset($reported_log_ids[(int) $sfs_row['log_id']]);
			$review_status_text = $this->format_sfs_review_status($sfs_row, $already_reported, $user);
			$row_class = $this->get_sfs_row_class($sfs_row, $already_reported);

			$template->assign_block_vars('sfs_logs', array(
				'ID' => (int) $sfs_row['log_id'],
				'TIME' => $user->format_date((int) $sfs_row['created_at']),
				'SOURCE' => $sfs_row['check_source'],
				'IP' => $sfs_row['user_ip'],
				'USERNAME' => $sfs_row['username'],
				'EMAIL' => $sfs_row['user_email'],
				'LISTED_COUNT' => (int) $sfs_row['listed_count'],
				'STRONG_HIT' => !empty($sfs_row['strong_hit']) ? $user->lang('YES') : $user->lang('NO'),
				'BLOCKED' => !empty($sfs_row['blocked']) ? $user->lang('YES') : $user->lang('NO'),
				'ACTION_MODE' => $this->format_sfs_action_mode($action_mode, $user),
				'MATCHED' => $matched ? $user->lang('YES') : $user->lang('NO'),
				'DETAILS' => $this->format_sfs_group_details(!empty($detail_parts) ? implode('; ', $detail_parts) : '', (int) $sfs_group['count']),
				'GROUP_COUNT' => (int) $sfs_group['count'],
				'S_GROUPED' => ((int) $sfs_group['count'] > 1),
				'REVIEW_STATUS' => $review_status_text,
				'ROW_CLASS' => $row_class,
				'U_REPORT_PREFILL' => $this->append_url_param($this->get_sfs_mode_url(), 'sfs_prefill_log_id', (int) $sfs_row['log_id']),
				'S_CAN_PREFILL_REPORT' => ($sfs_row['user_ip'] !== '' || $sfs_row['user_email'] !== '' || $sfs_row['username'] !== ''),
				'S_ALREADY_REPORTED' => $already_reported || (isset($sfs_row['review_status']) && in_array((string) $sfs_row['review_status'], array('reported', 'reported_blocked'), true)),
				'S_LOCAL_BLOCKED' => (isset($sfs_row['local_action']) && (string) $sfs_row['local_action'] === 'blocked') || (isset($sfs_row['review_status']) && in_array((string) $sfs_row['review_status'], array('blocked', 'reported_blocked'), true)),
				'S_ALLOWED' => isset($sfs_row['review_status']) && (string) $sfs_row['review_status'] === 'allowed',
				'S_REVIEWED' => ((isset($sfs_row['review_status']) && (string) $sfs_row['review_status'] !== '') || (isset($sfs_row['local_action']) && (string) $sfs_row['local_action'] !== '')),
			));
		}

		$filter_params = '';
		if ($sfs_filter_action !== '')
		{
			$filter_params .= '&amp;sfs_filter_action=' . urlencode($sfs_filter_action);
		}
		if ($sfs_filter_blocked !== '')
		{
			$filter_params .= '&amp;sfs_filter_blocked=' . urlencode($sfs_filter_blocked);
		}
		if ($sfs_filter_review !== '')
		{
			$filter_params .= '&amp;sfs_filter_review=' . urlencode($sfs_filter_review);
		}
		if ($sfs_filter_query !== '')
		{
			$filter_params .= '&amp;sfs_filter_query=' . urlencode($sfs_filter_query);
		}

		$base_url = ($pagination_base_url !== null && $pagination_base_url !== '')
			? $pagination_base_url
			: ($this->u_action . $filter_params);
		$sfs_pagination = $this->pagination->build_pagination($base_url, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start, 'sfs_start');
		$sfs_page_number = $this->pagination->build_page_number($user, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start);
		$sfs_review_stats = $this->get_sfs_review_stats($db, $table_prefix);

		$template->assign_vars(array(
			'S_HAS_SFS_LOGS' => $has_sfs_logs,
			'TOTAL_SFS_LOGS' => $total_sfs_logs,
			'TOTAL_SFS_LOGS_FILTERED' => $total_sfs_logs_filtered,
			'SFS_FILTER_ACTION' => $sfs_filter_action,
			'SFS_FILTER_BLOCKED' => $sfs_filter_blocked,
			'SFS_FILTER_REVIEW' => $sfs_filter_review,
			'SFS_FILTER_QUERY' => $sfs_filter_query,
			'S_SFS_FILTER_ACTIVE' => ($sfs_filter_action !== '' || $sfs_filter_blocked !== '' || $sfs_filter_review !== '' || $sfs_filter_query !== ''),
			'SFS_REVIEW_TOTAL' => $sfs_review_stats['total'],
			'SFS_REVIEW_PENDING' => $sfs_review_stats['pending'],
			'SFS_REVIEW_REPORTED' => $sfs_review_stats['reported'],
			'SFS_REVIEW_BLOCKED' => $sfs_review_stats['blocked'],
			'SFS_REVIEW_REPORTED_BLOCKED' => $sfs_review_stats['reported_blocked'],
			'SFS_REVIEW_ALLOWED' => $sfs_review_stats['allowed'],
			'SFS_PAGINATION' => $sfs_pagination,
			'SFS_PAGE_NUMBER' => $sfs_page_number,
		));
	}


	/**
	 * Build ACP-visible SFS groups without deleting raw audit rows.
	 *
	 * Older rows can contain only the IP and a later row from the same form
	 * submission can contain the username/e-mail.  Grouping at render time keeps
	 * the audit trail intact, but displays the most complete candidate for
	 * review and StopForumSpam reporting.
	 */
	protected function group_sfs_log_rows(array $rows)
	{
		$groups = array();
		$window = 30;

		foreach ($rows as $row)
		{
			$matched_index = null;

			foreach ($groups as $index => $group)
			{
				if ($this->sfs_rows_belong_to_same_group($group['row'], $row, $window))
				{
					$matched_index = $index;
					break;
				}
			}

			if ($matched_index === null)
			{
				$groups[] = array(
					'row' => $this->normalize_sfs_group_row($row),
					'rows' => array($row),
					'ids' => array((int) $row['log_id']),
					'count' => 1,
				);
				continue;
			}

			$groups[$matched_index]['row'] = $this->merge_sfs_group_row($groups[$matched_index]['row'], $row);
			$groups[$matched_index]['rows'][] = $row;
			$groups[$matched_index]['ids'][] = (int) $row['log_id'];
			$groups[$matched_index]['count']++;
		}

		usort($groups, array($this, 'sort_sfs_groups'));

		return $groups;
	}

	protected function sfs_rows_belong_to_same_group(array $existing, array $incoming, $window)
	{
		$existing_key = isset($existing['submission_key']) ? trim((string) $existing['submission_key']) : '';
		$incoming_key = isset($incoming['submission_key']) ? trim((string) $incoming['submission_key']) : '';

		if ($existing_key !== '' || $incoming_key !== '')
		{
			return $existing_key !== '' && $incoming_key !== '' && hash_equals($existing_key, $incoming_key);
		}

		if ((string) $existing['check_source'] !== (string) $incoming['check_source'])
		{
			return false;
		}

		if ((string) $existing['user_ip'] === '' || (string) $incoming['user_ip'] === '' || (string) $existing['user_ip'] !== (string) $incoming['user_ip'])
		{
			return false;
		}

		if (abs((int) $existing['created_at'] - (int) $incoming['created_at']) > (int) $window)
		{
			return false;
		}

		$existing_email = strtolower(trim((string) $existing['user_email']));
		$incoming_email = strtolower(trim((string) $incoming['user_email']));
		if ($existing_email !== '' && $incoming_email !== '' && $existing_email !== $incoming_email)
		{
			return false;
		}

		$existing_username = strtolower(trim((string) $existing['username']));
		$incoming_username = strtolower(trim((string) $incoming['username']));
		if ($existing_username !== '' && $incoming_username !== '' && $existing_username !== $incoming_username)
		{
			return false;
		}

		return true;
	}

	protected function normalize_sfs_group_row(array $row)
	{
		$row['user_email'] = isset($row['user_email']) ? (string) $row['user_email'] : '';
		$row['username'] = isset($row['username']) ? (string) $row['username'] : '';
		$row['listed_count'] = isset($row['listed_count']) ? (int) $row['listed_count'] : 0;
		$row['strong_hit'] = !empty($row['strong_hit']) ? 1 : 0;
		$row['blocked'] = !empty($row['blocked']) ? 1 : 0;
		$row['details_json'] = isset($row['details_json']) ? (string) $row['details_json'] : '';
		$row['action_mode'] = isset($row['action_mode']) ? (string) $row['action_mode'] : '';
		$row['review_status'] = isset($row['review_status']) ? (string) $row['review_status'] : '';
		$row['local_action'] = isset($row['local_action']) ? (string) $row['local_action'] : '';
		$row['submission_key'] = isset($row['submission_key']) ? (string) $row['submission_key'] : '';

		return $row;
	}

	protected function merge_sfs_group_row(array $base, array $incoming)
	{
		$base = $this->normalize_sfs_group_row($base);
		$incoming = $this->normalize_sfs_group_row($incoming);

		$base_score = $this->sfs_report_data_score($base);
		$incoming_score = $this->sfs_report_data_score($incoming);

		if ($incoming_score > $base_score)
		{
			$base['log_id'] = (int) $incoming['log_id'];
		}

		if ((string) $base['user_email'] === '' && (string) $incoming['user_email'] !== '')
		{
			$base['user_email'] = (string) $incoming['user_email'];
		}
		if ((string) $base['username'] === '' && (string) $incoming['username'] !== '')
		{
			$base['username'] = (string) $incoming['username'];
		}

		$base['created_at'] = max((int) $base['created_at'], (int) $incoming['created_at']);
		$base['listed_count'] = max((int) $base['listed_count'], (int) $incoming['listed_count']);
		$base['strong_hit'] = (!empty($base['strong_hit']) || !empty($incoming['strong_hit'])) ? 1 : 0;
		$base['blocked'] = (!empty($base['blocked']) || !empty($incoming['blocked'])) ? 1 : 0;
		$base['action_mode'] = $this->strongest_sfs_action_mode((string) $base['action_mode'], (string) $incoming['action_mode']);
		$base['details_json'] = $this->merge_sfs_details_json((string) $base['details_json'], (string) $incoming['details_json']);
		$base['review_status'] = $this->strongest_sfs_review_status((string) $base['review_status'], (string) $incoming['review_status']);
		$base['local_action'] = $this->strongest_sfs_local_action((string) $base['local_action'], (string) $incoming['local_action']);

		return $base;
	}

	protected function sfs_report_data_score(array $row)
	{
		$score = 0;
		$score += ((string) $row['user_ip'] !== '') ? 1 : 0;
		$score += ((string) $row['username'] !== '') ? 2 : 0;
		$score += ((string) $row['user_email'] !== '') ? 4 : 0;

		return $score;
	}

	protected function strongest_sfs_action_mode($a, $b)
	{
		$rank = array('disabled' => 0, 'whitelist' => 1, 'log_only' => 2, 'soft' => 3, 'block' => 4);
		$a = isset($rank[$a]) ? $a : 'log_only';
		$b = isset($rank[$b]) ? $b : 'log_only';

		return ($rank[$b] > $rank[$a]) ? $b : $a;
	}

	protected function strongest_sfs_review_status($a, $b)
	{
		$rank = array('' => 0, 'allowed' => 1, 'reported' => 2, 'blocked' => 3, 'reported_blocked' => 4);
		$a = isset($rank[$a]) ? $a : '';
		$b = isset($rank[$b]) ? $b : '';

		return ($rank[$b] > $rank[$a]) ? $b : $a;
	}

	protected function strongest_sfs_local_action($a, $b)
	{
		$rank = array('' => 0, 'allowed' => 1, 'reported' => 2, 'blocked' => 3);
		$a = isset($rank[$a]) ? $a : '';
		$b = isset($rank[$b]) ? $b : '';

		return ($rank[$b] > $rank[$a]) ? $b : $a;
	}

	protected function merge_sfs_details_json($existing_json, $incoming_json)
	{
		$existing = json_decode((string) $existing_json, true);
		$incoming = json_decode((string) $incoming_json, true);

		if (!is_array($existing))
		{
			$existing = array();
		}
		if (!is_array($incoming))
		{
			$incoming = array();
		}

		foreach ($incoming as $key => $incoming_value)
		{
			if (!isset($existing[$key]) || !is_array($existing[$key]) || !is_array($incoming_value))
			{
				if (!isset($existing[$key]) || $existing[$key] === '' || $existing[$key] === array())
				{
					$existing[$key] = $incoming_value;
				}
				continue;
			}

			$existing[$key] = array_merge($existing[$key], $incoming_value);

			if (isset($incoming_value['confidence'], $existing[$key]['confidence']))
			{
				$existing[$key]['confidence'] = max((float) $existing[$key]['confidence'], (float) $incoming_value['confidence']);
			}
			if (isset($incoming_value['frequency'], $existing[$key]['frequency']))
			{
				$existing[$key]['frequency'] = max((int) $existing[$key]['frequency'], (int) $incoming_value['frequency']);
			}
			if (isset($incoming_value['is_listed'], $existing[$key]['is_listed']))
			{
				$existing[$key]['is_listed'] = !empty($existing[$key]['is_listed']) || !empty($incoming_value['is_listed']);
			}
			if (isset($incoming_value['cached'], $existing[$key]['cached']))
			{
				$existing[$key]['cached'] = !empty($existing[$key]['cached']) || !empty($incoming_value['cached']);
			}
		}

		return json_encode($existing);
	}

	protected function sort_sfs_groups($a, $b)
	{
		if ((int) $a['row']['created_at'] === (int) $b['row']['created_at'])
		{
			return (int) $b['row']['log_id'] - (int) $a['row']['log_id'];
		}

		return (int) $b['row']['created_at'] - (int) $a['row']['created_at'];
	}

	protected function sfs_group_has_successful_submission($db, $table_prefix, array $log_ids)
	{
		$log_ids = array_values(array_unique(array_filter(array_map('intval', $log_ids))));
		if (empty($log_ids))
		{
			return false;
		}

		$sql = 'SELECT submit_id FROM ' . $table_prefix . 'antispamguard_sfs_submit_log
			WHERE source = \'sfs_log\'
				AND ' . $db->sql_in_set('source_log_id', $log_ids) . "
				AND status = 'success'";
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return !empty($row);
	}

	protected function get_successful_submission_log_ids($db, $table_prefix, array $log_ids)
	{
		$log_ids = array_values(array_unique(array_filter(array_map('intval', $log_ids))));
		if (empty($log_ids))
		{
			return array();
		}

		$sql = 'SELECT source_log_id
			FROM ' . $table_prefix . "antispamguard_sfs_submit_log
			WHERE source = 'sfs_log'
				AND status = 'success'
				AND " . $db->sql_in_set('source_log_id', $log_ids);
		$result = $db->sql_query($sql);
		$reported = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$reported[(int) $row['source_log_id']] = true;
		}
		$db->sql_freeresult($result);

		return $reported;
	}

	protected function format_sfs_group_details($details, $count)
	{
		$details = (string) $details;
		if ((int) $count <= 1)
		{
			return $details;
		}

		$group_note = '×' . (int) $count;

		return ($details !== '') ? ($group_note . '; ' . $details) : $group_note;
	}

	public function get_sfs_review_stats($db, $table_prefix)
	{
		$stats = array(
			'total' => 0,
			'pending' => 0,
			'reported' => 0,
			'blocked' => 0,
			'reported_blocked' => 0,
			'allowed' => 0,
		);

		$table = $table_prefix . 'antispamguard_sfs_log';
		$sql = 'SELECT review_status, COUNT(log_id) AS total_rows
			FROM ' . $table . '
			GROUP BY review_status';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$status = isset($row['review_status']) ? (string) $row['review_status'] : '';
			$count = (int) $row['total_rows'];
			$stats['total'] += $count;

			if ($status === '' || $status === null)
			{
				$stats['pending'] += $count;
			}
			else if (isset($stats[$status]))
			{
				$stats[$status] += $count;
			}
			else
			{
				$stats['pending'] += $count;
			}
		}

		$db->sql_freeresult($result);

		return $stats;
	}

	public function is_sfs_moderation_action($request)
	{
		$actions = array(
			'report_sfs_log',
			'block_sfs_log',
			'allow_sfs_log',
			'clear_sfs_review',
			'bulk_report_sfs_logs',
			'bulk_block_sfs_logs',
			'bulk_allow_sfs_logs',
			'bulk_clear_sfs_review',
			'bulk_report_block_sfs_logs',
		);

		foreach ($actions as $action)
		{
			if ($request->is_set_post($action))
			{
				return true;
			}
		}

		return false;
	}

	public function handle_sfs_moderation_action($db, $request, $user, $config, $table_prefix)
	{
		$summary = array(
			'reported' => 0,
			'blocked' => 0,
			'already_blocked' => 0,
			'allowed' => 0,
			'cleared' => 0,
			'skipped' => 0,
			'failed' => 0,
		);

		$mode = '';
		$ids = array();

		if ($request->is_set_post('report_sfs_log'))
		{
			$mode = 'report';
			$ids = array($request->variable('report_sfs_log', 0));
		}
		else if ($request->is_set_post('block_sfs_log'))
		{
			$mode = 'block';
			$ids = array($request->variable('block_sfs_log', 0));
		}
		else if ($request->is_set_post('allow_sfs_log'))
		{
			$mode = 'allow';
			$ids = array($request->variable('allow_sfs_log', 0));
		}
		else if ($request->is_set_post('clear_sfs_review'))
		{
			$mode = 'clear';
			$ids = array($request->variable('clear_sfs_review', 0));
		}
		else if ($request->is_set_post('bulk_report_block_sfs_logs'))
		{
			$mode = 'report_block';
			$ids = $request->variable('sfs_selected_logs', array(0));
		}
		else if ($request->is_set_post('bulk_report_sfs_logs'))
		{
			$mode = 'report';
			$ids = $request->variable('sfs_selected_logs', array(0));
		}
		else if ($request->is_set_post('bulk_block_sfs_logs'))
		{
			$mode = 'block';
			$ids = $request->variable('sfs_selected_logs', array(0));
		}
		else if ($request->is_set_post('bulk_allow_sfs_logs'))
		{
			$mode = 'allow';
			$ids = $request->variable('sfs_selected_logs', array(0));
		}
		else if ($request->is_set_post('bulk_clear_sfs_review'))
		{
			$mode = 'clear';
			$ids = $request->variable('sfs_selected_logs', array(0));
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
		$ids = array_slice($ids, 0, 50);

		if (empty($ids) || $mode === '')
		{
			$summary['skipped']++;
			return $summary;
		}

		foreach ($ids as $log_id)
		{
			$row = $this->get_sfs_log_row($db, $table_prefix, $log_id);
			if (!$row)
			{
				$summary['skipped']++;
				continue;
			}

			if ($mode === 'allow')
			{
				$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'allowed', 'allowed');
				$summary['allowed']++;
				continue;
			}

			if ($mode === 'clear')
			{
				$this->clear_sfs_log_review($db, $table_prefix, $log_id, $user);
				$summary['cleared']++;
				continue;
			}

			$reported = false;
			$was_reported = false;
			$blocked = false;
			$already_blocked = false;

			if ($mode === 'report' || $mode === 'report_block')
			{
				if (!$this->sfs_log_can_be_reported($row))
				{
					$summary['skipped']++;
				}
				else if ($this->has_successful_sfs_submission($db, $table_prefix, $log_id))
				{
					$was_reported = true;
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'reported', (string) $row['local_action']);
					$summary['skipped']++;
				}
				else
				{
					global $phpbb_container;

					$sfs_client = $phpbb_container->get('mundophpbb.antispamguard.stopforumspam_client');
					$evidence = $user->lang('ACP_ANTISPAMGUARD_SFS_SUBMIT_EVIDENCE_FROM_LOG', (int) $row['log_id'], (string) $row['check_source'], $user->format_date((int) $row['created_at']));
					$submit_result = $sfs_client->submit_spammer((string) $row['user_ip'], (string) $row['user_email'], (string) $row['username'], $evidence);
					$this->record_sfs_submission($db, $table_prefix, $user, (string) $row['user_ip'], (string) $row['user_email'], (string) $row['username'], $evidence, 'sfs_log', (int) $row['log_id'], $submit_result);

					if (!empty($submit_result['success']))
					{
						$reported = true;
						$summary['reported']++;
					}
					else
					{
						$summary['failed']++;
					}
				}
			}

			if ($mode === 'block' || $mode === 'report_block')
			{
				$block_status = $this->add_sfs_log_ip_to_blacklist($config, $row);
				if ($block_status === 'added')
				{
					$blocked = true;
					$summary['blocked']++;
				}
				else if ($block_status === 'exists')
				{
					$already_blocked = true;
					$summary['already_blocked']++;
				}
				else
				{
					$summary['skipped']++;
				}
			}

			if ($mode === 'report')
			{
				if ($reported)
				{
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'reported', (string) $row['local_action']);
				}
			}
			else if ($mode === 'block')
			{
				if ($blocked || $already_blocked)
				{
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'blocked', 'blocked');
				}
			}
			else if ($mode === 'report_block')
			{
				if (($reported || $was_reported) && ($blocked || $already_blocked))
				{
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'reported_blocked', 'blocked');
				}
				else if ($reported || $was_reported)
				{
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'reported', (string) $row['local_action']);
				}
				else if ($blocked || $already_blocked)
				{
					$this->mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, 'blocked', 'blocked');
				}
			}
		}

		return $summary;
	}

	public function get_sfs_log_row($db, $table_prefix, $log_id)
	{
		$sql = 'SELECT * FROM ' . $table_prefix . 'antispamguard_sfs_log
			WHERE log_id = ' . (int) $log_id;
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return $row;
	}

	public function sfs_log_can_be_reported(array $row)
	{
		return ((string) $row['user_ip'] !== '' || (string) $row['user_email'] !== '' || (string) $row['username'] !== '');
	}

	public function has_successful_sfs_submission($db, $table_prefix, $log_id)
	{
		$sql = 'SELECT submit_id FROM ' . $table_prefix . "antispamguard_sfs_submit_log
			WHERE source = 'sfs_log'
				AND source_log_id = " . (int) $log_id . "
				AND status = 'success'";
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return !empty($row);
	}

	public function add_sfs_log_ip_to_blacklist($config, array $row)
	{
		$ip = trim((string) $row['user_ip']);
		if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP))
		{
			return 'invalid';
		}

		$current = isset($config['antispamguard_ip_blacklist']) ? (string) $config['antispamguard_ip_blacklist'] : '';
		$current_normalized = $this->settings_helper->normalize_ip_list($current);
		$updated = $this->settings_helper->normalize_ip_list($current . "\n" . $ip);

		if ($updated === $current_normalized)
		{
			return 'exists';
		}

		$config->set('antispamguard_ip_blacklist', $updated);

		return 'added';
	}

	public function mark_sfs_log_reviewed($db, $table_prefix, $log_id, $user, $review_status, $local_action)
	{
		$old_row = $this->get_sfs_log_row($db, $table_prefix, $log_id);
		$old_review_status = $old_row && isset($old_row['review_status']) ? (string) $old_row['review_status'] : '';
		$old_local_action = $old_row && isset($old_row['local_action']) ? (string) $old_row['local_action'] : '';

		$data = array(
			'review_status' => (string) $review_status,
			'reviewed_at' => time(),
			'reviewed_by' => isset($user->data['user_id']) ? (int) $user->data['user_id'] : 0,
			'local_action' => (string) $local_action,
		);

		$sql = 'UPDATE ' . $table_prefix . 'antispamguard_sfs_log
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE log_id = ' . (int) $log_id;
		$db->sql_query($sql);

		$this->record_sfs_review_audit($db, $table_prefix, $log_id, $user, (string) $review_status, $old_review_status, (string) $review_status, $old_local_action, (string) $local_action);
	}

	public function clear_sfs_log_review($db, $table_prefix, $log_id, $user = null)
	{
		if ($user === null)
		{
			global $user;
		}

		$old_row = $this->get_sfs_log_row($db, $table_prefix, $log_id);
		$old_review_status = $old_row && isset($old_row['review_status']) ? (string) $old_row['review_status'] : '';
		$old_local_action = $old_row && isset($old_row['local_action']) ? (string) $old_row['local_action'] : '';

		$data = array(
			'review_status' => '',
			'reviewed_at' => 0,
			'reviewed_by' => 0,
			'local_action' => '',
		);

		$sql = 'UPDATE ' . $table_prefix . 'antispamguard_sfs_log
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE log_id = ' . (int) $log_id;
		$db->sql_query($sql);

		$this->record_sfs_review_audit($db, $table_prefix, $log_id, $user, 'clear', $old_review_status, '', $old_local_action, '');
	}

	public function record_sfs_review_audit($db, $table_prefix, $log_id, $user, $action, $old_review_status, $new_review_status, $old_local_action, $new_local_action, $note = '')
	{
		$table = $table_prefix . 'antispamguard_sfs_review_log';

		$sql_ary = array(
			'sfs_log_id' => (int) $log_id,
			'action' => (string) $action,
			'old_review_status' => (string) $old_review_status,
			'new_review_status' => (string) $new_review_status,
			'old_local_action' => (string) $old_local_action,
			'new_local_action' => (string) $new_local_action,
			'admin_user_id' => isset($user->data['user_id']) ? (int) $user->data['user_id'] : 0,
			'created_at' => time(),
			'note' => (string) $note,
		);

		$sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $sql_ary);
		$db->sql_query($sql);
	}

	public function prune_old_sfs_logs($db, $table_prefix, $retention_days, $preserve_reviewed = true)
	{
		$retention_days = (int) $retention_days;

		if ($retention_days <= 0)
		{
			return 0;
		}

		$table = $table_prefix . 'antispamguard_sfs_log';
		$submit_table = $table_prefix . 'antispamguard_sfs_submit_log';
		$cutoff = time() - ($retention_days * 86400);
		$where = 'created_at < ' . (int) $cutoff;

		if ($preserve_reviewed)
		{
			$where .= " AND (review_status = '' OR review_status IS NULL)";
			$where .= ' AND log_id NOT IN (SELECT source_log_id FROM ' . $submit_table . " WHERE source = 'sfs_log' AND status = 'success')";
		}

		$sql = 'DELETE FROM ' . $table . ' WHERE ' . $where;
		$db->sql_query($sql);

		return (int) $db->sql_affectedrows();
	}

	public function get_sfs_row_class(array $row, $already_reported)
	{
		$review_status = isset($row['review_status']) ? (string) $row['review_status'] : '';
		$local_action = isset($row['local_action']) ? (string) $row['local_action'] : '';

		if ($review_status === 'allowed')
		{
			return 'asg-sfs-row-allowed';
		}

		if ($review_status === 'reported_blocked')
		{
			return 'asg-sfs-row-reported-blocked';
		}

		if ($local_action === 'blocked' || $review_status === 'blocked')
		{
			return 'asg-sfs-row-blocked';
		}

		if ($already_reported || $review_status === 'reported')
		{
			return 'asg-sfs-row-reported';
		}

		return '';
	}

	public function format_sfs_review_status(array $row, $already_reported, $user)
	{
		$review_status = isset($row['review_status']) ? (string) $row['review_status'] : '';
		$local_action = isset($row['local_action']) ? (string) $row['local_action'] : '';

		if ($review_status === 'allowed')
		{
			return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_ALLOWED');
		}

		if ($review_status === 'reported_blocked')
		{
			return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_REPORTED_BLOCKED');
		}

		if ($local_action === 'blocked' || $review_status === 'blocked')
		{
			return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_BLOCKED_LOCAL');
		}

		if ($already_reported || $review_status === 'reported')
		{
			return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_REPORTED');
		}

		return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_PENDING');
	}

	public function get_sfs_mode_url()
	{
		if (strpos($this->u_action, 'mode=') !== false)
		{
			return preg_replace('/mode=[^&;]+/', 'mode=sfs', $this->u_action, 1);
		}

		return $this->u_action . (strpos($this->u_action, '?') === false ? '?' : '&amp;') . 'mode=sfs';
	}

	public function append_url_param($url, $name, $value)
	{
		return $url . (strpos($url, '?') === false ? '?' : '&amp;') . urlencode($name) . '=' . urlencode((string) $value);
	}

	public function get_sfs_submit_prefill($db, $request, $user, $table_prefix)
	{
		$prefill = array(
			'ip' => '',
			'email' => '',
			'username' => '',
			'evidence' => '',
			'source' => 'manual_acp',
			'source_log_id' => 0,
			'prefilled' => false,
		);

		$log_id = max(0, $request->variable('sfs_prefill_log_id', 0));
		if ($log_id <= 0)
		{
			return $prefill;
		}

		$sql = 'SELECT log_id, created_at, check_source, user_ip, user_email, username
			FROM ' . $table_prefix . 'antispamguard_sfs_log
			WHERE log_id = ' . (int) $log_id;
		$result = $db->sql_query_limit($sql, 1);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			return $prefill;
		}

		$prefill['ip'] = (string) $row['user_ip'];
		$prefill['email'] = (string) $row['user_email'];
		$prefill['username'] = (string) $row['username'];
		$prefill['source'] = 'sfs_log';
		$prefill['source_log_id'] = (int) $row['log_id'];
		$prefill['evidence'] = $user->lang('ACP_ANTISPAMGUARD_SFS_SUBMIT_EVIDENCE_FROM_LOG', (int) $row['log_id'], (string) $row['check_source'], $user->format_date((int) $row['created_at']));
		$prefill['prefilled'] = true;

		return $prefill;
	}

	public function record_sfs_submission($db, $table_prefix, $user, $ip, $email, $username, $evidence, $source, $source_log_id, array $result)
	{
		$table = $table_prefix . 'antispamguard_sfs_submit_log';
		$status = !empty($result['success']) ? 'success' : (!empty($result['status']) ? (string) $result['status'] : 'failed');
		$response_text = '';

		if (!empty($result['response']))
		{
			$response_text = (string) $result['response'];
		}
		elseif (!empty($result['message']))
		{
			$response_text = (string) $result['message'];
		}

		$data = array(
			'user_id' => isset($user->data['user_id']) ? (int) $user->data['user_id'] : 0,
			'admin_username' => isset($user->data['username']) ? (string) $user->data['username'] : '',
			'admin_ip' => isset($user->ip) ? (string) $user->ip : '',
			'spammer_ip' => (string) $ip,
			'spammer_email' => (string) $email,
			'spammer_username' => (string) $username,
			'evidence' => (string) $evidence,
			'source' => (string) $source,
			'source_log_id' => (int) $source_log_id,
			'status' => $status,
			'response_text' => $response_text,
			'created_at' => time(),
		);

		$sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		return (int) $db->sql_nextid();
	}

	public function assign_sfs_submission_logs($db, $template, $user, $table_prefix)
	{
		$table = $table_prefix . 'antispamguard_sfs_submit_log';
		$has_submission_logs = false;
		$total_submission_logs = 0;

		$sql = 'SELECT COUNT(submit_id) AS total_logs FROM ' . $table;
		$result = $db->sql_query($sql);
		$total_submission_logs = (int) $db->sql_fetchfield('total_logs');
		$db->sql_freeresult($result);

		$sql = 'SELECT * FROM ' . $table . '
			ORDER BY created_at DESC, submit_id DESC';
		$result = $db->sql_query_limit($sql, 25);

		while ($row = $db->sql_fetchrow($result))
		{
			$has_submission_logs = true;
			$template->assign_block_vars('sfs_submission_logs', array(
				'ID' => (int) $row['submit_id'],
				'TIME' => $user->format_date((int) $row['created_at']),
				'ADMIN' => (string) $row['admin_username'],
				'IP' => (string) $row['spammer_ip'],
				'USERNAME' => (string) $row['spammer_username'],
				'EMAIL' => (string) $row['spammer_email'],
				'SOURCE' => (string) $row['source'],
				'SOURCE_LOG_ID' => (int) $row['source_log_id'],
				'STATUS' => (string) $row['status'],
				'RESPONSE' => (string) $row['response_text'],
			));
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'S_HAS_SFS_SUBMISSION_LOGS' => $has_submission_logs,
			'TOTAL_SFS_SUBMISSION_LOGS' => $total_submission_logs,
		));
	}
	public function format_sfs_action_mode($mode, \phpbb\user $user)
	{
		switch ($mode)
		{
			case 'soft':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_SOFT');
			case 'log_only':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_LOG_ONLY');
			case 'disabled':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_DISABLED');
			case 'whitelist':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_WHITELIST');
			case 'block':
			default:
				return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_BLOCK');
		}
	}

	public function format_sfs_status($status, \phpbb\user $user)
	{
		switch ($status)
		{
			case 'sfs_disabled':
			case 'sfs_disabled_manual_check':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_DISABLED');
			case 'whitelisted':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_WHITELISTED');
			case 'partial_error':
				return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_ERROR');
			case 'checked':
			default:
				return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_CHECKED');
		}
	}

	public function export_sfs_logs_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix, \phpbb\request\request_interface $request)
	{
		$sfs_filter_action = $request->variable('sfs_filter_action', '');
		$sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');
		$sfs_filter_review = $request->variable('sfs_filter_review', '');
		$sfs_filter_query = trim($request->variable('sfs_filter_query', '', true));
		$sfs_filter_query = $this->settings_helper->truncate_for_storage($sfs_filter_query, 100);

		if (!in_array($sfs_filter_action, array('', 'block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
		{
			$sfs_filter_action = '';
		}

		if (!in_array($sfs_filter_blocked, array('', '1', '0'), true))
		{
			$sfs_filter_blocked = '';
		}

		if (!in_array($sfs_filter_review, array('', 'pending', 'reported', 'blocked', 'reported_blocked', 'allowed'), true))
		{
			$sfs_filter_review = '';
		}

		$filename = 'antispamguard_sfs_logs_' . gmdate('Ymd_His') . '.csv';

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Pragma: no-cache');
		header('Expires: 0');

		$output = fopen('php://output', 'w');

		// UTF-8 BOM for better spreadsheet compatibility.
		fwrite($output, "\xEF\xBB\xBF");

		fputcsv($output, array(
			'log_id',
			'created_at',
			'source',
			'ip',
			'username',
			'email',
			'listed_count',
			'strong_hit',
			'blocked',
			'action_mode',
			'matched',
			'review_status',
			'reviewed_at',
			'reviewed_by',
			'local_action',
			'details',
		));

		$where = array();

		if ($sfs_filter_blocked !== '')
		{
			$where[] = 'blocked = ' . (int) $sfs_filter_blocked;
		}

		if ($sfs_filter_action !== '')
		{
			$where[] = "action_mode = '" . $db->sql_escape($sfs_filter_action) . "'";
		}

		if ($sfs_filter_review !== '')
		{
			if ($sfs_filter_review === 'pending')
			{
				$where[] = "(review_status = '' OR review_status IS NULL)";
			}
			else
			{
				$where[] = "review_status = '" . $db->sql_escape($sfs_filter_review) . "'";
			}
		}

		if ($sfs_filter_query !== '')
		{
			$sfs_like = $db->sql_like_expression($db->get_any_char() . $db->sql_escape($sfs_filter_query) . $db->get_any_char());
			$where[] = '(user_ip ' . $sfs_like . ' OR user_email ' . $sfs_like . ' OR username ' . $sfs_like . ')';
		}

		$where_sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

		$sql = 'SELECT *
			FROM ' . $table_prefix . 'antispamguard_sfs_log' . $where_sql . '
			ORDER BY created_at DESC';
		$result = $db->sql_query_limit($sql, 1000);

		while ($row = $db->sql_fetchrow($result))
		{
			$details = json_decode($row['details_json'], true);
			$decision_meta = is_array($details) && isset($details['_decision']) && is_array($details['_decision']) ? $details['_decision'] : array();
			$action_mode = (isset($row['action_mode']) && (string) $row['action_mode'] !== '') ? (string) $row['action_mode'] : (isset($decision_meta['action_mode']) ? (string) $decision_meta['action_mode'] : 'block');
			$matched = !empty($decision_meta['matched']) ? 1 : 0;

			fputcsv($output, $this->settings_helper->sanitize_csv_row(array(
				(int) $row['log_id'],
				$user->format_date((int) $row['created_at']),
				$row['check_source'],
				$row['user_ip'],
				$row['username'],
				$row['user_email'],
				(int) $row['listed_count'],
				!empty($row['strong_hit']) ? 1 : 0,
				!empty($row['blocked']) ? 1 : 0,
				$action_mode,
				$matched,
				isset($row['review_status']) ? $row['review_status'] : '',
				!empty($row['reviewed_at']) ? $user->format_date((int) $row['reviewed_at']) : '',
				isset($row['reviewed_by']) ? (int) $row['reviewed_by'] : 0,
				isset($row['local_action']) ? $row['local_action'] : '',
				$row['details_json'],
			)));
		}

		$db->sql_freeresult($result);
		fclose($output);
		garbage_collection();
		exit_handler();
	}
}
