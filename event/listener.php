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

    public function __construct(config $config, request_interface $request, template $template, user $user, driver_interface $db, $table_prefix, sfs_decision $sfs_decision, ip_reputation $ip_reputation, ip_rate_limit $ip_rate_limit)
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
    }

    public static function getSubscribedEvents()
    {
        return array(
            'core.user_setup'                       => 'early_ucp_checks',
            'core.page_header_after'                => 'assign_template_vars',
            'core.ucp_register_data_before'         => 'validate_registration',
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

        if (strtoupper($this->request->server('REQUEST_METHOD', 'GET')) !== 'POST')
        {
            return;
        }

        $mode = $this->request->variable('mode', '');
        $i = $this->request->variable('i', '');
        $request_uri = (string) $this->request->server('REQUEST_URI', '');
        $script_name = (string) $this->request->server('SCRIPT_NAME', '');

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

        if (!empty($this->config['antispamguard_simulation_mode']))
        {
            $this->write_log($this->get_simulation_log_reason($reason), $form_type);
            return;
        }

        $this->write_log($reason, $form_type);
        \trigger_error($this->get_block_message($reason));
    }

    public function early_contact_check($event)
    {
        $this->user->add_lang_ext('mundophpbb/antispamguard', 'common');

        if (empty($this->config['antispamguard_enabled']) || empty($this->config['antispamguard_protect_contact']))
        {
            return;
        }

        if (strtoupper($this->request->server('REQUEST_METHOD', 'GET')) !== 'POST')
        {
            return;
        }

        $mode = $this->request->variable('mode', '');
        $request_uri = (string) $this->request->server('REQUEST_URI', '');
        $script_name = (string) $this->request->server('SCRIPT_NAME', '');

        $is_contact = ($mode === 'contactadmin' || $mode === 'email' || strpos($request_uri, 'mode=contactadmin') !== false || strpos($request_uri, 'mode=email') !== false)
            && strpos($script_name, 'memberlist.php') !== false;

        if (!$is_contact)
        {
            return;
        }

        $reason = $this->get_submission_block_reason('contact');
        if ($reason === '')
        {
            return;
        }

        if (!empty($this->config['antispamguard_simulation_mode']))
        {
            $this->write_log($this->get_simulation_log_reason($reason), 'contact');
            return;
        }

        $this->write_log($reason, 'contact');
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
        $token = $this->build_token($timestamp);

        $register_notice_text = $this->get_register_notice_text();

        $this->template->assign_vars(array(
            'ANTISPAMGUARD_ENABLED' => true,
            'ANTISPAMGUARD_REGISTER_NOTICE_ENABLED' => !empty($this->config['antispamguard_register_notice_enabled']),
            'ANTISPAMGUARD_REGISTER_NOTICE_TEXT' => $register_notice_text,
            'ANTISPAMGUARD_HP_NAME' => $this->get_honeypot_name($timestamp),
            'ANTISPAMGUARD_HP_CLASS' => $this->get_honeypot_class($timestamp),
            'ANTISPAMGUARD_HP_STYLE' => $this->get_honeypot_style($timestamp),
            'ANTISPAMGUARD_TS'      => $timestamp . ':' . $token,
            'ANTISPAMGUARD_PROTECT_CONTACT' => !empty($this->config['antispamguard_protect_contact']),
            'ANTISPAMGUARD_PROTECT_PM' => !empty($this->config['antispamguard_protect_pm']),
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

        return in_array((int) $this->user->data['group_id'], $configured, true);
    }

    protected function validate_submission($event, $form_type)
    {
        $this->user->add_lang_ext('mundophpbb/antispamguard', 'common');

        $errors = $event['error'];
        $reason = $this->get_submission_block_reason($form_type);

        if ($reason !== '')
        {
            if (!empty($this->config['antispamguard_simulation_mode']))
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
        
        if ($this->ip_whitelist_matches((string) $this->user->ip) && $this->get_ip_whitelist_mode() === 'total')
        {
            return '';
        }

$reasons = array();

        if ($this->ip_is_whitelisted())
        {
            return '';
        }

        if ($this->ip_is_blacklisted())
        {
            return 'ip_blacklist';
        }

        if (!$this->passes_ip_rate_limit())
        {
            $reasons[] = 'ip_rate_limit';
        }

        if (!$this->passes_honeypot())
        {
            $reasons[] = 'honeypot';
        }

        if (!$this->passes_timestamp())
        {
            $reasons[] = 'timestamp';
        }

        if (!empty($this->config['antispamguard_ip_rate_limit_enabled']))
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
                    $this->ip_reputation->add_event((string) $this->user->ip, 'ip_rate_limit');
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

        if ($this->sfs_reputation_is_blocked($form_type))
        {
            $reasons[] = 'sfs_reputation';
        }

        if (!empty($reasons))
        {
            foreach ($reasons as $reason)
            {
                $this->ip_reputation->add_event((string) $this->user->ip, $reason);
            }
        }

        if (!empty($this->config['antispamguard_ip_reputation_enabled']))
        {
            $ip_reputation = $this->ip_reputation->get((string) $this->user->ip);

            if (!empty($ip_reputation['blocked']))
            {
                $reasons[] = 'ip_reputation';
            }
        }

                $this->force_sfs_debug_trace($reasons);

$slow_spam_reason = $this->check_slow_spam();

        if ($slow_spam_reason !== '')
        {
            $reasons[] = $slow_spam_reason;
        }

        $combined_reason = $this->apply_combined_decision_engine($reasons);

        if ($combined_reason !== '')
        {
            $reasons[] = $combined_reason;
        }

        $this->apply_shadowban($reasons);
        $this->apply_autoban($reasons);

        $final_reason = !empty($reasons) ? implode(',', array_unique($reasons)) : '';

        // The regular caller writes to antispamguard_log with the canonical schema.
        // Do not write an extra audit row here: older packages attempted to insert
        // columns named ip/user_id/action into phpbb_antispamguard_log, but the
        // phpBB extension schema uses user_ip and has no user_id/action columns.
        $this->record_antispam_alerts($final_reason);

        return $final_reason;
    }

    protected function sfs_reputation_is_blocked($form_type)
    {
        if (empty($this->config['antispamguard_sfs_enabled']))
        {
            return false;
        }

        $ip = (string) $this->user->ip;
        $email = $this->request->variable('email', '', true);
        $username = $this->request->variable('username', '', true);

        if ($email === '' && !empty($this->user->data['user_email']))
        {
            $email = (string) $this->user->data['user_email'];
        }

        if ($username === '' && !empty($this->user->data['username']))
        {
            $username = (string) $this->user->data['username'];
        }

        $decision = $this->sfs_decision->should_block($ip, $email, $username, $form_type);

        return !empty($decision['block']);
    }

    protected function record_antispam_alerts($reason)
    {
        global $phpbb_container;

        if ((string) $reason === '' || !isset($phpbb_container) || !$phpbb_container->has('mundophpbb.antispamguard.alerts'))
        {
            return;
        }

        $important = array('combined_decision', 'slow_spam', 'ip_rate_limit', 'sfs_reputation');

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

        $severity = (strpos((string) $reason, 'combined_decision') !== false || strpos((string) $reason, 'sfs_reputation') !== false) ? 'high' : 'medium';

        $alerts->add(
            'submission_risk',
            $severity,
            $ip,
            $user_id,
            $username,
            'AntiSpam Guard detected a risky submission.',
            array('reason' => (string) $reason)
        );
    }

    protected function force_sfs_debug_trace(array $reasons)
    {
        if (empty($this->config['antispamguard_sfs_enabled']))
        {
            return;
        }

        if (empty($this->config['antispamguard_sfs_debug_log_all']))
        {
            return;
        }

        $ip = !empty($this->user->ip) ? (string) $this->user->ip : '';

        if (!empty($this->config['antispamguard_sfs_debug_localhost_only'])
            && !in_array($ip, array('127.0.0.1', '::1', 'localhost'), true))
        {
            return;
        }

        if (in_array('sfs_reputation', $reasons, true))
        {
            return;
        }

        $email = '';
        $username = '';

        if (isset($this->user->data['user_email']))
        {
            $email = (string) $this->user->data['user_email'];
        }

        if (isset($this->user->data['username']))
        {
            $username = (string) $this->user->data['username'];
        }

        // Registration forms may not have a logged-in user's email/username yet.
        if ($email === '')
        {
            $email = $this->request->variable('email', '');
        }

        if ($username === '')
        {
            $username = $this->request->variable('username', '', true);
        }

        // Force a trace decision. In debug mode, sfs_decision logs checked_not_listed too.
        $this->sfs_decision->should_block($ip, $email, $username, 'debug_localhost');
    }

    protected function restore_submission_audit_log($reason)
    {
        // Kept only for backward compatibility with earlier 3.3.x packages.
        // The actual block/simulation log is written by write_log(), which uses
        // the correct antispamguard_log columns: user_ip, username, email,
        // form_type, reason and user_agent.
        return;
    }

    protected function check_slow_spam()
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
        $action_type = 'submission';

        $tracker->log($ip, $user_id, $action_type);

        return $tracker->is_slow_spam($ip, $action_type) ? 'slow_spam' : '';
    }

    protected function apply_combined_decision_engine(array $reasons)
    {
        global $phpbb_container;

        if (!isset($phpbb_container) || !$phpbb_container->has('mundophpbb.antispamguard.decision_engine'))
        {
            return '';
        }

        $decision_engine = $phpbb_container->get('mundophpbb.antispamguard.decision_engine');

        if (!$decision_engine->is_enabled())
        {
            return '';
        }

        $signals = array(
            'honeypot' => in_array('honeypot', $reasons, true),
            'timestamp_too_fast' => in_array('timestamp_too_fast', $reasons, true) || in_array('timestamp', $reasons, true),
            'timestamp_expired' => in_array('timestamp_expired', $reasons, true),
            'rate_limit' => in_array('ip_rate_limit', $reasons, true),
            'slow_spam' => in_array('slow_spam', $reasons, true),
            'sfs' => in_array('sfs_reputation', $reasons, true),
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

        $decision = $decision_engine->evaluate($signals);

        if (!empty($decision['action']) && $decision['action'] === 'block')
        {
            return 'combined_decision';
        }

        return '';
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

        $entries = preg_split('/\r\n|\r|\n/', $list);

        foreach ($entries as $entry)
        {
            $entry = trim($entry);

            if ($entry === '' || strpos($entry, '#') === 0)
            {
                continue;
            }

            if ($this->ip_entry_matches($ip, $entry))
            {
                return true;
            }
        }

        return false;
    }

    protected function ip_entry_matches($ip, $entry)
    {
        if ($entry === $ip)
        {
            return true;
        }

        if (strpos($entry, '*') !== false)
        {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($entry, '/')) . '$/i';

            return (bool) preg_match($pattern, $ip);
        }

        if (strpos($entry, '/') !== false)
        {
            return $this->ip_cidr_matches($ip, $entry);
        }

        return false;
    }

    protected function ip_cidr_matches($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2)
        {
            return false;
        }

        $subnet = trim($parts[0]);
        $bits = (int) trim($parts[1]);

        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin))
        {
            return false;
        }

        $length = strlen($ip_bin);
        $max_bits = $length * 8;

        if ($bits < 0 || $bits > $max_bits)
        {
            return false;
        }

        $full_bytes = (int) floor($bits / 8);
        $remaining_bits = $bits % 8;

        if ($full_bytes > 0 && substr($ip_bin, 0, $full_bytes) !== substr($subnet_bin, 0, $full_bytes))
        {
            return false;
        }

        if ($remaining_bits === 0)
        {
            return true;
        }

        $mask = (0xff << (8 - $remaining_bits)) & 0xff;

        return ((ord($ip_bin[$full_bytes]) & $mask) === (ord($subnet_bin[$full_bytes]) & $mask));
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
        $current_ip = (string) $this->user->ip;
        if ($current_ip === '' || trim($raw_list) === '')
        {
            return false;
        }

        $items = preg_split('/[\r\n,]+/', $raw_list);
        foreach ($items as $item)
        {
            $item = trim($item);
            if ($item === '')
            {
                continue;
            }

            if (strpos($item, '/') !== false)
            {
                if ($this->ip_in_cidr($current_ip, $item))
                {
                    return true;
                }
            }
            else if (strcasecmp($current_ip, $item) === 0)
            {
                return true;
            }
        }

        return false;
    }

    protected function ip_in_cidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false)
        {
            return false;
        }

        list($subnet, $prefix) = explode('/', $cidr, 2);
        $subnet = trim($subnet);
        $prefix = (int) trim($prefix);

        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin))
        {
            return false;
        }

        $bits = strlen($ip_bin) * 8;
        if ($prefix < 0 || $prefix > $bits)
        {
            return false;
        }

        $bytes = (int) floor($prefix / 8);
        $remainder = $prefix % 8;

        if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes))
        {
            return false;
        }

        if ($remainder === 0)
        {
            return true;
        }

        $mask = chr((0xff << (8 - $remainder)) & 0xff);
        return ($ip_bin[$bytes] & $mask) === ($subnet_bin[$bytes] & $mask);
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
            return 'simulation_timestamp';
        case 'content_filter':
            return 'simulation_content_filter';
        case 'too_many_urls':
            return 'simulation_too_many_urls';
        case 'sfs_reputation':
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
                return $this->user->lang('ANTISPAMGUARD_BLOCKED_TIME');
            case 'content_filter':
            case 'too_many_urls':
                return $this->user->lang('ANTISPAMGUARD_BLOCKED_CONTENT');
            case 'sfs_reputation':
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


    protected function passes_honeypot()
    {
        $timestamp = $this->get_timestamp_from_request();

        if ($timestamp <= 0)
        {
            return false;
        }

        $field_name = $this->get_honeypot_name($timestamp);
        $value = $this->request->variable($field_name, '', true);

        return trim($value) === '';
    }

    protected function get_timestamp_block_reason()
    {
        $timestamp = $this->get_timestamp_from_request();
        $token = $this->get_token_from_request();

        if ($timestamp <= 0 || !hash_equals($this->build_token($timestamp), $token))
        {
            return 'timestamp';
        }

        $age = time() - $timestamp;
        $min_seconds = isset($this->config['antispamguard_min_seconds']) ? (int) $this->config['antispamguard_min_seconds'] : 3;

        if ($age < $min_seconds)
        {
            return 'timestamp_too_fast';
        }

        $max_age = isset($this->config['antispamguard_max_form_age']) ? (int) $this->config['antispamguard_max_form_age'] : 3600;

        if ($max_age > 0 && $age > $max_age)
        {
            return 'timestamp_expired';
        }

        return '';
    }

    protected function passes_timestamp()
    {
        $timestamp = $this->get_timestamp_from_request();
        $token = $this->get_token_from_request();

        if ($timestamp <= 0 || !hash_equals($this->build_token($timestamp), $token))
        {
            return false;
        }

        $elapsed = time() - $timestamp;
        $min_seconds = max(0, (int) $this->config['antispamguard_min_seconds']);
        $max_seconds = max($min_seconds + 1, (int) $this->config['antispamguard_max_seconds']);

        return $elapsed >= $min_seconds && $elapsed <= $max_seconds;
    }

    protected function write_log($reason, $form_type = 'register')
    {
        $table = $this->table_prefix . 'antispamguard_log';
        $sql_ary = array(
            'log_time'   => time(),
            'user_ip'    => (string) $this->user->ip,
            'username'   => $this->truncate_for_storage($this->request->variable('username', '', true), 255),
            'email'      => $this->truncate_for_storage($this->request->variable('email', '', true), 255),
            'form_type'  => $this->truncate_for_storage($form_type, 30),
            'reason'     => $this->normalize_log_reason($reason),
            'user_agent' => $this->truncate_for_storage($this->request->server('HTTP_USER_AGENT', ''), 255),
        );

        $sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
        $this->db->sql_query($sql);
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

    protected function get_timestamp_from_request()
    {
        $raw = $this->request->variable('antispamguard_ts', '');

        if (strpos($raw, ':') === false)
        {
            return 0;
        }

        list($timestamp, $token) = explode(':', $raw, 2);

        return (int) $timestamp;
    }

    protected function get_token_from_request()
    {
        $raw = $this->request->variable('antispamguard_ts', '');

        if (strpos($raw, ':') === false)
        {
            return '';
        }

        list($timestamp, $token) = explode(':', $raw, 2);

        return (string) $token;
    }

    protected function build_token($timestamp)
    {
        $secret = isset($this->config['cookie_name']) ? $this->config['cookie_name'] : 'phpbb';
        $secret .= isset($this->config['cookie_salt']) ? $this->config['cookie_salt'] : '';

        return hash_hmac('sha256', (string) $timestamp, $secret);
    }

    protected function get_honeypot_class($timestamp = 0)
    {
        if (empty($this->config['antispamguard_hp_camouflage_enabled']) || (int) $timestamp <= 0)
        {
            return 'antispamguard-hp';
        }

        return 'asg-field-' . substr($this->build_token((int) $timestamp), 12, 10);
    }

    protected function get_honeypot_style($timestamp = 0)
    {
        if (empty($this->config['antispamguard_hp_camouflage_enabled']))
        {
            return 'display:none;';
        }

        return 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;';
    }

    protected function get_honeypot_name($timestamp = 0)
    {
        if (!empty($this->config['antispamguard_hp_dynamic_enabled']) && (int) $timestamp > 0)
        {
            $prefix = isset($this->config['antispamguard_hp_dynamic_prefix']) ? trim((string) $this->config['antispamguard_hp_dynamic_prefix']) : 'asg_hp';

            if ($prefix === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,20}$/', $prefix))
            {
                $prefix = 'asg_hp';
            }

            return $prefix . '_' . substr($this->build_token((int) $timestamp), 0, 12);
        }

        $field_name = isset($this->config['antispamguard_hp_name']) ? trim($this->config['antispamguard_hp_name']) : '';

        if ($field_name === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $field_name))
        {
            return 'homepage';
        }

        return $field_name;
    }
    protected function apply_shadowban(array $reasons)
    {
        if (empty($this->config['antispamguard_shadowban_enabled']))
        {
            return;
        }

        $signals = array(
            'honeypot' => in_array('honeypot', $reasons, true),
            'timestamp_too_fast' => in_array('timestamp_too_fast', $reasons, true) || in_array('timestamp', $reasons, true),
            'timestamp_expired' => in_array('timestamp_expired', $reasons, true),
            'rate_limit' => in_array('ip_rate_limit', $reasons, true),
            'slow_spam' => in_array('slow_spam', $reasons, true),
            'sfs' => in_array('sfs_reputation', $reasons, true),
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

        $decision_engine = new \mundophpbb\antispamguard\service\decision_engine($this->config);
        $result = $decision_engine->evaluate($signals);

        $threshold = isset($this->config['antispamguard_shadowban_threshold']) ? (int) $this->config['antispamguard_shadowban_threshold'] : 0;
        if ($threshold > 0 && isset($result['score']) && (int) $result['score'] >= $threshold && !defined('ANTISPAM_SHADOWBAN'))
        {
            define('ANTISPAM_SHADOWBAN', true);
        }
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
            'slow_spam' => in_array('slow_spam', $reasons, true),
            'sfs' => in_array('sfs_reputation', $reasons, true),
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
