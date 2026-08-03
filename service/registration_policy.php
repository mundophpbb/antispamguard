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

        // StopForumSpam is an external reputation signal. When it is the only
        // meaningful reason for a registration with username/e-mail submitted,
        // keep the event in audit mode to avoid blocking a legitimate user using
        // a shared, VPN, proxy, corporate, or previously abused IP. If there is
        // also a local bot signal, such as honeypot or timestamp failure, block.
        if (in_array('sfs_reputation', $reasons, true))
        {
            return !$this->has_local_bot_signal($reasons);
        }

        $soft_reasons = array(
            'timestamp_too_fast',
            'timestamp_expired',
            'ip_reputation',
            'slow_spam',
            'combined_decision',
        );

        foreach ($reasons as $reason)
        {
            if (!in_array($reason, $soft_reasons, true))
            {
                return false;
            }
        }

        return true;
    }

    public function should_audit_strict(array $reasons)
    {
        // Strict mode means every detected signal blocks.  Older versions
        // audited the exact honeypot + invalid-token combination produced by
        // direct bot POSTs, allowing the most obvious automated registrations.
        return false;
    }

    protected function has_hard_block_reason(array $reasons)
    {
        $hard_block_reasons = array(
            'ip_blacklist',
            'honeypot',
            'timestamp',
            'content_filter',
            'too_many_urls',
            'ip_rate_limit',
            'subnet_abuse',
            'random_gmail',
            'sfs_identity',
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

    protected function has_local_bot_signal(array $reasons)
    {
        $local_bot_reasons = array(
            'honeypot',
            'timestamp',
            'timestamp_too_fast',
            'timestamp_expired',
            'slow_spam',
        );

        foreach ($local_bot_reasons as $local_reason)
        {
            if (in_array($local_reason, $reasons, true))
            {
                return true;
            }
        }

        return false;
    }
}
