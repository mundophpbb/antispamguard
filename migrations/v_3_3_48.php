<?php
/**
 * AntiSpam Guard 3.3.48 - safer recommended defaults for small/medium forums.
 *
 * This migration intentionally uses only phpBB migration tools. No custom
 * callback is used here, avoiding partial-step issues in phpBB's migrator.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_48 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.48', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_45');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_protect_posts', 0)),
            array('config.update', array('antispamguard_register_audit_soft_signals', 1)),

            // StopForumSpam remains available, but defaults are more conservative.
            array('config.update', array('antispamguard_sfs_min_confidence', 80)),
            array('config.update', array('antispamguard_sfs_min_frequency', 5)),
            array('config.update', array('antispamguard_sfs_block_multiple_hits', 0)),
            array('config.update', array('antispamguard_ip_reputation_weight_sfs', 2)),

            // Local reputation remains useful for diagnostics, not standalone blocking.
            array('config.update', array('antispamguard_ip_reputation_threshold', 20)),
            array('config.update', array('antispamguard_decision_weight_ip_reputation', 0)),

            // Rate/subnet rules are opt-in by default to avoid false positives during tests.
            array('config.update', array('antispamguard_ip_rate_limit_enabled', 0)),
            array('config.update', array('antispamguard_subnet_rate_limit_enabled', 0)),

            // Combined score becomes more conservative. Honeypot and strong SFS still block.
            array('config.update', array('antispamguard_decision_score_log', 25)),
            array('config.update', array('antispamguard_decision_score_block', 80)),
            array('config.update', array('antispamguard_decision_weight_honeypot', 100)),
            array('config.update', array('antispamguard_decision_weight_timestamp_fast', 15)),
            array('config.update', array('antispamguard_decision_weight_timestamp_expired', 5)),
            array('config.update', array('antispamguard_decision_weight_rate_limit', 25)),
            array('config.update', array('antispamguard_decision_weight_sfs', 80)),
            array('config.update', array('antispamguard_decision_weight_slowspam', 15)),
            array('config.update', array('antispamguard_decision_weight_subnet_abuse', 30)),
            array('config.update', array('antispamguard_decision_weight_random_gmail', 10)),
            array('config.update', array('antispamguard_ip_reputation_weight_subnet_abuse', 1)),
            array('config.update', array('antispamguard_ip_reputation_weight_random_gmail', 1)),

            array('config.update', array('antispamguard_version', '3.3.48')),
        );
    }
}
