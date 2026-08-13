<?php
/**
 * AntiSpam Guard event listener.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\event;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use mundophpbb\antispamguard\service\sfs_decision;
use mundophpbb\antispamguard\service\ip_reputation;
use mundophpbb\antispamguard\service\ip_rate_limit;
use mundophpbb\antispamguard\service\form_guard;
use mundophpbb\antispamguard\service\ip_matcher;
use mundophpbb\antispamguard\service\registration_policy;
use mundophpbb\antispamguard\service\registration_audit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class listener implements EventSubscriberInterface
{
	protected $config;
	protected $request;
	protected $template;
	protected $user;
	protected $db;
	protected $table_prefix;
	protected $sfs_decision;
	protected $ip_reputation;
	protected $ip_rate_limit;
	protected $form_guard;
	protected $ip_matcher;
	protected $registration_audit;
	protected $registration_sfs_analyzed = false;
	protected $registration_submission_recorded = false;

	public function __construct(config $config, request_interface $request, template $template, user $user, driver_interface $db, $table_prefix, sfs_decision $sfs_decision, ip_reputation $ip_reputation, ip_rate_limit $ip_rate_limit, form_guard $form_guard, ip_matcher $ip_matcher, registration_audit $registration_audit)
	{
		$this->config = $config;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->sfs_decision = $sfs_decision;
		$this->ip_reputation = $ip_reputation;
		$this->ip_rate_limit = $ip_rate_limit;
		$this->form_guard = $form_guard;
		$this->ip_matcher = $ip_matcher;
		$this->registration_audit = $registration_audit;
	}

	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup_after'              => 'early_ucp_checks',
			'core.page_header_after'                => 'assign_template_vars',
			'core.ucp_register_data_after'          => 'validate_registration',
			'core.posting_modify_submission_errors' => 'validate_posting',
		);
	}


	public function early_ucp_checks($event)
	{
		$this->user->add_lang_ext('mundophpbb/antispamguard', 'common');

		if (empty($this->config['antispamguard_enabled']))
		{
			return;
		}

		$request_method = strtoupper($this->request->server('REQUEST_METHOD', 'GET'));
		$mode = $this->request->variable('mode', '');
		$i = $this->request->variable('i', '');
		$request_uri = (string) $this->request->server('REQUEST_URI', '');
		$script_name = (string) $this->request->server('SCRIPT_NAME', '');

		if ($request_method === 'GET' && $this->is_registration_request($mode, $request_uri, $script_name))
		{
			$this->registration_audit->record_page_view(
				(string) $this->user->ip,
				(string) $this->request->server('HTTP_USER_AGENT', '')
			);
			return;
		}

		if ($request_method !== 'POST')
		{
			return;
		}

		if (!empty($this->config['antispamguard_protect_contact']))
		{
			$is_contact = ($mode === 'contactadmin' || $mode === 'email' || strpos($request_uri, 'mode=contactadmin') !== false || strpos($request_uri, 'mode=email') !== false) && strpos($script_name, 'memberlist.php') !== false;
			if ($is_contact)
			{
				$this->validate_early_submission('contact');
			}
		}

		if (!empty($this->config['antispamguard_protect_pm']))
		{
			$is_pm = strpos($script_name, 'ucp.php') !== false && (strpos($i, 'pm') !== false || strpos($request_uri, 'i=pm') !== false) && ($mode === 'compose' || strpos($request_uri, 'mode=compose') !== false);
			if ($is_pm)
			{
				$this->validate_early_submission('pm');
			}
		}
	}

	protected function validate_early_submission($form_type)
	{
		if ($this->user_primary_group_is_bypassed())
		{
			return;
		}

		$reason = $this->get_submission_block_reason($form_type);
		if ($reason === '')
		{
			return;
		}

		if ($this->is_audit_only_reason($reason))
		{
			$this->write_log($reason, $form_type);
			return;
		}

		if (!empty($this->config['antispamguard_simulation_mode']))
		{
			$this->write_log($this->get_simulation_log_reason($reason), $form_type);
			return;
		}

		$this->write_log($reason, $form_type);
		\trigger_error($this->get_block_message($reason));
	}

	public function assign_template_vars($event)
	{
		if (empty($this->config['antispamguard_enabled']))
		{
			return;
		}

		$this->user->add_lang_ext('mundophpbb/antispamguard', 'common');

		$timestamp = time();
		$token = $this->form_guard->build_token($timestamp);

		$register_notice_text = $this->get_register_notice_text();
		$mode = $this->request->variable('mode', '');
		$i = $this->request->variable('i', '');
		$request_uri = (string) $this->request->server('REQUEST_URI', '');
		$script_name = (string) $this->request->server('SCRIPT_NAME', '');
		$is_contact = ($mode === 'contactadmin' || $mode === 'email' || strpos($request_uri, 'mode=contactadmin') !== false || strpos($request_uri, 'mode=email') !== false)
			&& strpos($script_name, 'memberlist.php') !== false;
		$is_pm = strpos($script_name, 'ucp.php') !== false
			&& (strpos($i, 'pm') !== false || strpos($request_uri, 'i=pm') !== false)
			&& ($mode === 'compose' || strpos($request_uri, 'mode=compose') !== false);

		$this->template->assign_vars(array(
			'ANTISPAMGUARD_ENABLED' => true,
			'ANTISPAMGUARD_REGISTER_NOTICE_ENABLED' => !empty($this->config['antispamguard_register_notice_enabled']),
			'ANTISPAMGUARD_REGISTER_NOTICE_TEXT' => $register_notice_text,
			'ANTISPAMGUARD_HP_NAME' => $this->form_guard->get_honeypot_name($timestamp),
			'ANTISPAMGUARD_HP_CLASS' => $this->form_guard->get_honeypot_class($timestamp),
			'ANTISPAMGUARD_HP_STYLE' => $this->form_guard->get_honeypot_style($timestamp),
			'ANTISPAMGUARD_TS'      => $timestamp . ':' . $token,
			'ANTISPAMGUARD_PROTECT_POSTS' => !empty($this->config['antispamguard_protect_posts']),
			'ANTISPAMGUARD_PROTECT_CONTACT' => !empty($this->config['antispamguard_protect_contact']),
			'ANTISPAMGUARD_PROTECT_PM' => !empty($this->config['antispamguard_protect_pm']),
			'ANTISPAMGUARD_IS_CONTACT' => $is_contact,
			'ANTISPAMGUARD_IS_PM' => $is_pm,
		));
	}

	protected function get_register_notice_text()
	{
		$notice_text = isset($this->config['antispamguard_register_notice_text']) ? trim((string) $this->config['antispamguard_register_notice_text']) : '';

		if ($notice_text === '')
		{
			$notice_text = (string) $this->user->lang('ANTISPAMGUARD_REGISTER_NOTICE_DEFAULT');
		}

		$notice_text = trim(strip_tags($notice_text));
		$notice_text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $notice_text);
		$notice_text = preg_replace('/\s+/u', ' ', $notice_text);

		return $this->truncate_for_storage($notice_text, 255);
	}

	public function validate_registration($event)
	{
		if (empty($this->config['antispamguard_enabled']))
		{
			return;
		}

		$this->validate_submission($event, 'register');
	}

	public function validate_posting($event)
	{
		if (empty($this->config['antispamguard_enabled']) || empty($this->config['antispamguard_protect_posts']))
		{
			return;
		}

		if ((!isset($this->config['antispamguard_posts_guests_only']) || !empty($this->config['antispamguard_posts_guests_only'])) && (int) $this->user->data['user_id'] !== ANONYMOUS)
		{
			return;
		}

		if ($this->user_primary_group_is_bypassed())
		{
			return;
		}

		$this->validate_submission($event, 'post');
	}

	protected function user_primary_group_is_bypassed()
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return false;
		}

		$raw_group_ids = isset($this->config['antispamguard_bypass_group_ids']) ? (string) $this->config['antispamguard_bypass_group_ids'] : '';
		if ($raw_group_ids === '')
		{
			return false;
		}

		$configured = array_filter(array_map('intval', explode(',', $raw_group_ids)));
		if (empty($configured))
		{
			return false;
		}

		if (in_array((int) $this->user->data['group_id'], $configured, true))
		{
			return true;
		}

		// A bypass group does not need to be the user's primary group.
		if (!defined('USER_GROUP_TABLE'))
		{
			return false;
		}

		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . (int) $this->user->data['user_id'] . '
				AND user_pending = 0
				AND ' . $this->db->sql_in_set('group_id', $configured);
		$result = $this->db->sql_query_limit($sql, 1);
		$matched = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $matched;
	}

	protected function validate_submission($event, $form_type)
	{
		$this->user->add_lang_ext('mundophpbb/antispamguard', 'common');

		$errors = $event['error'];

		// phpBB has already rejected this submission (form token, CAPTCHA,
		// username/e-mail validation, permissions, etc.).  Avoid expensive
		// reputation lookups and duplicate anti-spam errors for a request that
		// cannot create content or an account anyway.
		if (!empty($errors))
		{
			if ($form_type === 'register')
			{
				$reason = $this->get_phpbb_rejection_audit_reason();
				$this->record_registration_submission(true, false, $reason);
				$this->write_log('audit:' . $reason, $form_type, $this->get_registration_audit_window());
			}
			return;
		}

		$reason = $this->get_submission_block_reason($form_type);

		if ($form_type === 'register')
		{
			$this->record_registration_submission(false, $this->is_local_registration_rejection($reason), $reason !== '' ? $reason : 'accepted');
		}

		if ($reason !== '')
		{
			if ($this->is_audit_only_reason($reason))
			{
				$this->write_log($reason, $form_type);
			}
			else if (!empty($this->config['antispamguard_simulation_mode']))
			{
				$this->write_log($this->get_simulation_log_reason($reason), $form_type);
			}
			else
			{
				$this->write_log($reason, $form_type);
				$reasons = explode(',', $reason);
				foreach ($reasons as $single_reason)
				{
					$errors[] = $this->get_block_message($single_reason);
				}
			}
		}

		$event['error'] = $errors;
	}


	protected function get_submission_block_reason($form_type)
	{
		$ip = (string) $this->user->ip;
		$ip_whitelisted = $this->ip_whitelist_matches($ip);

		if ($ip_whitelisted && $this->get_ip_whitelist_mode() === 'total')
		{
			return '';
		}

		$reasons = array();
		$score_signals = array();
		$audit_reasons = array();

		if (!$ip_whitelisted && $this->ip_is_blacklisted())
		{
			$this->force_sfs_debug_trace(array('ip_blacklist'), $form_type);
			return 'ip_blacklist';
		}

		// Keep the legacy log-based limiter only as a compatibility fallback.
		// When the dedicated limiter is enabled, running both implementations
		// doubles queries and can produce contradictory decisions.
		if (!$ip_whitelisted && empty($this->config['antispamguard_ip_rate_limit_enabled']) && !$this->passes_ip_rate_limit())
		{
			$reasons[] = 'ip_rate_limit';
		}

		if (!$this->form_guard->passes_honeypot($this->request->variable('antispamguard_ts', ''), array()))
		{
			$reasons[] = 'honeypot';
		}

		$timestamp_reason = $this->form_guard->get_timestamp_block_reason(
			$this->request->variable('antispamguard_ts', '')
		);
		if ($timestamp_reason !== '')
		{
			$reasons[] = $timestamp_reason;
		}

		if (!$ip_whitelisted && !empty($this->config['antispamguard_ip_rate_limit_enabled']))
		{
			$rate_result = $this->ip_rate_limit->hit((string) $this->user->ip);

			if (!empty($rate_result['limited']))
			{
				if ($rate_result['action'] === 'block')
				{
					$reasons[] = 'ip_rate_limit';
				}
				else if ($rate_result['action'] === 'score')
				{
					$score_signals[] = 'ip_rate_limit';
				}
				else if ($rate_result['action'] === 'log_only')
				{
					$audit_reasons[] = 'ip_rate_limit';
				}
			}
		}

		if (!$ip_whitelisted && !empty($this->config['antispamguard_subnet_rate_limit_enabled']))
		{
			$subnet_result = $this->ip_rate_limit->hit_subnet((string) $this->user->ip);

			if (!empty($subnet_result['limited']))
			{
				if ($subnet_result['action'] === 'block')
				{
					$reasons[] = 'subnet_abuse';
				}
				else if ($subnet_result['action'] === 'score')
				{
					$score_signals[] = 'subnet_abuse';
				}
				else if ($subnet_result['action'] === 'log_only')
				{
					$audit_reasons[] = 'subnet_abuse';
				}
			}
		}

		if (!empty($this->config['antispamguard_content_filter_enabled']))
		{
			$content_reason = $this->detect_suspicious_content($form_type);
			if ($content_reason !== '')
			{
				$reasons[] = $content_reason;
			}
		}

		if ($this->has_random_gmail_pattern($form_type))
		{
			$reasons[] = 'random_gmail';
		}

		// Remote reputation is a last-mile signal.  Do not let a bot that has
		// already failed a decisive local check amplify outbound SFS traffic.
		if (!$ip_whitelisted && !$this->has_definitive_local_block($reasons, $form_type))
		{
			$sfs_result = $this->get_sfs_reputation_decision($form_type);

			if (!empty($sfs_result['block']))
			{
				$reasons[] = $this->sfs_has_identity_match($sfs_result) ? 'sfs_identity' : 'sfs_reputation';
			}
			else if (!empty($sfs_result['soft']) && !empty($sfs_result['matched']))
			{
				$score_signals[] = 'sfs_reputation';
			}
			else if (!empty($sfs_result['log_only']) && !empty($sfs_result['matched']))
			{
				$audit_reasons[] = $this->sfs_has_identity_match($sfs_result) ? 'sfs_identity' : 'sfs_reputation';
			}
		}
		else if (!$ip_whitelisted)
		{
			// Debug tracing is intentionally restricted by its own duration
			// and localhost settings. It runs only when a decisive local rule
			// skipped the regular SFS lookup, avoiding duplicate clean logs.
			$this->force_sfs_debug_trace($reasons, $form_type);
		}

		if (!$ip_whitelisted && !$this->has_definitive_local_block($reasons, $form_type) && !empty($this->config['antispamguard_ip_reputation_enabled']))
		{
			$ip_reputation = $this->ip_reputation->get((string) $this->user->ip);

			if (!empty($ip_reputation['blocked']))
			{
				$reasons[] = 'ip_reputation';
			}
		}

		$slow_spam_reason = ($ip_whitelisted || $this->has_definitive_local_block($reasons, $form_type)) ? '' : $this->check_slow_spam($form_type);

		if ($slow_spam_reason !== '')
		{
			$reasons[] = $slow_spam_reason;
		}

		$decision_signals = array_values(array_unique(array_merge($reasons, $score_signals)));
		$combined_decision = $this->has_definitive_local_block($reasons, $form_type)
			? array('score' => 0, 'action' => 'allow', 'reasons' => array())
			: $this->apply_combined_decision_engine($decision_signals);

		if (!empty($combined_decision['action']) && $combined_decision['action'] === 'block')
		{
			$reasons[] = 'combined_decision';
		}
		else if (!empty($combined_decision['action']) && $combined_decision['action'] === 'log')
		{
			$audit_reasons[] = 'combined_decision';
		}

		if (!$ip_whitelisted)
		{
			$this->apply_autoban($decision_signals);
		}

		$reasons = array_unique($reasons);

		if (!empty($reasons) && $this->should_audit_registration_without_blocking($form_type, $reasons))
		{
			$reasons[] = 'possible_false_positive';
			$final_reason = 'audit:' . implode(',', array_unique($reasons));
		}
		else if (empty($reasons) && !empty($audit_reasons))
		{
			$audit_reasons[] = 'possible_false_positive';
			$final_reason = 'audit:' . implode(',', array_unique($audit_reasons));
		}
		else
		{
			$final_reason = !empty($reasons) ? implode(',', $reasons) : '';
		}

		if ($final_reason !== '')
		{
			$reputation_events = $this->is_audit_only_reason($final_reason)
				? $score_signals
				: array_merge($reasons, $score_signals);

			foreach (array_unique($reputation_events) as $reason)
			{
				$reason = trim($reason);

				if ($reason !== '' && !in_array($reason, array('ip_reputation', 'combined_decision', 'possible_false_positive'), true))
				{
					$this->ip_reputation->add_event((string) $this->user->ip, $reason);
				}
			}
		}

		// The regular caller writes to antispamguard_log with the canonical schema.
		// Do not write an extra audit row here: older packages attempted to insert
		// columns named ip/user_id/action into phpbb_antispamguard_log, but the
		// phpBB extension schema uses user_ip and has no user_id/action columns.
		$this->record_antispam_alerts($final_reason);

		return $final_reason;
	}


	protected function is_audit_only_reason($reason)
	{
		return strpos((string) $reason, 'audit:') === 0;
	}

	protected function strip_audit_reason_prefix($reason)
	{
		$reason = (string) $reason;

		if ($this->is_audit_only_reason($reason))
		{
			return substr($reason, 6);
		}

		return $reason;
	}

	protected function should_audit_registration_without_blocking($form_type, array $reasons)
	{
		$policy = new registration_policy();
		$lenient = !isset($this->config['antispamguard_register_audit_soft_signals'])
			|| !empty($this->config['antispamguard_register_audit_soft_signals']);

		return $policy->should_audit_without_blocking(
			$form_type,
			$reasons,
			$this->registration_has_submitted_identity(),
			$lenient
		);
	}

	protected function registration_has_submitted_identity()
	{
		$username = trim((string) $this->request->variable('username', '', true));
		$email = trim((string) $this->request->variable('email', '', true));

		return $username !== '' && $email !== '';
	}


	protected function has_random_gmail_pattern($form_type)
	{
		$form_type = (string) $form_type;

		if ($form_type === 'contact')
		{
			if (empty($this->config['antispamguard_random_gmail_enabled']))
			{
				return false;
			}
		}
		else if ($form_type === 'register')
		{
			if (empty($this->config['antispamguard_random_gmail_register_enabled']))
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		$email = strtolower(trim((string) $this->request->variable('email', '', true)));

		if ($email === '')
		{
			return false;
		}

		return (bool) preg_match('/^[a-z0-9]{9}@gmail\.com$/', $email);
	}

	protected function sfs_reputation_is_blocked($form_type)
	{
		$decision = $this->get_sfs_reputation_decision($form_type);

		return !empty($decision['block']);
	}

	protected function get_sfs_reputation_decision($form_type)
	{
		if (empty($this->config['antispamguard_sfs_enabled']))
		{
			return array(
				'block' => false,
				'matched' => false,
				'soft' => false,
				'log_only' => false,
				'results' => array(),
			);
		}

		$ip = (string) $this->user->ip;
		$email = trim((string) $this->request->variable('email', '', true));
		$username = trim((string) $this->request->variable('username', '', true));

		// During registration phpBB's current user is still the guest account,
		// commonly named "Anonymous". Never use that account identity as the
		// SFS username/email for a registration attempt; it can cause a false
		// SFS hit unrelated to the submitted account.
		if ($form_type !== 'register')
		{
			if ($email === '' && !empty($this->user->data['user_email']) && !$this->current_user_is_anonymous())
			{
				$email = trim((string) $this->user->data['user_email']);
			}

			if ($username === '' && !empty($this->user->data['username']) && !$this->current_user_is_anonymous())
			{
				$username = trim((string) $this->user->data['username']);
			}
		}

		$submission_key = hash('sha256',
			(string) $this->request->variable('antispamguard_ts', '') . '|' .
			$ip . '|' . (string) $form_type
		);

		if ($form_type === 'register')
		{
			$this->registration_sfs_analyzed = true;
		}

		return $this->sfs_decision->should_block($ip, $email, $username, $form_type, false, $submission_key);
	}

	protected function is_registration_request($mode, $request_uri, $script_name)
	{
		return strpos((string) $script_name, 'ucp.php') !== false
			&& ((string) $mode === 'register' || strpos((string) $request_uri, 'mode=register') !== false);
	}

	protected function get_phpbb_rejection_audit_reason()
	{
		$reasons = array('phpbb_rejected');
		$raw_timestamp = $this->request->variable('antispamguard_ts', '');

		if (!$this->form_guard->passes_honeypot($raw_timestamp, array()))
		{
			$reasons[] = 'honeypot';
		}

		$timestamp_reason = $this->form_guard->get_timestamp_block_reason($raw_timestamp);
		if ($timestamp_reason !== '')
		{
			$reasons[] = $timestamp_reason;
		}

		return implode(',', array_unique($reasons));
	}

	protected function record_registration_submission($phpbb_rejected, $local_rejected, $reason)
	{
		if ($this->registration_submission_recorded)
		{
			return;
		}

		$this->registration_audit->record_submission(
			(string) $this->user->ip,
			(string) $this->request->server('HTTP_USER_AGENT', ''),
			array(
				'phpbb_rejected' => (bool) $phpbb_rejected,
				'local_rejected' => (bool) $local_rejected,
				'sfs_analyzed'   => (bool) $this->registration_sfs_analyzed,
				'reason'         => $this->strip_audit_reason_prefix($reason),
			)
		);
		$this->registration_submission_recorded = true;
	}

	protected function is_local_registration_rejection($reason)
	{
		if ($reason === '' || $this->is_audit_only_reason($reason) || !empty($this->config['antispamguard_simulation_mode']))
		{
			return false;
		}

		$parts = array_map('trim', explode(',', $this->strip_audit_reason_prefix($reason)));
		$has_combined_decision = in_array('combined_decision', $parts, true);
		$has_sfs_reason = in_array('sfs_reputation', $parts, true) || in_array('sfs_identity', $parts, true);

		foreach ($parts as $part)
		{
			if ($part !== '' && !in_array($part, array('sfs_reputation', 'sfs_identity', 'combined_decision', 'possible_false_positive'), true))
			{
				return true;
			}
		}

		// A combined-only block is produced by local score signals. When an
		// SFS rule caused the decision, its canonical SFS reason is preserved.
		return $has_combined_decision && !$has_sfs_reason;
	}

	protected function get_registration_audit_window()
	{
		return isset($this->config['antispamguard_registration_audit_window'])
			? max(60, min(3600, (int) $this->config['antispamguard_registration_audit_window']))
			: 300;
	}

	protected function has_definitive_local_block(array $reasons, $form_type = '')
	{
		$definitive = array(
			'ip_blacklist',
			'ip_rate_limit',
			'subnet_abuse',
			'honeypot',
			'timestamp',
			'content_filter',
			'too_many_urls',
			'random_gmail',
		);

		foreach ($definitive as $reason)
		{
			if (in_array($reason, $reasons, true))
			{
				return true;
			}
		}

		if (in_array('timestamp_too_fast', $reasons, true) || in_array('timestamp_expired', $reasons, true))
		{
			$lenient_registration = (string) $form_type === 'register'
				&& (!isset($this->config['antispamguard_register_audit_soft_signals']) || !empty($this->config['antispamguard_register_audit_soft_signals']));

			return !$lenient_registration;
		}

		return false;
	}

	protected function sfs_has_identity_match(array $decision)
	{
		if (isset($decision['hard_identity_match']))
		{
			return !empty($decision['hard_identity_match']);
		}

		$strong = isset($decision['strong_identifiers']) ? (array) $decision['strong_identifiers'] : array();

		return in_array('email', $strong, true)
			|| (in_array('username', $strong, true) && in_array('ip', $strong, true));
	}

	protected function record_antispam_alerts($reason)
	{
		global $phpbb_container;

		if ((string) $reason === '' || $this->is_audit_only_reason($reason) || !isset($phpbb_container) || !$phpbb_container->has('mundophpbb.antispamguard.alerts'))
		{
			return;
		}

		$important = array('combined_decision', 'slow_spam', 'ip_rate_limit', 'subnet_abuse', 'random_gmail', 'sfs_reputation', 'sfs_identity');

		$matched = false;
		foreach ($important as $item)
		{
			if (strpos((string) $reason, $item) !== false)
			{
				$matched = true;
				break;
			}
		}

		if (!$matched)
		{
			return;
		}

		$alerts = $phpbb_container->get('mundophpbb.antispamguard.alerts');

		$ip = !empty($this->user->ip) ? (string) $this->user->ip : '';
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : 0;
		$username = isset($this->user->data['username']) ? (string) $this->user->data['username'] : '';

		$severity = (strpos((string) $reason, 'combined_decision') !== false || strpos((string) $reason, 'sfs_reputation') !== false || strpos((string) $reason, 'sfs_identity') !== false || strpos((string) $reason, 'subnet_abuse') !== false) ? 'high' : 'medium';

		$alerts->add(
			'submission_risk',
			$severity,
			$ip,
			$user_id,
			$username,
			(string) $this->user->lang('ANTISPAMGUARD_ALERT_RISKY_SUBMISSION'),
			array('reason' => (string) $reason)
		);
	}

	protected function current_user_is_anonymous()
	{
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;

		return $user_id === ANONYMOUS;
	}

	protected function force_sfs_debug_trace(array $reasons, $form_type = 'submission')
	{
		if (empty($this->config['antispamguard_sfs_enabled']))
		{
			return;
		}

		if (empty($this->config['antispamguard_sfs_debug_log_all']))
		{
			return;
		}

		$debug_until = isset($this->config['antispamguard_sfs_debug_until']) ? (int) $this->config['antispamguard_sfs_debug_until'] : 0;
		if ($debug_until > 0 && time() > $debug_until)
		{
			return;
		}

		$ip = !empty($this->user->ip) ? (string) $this->user->ip : '';

		if (!empty($this->config['antispamguard_sfs_debug_localhost_only'])
			&& !in_array($ip, array('127.0.0.1', '::1', 'localhost'), true))
		{
			return;
		}

		if (in_array('sfs_reputation', $reasons, true) || in_array('sfs_identity', $reasons, true))
		{
			return;
		}

		// Prefer submitted form identity. The current phpBB user can still be
		// ANONYMOUS while a registration/contact submission is being validated.
		$email = trim((string) $this->request->variable('email', '', true));
		$username = trim((string) $this->request->variable('username', '', true));

		if (!$this->current_user_is_anonymous())
		{
			if ($email === '' && isset($this->user->data['user_email']))
			{
				$email = trim((string) $this->user->data['user_email']);
			}

			if ($username === '' && isset($this->user->data['username']))
			{
				$username = trim((string) $this->user->data['username']);
			}
		}

		$submission_key = hash('sha256',
			(string) $this->request->variable('antispamguard_ts', '') . '|' .
			$ip . '|' . (string) $form_type
		);

		// In debug mode, sfs_decision also logs clean and failed checks.
		$this->sfs_decision->should_block($ip, $email, $username, 'debug_' . (string) $form_type, false, $submission_key);
	}

	protected function restore_submission_audit_log($reason)
	{
		// Kept only for backward compatibility with earlier 3.3.x packages.
		// The actual block/simulation log is written by write_log(), which uses
		// the correct antispamguard_log columns: user_ip, username, email,
		// form_type, reason and user_agent.
		return;
	}

	protected function check_slow_spam($form_type = 'submission')
	{
		global $phpbb_container;

		if (!isset($phpbb_container) || !$phpbb_container->has('mundophpbb.antispamguard.activity_tracker'))
		{
			return '';
		}

		$tracker = $phpbb_container->get('mundophpbb.antispamguard.activity_tracker');

		if (!$tracker->is_enabled())
		{
			return '';
		}

		$ip = !empty($this->user->ip) ? (string) $this->user->ip : '';
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : 0;
		$action_type = trim((string) $form_type);
		if ($action_type === '')
		{
			$action_type = 'submission';
		}

		$tracker->log($ip, $user_id, $action_type);

		return $tracker->is_slow_spam($ip, $action_type) ? 'slow_spam' : '';
	}

	protected function apply_combined_decision_engine(array $reasons)
	{
		global $phpbb_container;

		if (!isset($phpbb_container) || !$phpbb_container->has('mundophpbb.antispamguard.decision_engine'))
		{
			return array('score' => 0, 'action' => 'allow', 'reasons' => array());
		}

		$decision_engine = $phpbb_container->get('mundophpbb.antispamguard.decision_engine');

		if (!$decision_engine->is_enabled())
		{
			return array('score' => 0, 'action' => 'allow', 'reasons' => array());
		}

		$signals = array(
			'honeypot' => in_array('honeypot', $reasons, true),
			'timestamp_too_fast' => in_array('timestamp_too_fast', $reasons, true) || in_array('timestamp', $reasons, true),
			'timestamp_expired' => in_array('timestamp_expired', $reasons, true),
			'rate_limit' => in_array('ip_rate_limit', $reasons, true),
			'subnet_abuse' => in_array('subnet_abuse', $reasons, true),
			'random_gmail' => in_array('random_gmail', $reasons, true),
			'slow_spam' => in_array('slow_spam', $reasons, true),
			'sfs' => in_array('sfs_reputation', $reasons, true) || in_array('sfs_identity', $reasons, true),
			'ip_reputation_score' => 0,
		);

		if (!empty($this->ip_reputation) && !empty($this->user->ip))
		{
			$rep = $this->ip_reputation->get((string) $this->user->ip);

			if (isset($rep['score']))
			{
				$signals['ip_reputation_score'] = (int) $rep['score'];
			}
		}

		return $decision_engine->evaluate($signals);
	}

	protected function get_ip_whitelist_mode()
	{
		$mode = isset($this->config['antispamguard_ip_whitelist_mode']) ? (string) $this->config['antispamguard_ip_whitelist_mode'] : 'partial';

		return ($mode === 'total') ? 'total' : 'partial';
	}

	protected function ip_whitelist_matches($ip)
	{
		$ip = trim((string) $ip);

		if ($ip === '')
		{
			return false;
		}

		$list = '';

		if (!empty($this->config['antispamguard_ip_whitelist']))
		{
			$list .= "
" . (string) $this->config['antispamguard_ip_whitelist'];
		}

		if (!empty($this->config['antispamguard_trusted_ip_whitelist']))
		{
			$list .= "
" . (string) $this->config['antispamguard_trusted_ip_whitelist'];
		}

		if (trim($list) === '')
		{
			return false;
		}

		return $this->ip_matcher->whitelist_match($ip, trim($list))['matched'];
	}

	protected function ip_is_whitelisted()
	{
		return $this->ip_whitelist_matches((string) $this->user->ip);
	}

	protected function ip_is_blacklisted()
	{
		return $this->ip_matches_list(isset($this->config['antispamguard_ip_blacklist']) ? (string) $this->config['antispamguard_ip_blacklist'] : '');
	}

	protected function ip_matches_list($raw_list)
	{
		return $this->ip_matcher->matches_list((string) $this->user->ip, $raw_list);
	}

	protected function get_simulation_log_reason($reason)
	{
		$reasons = array_filter(array_map('trim', explode(',', (string) $reason)));
		if (count($reasons) !== 1)
		{
			return 'simulation_multiple';
		}

		$single_reason = reset($reasons);
		switch ($single_reason)
		{
			case 'ip_blacklist':
				return 'simulation_ip_blacklist';
			case 'ip_rate_limit':
				return 'simulation_ip_rate_limit';
			case 'timestamp':
			case 'timestamp_too_fast':
			case 'timestamp_expired':
				return 'simulation_timestamp';
			case 'content_filter':
				return 'simulation_content_filter';
			case 'too_many_urls':
				return 'simulation_too_many_urls';
			case 'sfs_reputation':
			case 'sfs_identity':
				return 'simulation_sfs_reputation';
			case 'honeypot':
			default:
				return 'simulation_honeypot';
		}
	}

	protected function get_block_message($reason)
	{
		if (!empty($this->config['antispamguard_silent_mode']))
		{
			return $this->user->lang('ANTISPAMGUARD_BLOCKED_GENERIC');
		}

		switch ($reason)
		{
			case 'ip_blacklist':
				return $this->user->lang('ANTISPAMGUARD_BLOCKED_IP');
			case 'ip_rate_limit':
				return $this->user->lang('ANTISPAMGUARD_BLOCKED_RATE_LIMIT');
			case 'timestamp':
			case 'timestamp_too_fast':
			case 'timestamp_expired':
				return $this->user->lang('ANTISPAMGUARD_BLOCKED_TIME');
			case 'content_filter':
			case 'too_many_urls':
				return $this->user->lang('ANTISPAMGUARD_BLOCKED_CONTENT');
			case 'sfs_reputation':
			case 'sfs_identity':
				return $this->user->lang('ANTISPAMGUARD_BLOCKED_SFS');
			case 'honeypot':
			default:
				return $this->user->lang('ANTISPAMGUARD_BLOCKED');
		}
	}

	protected function passes_ip_rate_limit()
	{
		if (empty($this->config['antispamguard_rate_limit_enabled']))
		{
			return true;
		}

		$max_attempts = isset($this->config['antispamguard_rate_limit_max_attempts']) ? (int) $this->config['antispamguard_rate_limit_max_attempts'] : 0;
		$window_seconds = isset($this->config['antispamguard_rate_limit_window']) ? (int) $this->config['antispamguard_rate_limit_window'] : 0;

		if ($max_attempts <= 0 || $window_seconds <= 0)
		{
			return true;
		}

		$table = $this->table_prefix . 'antispamguard_log';
		$since = time() - $window_seconds;
		$ip = (string) $this->user->ip;

		$sql = 'SELECT COUNT(log_id) AS total_attempts
			FROM ' . $table . "
			WHERE user_ip = '" . $this->db->sql_escape($ip) . "'
				AND log_time >= " . (int) $since;
		$result = $this->db->sql_query($sql);
		$total_attempts = (int) $this->db->sql_fetchfield('total_attempts');
		$this->db->sql_freeresult($result);

		return $total_attempts < $max_attempts;
	}

	protected function detect_suspicious_content($form_type)
	{
		$content = $this->collect_submission_content($form_type);
		$normalized_content = strtolower($content);

		foreach ($this->get_blocked_keywords() as $keyword)
		{
			if ($keyword !== '' && strpos($normalized_content, strtolower($keyword)) !== false)
			{
				return 'content_filter';
			}
		}

		$max_urls = isset($this->config['antispamguard_max_urls']) ? (int) $this->config['antispamguard_max_urls'] : 0;
		if ($max_urls > 0 && $this->count_urls($content) > $max_urls)
		{
			return 'too_many_urls';
		}

		return '';
	}

	protected function collect_submission_content($form_type)
	{
		$fields = array('username', 'email');

		if ($form_type === 'post')
		{
			$fields = array_merge($fields, array('subject', 'message', 'poll_title'));
		}

		if ($form_type === 'contact')
		{
			$fields = array_merge($fields, array('subject', 'message', 'email', 'name', 'sender_name', 'sender_email'));
		}

		if ($form_type === 'pm')
		{
			$fields = array_merge($fields, array('subject', 'message', 'username_list', 'address_list'));
		}

		$content = array();
		foreach ($fields as $field)
		{
			$content[] = $this->request->variable($field, '', true);
		}

		return implode("\n", $content);
	}

	protected function get_blocked_keywords()
	{
		$raw_keywords = isset($this->config['antispamguard_blocked_keywords']) ? (string) $this->config['antispamguard_blocked_keywords'] : '';
		$lines = preg_split('/[\r\n,]+/', $raw_keywords);
		$keywords = array();

		foreach ($lines as $line)
		{
			$keyword = trim($line);
			if ($keyword !== '')
			{
				$keywords[] = $keyword;
			}
		}

		return $keywords;
	}

	protected function count_urls($content)
	{
		preg_match_all('#(?:https?://|www\.)\S+#i', $content, $matches);
		return count($matches[0]);
	}


	protected function write_log($reason, $form_type = 'register', $duplicate_window = 30)
	{
		$table = $this->table_prefix . 'antispamguard_log';
		$now = time();
		$audit_only = $this->is_audit_only_reason($reason);
		$clean_reason = $this->strip_audit_reason_prefix($reason);
		$log_action = $audit_only ? 'review' : (!empty($this->config['antispamguard_simulation_mode']) ? 'simulation' : 'blocked');
		$sql_ary = array(
			'log_time'   => $now,
			'user_ip'    => (string) $this->user->ip,
			'username'   => $this->truncate_for_storage($this->request->variable('username', '', true), 255),
			'email'      => $this->truncate_for_storage($this->request->variable('email', '', true), 255),
			'form_type'  => $this->truncate_for_storage($form_type, 30),
			'reason'     => $this->normalize_log_reason($clean_reason),
			'user_agent' => $this->truncate_for_storage($this->request->server('HTTP_USER_AGENT', ''), 255),
			'risk_score' => $this->calculate_log_score($clean_reason),
			'risk_level' => $this->get_log_risk_level($clean_reason),
			'action'     => $log_action,
			'matched_rules' => $this->normalize_log_reason($clean_reason),
		);

		// phpBB can execute more than one validation path for some submissions
		// (notably the contact form). Avoid storing the same block twice when
		// the same request reaches the logger again during the same pass.
		if ($this->recent_duplicate_log_exists($table, $sql_ary, $now, $duplicate_window))
		{
			return;
		}

		$sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}

	protected function recent_duplicate_log_exists($table, array $log_row, $now, $duplicate_window = 30)
	{
		$window_start = max(0, (int) $now - max(1, (int) $duplicate_window));

		// De-duplicate by request identity. Some phpBB validation paths can log
		// an early row before username/email are available, then another row for
		// the same registration with the submitted identity. Treat the sparse
		// row and the completed row as the same person and merge them.
		$identity_sql = array(
			"user_ip = '" . $this->db->sql_escape($log_row['user_ip']) . "'",
			"form_type = '" . $this->db->sql_escape($log_row['form_type']) . "'",
			"user_agent = '" . $this->db->sql_escape($log_row['user_agent']) . "'",
		);

		if ((string) $log_row['email'] !== '')
		{
			$identity_sql[] = "(email = '" . $this->db->sql_escape($log_row['email']) . "' OR email = '')";
		}

		if ((string) $log_row['username'] !== '')
		{
			$identity_sql[] = "(username = '" . $this->db->sql_escape($log_row['username']) . "' OR username = '')";
		}

		$sql = 'SELECT log_id, reason, username, email
			FROM ' . $table . "
			WHERE log_time >= " . (int) $window_start . "
				AND " . implode(' AND ', $identity_sql) . "
			ORDER BY log_time DESC, log_id DESC";
		$result = $this->db->sql_query_limit($sql, 10);

		$row = false;
		while ($candidate = $this->db->sql_fetchrow($result))
		{
			if ($this->is_same_logged_submission($candidate, $log_row))
			{
				$row = $candidate;
				break;
			}
		}
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return false;
		}

		$merged_reason = $this->merge_log_reasons($row['reason'], $log_row['reason']);
		$merged_username = ((string) $row['username'] !== '') ? (string) $row['username'] : (string) $log_row['username'];
		$merged_email = ((string) $row['email'] !== '') ? (string) $row['email'] : (string) $log_row['email'];

		if ($merged_reason === (string) $row['reason']
			&& $merged_username === (string) $row['username']
			&& $merged_email === (string) $row['email'])
		{
			return true;
		}

		$sql = 'UPDATE ' . $table . "
			SET reason = '" . $this->db->sql_escape($this->truncate_for_storage($merged_reason, 191)) . "',
				username = '" . $this->db->sql_escape($this->truncate_for_storage($merged_username, 255)) . "',
				email = '" . $this->db->sql_escape($this->truncate_for_storage($merged_email, 255)) . "',
				risk_score = " . (int) $this->calculate_log_score($merged_reason) . ",
				risk_level = '" . $this->db->sql_escape($this->get_log_risk_level($merged_reason)) . "',
				matched_rules = '" . $this->db->sql_escape($this->normalize_log_reason($merged_reason)) . "'
			WHERE log_id = " . (int) $row['log_id'];
		$this->db->sql_query($sql);

		return true;
	}

	protected function is_same_logged_submission(array $existing, array $incoming)
	{
		$existing_email = strtolower(trim((string) $existing['email']));
		$incoming_email = strtolower(trim((string) $incoming['email']));
		$existing_username = strtolower(trim((string) $existing['username']));
		$incoming_username = strtolower(trim((string) $incoming['username']));

		if ($existing_email !== '' && $incoming_email !== '' && $existing_email !== $incoming_email)
		{
			return false;
		}

		if ($existing_username !== '' && $incoming_username !== '' && $existing_username !== $incoming_username)
		{
			return false;
		}

		return true;
	}

	protected function merge_log_reasons($existing_reason, $new_reason)
	{
		$parts = array();

		foreach (array($existing_reason, $new_reason) as $reason)
		{
			foreach (explode(',', (string) $reason) as $part)
			{
				$part = trim($part);

				if ($part !== '' && !in_array($part, $parts, true))
				{
					$parts[] = $part;
				}
			}
		}

		return implode(',', $parts);
	}


	protected function calculate_log_score($reason)
	{
		$score = 0;
		$weights = array(
			'honeypot' => 100,
			'timestamp' => 30,
			'timestamp_too_fast' => 30,
			'timestamp_expired' => 15,
			'slow_spam' => 35,
			'ip_rate_limit' => 40,
			'subnet_abuse' => 45,
			'random_gmail' => 20,
			'sfs_reputation' => 50,
			'sfs_identity' => 80,
			'ip_reputation' => 40,
			'ip_blacklist' => 100,
			'content_filter' => 20,
			'too_many_urls' => 20,
			'combined_decision' => 0,
			'possible_false_positive' => 0,
		);

		foreach (explode(',', (string) $reason) as $part)
		{
			$part = trim($part);
			if (strpos($part, 'simulation_') === 0)
			{
				$part = substr($part, 11);
			}

			if (isset($weights[$part]))
			{
				$score += (int) $weights[$part];
			}
		}

		return min(255, max(0, (int) $score));
	}

	protected function get_log_risk_level($reason)
	{
		$score = $this->calculate_log_score($reason);

		if ($score >= 100 || strpos((string) $reason, 'sfs_reputation') !== false || strpos((string) $reason, 'sfs_identity') !== false || strpos((string) $reason, 'subnet_abuse') !== false)
		{
			return 'high';
		}

		if ($score >= 40)
		{
			return 'medium';
		}

		return 'low';
	}

	protected function normalize_log_reason($reason)
	{
		$reason = trim((string) $reason);

		if ($reason === '')
		{
			return '';
		}

		$parts = array();
		foreach (explode(',', $reason) as $part)
		{
			$part = trim($part);

			if ($part !== '' && !in_array($part, $parts, true))
			{
				$parts[] = $part;
			}
		}

		$reason = !empty($parts) ? implode(',', $parts) : $reason;

		return $this->truncate_for_storage($reason, 191);
	}

	protected function truncate_for_storage($value, $max_length)
	{
		$value = (string) $value;
		$max_length = (int) $max_length;

		if ($max_length <= 0)
		{
			return '';
		}

		if (function_exists('utf8_strlen') && function_exists('utf8_substr'))
		{
			return utf8_strlen($value) > $max_length ? utf8_substr($value, 0, $max_length) : $value;
		}

		if (function_exists('mb_strlen') && function_exists('mb_substr'))
		{
			return mb_strlen($value, 'UTF-8') > $max_length ? mb_substr($value, 0, $max_length, 'UTF-8') : $value;
		}

		return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
	}

	protected function apply_autoban(array $reasons)
	{
		if (empty($this->config['antispamguard_autoban_enabled']))
		{
			return;
		}

		if (empty($this->user->ip))
		{
			return;
		}

		$signals = array(
			'honeypot' => in_array('honeypot', $reasons, true),
			'timestamp_too_fast' => in_array('timestamp_too_fast', $reasons, true) || in_array('timestamp', $reasons, true),
			'timestamp_expired' => in_array('timestamp_expired', $reasons, true),
			'rate_limit' => in_array('ip_rate_limit', $reasons, true),
			'subnet_abuse' => in_array('subnet_abuse', $reasons, true),
			'random_gmail' => in_array('random_gmail', $reasons, true),
			'slow_spam' => in_array('slow_spam', $reasons, true),
			'sfs' => in_array('sfs_reputation', $reasons, true) || in_array('sfs_identity', $reasons, true),
			'ip_reputation_score' => 0,
		);

		if (!empty($this->ip_reputation))
		{
			$rep = $this->ip_reputation->get((string) $this->user->ip);
			if (isset($rep['score']))
			{
				$signals['ip_reputation_score'] = (int) $rep['score'];
			}
		}

		$decision_engine = new \mundophpbb\antispamguard\service\decision_engine($this->config);
		$result = $decision_engine->evaluate($signals);

		$threshold = isset($this->config['antispamguard_autoban_threshold']) ? (int) $this->config['antispamguard_autoban_threshold'] : 0;
		if ($threshold > 0 && isset($result['score']) && (int) $result['score'] >= $threshold)
		{
			$duration = isset($this->config['antispamguard_autoban_duration']) ? (int) $this->config['antispamguard_autoban_duration'] : 3600;
			$this->ban_ip((string) $this->user->ip, $duration);
		}
	}

	protected function ban_ip($ip, $duration)
	{
		if (!defined('BANLIST_TABLE'))
		{
			return;
		}

		$ip = trim((string) $ip);
		if ($ip === '')
		{
			return;
		}

		$duration = max(0, (int) $duration);
		$ban_end = $duration > 0 ? time() + $duration : 0;

		$sql = 'SELECT ban_id
			FROM ' . BANLIST_TABLE . "
			WHERE ban_ip = '" . $this->db->sql_escape($ip) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return;
		}

		$sql = 'INSERT INTO ' . BANLIST_TABLE . ' ' . $this->db->sql_build_array('INSERT', array(
			'ban_ip' => $ip,
			'ban_start' => time(),
			'ban_end' => $ban_end,
			'ban_exclude' => 0,
			'ban_reason' => 'Auto-ban AntiSpamGuard',
		));
		$this->db->sql_query($sql);
	}

}
