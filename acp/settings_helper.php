<?php
/**
 * AntiSpam Guard — ACP settings sanitization and import/export.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

use mundophpbb\antispamguard\service\ip_matcher;

class settings_helper
{
    /** @var ip_matcher */
    protected $ip_matcher;

    public function __construct(ip_matcher $ip_matcher = null)
    {
        $this->ip_matcher = $ip_matcher ?: new ip_matcher();
    }

    public function normalize_blocked_keywords($raw_keywords)
    {
        $items = preg_split('/[\r\n,]+/', (string) $raw_keywords);
        $keywords = array();

        foreach ($items as $item)
        {
            $keyword = trim($item);

            if ($keyword !== '')
            {
                $keywords[$keyword] = $keyword;
            }
        }

        ksort($keywords);

        return implode("\n", $keywords);
    }

    public function normalize_ip_list($raw_ips)
    {
        return $this->ip_matcher->normalize_list($raw_ips);
    }

    public function normalize_group_ids($raw_group_ids)
    {
        $items = preg_split('/[^0-9]+/', (string) $raw_group_ids);
        $group_ids = array();

        foreach ($items as $item)
        {
            $group_id = (int) $item;

            if ($group_id > 0)
            {
                $group_ids[$group_id] = $group_id;
            }
        }

        sort($group_ids);

        return implode(',', $group_ids);
    }

    public function sanitize_secret($value, $max_length)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\x00-\x20\x7F]/', '', $value);

        return $this->truncate_for_storage($value, (int) $max_length);
    }

    public function mask_secret($value)
    {
        $value = trim((string) $value);
        $length = strlen($value);

        if ($length === 0)
        {
            return '';
        }

        if ($length <= 4)
        {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)) . substr($value, -4);
    }

    public function truncate_for_storage($value, $max_length)
    {
        $value = (string) $value;
        $max_length = (int) $max_length;

        if ($max_length <= 0)
        {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr'))
        {
            if (mb_strlen($value, 'UTF-8') > $max_length)
            {
                return mb_substr($value, 0, $max_length, 'UTF-8');
            }

            return $value;
        }

        return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
    }

    public function sanitize_register_notice_text($value, $default = '')
    {
        $notice_text = trim((string) $value);

        if ($notice_text === '')
        {
            $notice_text = trim((string) $default);
        }

        $notice_text = trim(strip_tags($notice_text));
        $notice_text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $notice_text);
        $notice_text = preg_replace('/\s+/u', ' ', $notice_text);

        return $this->truncate_for_storage($notice_text, 255);
    }

    public function get_settings_keys()
    {
        return array(
            'antispamguard_enabled',
            'antispamguard_register_notice_enabled',
            'antispamguard_register_notice_text',
            'antispamguard_register_audit_soft_signals',
            'antispamguard_hp_name',
            'antispamguard_protect_posts',
            'antispamguard_protect_contact',
            'antispamguard_protect_pm',
            'antispamguard_posts_guests_only',
            'antispamguard_bypass_group_ids',
            'antispamguard_content_filter_enabled',
            'antispamguard_blocked_keywords',
            'antispamguard_max_urls',
            'antispamguard_ip_whitelist',
            'antispamguard_ip_blacklist',
            'antispamguard_rate_limit_enabled',
            'antispamguard_rate_limit_max_attempts',
            'antispamguard_rate_limit_window',
            'antispamguard_log_retention_enabled',
            'antispamguard_log_retention_days',
            'antispamguard_registration_audit_enabled',
            'antispamguard_registration_track_page_views',
            'antispamguard_registration_audit_window',
            'antispamguard_registration_audit_retention_days',
            'antispamguard_silent_mode',
            'antispamguard_simulation_mode',
            'antispamguard_min_seconds',
            'antispamguard_max_seconds',
        );
    }

    public function get_extension_version($config = null, $composer_dir = null)
    {
        $composer_file = ($composer_dir ?: dirname(__DIR__)) . '/composer.json';

        if (is_file($composer_file) && is_readable($composer_file))
        {
            $composer = json_decode((string) file_get_contents($composer_file), true);

            if (is_array($composer) && !empty($composer['version']))
            {
                return (string) $composer['version'];
            }
        }

        if ($config !== null && isset($config['antispamguard_version']) && (string) $config['antispamguard_version'] !== '')
        {
            return (string) $config['antispamguard_version'];
        }

        return 'unknown';
    }

    /**
     * @param \phpbb\config\config $config
     */
    public function import_settings_json($config, $raw_settings)
    {
        $raw_settings = trim((string) $raw_settings);

        if ($raw_settings === '')
        {
            return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_EMPTY', 'count' => 0);
        }

        $data = json_decode($raw_settings, true);

        if (!is_array($data) || !isset($data['settings']) || !is_array($data['settings']))
        {
            return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_INVALID', 'count' => 0);
        }

        $allowed = array_flip($this->get_settings_keys());
        $imported = 0;

        foreach ($data['settings'] as $key => $value)
        {
            if (!isset($allowed[$key]))
            {
                continue;
            }

            $value = is_array($value) ? '' : (string) $value;

            switch ($key)
            {
                case 'antispamguard_hp_name':
                    $value = trim($value);

                    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $value))
                    {
                        $value = 'homepage';
                    }
                break;

                case 'antispamguard_bypass_group_ids':
                    $value = $this->normalize_group_ids($value);
                break;

                case 'antispamguard_register_notice_text':
                    $value = $this->sanitize_register_notice_text($value);
                break;

                case 'antispamguard_blocked_keywords':
                    $value = $this->normalize_blocked_keywords($value);
                break;

                case 'antispamguard_ip_whitelist':
                case 'antispamguard_ip_blacklist':
                    $value = $this->normalize_ip_list($value);
                break;

                case 'antispamguard_max_urls':
                    $value = max(0, (int) $value);
                break;

                case 'antispamguard_rate_limit_max_attempts':
                    $value = max(1, (int) $value);
                break;

                case 'antispamguard_rate_limit_window':
                    $value = max(60, (int) $value);
                break;

                case 'antispamguard_log_retention_days':
                    $value = max(1, (int) $value);
                break;

                case 'antispamguard_registration_audit_window':
                    $value = max(60, min(3600, (int) $value));
                break;

                case 'antispamguard_registration_audit_retention_days':
                    $value = max(1, min(365, (int) $value));
                break;

                case 'antispamguard_min_seconds':
                    $value = max(0, (int) $value);
                break;

                case 'antispamguard_max_seconds':
                    $value = max(10, (int) $value);
                break;

                case 'antispamguard_enabled':
                case 'antispamguard_register_notice_enabled':
                case 'antispamguard_register_audit_soft_signals':
                case 'antispamguard_protect_posts':
                case 'antispamguard_protect_contact':
                case 'antispamguard_protect_pm':
                case 'antispamguard_posts_guests_only':
                case 'antispamguard_content_filter_enabled':
                case 'antispamguard_rate_limit_enabled':
                case 'antispamguard_log_retention_enabled':
                case 'antispamguard_silent_mode':
                case 'antispamguard_simulation_mode':
                    $value = !empty($value) ? 1 : 0;
                break;
            }

            $config->set($key, $value);
            $imported++;
        }

        return array('success' => true, 'message' => '', 'count' => $imported);
    }

    /**
     * @param \phpbb\config\config $config
     */
    public function export_settings_payload($config)
    {
        $data = array(
            'extension' => 'mundophpbb/antispamguard',
            'version' => $this->get_extension_version($config),
            'exported_at' => gmdate('c'),
            'settings' => array(),
        );

        foreach ($this->get_settings_keys() as $key)
        {
            if (isset($config[$key]))
            {
                $data['settings'][$key] = (string) $config[$key];
            }
        }

        return $data;
    }
}
