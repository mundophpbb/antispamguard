<?php

use mundophpbb\antispamguard\service\decision_engine;

class DecisionEngineTest extends TestCase
{
    public function run()
    {
        $config = new ConfigStub(array(
            'antispamguard_decision_engine_enabled' => 1,
            'antispamguard_decision_score_block' => 60,
            'antispamguard_decision_score_log' => 30,
            'antispamguard_decision_weight_honeypot' => 100,
            'antispamguard_decision_weight_timestamp_fast' => 30,
        ));

        $engine = new decision_engine($config);

        $this->assertTrue($engine->is_enabled(), 'engine enabled');

        $allow = $engine->evaluate(array());
        $this->assertSame('allow', $allow['action'], 'no signals allow');
        $this->assertSame(0, $allow['score'], 'no score');

        $block = $engine->evaluate(array('honeypot' => true));
        $this->assertSame('block', $block['action'], 'honeypot blocks');
        $this->assertTrue($block['score'] >= 60, 'honeypot score');

        $log = $engine->evaluate(array('timestamp_too_fast' => true));
        $this->assertSame('log', $log['action'], 'fast timestamp logs');
    }
}