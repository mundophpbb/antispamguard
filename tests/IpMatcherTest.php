<?php

use mundophpbb\antispamguard\service\ip_matcher;

class IpMatcherTest extends TestCase
{
    public function run()
    {
        $matcher = new ip_matcher();

        $this->assertTrue($matcher->entry_matches('203.0.113.10', '203.0.113.10'), 'exact ip');
        $this->assertTrue($matcher->entry_matches('203.0.113.55', '203.0.113.*'), 'wildcard');
        $this->assertTrue($matcher->cidr_matches('10.0.0.5', '10.0.0.0/24'), 'cidr inside');
        $this->assertTrue(!$matcher->cidr_matches('10.0.1.5', '10.0.0.0/24'), 'cidr outside');
        $this->assertTrue($matcher->matches_list('1.2.3.4', "1.2.3.4\n10.0.0.0/8"), 'list match');
        $this->assertTrue(!$matcher->matches_list('8.8.8.8', "1.2.3.4"), 'list no match');

        $match = $matcher->whitelist_match('192.168.1.10', "192.168.0.0/16\n# comment");
        $this->assertTrue($match['matched'], 'whitelist cidr');
        $this->assertSame('192.168.0.0/16', $match['entry'], 'whitelist entry');

        $normalized = $matcher->normalize_list(" 1.2.3.4 \n 10.0.0.0/8 \n bad \n 1.2.3.4 ");
        $this->assertSame("1.2.3.4\n10.0.0.0/8", $normalized, 'normalize dedupe');
    }
}