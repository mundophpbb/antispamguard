<?php
/**
 * AntiSpam Guard 2.9.9 - Combined decision engine.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_9_9 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.9.9', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_9_4');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_decision_engine_enabled', 1)),
            array('config.add', array('antispamguard_decision_score_log', 30)),
            array('config.add', array('antispamguard_decision_score_block', 60)),
            array('config.add', array('antispamguard_decision_weight_honeypot', 100)),
            array('config.add', array('antispamguard_decision_weight_timestamp_fast', 30)),
            array('config.add', array('antispamguard_decision_weight_timestamp_expired', 15)),
            array('config.add', array('antispamguard_decision_weight_rate_limit', 40)),
            array('config.add', array('antispamguard_decision_weight_sfs', 50)),
            array('config.add', array('antispamguard_decision_weight_ip_reputation', 1)),
            array('config.update', array('antispamguard_version', '2.9.9')),
        );
    }
}
