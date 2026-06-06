<?php
/**
 * Registration-specific anti-spam policy (audit vs block).
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class registration_policy
{
    public function should_audit_without_blocking($form_type, array $reasons, $has_identity, $lenient_enabled = true)
    {
        if ((string) $form_type !== 'register' || !$has_identity || empty($reasons))
        {
            return false;
        }

        if (!$lenient_enabled)
        {
            return $this->should_audit_strict($reasons);
        }

        if ($this->has_hard_block_reason($reasons))
        {
            return false;
        }

        $soft_reasons = array(
            'honeypot',
            'timestamp',
            'timestamp_too_fast',
            'timestamp_expired',
            'ip_reputation',
            'combined_decision',
        );

        foreach ($reasons as $reason)
        {
            if (in_array($reason, $soft_reasons, true))
            {
                return true;
            }
        }

        return false;
    }

    public function should_audit_strict(array $reasons)
    {
        if ($this->has_hard_block_reason($reasons))
        {
            return false;
        }

        $has_honeypot = in_array('honeypot', $reasons, true);
        $has_timestamp = in_array('timestamp', $reasons, true)
            || in_array('timestamp_too_fast', $reasons, true)
            || in_array('timestamp_expired', $reasons, true);

        return $has_honeypot && $has_timestamp;
    }

    protected function has_hard_block_reason(array $reasons)
    {
        $hard_block_reasons = array(
            'sfs_reputation',
            'ip_blacklist',
            'content_filter',
            'too_many_urls',
            'ip_rate_limit',
            'subnet_abuse',
            'random_gmail',
            'slow_spam',
        );

        foreach ($hard_block_reasons as $hard_reason)
        {
            if (in_array($hard_reason, $reasons, true))
            {
                return true;
            }
        }

        return false;
    }
}