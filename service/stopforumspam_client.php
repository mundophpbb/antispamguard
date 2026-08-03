<?php
/**
 * AntiSpam Guard - StopForumSpam client.
 */

namespace mundophpbb\antispamguard\service;

class stopforumspam_client
{
    protected $sfs_cache;
    protected $config;
    /** @var object|null Reusable Guzzle-compatible HTTP client. */
    protected $http_client;

    public function __construct(sfs_cache $sfs_cache, \phpbb\config\config $config = null, $http_client = null)
    {
        $this->sfs_cache = $sfs_cache;
        $this->config = $config;
        $this->http_client = $http_client;
    }

    public function check($type, $value)
    {
        $type = (string) $type;
        $value = trim((string) $value);

        if (!in_array($type, array('ip', 'email', 'username'), true) || $value === '')
        {
            return false;
        }

        $results = $this->check_many(array($type => $value));

        return isset($results[$type]) ? $results[$type] : false;
    }

    /**
     * Check every missing identity field with one StopForumSpam request.
     * Cached fields are returned without being sent again.
     *
     * @param array<string,string> $checks
     * @return array<string,array|false>
     */
    public function check_many(array $checks)
    {
        $results = array();
        $missing = array();
        $normalized = array();

        foreach ($checks as $type => $value)
        {
            $type = (string) $type;
            $value = $this->normalize_lookup_value($type, $value);

            if (!in_array($type, array('ip', 'email', 'username'), true) || $value === '')
            {
                continue;
            }

            $normalized[$type] = $value;
        }

        $cached_results = $this->sfs_cache->get_many($normalized);

        foreach ($normalized as $type => $value)
        {
            $cache = isset($cached_results[$type]) ? $cached_results[$type] : false;

            if (!empty($cache['cached']))
            {
                $results[$type] = $cache;
            }
            else
            {
                $missing[$type] = $value;
            }
        }

        if (empty($missing))
        {
            return $results;
        }

        if ($this->is_circuit_open())
        {
            foreach ($missing as $type => $value)
            {
                $results[$type] = $this->error_result(false, 'circuit_open');
            }

            return $results;
        }

        $url = 'https://api.stopforumspam.org/api?json&' . http_build_query($missing, '', '&');
        $response = $this->http_get($url);

        if ($response === false)
        {
            $this->record_remote_failure();

            foreach ($missing as $type => $value)
            {
                $this->sfs_cache->set_error($type, $value);
                $results[$type] = $this->error_result(false, 'request_failed');
            }

            return $results;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || (isset($data['success']) && empty($data['success'])))
        {
            $this->record_remote_failure();

            foreach ($missing as $type => $value)
            {
                $this->sfs_cache->set_error($type, $value);
                $results[$type] = $this->error_result(false, 'invalid_response');
            }

            return $results;
        }

        $valid_response = false;

        foreach ($missing as $type => $value)
        {
            if (!isset($data[$type]) || !is_array($data[$type]))
            {
                $this->sfs_cache->set_error($type, $value);
                $results[$type] = $this->error_result(false, 'missing_result');
                continue;
            }

            $entry = $data[$type];
            $is_listed = !empty($entry['appears']);
            $confidence = isset($entry['confidence']) ? (float) $entry['confidence'] : 0;
            $frequency = isset($entry['frequency']) ? (int) $entry['frequency'] : 0;

            $this->sfs_cache->set($type, $value, array($type => $entry), $is_listed, $confidence, $frequency);

            $results[$type] = array(
                'cached' => false,
                'data' => array($type => $entry),
                'is_listed' => $is_listed,
                'confidence' => $confidence,
                'frequency' => $frequency,
                'error' => false,
            );
            $valid_response = true;
        }

        if ($valid_response)
        {
            $this->record_remote_success();
        }

        return $results;
    }

    protected function normalize_lookup_value($type, $value)
    {
        $value = trim((string) $value);

        if ($type === 'email' || $type === 'username')
        {
            $value = strtolower($value);
        }

        return $value;
    }

    protected function error_result($cached, $status)
    {
        return array(
            'cached' => (bool) $cached,
            'data' => array('error' => true, 'status' => (string) $status),
            'is_listed' => false,
            'confidence' => 0,
            'frequency' => 0,
            'error' => true,
            'error_status' => (string) $status,
        );
    }

    protected function is_circuit_open()
    {
        return $this->config !== null
            && !empty($this->config['antispamguard_sfs_circuit_until'])
            && (int) $this->config['antispamguard_sfs_circuit_until'] > time();
    }

    protected function record_remote_failure()
    {
        if ($this->config === null)
        {
            return;
        }

        $failures = isset($this->config['antispamguard_sfs_failure_count'])
            ? ((int) $this->config['antispamguard_sfs_failure_count']) + 1
            : 1;
        $threshold = isset($this->config['antispamguard_sfs_circuit_threshold'])
            ? max(1, (int) $this->config['antispamguard_sfs_circuit_threshold'])
            : 3;

        if ($failures >= $threshold)
        {
            $cooldown = isset($this->config['antispamguard_sfs_circuit_cooldown'])
                ? max(60, (int) $this->config['antispamguard_sfs_circuit_cooldown'])
                : 300;
            $this->config->set('antispamguard_sfs_circuit_until', time() + $cooldown, true);
            $failures = 0;
        }

        $this->config->set('antispamguard_sfs_failure_count', $failures, true);
    }

