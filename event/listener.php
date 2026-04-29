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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

if (!defined('ANONYMOUS'))
{
    define('ANONYMOUS', 1);
}

class listener implements EventSubscriberInterface
{
    protected $config;
    protected $request;
    protected $template;
    protected $user;
    protected $db;
    protected $table_prefix;

    public function __construct(config $config, request_interface $request, template $template, user $user, driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->table_prefix = $table_prefix;
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

        $this->template->assign_vars(array(
            'ANTISPAMGUARD_ENABLED' => true,
            'ANTISPAMGUARD_HP_NAME' => $this->get_honeypot_name(),
            'ANTISPAMGUARD_TS'      => $timestamp . ':' . $token,
            'ANTISPAMGUARD_PROTECT_CONTACT' => !empty($this->config['antispamguard_protect_contact']),
            'ANTISPAMGUARD_PROTECT_PM' => !empty($this->config['antispamguard_protect_pm']),
        ));
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

        if (!empty($this->config['antispamguard_content_filter_enabled']))
        {
            $content_reason = $this->detect_suspicious_content($form_type);
            if ($content_reason !== '')
            {
                $reasons[] = $content_reason;
            }
        }

        return !empty($reasons) ? implode(',', $reasons) : '';
    }

    protected function ip_is_whitelisted()
    {
        return $this->ip_matches_list(isset($this->config['antispamguard_ip_whitelist']) ? (string) $this->config['antispamguard_ip_whitelist'] : '');
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
        $field_name = $this->get_honeypot_name();
        $value = $this->request->variable($field_name, '', true);

        return trim($value) === '';
    }

    protected function passes_timestamp()
    {
        $raw = $this->request->variable('antispamguard_ts', '');

        if (strpos($raw, ':') === false)
        {
            return false;
        }

        list($timestamp, $token) = explode(':', $raw, 2);
        $timestamp = (int) $timestamp;

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
            'username'   => $this->request->variable('username', '', true),
            'email'      => $this->request->variable('email', '', true),
            'form_type'  => $form_type,
            'reason'     => $reason,
            'user_agent' => substr($this->request->server('HTTP_USER_AGENT', ''), 0, 255),
        );

        $sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
        $this->db->sql_query($sql);
    }

    protected function build_token($timestamp)
    {
        $secret = isset($this->config['cookie_name']) ? $this->config['cookie_name'] : 'phpbb';
        $secret .= isset($this->config['cookie_salt']) ? $this->config['cookie_salt'] : '';

        return hash_hmac('sha256', (string) $timestamp, $secret);
    }

    protected function get_honeypot_name()
    {
        $field_name = isset($this->config['antispamguard_hp_name']) ? trim($this->config['antispamguard_hp_name']) : '';

        if ($field_name === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $field_name))
        {
            return 'homepage';
        }

        return $field_name;
    }
}
