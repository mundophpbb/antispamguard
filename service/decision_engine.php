<?php
namespace mundophpbb\antispamguard\service;

class decision_engine
{
	protected $config;

	public function __construct($config)
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
		$contributions = array();

		if (!empty($signals['honeypot']))
		{
			$contributions['honeypot'] = $this->weight('honeypot', 100);
			$score += $contributions['honeypot'];
			$reasons[] = 'honeypot';
		}

		if (!empty($signals['timestamp_too_fast']))
		{
			$contributions['timestamp_too_fast'] = $this->weight('timestamp_fast', 15);
			$score += $contributions['timestamp_too_fast'];
			$reasons[] = 'timestamp_too_fast';
		}

		if (!empty($signals['timestamp_expired']))
		{
			$contributions['timestamp_expired'] = $this->weight('timestamp_expired', 5);
			$score += $contributions['timestamp_expired'];
			$reasons[] = 'timestamp_expired';
		}

		if (!empty($signals['slow_spam']))
		{
			$contributions['slow_spam'] = $this->weight('slowspam', 15);
			$score += $contributions['slow_spam'];
			$reasons[] = 'slow_spam';
		}

		if (!empty($signals['rate_limit']))
		{
			$contributions['ip_rate_limit'] = $this->weight('rate_limit', 25);
			$score += $contributions['ip_rate_limit'];
			$reasons[] = 'ip_rate_limit';
		}

		if (!empty($signals['subnet_abuse']))
		{
			$contributions['subnet_abuse'] = $this->weight('subnet_abuse', 30);
			$score += $contributions['subnet_abuse'];
			$reasons[] = 'subnet_abuse';
		}

		if (!empty($signals['random_gmail']))
		{
			$contributions['random_gmail'] = $this->weight('random_gmail', 10);
			$score += $contributions['random_gmail'];
			$reasons[] = 'random_gmail';
		}

		if (!empty($signals['sfs']))
		{
			$contributions['sfs_reputation'] = $this->weight('sfs', 80);
			$score += $contributions['sfs_reputation'];
			$reasons[] = 'sfs_reputation';
		}

		if (isset($signals['ip_reputation_score']))
		{
			$reputation_score = max(0, min(255, (int) $signals['ip_reputation_score']));
			$contributions['ip_reputation'] = $reputation_score * $this->weight('ip_reputation', 0);
			$score += $contributions['ip_reputation'];

			if ((int) $signals['ip_reputation_score'] > 0)
			{
				$reasons[] = 'ip_reputation';
			}
		}

		$score = max(0, min(65535, (int) $score));

		return array(
			'score' => $score,
			'action' => $this->decide($score),
			'reasons' => array_unique($reasons),
			'contributions' => $contributions,
			'engine_version' => 1,
		);
	}

	public function decide($score)
	{
		$block = isset($this->config['antispamguard_decision_score_block']) ? (int) $this->config['antispamguard_decision_score_block'] : 80;
		$log = isset($this->config['antispamguard_decision_score_log']) ? (int) $this->config['antispamguard_decision_score_log'] : 25;

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

		$value = isset($this->config[$key]) ? (int) $this->config[$key] : (int) $default;

		return max(0, min(255, $value));
	}
}