    protected function record_remote_success()
    {
        if ($this->config === null)
        {
            return;
        }

        if (!empty($this->config['antispamguard_sfs_failure_count']))
        {
            $this->config->set('antispamguard_sfs_failure_count', 0, true);
        }
        if (!empty($this->config['antispamguard_sfs_circuit_until']))
        {
            $this->config->set('antispamguard_sfs_circuit_until', 0, true);
        }
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
        return $this->http_request('GET', $url, array(
            'Accept' => 'application/json',
        ));
    }

    protected function http_post($url, array $fields)
    {
        return $this->http_request('POST', $url, array(
            'Accept' => 'text/plain, application/json',
        ), $fields);
    }

    /**
     * Use the Guzzle library shipped with phpBB without depending on a core
     * container service name. Not every supported phpBB 3.3 installation
     * exposes an "http_client" service identifier. Redirects are disabled because both
     * SFS endpoints are fixed and a redirect must never move submitted
     * identities or the private API key to another host.
     */
    protected function http_request($method, $url, array $headers, array $form_fields = null)
    {
        $http_client = $this->get_http_client();
        if ($http_client === null)
        {
            return false;
        }

        $options = array(
            'timeout' => $this->get_timeout(),
            'connect_timeout' => $this->get_timeout(),
            'http_errors' => false,
            'allow_redirects' => false,
            'headers' => array_merge(array(
                'User-Agent' => 'AntiSpamGuard/3.3.66',
            ), $headers),
        );

        if ($form_fields !== null)
        {
            $options['form_params'] = $form_fields;
        }

        $attempt = 0;
        $retries = $this->get_retries();

        while ($attempt <= $retries)
        {
            try
            {
                $response = $http_client->request((string) $method, (string) $url, $options);
                $status = is_object($response) && is_callable(array($response, 'getStatusCode'))
                    ? (int) $response->getStatusCode()
                    : 0;

                if ($status >= 200 && $status < 300)
                {
                    $body = $this->read_response_body($response);
                    if ($body !== false)
                    {
                        return $body;
                    }
                }
            }
            catch (\Exception $e)
            {
                // The circuit breaker records the final failure after retries.
            }

            $attempt++;
        }

        return false;
    }

    /**
     * Lazily build one client per AntiSpam Guard service instance. Keeping the
     * optional constructor argument supports tests and custom integrations,
     * while production no longer requires a version-specific phpBB service.
     */
    protected function get_http_client()
    {
        if ($this->http_client !== null && is_callable(array($this->http_client, 'request')))
        {
            return $this->http_client;
        }

        if (!class_exists('\\GuzzleHttp\\Client'))
        {
            return null;
        }

        try
        {
            $this->http_client = new \GuzzleHttp\Client();
        }
        catch (\Exception $e)
        {
            $this->http_client = null;
        }

        return $this->http_client !== null && is_callable(array($this->http_client, 'request'))
            ? $this->http_client
            : null;
    }

    protected function read_response_body($response)
    {
        if (!is_object($response) || !is_callable(array($response, 'getBody')))
        {
            return false;
        }

        $stream = $response->getBody();
        $max_bytes = $this->get_max_response_bytes();

        if (is_object($stream) && is_callable(array($stream, 'getSize')))
        {
            $declared_size = $stream->getSize();
            if ($declared_size !== null && (int) $declared_size > $max_bytes)
            {
                return false;
            }
        }

        if (is_object($stream) && is_callable(array($stream, 'read')) && is_callable(array($stream, 'eof')))
        {
            $contents = '';
            while (!$stream->eof() && strlen($contents) <= $max_bytes)
            {
                $remaining = ($max_bytes + 1) - strlen($contents);
                $chunk = (string) $stream->read(min(8192, $remaining));
                if ($chunk === '')
                {
                    break;
                }
                $contents .= $chunk;
            }
        }
        else if (is_object($stream) && is_callable(array($stream, 'read')))
        {
            $contents = (string) $stream->read($max_bytes + 1);
        }
        else
        {
            $contents = (string) $stream;
        }

        return strlen($contents) <= $max_bytes ? $contents : false;
    }

    protected function get_timeout()
    {
        return ($this->config !== null && isset($this->config['antispamguard_sfs_http_timeout']))
            ? max(1, min(10, (int) $this->config['antispamguard_sfs_http_timeout']))
            : 2;
    }

    protected function get_retries()
    {
        return ($this->config !== null && isset($this->config['antispamguard_sfs_http_retries']))
            ? max(0, min(2, (int) $this->config['antispamguard_sfs_http_retries']))
            : 1;
    }

    protected function get_max_response_bytes()
    {
        return ($this->config !== null && isset($this->config['antispamguard_sfs_http_max_response_bytes']))
            ? max(4096, min(1048576, (int) $this->config['antispamguard_sfs_http_max_response_bytes']))
            : 262144;
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
