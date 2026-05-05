<?php
namespace mundophpbb\antispamguard\service;

class decision_engine
{
    protected $config;

    public function __construct(\phpbb\config\config $config)
    {
        $this->config = $config;
    }

    public function is_enabled()
    {
        return !empty($this->config['antispamguard_decision_engine_enabled']);
    }

    public function evaluate(array $signals)
    {
        $score = 0;
        $reasons = array();

        if (!empty($signals['honeypot']))
        {
            $score += $this->weight('honeypot', 100);
            $reasons[] = 'honeypot';
        }

        if (!empty($signals['timestamp_too_fast']))
        {
            $score += $this->weight('timestamp_fast', 30);
            $reasons[] = 'timestamp_too_fast';
        }

        if (!empty($signals['timestamp_expired']))
        {
            $score += $this->weight('timestamp_expired', 15);
            $reasons[] = 'timestamp_expired';
        }

        if (!empty($signals['slow_spam']))
        {
            $score += $this->weight('slowspam', 35);
            $reasons[] = 'slow_spam';
        }

        if (!empty($signals['rate_limit']))
        {
            $score += $this->weight('rate_limit', 40);
            $reasons[] = 'ip_rate_limit';
        }

        if (!empty($signals['subnet_abuse']))
        {
            $score += $this->weight('subnet_abuse', 45);
            $reasons[] = 'subnet_abuse';
        }

        if (!empty($signals['random_gmail']))
        {
            $score += $this->weight('random_gmail', 20);
            $reasons[] = 'random_gmail';
        }

        if (!empty($signals['sfs']))
        {
            $score += $this->weight('sfs', 50);
            $reasons[] = 'sfs_reputation';
        }

        if (isset($signals['ip_reputation_score']))
        {
            $score += ((int) $signals['ip_reputation_score']) * $this->weight('ip_reputation', 1);

            if ((int) $signals['ip_reputation_score'] > 0)
            {
                $reasons[] = 'ip_reputation';
            }
        }

        return array(
            'score' => $score,
            'action' => $this->decide($score),
            'reasons' => array_unique($reasons),
        );
    }

    public function decide($score)
    {
        $block = isset($this->config['antispamguard_decision_score_block']) ? (int) $this->config['antispamguard_decision_score_block'] : 60;
        $log = isset($this->config['antispamguard_decision_score_log']) ? (int) $this->config['antispamguard_decision_score_log'] : 30;

        if ((int) $score >= $block)
        {
            return 'block';
        }

        if ((int) $score >= $log)
        {
            return 'log';
        }

        return 'allow';
    }

    protected function weight($name, $default)
    {
        $key = 'antispamguard_decision_weight_' . $name;

        return isset($this->config[$key]) ? (int) $this->config[$key] : (int) $default;
    }
}
