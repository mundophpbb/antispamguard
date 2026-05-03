<?php
/**
 * AntiSpam Guard - StopForumSpam client.
 */

namespace mundophpbb\antispamguard\service;

class stopforumspam_client
{
    protected $sfs_cache;
    protected $config;
    protected $timeout = 5;
    protected $retries = 2;

    public function __construct(sfs_cache $sfs_cache, \phpbb\config\config $config = null)
    {
        $this->sfs_cache = $sfs_cache;
        $this->config = $config;
    }

    public function check($type, $value)
    {
        $type = (string) $type;
        $value = trim((string) $value);

        if (!in_array($type, array('ip', 'email', 'username'), true) || $value === '')
        {
            return false;
        }

        $cache = $this->sfs_cache->get($type, $value);

        if (!empty($cache['cached']))
        {
            return $cache;
        }

        $url = 'https://api.stopforumspam.org/api?json&' . $type . '=' . urlencode($value);
        $response = $this->http_get($url);

        if ($response === false)
        {
            $this->sfs_cache->set_error($type, $value);
            return false;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data[$type]))
        {
            $this->sfs_cache->set_error($type, $value);
            return false;
        }

        $entry = $data[$type];

        $is_listed = !empty($entry['appears']);
        $confidence = isset($entry['confidence']) ? (float) $entry['confidence'] : 0;
        $frequency = isset($entry['frequency']) ? (int) $entry['frequency'] : 0;

        $this->sfs_cache->set($type, $value, $data, $is_listed, $confidence, $frequency);

        return array(
            'cached' => false,
            'data' => $data,
            'is_listed' => $is_listed,
            'confidence' => $confidence,
            'frequency' => $frequency,
        );
    }

    public function has_api_key()
    {
        return $this->get_api_key() !== '';
    }

    public function get_api_key_masked()
    {
        return $this->mask_api_key($this->get_api_key());
    }

    public function submit_spammer($ip, $email, $username, $evidence = '')
    {
        $api_key = $this->get_api_key();
        $ip = trim((string) $ip);
        $email = trim((string) $email);
        $username = trim((string) $username);
        $evidence = trim((string) $evidence);

        if ($api_key === '')
        {
            return array(
                'success' => false,
                'status' => 'missing_api_key',
                'message' => 'StopForumSpam API key is not configured.',
            );
        }

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP))
        {
            return array(
                'success' => false,
                'status' => 'invalid_ip',
                'message' => 'Invalid IP address.',
            );
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return array(
                'success' => false,
                'status' => 'invalid_email',
                'message' => 'Invalid email address.',
            );
        }

        if ($username === '')
        {
            return array(
                'success' => false,
                'status' => 'invalid_username',
                'message' => 'Invalid username.',
            );
        }

        $args = array(
            'username' => $this->truncate($this->strip_control_chars($username), 255),
            'ip_addr' => $ip,
            'email' => $this->truncate($this->strip_control_chars($email), 255),
            'api_key' => $api_key,
        );

        if ($evidence !== '')
        {
            $args['evidence'] = $this->truncate($this->strip_control_chars($evidence), 1024);
        }

        $response = $this->http_post('https://www.stopforumspam.com/add.php', $args);

        if ($response === false)
        {
            return array(
                'success' => false,
                'status' => 'request_failed',
                'message' => 'StopForumSpam submission request failed.',
            );
        }

        $response_text = trim((string) $response);
        $lower = strtolower($response_text);

        if (strpos($lower, 'invalid') !== false || strpos($lower, 'error') !== false || strpos($lower, 'denied') !== false || strpos($lower, 'fail') !== false)
        {
            return array(
                'success' => false,
                'status' => 'remote_rejected',
                'message' => 'StopForumSpam rejected the submission.',
                'response' => $this->truncate($response_text, 300),
            );
        }

        return array(
            'success' => true,
            'status' => 'submitted',
            'message' => 'StopForumSpam submission completed.',
            'response' => $this->truncate($response_text, 300),
        );
    }

    protected function get_api_key()
    {
        if ($this->config === null || !isset($this->config['antispamguard_sfs_api_key']))
        {
            return '';
        }

        return trim((string) $this->config['antispamguard_sfs_api_key']);
    }

    protected function mask_api_key($api_key)
    {
        $api_key = trim((string) $api_key);
        $length = strlen($api_key);

        if ($length === 0)
        {
            return '';
        }

        if ($length <= 4)
        {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)) . substr($api_key, -4);
    }

    protected function http_get($url)
    {
        $attempt = 0;
        $response = false;

        while ($attempt <= $this->retries)
        {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'header' => "User-Agent: AntiSpamGuard/3.3.22\r\n",
                ),
            ));

            $response = @file_get_contents($url, false, $context);

            if ($response !== false)
            {
                break;
            }

            $attempt++;
        }

        return $response;
    }

    protected function http_post($url, array $fields)
    {
        $body = http_build_query($fields, '', '&');
        $attempt = 0;
        $response = false;

        while ($attempt <= $this->retries)
        {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'timeout' => $this->timeout,
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                        "Content-Length: " . strlen($body) . "\r\n" .
                        "User-Agent: AntiSpamGuard/3.3.22\r\n",
                    'content' => $body,
                ),
            ));

            $response = @file_get_contents($url, false, $context);

            if ($response !== false)
            {
                break;
            }

            $attempt++;
        }

        return $response;
    }

    protected function strip_control_chars($value)
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value);
    }

    protected function truncate($value, $max_length)
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
}
