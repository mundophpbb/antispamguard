<?php
/**
 * AntiSpam Guard — honeypot and timestamp/token validation.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

use phpbb\request\request_interface;

class form_guard
{
    protected $config;
    protected $request;

    public function __construct($config, request_interface $request = null)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @param string $raw_ts  Value of antispamguard_ts (timestamp:token)
     * @param array<string,mixed> $post_fields  Honeypot field values keyed by field name
     * @param int|null $now  Unix timestamp for tests
     */
    public function get_timestamp_block_reason($raw_ts, array $post_fields = array(), $now = null)
    {
        $timestamp = $this->parse_timestamp($raw_ts);
        $token = $this->parse_token($raw_ts);

        if ($timestamp <= 0 || !hash_equals($this->build_token($timestamp), $token))
        {
            return 'timestamp';
        }

        $now = $now === null ? time() : (int) $now;
        $age = $now - $timestamp;
        $min_seconds = max(0, isset($this->config['antispamguard_min_seconds']) ? (int) $this->config['antispamguard_min_seconds'] : 3);

        if ($age < $min_seconds)
        {
            return 'timestamp_too_fast';
        }

        $max_age = $this->get_max_form_elapsed_seconds();

        if ($max_age > 0 && $age > $max_age)
        {
            return 'timestamp_expired';
        }

        return '';
    }

    public function passes_honeypot($raw_ts, array $post_fields)
    {
        $timestamp = $this->parse_timestamp($raw_ts);

        if ($timestamp <= 0)
        {
            return false;
        }

        $field_name = $this->get_honeypot_name($timestamp);
        $has_field = array_key_exists($field_name, $post_fields);
        $value = $has_field ? (string) $post_fields[$field_name] : '';

        if (!$has_field && $this->request !== null)
        {
            if (!$this->request->is_set_post($field_name))
            {
                return false;
            }

            $value = $this->request->variable($field_name, '', true);
            $has_field = true;
        }

        return $has_field && trim($value) === '';
    }

    public function get_max_form_elapsed_seconds()
    {
        $limits = array();

        $max_seconds = isset($this->config['antispamguard_max_seconds']) ? (int) $this->config['antispamguard_max_seconds'] : 0;
        if ($max_seconds > 0)
        {
            $limits[] = $max_seconds;
        }

        $max_form_age = isset($this->config['antispamguard_max_form_age']) ? (int) $this->config['antispamguard_max_form_age'] : 0;
        if ($max_form_age > 0)
        {
            $limits[] = $max_form_age;
        }

        if (empty($limits))
        {
            return 0;
        }

        return min($limits);
    }

    public function build_token($timestamp)
    {
        $secret = isset($this->config['antispamguard_token_secret'])
            ? trim((string) $this->config['antispamguard_token_secret'])
            : '';

        // Backward-compatible fallback for partially upgraded installations.
        // The 3.3.63 migration creates a random dedicated secret.
        if ($secret === '')
        {
            $secret = isset($this->config['cookie_name']) ? (string) $this->config['cookie_name'] : 'phpbb';
            $secret .= isset($this->config['cookie_salt']) ? (string) $this->config['cookie_salt'] : '';
        }

        return hash_hmac('sha256', (string) $timestamp, $secret);
    }

    public function get_honeypot_class($timestamp = 0)
    {
        if (empty($this->config['antispamguard_hp_camouflage_enabled']) || (int) $timestamp <= 0)
        {
            return 'antispamguard-hp';
        }

        return 'asg-field-' . substr($this->build_token((int) $timestamp), 12, 10);
    }

    public function get_honeypot_style($timestamp = 0)
    {
        if (empty($this->config['antispamguard_hp_camouflage_enabled']))
        {
            return 'display:none;';
        }

        return 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;';
    }

    public function get_honeypot_name($timestamp = 0)
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

        $field_name = isset($this->config['antispamguard_hp_name']) ? trim((string) $this->config['antispamguard_hp_name']) : '';

        if ($field_name === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $field_name))
        {
            return 'homepage';
        }

        return $field_name;
    }

    public function parse_timestamp($raw_ts)
    {
        $raw_ts = (string) $raw_ts;

        if (strpos($raw_ts, ':') === false)
        {
            return 0;
        }

        list($timestamp,) = explode(':', $raw_ts, 2);

        return (int) $timestamp;
    }

    public function parse_token($raw_ts)
    {
        $raw_ts = (string) $raw_ts;

        if (strpos($raw_ts, ':') === false)
        {
            return '';
        }

        list(, $token) = explode(':', $raw_ts, 2);

        return (string) $token;
    }
}
