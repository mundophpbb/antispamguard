<?php

use mundophpbb\antispamguard\service\form_guard;

class FormGuardTest extends TestCase
{
    public function run()
    {
        $config = new ConfigStub(array(
            'cookie_name' => 'phpbb_test',
            'cookie_salt' => 'salt123',
            'antispamguard_min_seconds' => 3,
            'antispamguard_max_seconds' => 100,
            'antispamguard_max_form_age' => 200,
            'antispamguard_hp_dynamic_enabled' => 1,
            'antispamguard_hp_dynamic_prefix' => 'asg_hp',
        ));

        $guard = new form_guard($config);
        $now = 1000000;
        $ts = $now - 10;
        $token = $guard->build_token($ts);
        $raw = $ts . ':' . $token;

        $this->assertSame('', $guard->get_timestamp_block_reason($raw, array(), $now), 'valid submission');

        $fast_ts = $now - 1;
        $fast_raw = $fast_ts . ':' . $guard->build_token($fast_ts);
        $this->assertSame('timestamp_too_fast', $guard->get_timestamp_block_reason($fast_raw, array(), $now), 'too fast');
        $this->assertSame('timestamp_expired', $guard->get_timestamp_block_reason($raw, array(), $now + 200), 'expired uses min limit');
        $this->assertSame('timestamp', $guard->get_timestamp_block_reason('invalid', array(), $now), 'invalid token');
        $this->assertSame(100, $guard->get_max_form_elapsed_seconds(), 'stricter max seconds');

        $hp_name = $guard->get_honeypot_name($ts);
        $this->assertTrue(strpos($hp_name, 'asg_hp_') === 0, 'dynamic honeypot prefix');
        $this->assertTrue($guard->passes_honeypot($raw, array($hp_name => '')), 'empty honeypot passes');
        $this->assertTrue(!$guard->passes_honeypot($raw, array($hp_name => 'bot')), 'filled honeypot fails');
    }
}