<?php

use mundophpbb\antispamguard\service\registration_policy;

class RegistrationPolicyTest extends TestCase
{
    public function run()
    {
        $policy = new registration_policy();

        $this->assertTrue(
            $policy->should_audit_without_blocking('register', array('honeypot'), true, true),
            'honeypot-only registers in lenient mode'
        );

        $this->assertTrue(
            $policy->should_audit_without_blocking('register', array('timestamp_too_fast', 'combined_decision'), true, true),
            'fast submit with combined score audits'
        );

        $this->assertTrue(
            !$policy->should_audit_without_blocking('register', array('sfs_reputation', 'honeypot'), true, true),
            'SFS still blocks registration'
        );

        $this->assertTrue(
            !$policy->should_audit_without_blocking('register', array('honeypot'), true, false),
            'strict mode needs timestamp signal too'
        );

        $this->assertTrue(
            $policy->should_audit_without_blocking('register', array('honeypot', 'timestamp'), true, false),
            'strict mode allows audit when both soft signals fire'
        );
    }
}