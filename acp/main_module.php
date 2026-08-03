<?php
/**
 * AntiSpam Guard ACP module.
 */

namespace mundophpbb\antispamguard\acp;

use mundophpbb\antispamguard\service\ip_matcher;

class main_module
{
    public $u_action;

    /** @var settings_helper */
    protected $settings_helper;

    /** @var ip_matcher */
    protected $ip_matcher;

    /** @var pagination_helper */
    protected $pagination_helper;

    /** @var sfs_controller */
    protected $sfs_controller;

    protected function get_settings_helper()
    {
        if (!$this->settings_helper)
        {
            $this->settings_helper = new settings_helper();
        }

        return $this->settings_helper;
    }

    protected function get_ip_matcher()
    {
        if (!$this->ip_matcher)
        {
            $this->ip_matcher = new ip_matcher();
        }

        return $this->ip_matcher;
    }

    protected function get_pagination_helper()
    {
        if (!$this->pagination_helper)
        {
            $this->pagination_helper = new pagination_helper();
        }

        return $this->pagination_helper;
    }

    /**
     * @return sfs_controller
     */
    protected function get_sfs_controller()
    {
        if (!$this->sfs_controller)
        {
            $this->sfs_controller = new sfs_controller(
                $this->u_action,
                $this->get_settings_helper(),
                $this->get_pagination_helper()
            );
        }
        else
        {
            $this->sfs_controller->u_action = $this->u_action;
        }

        return $this->sfs_controller;
    }

    public function main($id, $mode)
    {
        global $config, $db, $request, $template, $user, $table_prefix;

        $user->add_lang_ext('mundophpbb/antispamguard', 'acp');

        $this->tpl_name = 'acp_antispamguard';
        $this->page_title = $user->lang('ACP_ANTISPAMGUARD_TITLE');

        add_form_key('mundophpbb_antispamguard');

        if ($request->is_set_post('enable_email_activation'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            if (empty($config['email_enable']))
            {
                trigger_error($user->lang('ACP_ANTISPAMGUARD_EMAIL_ACTIVATION_EMAIL_DISABLED') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $activation_self = defined('USER_ACTIVATION_SELF') ? USER_ACTIVATION_SELF : 1;
            $config->set('require_activation', $activation_self);

            if (function_exists('add_log'))
            {
                add_log('admin', 'LOG_CONFIG_USER');
            }

            trigger_error($user->lang('ACP_ANTISPAMGUARD_EMAIL_ACTIVATION_ENABLED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('reset_sfs_circuit'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $config->set('antispamguard_sfs_failure_count', 0, true);
            $config->set('antispamguard_sfs_circuit_until', 0, true);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_CIRCUIT_RESET_DONE') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('export_settings'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_settings_json($config);
        }

        if ($request->is_set_post('import_settings'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $raw_settings = $request->variable('antispamguard_import_settings', '', true);
            $result = $this->import_settings_json($config, $raw_settings);

            if (!$result['success'])
            {
                trigger_error($user->lang($result['message']) . adm_back_link($this->u_action), E_USER_WARNING);
            }

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SETTINGS_IMPORTED', $result['count']) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('prune_ip_reputation'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $ip_reputation = $phpbb_container->get('mundophpbb.antispamguard.ip_reputation');
            $removed_scores = $ip_reputation->prune();
            $config->set('antispamguard_ip_reputation_cleanup_last_gc', time(), false);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_IP_REPUTATION_PRUNE_DONE', $removed_scores) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('prune_ip_rate_limit'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $ip_rate_limit = $phpbb_container->get('mundophpbb.antispamguard.ip_rate_limit');
            $removed_rates = $ip_rate_limit->prune();
            $config->set('antispamguard_ip_rate_limit_cleanup_last_gc', time(), false);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_IP_RATE_LIMIT_PRUNE_DONE', $removed_rates) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('reset_ip_rate_limit'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $ip_rate_limit = $phpbb_container->get('mundophpbb.antispamguard.ip_rate_limit');
            $removed_rates = $ip_rate_limit->reset_all();

            trigger_error($user->lang('ACP_ANTISPAMGUARD_IP_RATE_LIMIT_RESET_DONE', $removed_rates) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('reset_ip_reputation'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $ip_reputation = $phpbb_container->get('mundophpbb.antispamguard.ip_reputation');
            $removed_scores = $ip_reputation->reset_all();

            trigger_error($user->lang('ACP_ANTISPAMGUARD_IP_REPUTATION_RESET_DONE', $removed_scores) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('run_sfs_cleanup_now'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $now = time();

            $sql = 'DELETE FROM ' . $table_prefix . 'antispamguard_sfs_cache
                WHERE expires_at <= ' . (int) $now;
            $db->sql_query($sql);
            $removed_cache = (int) $db->sql_affectedrows();

            $retention_days = isset($config['antispamguard_sfs_log_retention_days']) ? (int) $config['antispamguard_sfs_log_retention_days'] : 90;
            $preserve_reviewed = !empty($config['antispamguard_sfs_log_preserve_reviewed']);
            $removed_logs = $this->get_sfs_controller()->prune_old_sfs_logs($db, $table_prefix, $retention_days, $preserve_reviewed);

            $config->set('antispamguard_sfs_cleanup_last_gc', $now, false);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_RAN', $removed_cache, $removed_logs) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('clear_sfs_expired_cache'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sql = 'DELETE FROM ' . $table_prefix . 'antispamguard_sfs_cache
                WHERE expires_at <= ' . time();
            $db->sql_query($sql);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_EXPIRED_CACHE_CLEARED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('clear_sfs_cache'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $db->sql_query('DELETE FROM ' . $table_prefix . 'antispamguard_sfs_cache');

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_CACHE_CLEARED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('clear_sfs_logs'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $db->sql_query('DELETE FROM ' . $table_prefix . 'antispamguard_sfs_log');

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_LOGS_CLEARED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('export_sfs_logs'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->get_sfs_controller()->export_sfs_logs_csv($db, $user, $table_prefix, $request);
            return;
        }


        if ($this->get_sfs_controller()->is_sfs_moderation_action($request))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $summary = $this->get_sfs_controller()->handle_sfs_moderation_action($db, $request, $user, $config, $table_prefix);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_MODERATION_DONE', $summary['reported'], $summary['blocked'], $summary['already_blocked'], $summary['allowed'], $summary['cleared'], $summary['skipped'], $summary['failed']) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('remove_sfs_api_key'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $config->set('antispamguard_sfs_api_key', '', true);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_API_KEY_REMOVED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit_sfs_spammer'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $submit_ip = trim($request->variable('antispamguard_sfs_submit_ip', ''));
            $submit_email = trim($request->variable('antispamguard_sfs_submit_email', '', true));
            $submit_username = trim($request->variable('antispamguard_sfs_submit_username', '', true));
            $submit_evidence = trim($request->variable('antispamguard_sfs_submit_evidence', '', true));
            $submit_source = trim($request->variable('antispamguard_sfs_submit_source', 'manual_acp'));
            $submit_source_log_id = max(0, $request->variable('antispamguard_sfs_submit_source_log_id', 0));

            $sfs_client = $phpbb_container->get('mundophpbb.antispamguard.stopforumspam_client');
            $submit_result = $sfs_client->submit_spammer($submit_ip, $submit_email, $submit_username, $submit_evidence);

            $submit_audit_id = $this->get_sfs_controller()->record_sfs_submission($db, $table_prefix, $user, $submit_ip, $submit_email, $submit_username, $submit_evidence, $submit_source, $submit_source_log_id, $submit_result);

            if (empty($submit_result['success']))
            {
                $status = !empty($submit_result['status']) ? (string) $submit_result['status'] : 'unknown';
                trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_SUBMIT_FAILED_STATUS', $status, $submit_audit_id) . adm_back_link($this->u_action), E_USER_WARNING);
            }

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_SUBMIT_SUCCESS_LOGGED', $submit_audit_id) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('export_ip_reputation'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_ip_reputation_csv($db, $user, $table_prefix);
            return;
        }

        if ($request->is_set_post('export_ip_rate_limit'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_ip_rate_limit_csv($db, $user, $table_prefix);
            return;
        }

        if ($request->is_set_post('export_config_inventory'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_config_inventory_csv($db, $user);
            return;
        }

        if ($request->is_set_post('export_slowspam_activity'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_slowspam_activity_csv($db, $user, $table_prefix);
            return;
        }

        if ($request->is_set_post('prune_slowspam_activity'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $activity_tracker = $phpbb_container->get('mundophpbb.antispamguard.activity_tracker');
            $removed_activity = $activity_tracker->prune();
            $config->set('antispamguard_slowspam_cleanup_last_gc', time(), false);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SLOWSPAM_PRUNE_DONE', $removed_activity) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('mark_alerts_read'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $alerts = $phpbb_container->get('mundophpbb.antispamguard.alerts');
            $marked = $alerts->mark_all_read();

            trigger_error($user->lang('ACP_ANTISPAMGUARD_ALERTS_MARKED_READ', $marked) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('prune_alerts'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $alerts = $phpbb_container->get('mundophpbb.antispamguard.alerts');
            $removed = $alerts->prune();
            $config->set('antispamguard_alerts_last_gc', time(), false);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_ALERTS_PRUNED', $removed) . adm_back_link($this->u_action));
        }

        if ($mode === 'stats')
        {
            $this->show_stats($db, $template, $table_prefix);
            return;
        }

        if ($mode === 'logs')
        {
            $this->show_logs($db, $request, $template, $user, $table_prefix);
            return;
        }

        if ($mode === 'about')
        {
            $this->show_about($db, $template, $user, $config, $table_prefix);
            return;
        }

        if ($request->is_set_post('test_sfs_lookup'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $phpbb_container;

            $test_type = $request->variable('antispamguard_sfs_test_type', 'ip');
            $test_value = trim($request->variable('antispamguard_sfs_test_value', '', true));

            $allowed_types = array('ip', 'email', 'username');

            if (!in_array($test_type, $allowed_types, true) || $test_value === '')
            {
                trigger_error($user->lang('ACP_ANTISPAMGUARD_SFS_TEST_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sfs_client = $phpbb_container->get('mundophpbb.antispamguard.stopforumspam_client');
            $sfs_result = $sfs_client->check($test_type, $test_value);

            if (!$sfs_result)
            {
                $template->assign_vars(array(
                    'S_SFS_TEST_DONE' => true,
                    'SFS_TEST_TYPE' => $test_type,
                    'SFS_TEST_VALUE' => $test_value,
                    'SFS_TEST_CACHED' => $user->lang('NO'),
                    'SFS_TEST_LISTED' => $user->lang('NO'),
                    'SFS_TEST_CONFIDENCE' => 0,
                    'SFS_TEST_FREQUENCY' => 0,
                    'SFS_TEST_RESULT' => $user->lang('ACP_ANTISPAMGUARD_SFS_TEST_ERROR'),
                ));
            }
            else
            {
                $template->assign_vars(array(
                    'S_SFS_TEST_DONE' => true,
                    'SFS_TEST_TYPE' => $test_type,
                    'SFS_TEST_VALUE' => $test_value,
                    'SFS_TEST_CACHED' => !empty($sfs_result['cached']) ? $user->lang('YES') : $user->lang('NO'),
                    'SFS_TEST_LISTED' => !empty($sfs_result['is_listed']) ? $user->lang('YES') : $user->lang('NO'),
                    'SFS_TEST_CONFIDENCE' => isset($sfs_result['confidence']) ? $sfs_result['confidence'] : 0,
                    'SFS_TEST_FREQUENCY' => isset($sfs_result['frequency']) ? $sfs_result['frequency'] : 0,
                    'SFS_TEST_RESULT' => !empty($sfs_result['is_listed']) ? $user->lang('ACP_ANTISPAMGUARD_SFS_TEST_LISTED') : $user->lang('ACP_ANTISPAMGUARD_SFS_TEST_CLEAN'),
                ));
            }
        }

        if ($request->is_set_post('test_ip_whitelist'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $test_ip = trim($request->variable('antispamguard_ip_whitelist_test', ''));
            $whitelist = $request->variable('antispamguard_ip_whitelist', '', true);
            $mode = $request->variable('antispamguard_ip_whitelist_mode', isset($config['antispamguard_ip_whitelist_mode']) ? $config['antispamguard_ip_whitelist_mode'] : 'partial');

            if ($whitelist === '' && !empty($config['antispamguard_ip_whitelist']))
            {
                $whitelist = (string) $config['antispamguard_ip_whitelist'];
            }

            if ($whitelist === '' && !empty($config['antispamguard_trusted_ip_whitelist']))
            {
                $whitelist = (string) $config['antispamguard_trusted_ip_whitelist'];
            }

            $match = $this->get_ip_matcher()->whitelist_match($test_ip, $whitelist);

            $template->assign_vars(array(
                'S_IP_WHITELIST_TEST_DONE' => true,
                'IP_WHITELIST_TEST_IP' => $test_ip,
                'IP_WHITELIST_TEST_MATCHED' => $match['matched'] ? $user->lang('YES') : $user->lang('NO'),
                'IP_WHITELIST_TEST_ENTRY' => $match['entry'],
                'IP_WHITELIST_TEST_MODE' => ($mode === 'total') ? $user->lang('ACP_ANTISPAMGUARD_IP_WHITELIST_MODE_TOTAL') : $user->lang('ACP_ANTISPAMGUARD_IP_WHITELIST_MODE_PARTIAL'),
                'IP_WHITELIST_TEST_RESULT' => $match['matched'] ? $user->lang('ACP_ANTISPAMGUARD_IP_WHITELIST_TEST_MATCH') : $user->lang('ACP_ANTISPAMGUARD_IP_WHITELIST_TEST_NO_MATCH'),
            ));
        }

        if ($request->is_set_post('test_sfs_and_log'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $test_ip = trim($request->variable('antispamguard_sfs_test_ip', ''));
            $test_email = trim($request->variable('antispamguard_sfs_test_email', ''));
            $test_username = trim($request->variable('antispamguard_sfs_test_username', '', true));

            if ($test_ip === '')
            {
                $test_ip = !empty($user->ip) ? (string) $user->ip : '';
            }

            global $phpbb_container;

            $sfs_decision = $phpbb_container->get('mundophpbb.antispamguard.sfs_decision');
            $decision = $sfs_decision->should_block($test_ip, $test_email, $test_username, 'manual_acp_test', true);

            $template->assign_vars(array(
                'S_SFS_MANUAL_TEST_DONE' => true,
                'SFS_MANUAL_TEST_IP' => $test_ip,
                'SFS_MANUAL_TEST_EMAIL' => $test_email,
                'SFS_MANUAL_TEST_USERNAME' => $test_username,
                'SFS_MANUAL_TEST_LISTED_COUNT' => isset($decision['listed_count']) ? (int) $decision['listed_count'] : 0,
                'SFS_MANUAL_TEST_STRONG_HIT' => !empty($decision['strong_hit']) ? $user->lang('YES') : $user->lang('NO'),
                'SFS_MANUAL_TEST_BLOCK' => !empty($decision['block']) ? $user->lang('YES') : $user->lang('NO'),
                'SFS_MANUAL_TEST_ACTION_MODE' => isset($decision['action_mode']) ? $this->get_sfs_controller()->format_sfs_action_mode($decision['action_mode'], $user) : '',
                'SFS_MANUAL_TEST_LOGGED' => (!empty($decision['log_written']) || !empty($decision['logged'])),
                'SFS_MANUAL_TEST_LOG_ID' => isset($decision['log_id']) ? (int) $decision['log_id'] : 0,
                'SFS_MANUAL_TEST_LOG_STATUS' => (!empty($decision['log_written']) || !empty($decision['logged'])) ? $user->lang('ACP_ANTISPAMGUARD_SFS_MANUAL_TEST_LOGGED') : $user->lang('ACP_ANTISPAMGUARD_SFS_MANUAL_TEST_NOT_LOGGED'),
                'SFS_MANUAL_TEST_STATUS' => isset($decision['status']) ? $this->get_sfs_controller()->format_sfs_status($decision['status'], $user) : '',
            ));
        }

        if ($request->is_set_post('save_sfs'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $config->set('antispamguard_sfs_enabled', $request->variable('antispamguard_sfs_enabled', 0));
            $config->set('antispamguard_sfs_log_enabled', $request->variable('antispamguard_sfs_log_enabled', 0));
            $config->set('antispamguard_sfs_log_only_blocked', $request->variable('antispamguard_sfs_log_only_blocked', 0));
            $config->set('antispamguard_sfs_min_confidence', max(0, min(100, $request->variable('antispamguard_sfs_min_confidence', 80))));
            $config->set('antispamguard_sfs_min_frequency', max(1, $request->variable('antispamguard_sfs_min_frequency', 5)));
            $config->set('antispamguard_sfs_block_multiple_hits', $request->variable('antispamguard_sfs_block_multiple_hits', 0));
            $config->set('antispamguard_sfs_log_all_checks', $request->variable('antispamguard_sfs_log_all_checks', 0));
            $config->set('antispamguard_sfs_debug_log_all', $request->variable('antispamguard_sfs_debug_log_all', 0));
            $config->set('antispamguard_sfs_debug_localhost_only', $request->variable('antispamguard_sfs_debug_localhost_only', 1));
            $sfs_debug_duration = $request->variable('antispamguard_sfs_debug_duration', 0);
            if (!in_array((int) $sfs_debug_duration, array(0, 600, 1800, 3600, 86400), true))
            {
                $sfs_debug_duration = 0;
            }
            if (!empty($request->variable('antispamguard_sfs_debug_log_all', 0)))
            {
                if ((int) $sfs_debug_duration > 0)
                {
                    $config->set('antispamguard_sfs_debug_until', time() + (int) $sfs_debug_duration);
                }
            }
            else
            {
                $config->set('antispamguard_sfs_debug_until', 0);
            }
            $config->set('antispamguard_sfs_cleanup_interval', max(3600, $request->variable('antispamguard_sfs_cleanup_interval', 86400)));
            $config->set('antispamguard_sfs_log_retention_days', max(0, $request->variable('antispamguard_sfs_log_retention_days', 90)));
            $config->set('antispamguard_sfs_log_preserve_reviewed', $request->variable('antispamguard_sfs_log_preserve_reviewed', 0));
            if ($request->is_set_post('antispamguard_sfs_cache_ttl'))
            {
                $config->set('antispamguard_sfs_cache_ttl', max(60, $request->variable('antispamguard_sfs_cache_ttl', 86400)));
            }
            $config->set('antispamguard_sfs_error_cache_ttl', max(60, min(86400, $request->variable('antispamguard_sfs_error_cache_ttl', 300))));
            $config->set('antispamguard_sfs_http_timeout', max(1, min(10, $request->variable('antispamguard_sfs_http_timeout', 2))));
            $config->set('antispamguard_sfs_http_retries', max(0, min(2, $request->variable('antispamguard_sfs_http_retries', 1))));
            $config->set('antispamguard_sfs_http_max_response_bytes', max(4096, min(1048576, $request->variable('antispamguard_sfs_http_max_response_bytes', 262144))));
            $config->set('antispamguard_sfs_circuit_threshold', max(1, min(20, $request->variable('antispamguard_sfs_circuit_threshold', 3))));
            $config->set('antispamguard_sfs_circuit_cooldown', max(60, min(86400, $request->variable('antispamguard_sfs_circuit_cooldown', 300))));
            $config->set('antispamguard_sfs_whitelist_ips', $request->variable('antispamguard_sfs_whitelist_ips', '', true));
            $config->set('antispamguard_sfs_whitelist_emails', $request->variable('antispamguard_sfs_whitelist_emails', '', true));
            $config->set('antispamguard_sfs_whitelist_usernames', $request->variable('antispamguard_sfs_whitelist_usernames', '', true));
            $config->set('antispamguard_ip_reputation_weight_sfs', max(0, $request->variable('antispamguard_ip_reputation_weight_sfs', 2)));
            $config->set('antispamguard_decision_weight_sfs', max(0, $request->variable('antispamguard_decision_weight_sfs', 80)));
            $config->set('antispamguard_random_gmail_enabled', $request->variable('antispamguard_random_gmail_enabled', 1));
            $config->set('antispamguard_random_gmail_register_enabled', $request->variable('antispamguard_random_gmail_register_enabled', 0));

            $sfs_action_mode = $request->variable('antispamguard_sfs_action_mode', 'block');
            if (!in_array($sfs_action_mode, array('block', 'soft', 'log_only'), true))
            {
                $sfs_action_mode = 'block';
            }
            $config->set('antispamguard_sfs_action_mode', $sfs_action_mode);

            $sfs_api_key = trim($request->variable('antispamguard_sfs_api_key', '', true));
            if ($sfs_api_key !== '')
            {
                $config->set('antispamguard_sfs_api_key', $this->sanitize_secret($sfs_api_key, 191), true);
            }

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SAVED') . adm_back_link($this->u_action));
        }

        if ($mode === 'sfs')
        {
            $this->get_sfs_controller()->show_sfs($db, $request, $template, $user, $config, $table_prefix);
            return;
        }
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $field_name = trim($request->variable('antispamguard_hp_name', 'homepage'));
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $field_name))
            {
                trigger_error($user->lang('ACP_ANTISPAMGUARD_INVALID_FIELD') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $min_seconds = max(0, $request->variable('antispamguard_min_seconds', 3));
            $max_seconds = max($min_seconds + 1, $request->variable('antispamguard_max_seconds', 1800));

            $config->set('antispamguard_enabled', $request->variable('antispamguard_enabled', 0));
            $config->set('antispamguard_register_notice_enabled', $request->variable('antispamguard_register_notice_enabled', 0));
            $config->set('antispamguard_register_notice_text', $this->sanitize_register_notice_text($request->variable('antispamguard_register_notice_text', '', true), $user));
            $config->set('antispamguard_register_audit_soft_signals', $request->variable('antispamguard_register_audit_soft_signals', 0));
            $config->set('antispamguard_hp_name', $field_name);
            $config->set('antispamguard_hp_dynamic_enabled', $request->variable('antispamguard_hp_dynamic_enabled', 1));
            $hp_dynamic_prefix = trim($request->variable('antispamguard_hp_dynamic_prefix', 'asg_hp'));
            if ($hp_dynamic_prefix === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,20}$/', $hp_dynamic_prefix))
            {
                trigger_error($user->lang('ACP_ANTISPAMGUARD_INVALID_DYNAMIC_PREFIX') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $config->set('antispamguard_hp_dynamic_prefix', $hp_dynamic_prefix);
            $config->set('antispamguard_hp_camouflage_enabled', $request->variable('antispamguard_hp_camouflage_enabled', 1));
            $config->set('antispamguard_protect_posts', $request->variable('antispamguard_protect_posts', 0));
            $config->set('antispamguard_protect_contact', $request->variable('antispamguard_protect_contact', 0));
            $config->set('antispamguard_protect_pm', $request->variable('antispamguard_protect_pm', 0));
            $config->set('antispamguard_posts_guests_only', $request->variable('antispamguard_posts_guests_only', 1));
            $config->set('antispamguard_bypass_group_ids', $this->normalize_group_ids($request->variable('antispamguard_bypass_group_ids', '')));
            $config->set('antispamguard_content_filter_enabled', $request->variable('antispamguard_content_filter_enabled', 0));
            $config->set('antispamguard_blocked_keywords', $this->normalize_blocked_keywords($request->variable('antispamguard_blocked_keywords', '', true)));
            $config->set('antispamguard_max_urls', max(0, $request->variable('antispamguard_max_urls', 0)));
            $config->set('antispamguard_ip_whitelist', $this->normalize_ip_list($request->variable('antispamguard_ip_whitelist', '', true)));
            $ip_whitelist_mode = $request->variable('antispamguard_ip_whitelist_mode', 'partial');
            if (!in_array($ip_whitelist_mode, array('partial', 'total'), true))
            {
                $ip_whitelist_mode = 'partial';
            }
            $config->set('antispamguard_ip_whitelist_mode', $ip_whitelist_mode);
            $config->set('antispamguard_ip_blacklist', $this->normalize_ip_list($request->variable('antispamguard_ip_blacklist', '', true)));
            // The old log-count limiter was replaced by antispamguard_ip_rate.
            $config->set('antispamguard_rate_limit_enabled', 0);
            $config->set('antispamguard_rate_limit_max_attempts', max(1, $request->variable('antispamguard_rate_limit_max_attempts', 5)));
            $config->set('antispamguard_rate_limit_window', max(60, $request->variable('antispamguard_rate_limit_window', 3600)));
            $config->set('antispamguard_log_retention_enabled', $request->variable('antispamguard_log_retention_enabled', 0));
            $config->set('antispamguard_silent_mode', $request->variable('antispamguard_silent_mode', 0));
            $config->set('antispamguard_simulation_mode', $request->variable('antispamguard_simulation_mode', 0));
            $config->set('antispamguard_log_retention_days', max(1, $request->variable('antispamguard_log_retention_days', 30)));
            $config->set('antispamguard_min_seconds', $min_seconds);
            $config->set('antispamguard_max_seconds', $max_seconds);
            $config->set('antispamguard_max_form_age', max(0, $request->variable('antispamguard_max_form_age', 3600)));
            $config->set('antispamguard_ip_reputation_enabled', $request->variable('antispamguard_ip_reputation_enabled', 1));
            $config->set('antispamguard_ip_reputation_threshold', max(1, $request->variable('antispamguard_ip_reputation_threshold', 20)));
            $config->set('antispamguard_ip_reputation_decay_interval', max(0, $request->variable('antispamguard_ip_reputation_decay_interval', 600)));
            $config->set('antispamguard_ip_reputation_ttl', max(3600, $request->variable('antispamguard_ip_reputation_ttl', 86400)));
            $config->set('antispamguard_ip_reputation_cleanup_interval', max(3600, $request->variable('antispamguard_ip_reputation_cleanup_interval', 86400)));
            $config->set('antispamguard_ip_reputation_weight_honeypot', max(0, $request->variable('antispamguard_ip_reputation_weight_honeypot', 3)));
            $config->set('antispamguard_ip_reputation_weight_timestamp_fast', max(0, $request->variable('antispamguard_ip_reputation_weight_timestamp_fast', 2)));
            $config->set('antispamguard_ip_reputation_weight_timestamp_expired', max(0, $request->variable('antispamguard_ip_reputation_weight_timestamp_expired', 1)));

            $config->set('antispamguard_ip_reputation_weight_rate_limit', max(0, $request->variable('antispamguard_ip_reputation_weight_rate_limit', 3)));
            $config->set('antispamguard_ip_rate_limit_enabled', $request->variable('antispamguard_ip_rate_limit_enabled', 0));
            $config->set('antispamguard_ip_rate_limit_window', max(1, $request->variable('antispamguard_ip_rate_limit_window', 60)));
            $config->set('antispamguard_ip_rate_limit_max_hits', max(1, $request->variable('antispamguard_ip_rate_limit_max_hits', 5)));
            $config->set('antispamguard_ip_rate_limit_cleanup_interval', max(300, $request->variable('antispamguard_ip_rate_limit_cleanup_interval', 3600)));
            $ip_rate_limit_action = $request->variable('antispamguard_ip_rate_limit_action', 'block');
            if (!in_array($ip_rate_limit_action, array('block', 'score', 'log_only'), true))
            {
                $ip_rate_limit_action = 'block';
            }
            $config->set('antispamguard_ip_rate_limit_action', $ip_rate_limit_action);
            if ($request->is_set_post('antispamguard_decision_engine_enabled'))
            {
                $config->set('antispamguard_decision_engine_enabled', $request->variable('antispamguard_decision_engine_enabled', 1));
            }
            if ($request->is_set_post('antispamguard_decision_score_log'))
            {
                $config->set('antispamguard_decision_score_log', max(0, $request->variable('antispamguard_decision_score_log', 30)));
            }
            if ($request->is_set_post('antispamguard_decision_score_block'))
            {
                $config->set('antispamguard_decision_score_block', max(1, $request->variable('antispamguard_decision_score_block', 80)));
            }
            if ($request->is_set_post('antispamguard_decision_weight_honeypot'))
            {
                $config->set('antispamguard_decision_weight_honeypot', max(0, $request->variable('antispamguard_decision_weight_honeypot', 100)));
            }
            if ($request->is_set_post('antispamguard_decision_weight_timestamp_fast'))
            {
                $config->set('antispamguard_decision_weight_timestamp_fast', max(0, $request->variable('antispamguard_decision_weight_timestamp_fast', 15)));
            }
            if ($request->is_set_post('antispamguard_decision_weight_timestamp_expired'))
            {
                $config->set('antispamguard_decision_weight_timestamp_expired', max(0, $request->variable('antispamguard_decision_weight_timestamp_expired', 5)));
            }
            if ($request->is_set_post('antispamguard_decision_weight_rate_limit'))
            {
                $config->set('antispamguard_decision_weight_rate_limit', max(0, $request->variable('antispamguard_decision_weight_rate_limit', 25)));
            }
            if ($request->is_set_post('antispamguard_decision_weight_ip_reputation'))
            {
                $config->set('antispamguard_decision_weight_ip_reputation', max(0, $request->variable('antispamguard_decision_weight_ip_reputation', 0)));
            }
            $config->set('antispamguard_slowspam_enabled', $request->variable('antispamguard_slowspam_enabled', 1));
            $config->set('antispamguard_slowspam_window', max(60, $request->variable('antispamguard_slowspam_window', 1800)));
            $config->set('antispamguard_slowspam_threshold', max(1, $request->variable('antispamguard_slowspam_threshold', 8)));
            $config->set('antispamguard_slowspam_prune_after', max(3600, $request->variable('antispamguard_slowspam_prune_after', 86400)));
            $config->set('antispamguard_slowspam_cleanup_interval', max(3600, $request->variable('antispamguard_slowspam_cleanup_interval', 86400)));
            $config->set('antispamguard_alerts_enabled', $request->variable('antispamguard_alerts_enabled', 1));
            $config->set('antispamguard_alerts_retention', max(3600, $request->variable('antispamguard_alerts_retention', 604800)));
            $config->set('antispamguard_decision_weight_slowspam', max(0, $request->variable('antispamguard_decision_weight_slowspam', 15)));
            if ($request->is_set_post('antispamguard_trusted_ip_whitelist'))
            {
                $config->set('antispamguard_trusted_ip_whitelist', $this->normalize_ip_list($request->variable('antispamguard_trusted_ip_whitelist', '', true)));
            }
            if ($request->is_set_post('antispamguard_autoban_enabled'))
            {
                $config->set('antispamguard_autoban_enabled', $request->variable('antispamguard_autoban_enabled', 0));
            }
            if ($request->is_set_post('antispamguard_autoban_threshold'))
            {
                $config->set('antispamguard_autoban_threshold', max(1, $request->variable('antispamguard_autoban_threshold', 120)));
            }
            if ($request->is_set_post('antispamguard_autoban_duration'))
            {
                $config->set('antispamguard_autoban_duration', max(60, $request->variable('antispamguard_autoban_duration', 86400)));
            }
            if ($request->is_set_post('antispamguard_shadowban_enabled'))
            {
                $config->set('antispamguard_shadowban_enabled', $request->variable('antispamguard_shadowban_enabled', 0));
            }
            if ($request->is_set_post('antispamguard_shadowban_threshold'))
            {
                $config->set('antispamguard_shadowban_threshold', max(1, $request->variable('antispamguard_shadowban_threshold', 80)));
            }
            trigger_error($user->lang('ACP_ANTISPAMGUARD_SAVED') . adm_back_link($this->u_action));
        }

        $sfs_cache_total = 0;
        $sfs_cache_expired = 0;
        $sfs_logs_total = 0;

        $sql = 'SELECT COUNT(cache_id) AS total_cache
            FROM ' . $table_prefix . 'antispamguard_sfs_cache';
        $result = $db->sql_query($sql);
        $sfs_cache_total = (int) $db->sql_fetchfield('total_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(cache_id) AS expired_cache
            FROM ' . $table_prefix . 'antispamguard_sfs_cache
            WHERE expires_at <= ' . time();
        $result = $db->sql_query($sql);
        $sfs_cache_expired = (int) $db->sql_fetchfield('expired_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(log_id) AS total_logs
            FROM ' . $table_prefix . 'antispamguard_sfs_log';
        $result = $db->sql_query($sql);
        $sfs_logs_total = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $ip_reputation_total = 0;
        $ip_reputation_blocked = 0;

        $sql = 'SELECT COUNT(score_id) AS total_scores
            FROM ' . $table_prefix . 'antispamguard_ip_score';
        $result = $db->sql_query($sql);
        $ip_reputation_total = (int) $db->sql_fetchfield('total_scores');
        $db->sql_freeresult($result);

        $ip_reputation_threshold = isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 20;
        $sql = 'SELECT COUNT(score_id) AS blocked_scores
            FROM ' . $table_prefix . 'antispamguard_ip_score
            WHERE score >= ' . (int) $ip_reputation_threshold;
        $result = $db->sql_query($sql);
        $ip_reputation_blocked = (int) $db->sql_fetchfield('blocked_scores');
        $db->sql_freeresult($result);

        $ip_reputation_expired = 0;
        $sql = 'SELECT COUNT(score_id) AS expired_scores
            FROM ' . $table_prefix . 'antispamguard_ip_score
            WHERE expires_at <= ' . time();
        $result = $db->sql_query($sql);
        $ip_reputation_expired = (int) $db->sql_fetchfield('expired_scores');
        $db->sql_freeresult($result);

        $ip_rate_total = 0;
        $sql = 'SELECT COUNT(rate_id) AS total_rates
            FROM ' . $table_prefix . 'antispamguard_ip_rate';
        $result = $db->sql_query($sql);
        $ip_rate_total = (int) $db->sql_fetchfield('total_rates');
        $db->sql_freeresult($result);

        $ip_rate_expired = 0;
        $sql = 'SELECT COUNT(rate_id) AS expired_rates
            FROM ' . $table_prefix . 'antispamguard_ip_rate
            WHERE expires_at <= ' . time();
        $result = $db->sql_query($sql);
        $ip_rate_expired = (int) $db->sql_fetchfield('expired_rates');
        $db->sql_freeresult($result);

        $slowspam_activity_total = 0;
        $sql = 'SELECT COUNT(activity_id) AS total_activity
            FROM ' . $table_prefix . 'antispamguard_activity_log';
        $result = $db->sql_query($sql);
        $slowspam_activity_total = (int) $db->sql_fetchfield('total_activity');
        $db->sql_freeresult($result);

        $slowspam_activity_expired = 0;
        $slowspam_prune_after = isset($config['antispamguard_slowspam_prune_after']) ? max(3600, (int) $config['antispamguard_slowspam_prune_after']) : 86400;
        $sql = 'SELECT COUNT(activity_id) AS expired_activity
            FROM ' . $table_prefix . 'antispamguard_activity_log
            WHERE created_at <= ' . (time() - $slowspam_prune_after);
        $result = $db->sql_query($sql);
        $slowspam_activity_expired = (int) $db->sql_fetchfield('expired_activity');
        $db->sql_freeresult($result);

        $alerts_total = 0;
        $sql = 'SELECT COUNT(alert_id) AS total_alerts
            FROM ' . $table_prefix . 'antispamguard_alerts';
        $result = $db->sql_query($sql);
        $alerts_total = (int) $db->sql_fetchfield('total_alerts');
        $db->sql_freeresult($result);

        $alerts_unread = 0;
        $sql = 'SELECT COUNT(alert_id) AS unread_alerts
            FROM ' . $table_prefix . 'antispamguard_alerts
            WHERE is_read = 0';
        $result = $db->sql_query($sql);
        $alerts_unread = (int) $db->sql_fetchfield('unread_alerts');
        $db->sql_freeresult($result);

        $alerts_expired = 0;
        $alerts_retention = isset($config['antispamguard_alerts_retention']) ? max(3600, (int) $config['antispamguard_alerts_retention']) : 604800;
        $sql = 'SELECT COUNT(alert_id) AS expired_alerts
            FROM ' . $table_prefix . 'antispamguard_alerts
            WHERE created_at <= ' . (time() - $alerts_retention);
        $result = $db->sql_query($sql);
        $alerts_expired = (int) $db->sql_fetchfield('expired_alerts');
        $db->sql_freeresult($result);

        $register_notice_text = isset($config['antispamguard_register_notice_text']) ? (string) $config['antispamguard_register_notice_text'] : '';
        if (trim($register_notice_text) === '')
        {
            $register_notice_text = $this->get_default_register_notice_text($user);
        }
        $register_notice_text = $this->sanitize_register_notice_text($register_notice_text, $user);
        $activation_self = defined('USER_ACTIVATION_SELF') ? USER_ACTIVATION_SELF : 1;
        $email_delivery_enabled = !empty($config['email_enable']);
        $email_activation_enabled = isset($config['require_activation'])
            && (int) $config['require_activation'] === (int) $activation_self;

        $template->assign_vars(array(
            'S_SETTINGS' => true,
            'SFS_DEBUG_LOG_ALL' => !empty($config['antispamguard_sfs_debug_log_all']),
            'SFS_DEBUG_LOCALHOST_ONLY' => !isset($config['antispamguard_sfs_debug_localhost_only']) || !empty($config['antispamguard_sfs_debug_localhost_only']),
            'SFS_DEBUG_UNTIL' => isset($config['antispamguard_sfs_debug_until']) ? (int) $config['antispamguard_sfs_debug_until'] : 0,
            'SFS_DEBUG_UNTIL_FORMATTED' => !empty($config['antispamguard_sfs_debug_until']) ? $user->format_date((int) $config['antispamguard_sfs_debug_until']) : '',
            'SFS_LOG_ALL_CHECKS' => !empty($config['antispamguard_sfs_log_all_checks']),
            'SFS_CACHE_TTL' => isset($config['antispamguard_sfs_cache_ttl']) ? (int) $config['antispamguard_sfs_cache_ttl'] : 86400,
            'AUTOBAN_ENABLED' => !empty($config['antispamguard_autoban_enabled']),
            'AUTOBAN_THRESHOLD' => isset($config['antispamguard_autoban_threshold']) ? (int) $config['antispamguard_autoban_threshold'] : 120,
            'AUTOBAN_DURATION' => isset($config['antispamguard_autoban_duration']) ? (int) $config['antispamguard_autoban_duration'] : 86400,
            'SHADOWBAN_ENABLED' => !empty($config['antispamguard_shadowban_enabled']),
            'SHADOWBAN_THRESHOLD' => isset($config['antispamguard_shadowban_threshold']) ? (int) $config['antispamguard_shadowban_threshold'] : 80,
            'DECISION_ENGINE_ENABLED' => !isset($config['antispamguard_decision_engine_enabled']) || !empty($config['antispamguard_decision_engine_enabled']),
            'DECISION_SCORE_LOG' => isset($config['antispamguard_decision_score_log']) ? (int) $config['antispamguard_decision_score_log'] : 25,
            'DECISION_SCORE_BLOCK' => isset($config['antispamguard_decision_score_block']) ? (int) $config['antispamguard_decision_score_block'] : 80,
            'DECISION_WEIGHT_HONEYPOT' => isset($config['antispamguard_decision_weight_honeypot']) ? (int) $config['antispamguard_decision_weight_honeypot'] : 100,
            'DECISION_WEIGHT_TIMESTAMP_FAST' => isset($config['antispamguard_decision_weight_timestamp_fast']) ? (int) $config['antispamguard_decision_weight_timestamp_fast'] : 15,
            'DECISION_WEIGHT_TIMESTAMP_EXPIRED' => isset($config['antispamguard_decision_weight_timestamp_expired']) ? (int) $config['antispamguard_decision_weight_timestamp_expired'] : 5,
            'DECISION_WEIGHT_RATE_LIMIT' => isset($config['antispamguard_decision_weight_rate_limit']) ? (int) $config['antispamguard_decision_weight_rate_limit'] : 25,
            'DECISION_WEIGHT_SFS' => isset($config['antispamguard_decision_weight_sfs']) ? (int) $config['antispamguard_decision_weight_sfs'] : 80,
            'DECISION_WEIGHT_IP_REPUTATION' => isset($config['antispamguard_decision_weight_ip_reputation']) ? (int) $config['antispamguard_decision_weight_ip_reputation'] : 0,
            'DECISION_WEIGHT_SLOWSPAM' => isset($config['antispamguard_decision_weight_slowspam']) ? (int) $config['antispamguard_decision_weight_slowspam'] : 15,
            'SLOWSPAM_ENABLED' => !isset($config['antispamguard_slowspam_enabled']) || !empty($config['antispamguard_slowspam_enabled']),
            'SLOWSPAM_WINDOW' => isset($config['antispamguard_slowspam_window']) ? (int) $config['antispamguard_slowspam_window'] : 1800,
            'SLOWSPAM_THRESHOLD' => isset($config['antispamguard_slowspam_threshold']) ? (int) $config['antispamguard_slowspam_threshold'] : 8,
            'SLOWSPAM_PRUNE_AFTER' => isset($config['antispamguard_slowspam_prune_after']) ? (int) $config['antispamguard_slowspam_prune_after'] : 86400,
            'SLOWSPAM_CLEANUP_INTERVAL' => isset($config['antispamguard_slowspam_cleanup_interval']) ? (int) $config['antispamguard_slowspam_cleanup_interval'] : 86400,
            'SLOWSPAM_ACTIVITY_TOTAL' => $slowspam_activity_total,
            'SLOWSPAM_ACTIVITY_EXPIRED' => $slowspam_activity_expired,
            'SLOWSPAM_CLEANUP_LAST_GC' => !empty($config['antispamguard_slowspam_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_slowspam_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'ALERTS_ENABLED' => !isset($config['antispamguard_alerts_enabled']) || !empty($config['antispamguard_alerts_enabled']),
            'ALERTS_RETENTION' => isset($config['antispamguard_alerts_retention']) ? (int) $config['antispamguard_alerts_retention'] : 604800,
            'ALERTS_TOTAL' => $alerts_total,
            'ALERTS_UNREAD' => $alerts_unread,
            'ALERTS_EXPIRED' => $alerts_expired,
            'ALERTS_LAST_GC' => !empty($config['antispamguard_alerts_last_gc']) ? $user->format_date((int) $config['antispamguard_alerts_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'U_ACTION' => $this->u_action,
            'IP_WHITELIST_MODE' => isset($config['antispamguard_ip_whitelist_mode']) ? $config['antispamguard_ip_whitelist_mode'] : 'partial',
            'TRUSTED_IP_WHITELIST' => isset($config['antispamguard_trusted_ip_whitelist']) ? $config['antispamguard_trusted_ip_whitelist'] : '',
            'SFS_CACHE_TOTAL' => $sfs_cache_total,
            'SFS_CACHE_EXPIRED' => $sfs_cache_expired,
            'SFS_LOGS_TOTAL' => $sfs_logs_total,
            'SFS_CLEANUP_INTERVAL' => isset($config['antispamguard_sfs_cleanup_interval']) ? (int) $config['antispamguard_sfs_cleanup_interval'] : 86400,
            'SFS_LOG_RETENTION_DAYS' => isset($config['antispamguard_sfs_log_retention_days']) ? (int) $config['antispamguard_sfs_log_retention_days'] : 90,
            'SFS_LOG_PRESERVE_REVIEWED' => !empty($config['antispamguard_sfs_log_preserve_reviewed']),
            'SFS_CLEANUP_LAST_GC' => !empty($config['antispamguard_sfs_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_sfs_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'ANTISPAMGUARD_ENABLED' => !empty($config['antispamguard_enabled']),
            'ANTISPAMGUARD_REGISTER_NOTICE_ENABLED' => !empty($config['antispamguard_register_notice_enabled']),
            'ANTISPAMGUARD_REGISTER_NOTICE_TEXT' => $register_notice_text,
            'ANTISPAMGUARD_REGISTER_AUDIT_SOFT_SIGNALS' => !isset($config['antispamguard_register_audit_soft_signals']) || !empty($config['antispamguard_register_audit_soft_signals']),
            'EMAIL_DELIVERY_ENABLED' => $email_delivery_enabled,
            'EMAIL_ACTIVATION_ENABLED' => $email_activation_enabled,
            'EMAIL_VERIFICATION_READY' => $email_delivery_enabled && $email_activation_enabled,
            'ANTISPAMGUARD_HP_NAME' => isset($config['antispamguard_hp_name']) ? $config['antispamguard_hp_name'] : 'homepage',
            'ANTISPAMGUARD_HP_DYNAMIC_ENABLED' => !isset($config['antispamguard_hp_dynamic_enabled']) || !empty($config['antispamguard_hp_dynamic_enabled']),
            'ANTISPAMGUARD_HP_DYNAMIC_PREFIX' => isset($config['antispamguard_hp_dynamic_prefix']) ? $config['antispamguard_hp_dynamic_prefix'] : 'asg_hp',
            'ANTISPAMGUARD_HP_CAMOUFLAGE_ENABLED' => !isset($config['antispamguard_hp_camouflage_enabled']) || !empty($config['antispamguard_hp_camouflage_enabled']),
            'ANTISPAMGUARD_PROTECT_POSTS' => !empty($config['antispamguard_protect_posts']),
            'ANTISPAMGUARD_PROTECT_CONTACT' => !empty($config['antispamguard_protect_contact']),
            'ANTISPAMGUARD_PROTECT_PM' => !empty($config['antispamguard_protect_pm']),
            'ANTISPAMGUARD_POSTS_GUESTS_ONLY' => !isset($config['antispamguard_posts_guests_only']) || !empty($config['antispamguard_posts_guests_only']),
            'ANTISPAMGUARD_BYPASS_GROUP_IDS' => isset($config['antispamguard_bypass_group_ids']) ? $config['antispamguard_bypass_group_ids'] : '',
            'ANTISPAMGUARD_CONTENT_FILTER_ENABLED' => !empty($config['antispamguard_content_filter_enabled']),
            'ANTISPAMGUARD_BLOCKED_KEYWORDS' => isset($config['antispamguard_blocked_keywords']) ? $config['antispamguard_blocked_keywords'] : '',
            'ANTISPAMGUARD_MAX_URLS' => isset($config['antispamguard_max_urls']) ? (int) $config['antispamguard_max_urls'] : 0,
            'ANTISPAMGUARD_IP_WHITELIST' => isset($config['antispamguard_ip_whitelist']) ? $config['antispamguard_ip_whitelist'] : '',
            'ANTISPAMGUARD_IP_BLACKLIST' => isset($config['antispamguard_ip_blacklist']) ? $config['antispamguard_ip_blacklist'] : '',
            'ANTISPAMGUARD_RATE_LIMIT_ENABLED' => !empty($config['antispamguard_rate_limit_enabled']),
            'ANTISPAMGUARD_RATE_LIMIT_MAX_ATTEMPTS' => isset($config['antispamguard_rate_limit_max_attempts']) ? (int) $config['antispamguard_rate_limit_max_attempts'] : 5,
            'ANTISPAMGUARD_RATE_LIMIT_WINDOW' => isset($config['antispamguard_rate_limit_window']) ? (int) $config['antispamguard_rate_limit_window'] : 3600,
            'IP_REPUTATION_ENABLED' => !isset($config['antispamguard_ip_reputation_enabled']) || !empty($config['antispamguard_ip_reputation_enabled']),
            'IP_REPUTATION_THRESHOLD' => isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 20,
            'IP_REPUTATION_DECAY_INTERVAL' => isset($config['antispamguard_ip_reputation_decay_interval']) ? (int) $config['antispamguard_ip_reputation_decay_interval'] : 600,
            'IP_REPUTATION_TTL' => isset($config['antispamguard_ip_reputation_ttl']) ? (int) $config['antispamguard_ip_reputation_ttl'] : 86400,
            'IP_REPUTATION_CLEANUP_INTERVAL' => isset($config['antispamguard_ip_reputation_cleanup_interval']) ? (int) $config['antispamguard_ip_reputation_cleanup_interval'] : 86400,
            'IP_REPUTATION_WEIGHT_HONEYPOT' => isset($config['antispamguard_ip_reputation_weight_honeypot']) ? (int) $config['antispamguard_ip_reputation_weight_honeypot'] : 3,
            'IP_REPUTATION_WEIGHT_TIMESTAMP_FAST' => isset($config['antispamguard_ip_reputation_weight_timestamp_fast']) ? (int) $config['antispamguard_ip_reputation_weight_timestamp_fast'] : 2,
            'IP_REPUTATION_WEIGHT_TIMESTAMP_EXPIRED' => isset($config['antispamguard_ip_reputation_weight_timestamp_expired']) ? (int) $config['antispamguard_ip_reputation_weight_timestamp_expired'] : 1,
            'IP_REPUTATION_WEIGHT_RATE_LIMIT' => isset($config['antispamguard_ip_reputation_weight_rate_limit']) ? (int) $config['antispamguard_ip_reputation_weight_rate_limit'] : 3,
            'IP_REPUTATION_TOTAL' => $ip_reputation_total,
            'IP_REPUTATION_BLOCKED' => $ip_reputation_blocked,
            'IP_REPUTATION_EXPIRED' => $ip_reputation_expired,
            'IP_REPUTATION_CLEANUP_LAST_GC' => !empty($config['antispamguard_ip_reputation_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_ip_reputation_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'IP_RATE_LIMIT_ENABLED' => !empty($config['antispamguard_ip_rate_limit_enabled']),
            'IP_RATE_LIMIT_WINDOW' => isset($config['antispamguard_ip_rate_limit_window']) ? (int) $config['antispamguard_ip_rate_limit_window'] : 60,
            'IP_RATE_LIMIT_MAX_HITS' => isset($config['antispamguard_ip_rate_limit_max_hits']) ? (int) $config['antispamguard_ip_rate_limit_max_hits'] : 5,
            'IP_RATE_LIMIT_ACTION' => isset($config['antispamguard_ip_rate_limit_action']) ? (string) $config['antispamguard_ip_rate_limit_action'] : 'block',
            'IP_RATE_LIMIT_CLEANUP_INTERVAL' => isset($config['antispamguard_ip_rate_limit_cleanup_interval']) ? (int) $config['antispamguard_ip_rate_limit_cleanup_interval'] : 3600,
            'IP_RATE_LIMIT_CLEANUP_LAST_GC' => !empty($config['antispamguard_ip_rate_limit_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_ip_rate_limit_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'IP_RATE_LIMIT_TOTAL' => $ip_rate_total,
            'IP_RATE_LIMIT_EXPIRED' => $ip_rate_expired,
            'ANTISPAMGUARD_MAX_FORM_AGE' => isset($config['antispamguard_max_form_age']) ? (int) $config['antispamguard_max_form_age'] : 3600,
            'ANTISPAMGUARD_LOG_RETENTION_ENABLED' => !empty($config['antispamguard_log_retention_enabled']),
            'ANTISPAMGUARD_SILENT_MODE' => !empty($config['antispamguard_silent_mode']),
            'ANTISPAMGUARD_SIMULATION_MODE' => !empty($config['antispamguard_simulation_mode']),
            'ANTISPAMGUARD_LOG_RETENTION_DAYS' => isset($config['antispamguard_log_retention_days']) ? (int) $config['antispamguard_log_retention_days'] : 30,
            'ANTISPAMGUARD_CRON_LAST_PRUNE' => !empty($config['antispamguard_cron_last_prune']) ? $user->format_date((int) $config['antispamguard_cron_last_prune']) : $user->lang('ACP_ANTISPAMGUARD_CRON_NEVER'),
            'ANTISPAMGUARD_MIN_SECONDS' => isset($config['antispamguard_min_seconds']) ? (int) $config['antispamguard_min_seconds'] : 3,
            'ANTISPAMGUARD_MAX_SECONDS' => isset($config['antispamguard_max_seconds']) ? (int) $config['antispamguard_max_seconds'] : 1800,
            'ANTISPAMGUARD_IMPORT_SETTINGS' => '',
            'ANTISPAMGUARD_SFS_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
            'SFS_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? (string) $config['antispamguard_sfs_action_mode'] : 'block',
            'RANDOM_GMAIL_CONTACT_ENABLED' => !isset($config['antispamguard_random_gmail_enabled']) || !empty($config['antispamguard_random_gmail_enabled']),
            'RANDOM_GMAIL_REGISTER_ENABLED' => !empty($config['antispamguard_random_gmail_register_enabled']),
            'SFS_API_KEY_CONFIGURED' => !empty($config['antispamguard_sfs_api_key']),
            'SFS_API_KEY_MASKED' => !empty($config['antispamguard_sfs_api_key']) ? $this->mask_secret((string) $config['antispamguard_sfs_api_key']) : '',
            'ANTISPAMGUARD_SFS_LOG_ENABLED' => !isset($config['antispamguard_sfs_log_enabled']) || !empty($config['antispamguard_sfs_log_enabled']),
            'ANTISPAMGUARD_SFS_LOG_ONLY_BLOCKED' => !empty($config['antispamguard_sfs_log_only_blocked']),
            'ANTISPAMGUARD_SFS_MIN_CONFIDENCE' => isset($config['antispamguard_sfs_min_confidence']) ? (int) $config['antispamguard_sfs_min_confidence'] : 80,
            'ANTISPAMGUARD_SFS_MIN_FREQUENCY' => isset($config['antispamguard_sfs_min_frequency']) ? (int) $config['antispamguard_sfs_min_frequency'] : 5,
            'ANTISPAMGUARD_SFS_BLOCK_MULTIPLE_HITS' => !empty($config['antispamguard_sfs_block_multiple_hits']),
        ));
    }


    protected function get_default_register_notice_text($user = null)
    {
        if ($user !== null)
        {
            return (string) $user->lang('ACP_ANTISPAMGUARD_REGISTER_NOTICE_DEFAULT');
        }

        return 'Este fórum usa proteção antispam automática para reduzir cadastros abusivos e proteger a comunidade.';
    }

    protected function sanitize_register_notice_text($value, $user = null)
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        if ($value === '')
        {
            $value = $this->get_default_register_notice_text($user);
        }

        return $this->truncate_for_storage($value, 255);
    }

    protected function truncate_for_storage($value, $max_length)
    {
        $value = (string) $value;
        $max_length = (int) $max_length;

        if ($max_length <= 0)
        {
            return '';
        }

        if (function_exists('utf8_strlen') && function_exists('utf8_substr'))
        {
            return \utf8_strlen($value) > $max_length ? \utf8_substr($value, 0, $max_length) : $value;
        }

        return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
    }

    protected function normalize_blocked_keywords($raw_keywords)
    {
        return $this->get_settings_helper()->normalize_blocked_keywords($raw_keywords);
    }

    protected function normalize_ip_list($raw_ips)
    {
        return $this->get_settings_helper()->normalize_ip_list($raw_ips);
    }

    protected function normalize_group_ids($raw_group_ids)
    {
        return $this->get_settings_helper()->normalize_group_ids($raw_group_ids);
    }

    protected function sanitize_secret($value, $max_length)
    {
        return $this->get_settings_helper()->sanitize_secret($value, $max_length);
    }

    protected function mask_secret($value)
    {
        return $this->get_settings_helper()->mask_secret($value);
    }

    protected function get_settings_keys()
    {
        return $this->get_settings_helper()->get_settings_keys();
    }

    protected function get_extension_version($config = null)
    {
        return $this->get_settings_helper()->get_extension_version($config);
    }

    protected function export_settings_json($config)
    {
        $data = $this->get_settings_helper()->export_settings_payload($config);

        while (ob_get_level())
        {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="antispamguard_settings_' . gmdate('Y-m-d_H-i-s') . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');
        if ($output)
        {
            fwrite($output, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fclose($output);
        }

        garbage_collection();
        exit_handler();
    }

    protected function import_settings_json($config, $raw_settings)
    {
        return $this->get_settings_helper()->import_settings_json($config, $raw_settings);
    }

    protected function table_exists($db, $table)
    {
        $sql = "SHOW TABLES LIKE '" . $db->sql_escape($table) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return !empty($row);
    }

    protected function show_about($db, $template, $user, $config, $table_prefix)
    {
        $table = $table_prefix . 'antispamguard_log';
        $table_ok = $this->table_exists($db, $table);
        $total_logs = 0;
        $last_log = '';
        $db_error = '';

        if ($table_ok)
        {
            $sql = 'SELECT COUNT(log_id) AS total_logs, MAX(log_time) AS last_log_time FROM ' . $table;
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            $total_logs = (int) $row['total_logs'];
            $last_log = !empty($row['last_log_time']) ? $user->format_date((int) $row['last_log_time']) : $user->lang('ACP_ANTISPAMGUARD_NONE');
        }
        else
        {
            $db_error = $user->lang('ACP_ANTISPAMGUARD_TABLE_MISSING_EXPLAIN', $table);
            $last_log = $user->lang('ACP_ANTISPAMGUARD_NOT_AVAILABLE');
        }

        $required_configs = array(
            'antispamguard_enabled',
            'antispamguard_register_notice_enabled',
            'antispamguard_register_notice_text',
            'antispamguard_register_audit_soft_signals',
            'antispamguard_hp_name',
            'antispamguard_min_seconds',
            'antispamguard_max_seconds',
            'antispamguard_protect_posts',
            'antispamguard_protect_contact',
            'antispamguard_protect_pm',
            'antispamguard_log_retention_enabled',
            'antispamguard_log_retention_days',
            'antispamguard_cron_last_prune',
            'antispamguard_simulation_mode',
        );

        $missing_configs = array();
        foreach ($required_configs as $config_name)
        {
            if (!isset($config[$config_name]))
            {
                $missing_configs[] = $config_name;
            }
        }

        $cron_last = !empty($config['antispamguard_cron_last_prune']) ? $user->format_date((int) $config['antispamguard_cron_last_prune']) : $user->lang('ACP_ANTISPAMGUARD_CRON_NEVER');
        $retention_enabled = !empty($config['antispamguard_log_retention_enabled']);

        $sfs_diag_cache_total = 0;
        $sfs_diag_logs_total = 0;

        $sql = 'SELECT COUNT(cache_id) AS total_cache
            FROM ' . $table_prefix . 'antispamguard_sfs_cache';
        $result = $db->sql_query($sql);
        $sfs_diag_cache_total = (int) $db->sql_fetchfield('total_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(log_id) AS total_logs
            FROM ' . $table_prefix . 'antispamguard_sfs_log';
        $result = $db->sql_query($sql);
        $sfs_diag_logs_total = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $template->assign_vars(array(
            'S_ABOUT' => true,
            'SFS_DIAG_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
            'SFS_DIAG_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? $config['antispamguard_sfs_action_mode'] : 'block',
            'SFS_DIAG_CACHE_TOTAL' => $sfs_diag_cache_total,
            'SFS_DIAG_LOGS_TOTAL' => $sfs_diag_logs_total,
            'SFS_DIAG_API_KEY_CONFIGURED' => !empty($config['antispamguard_sfs_api_key']) ? $user->lang('YES') : $user->lang('NO'),
            'ANTISPAMGUARD_VERSION' => $this->get_extension_version($config),
            'ANTISPAMGUARD_PHP_VERSION' => PHP_VERSION,
            'ANTISPAMGUARD_TABLE_STATUS' => $table_ok ? $user->lang('ACP_ANTISPAMGUARD_STATUS_OK') : $user->lang('ACP_ANTISPAMGUARD_STATUS_ERROR'),
            'ANTISPAMGUARD_TABLE_NAME' => $table,
            'ANTISPAMGUARD_TOTAL_LOGS_ABOUT' => $total_logs,
            'ANTISPAMGUARD_LAST_LOG' => $last_log,
            'ANTISPAMGUARD_DB_ERROR' => $db_error,
            'ANTISPAMGUARD_CONFIG_STATUS' => empty($missing_configs) ? $user->lang('ACP_ANTISPAMGUARD_STATUS_OK') : $user->lang('ACP_ANTISPAMGUARD_STATUS_WARN'),
            'ANTISPAMGUARD_MISSING_CONFIGS' => empty($missing_configs) ? $user->lang('ACP_ANTISPAMGUARD_NONE') : implode(', ', $missing_configs),
            'ANTISPAMGUARD_CRON_LAST_PRUNE_ABOUT' => $cron_last,
            'ANTISPAMGUARD_RETENTION_STATUS' => $retention_enabled ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_GLOBAL_STATUS' => !empty($config['antispamguard_enabled']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_REGISTER_NOTICE_STATUS' => !empty($config['antispamguard_register_notice_enabled']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_SIMULATION_STATUS' => !empty($config['antispamguard_simulation_mode']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_REGISTER_STATUS' => !empty($config['antispamguard_enabled']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_POST_STATUS' => !empty($config['antispamguard_protect_posts']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_CONTACT_STATUS' => !empty($config['antispamguard_protect_contact']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
            'ANTISPAMGUARD_PM_STATUS' => !empty($config['antispamguard_protect_pm']) ? $user->lang('ACP_ANTISPAMGUARD_ENABLED') : $user->lang('ACP_ANTISPAMGUARD_DISABLED'),
        ));
    }

    /** @var logs_controller */
    protected $logs_controller;

    protected function get_logs_controller()
    {
        if (!$this->logs_controller)
        {
            $this->logs_controller = new logs_controller(
                $this->u_action,
                $this->get_settings_helper(),
                $this->get_pagination_helper(),
                $this->get_sfs_controller()
            );
        }
        else
        {
            $this->logs_controller->u_action = $this->u_action;
            $this->logs_controller->set_sfs_controller($this->get_sfs_controller());
        }

        return $this->logs_controller;
    }

    protected function show_stats($db, $template, $table_prefix)
    {
        $this->get_logs_controller()->show_stats($db, $template, $table_prefix);
    }

    protected function show_logs($db, $request, $template, $user, $table_prefix)
    {
        $this->get_logs_controller()->show_logs($db, $request, $template, $user, $table_prefix);
    }

    protected function export_logs_csv($db, $table, $filter_form = '', $filter_reason = '')
    {
        $this->get_logs_controller()->export_logs_csv($db, $table, $filter_form, $filter_reason);
    }

    protected function prune_old_logs($db, $table, $retention_days)
    {
        return $this->get_logs_controller()->prune_old_logs($db, $table, $retention_days);
    }

    protected function export_ip_reputation_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
    {
        $this->get_logs_controller()->export_ip_reputation_csv($db, $user, $table_prefix);
    }

    protected function format_ip_reputation_reason($reason, \phpbb\user $user)
    {
        return $this->get_logs_controller()->format_ip_reputation_reason($reason, $user);
    }

    protected function export_config_inventory_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user)
    {
        $filename = 'antispamguard_config_inventory_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array(
            'config_name',
            'config_value',
            'is_dynamic',
        ));

        $sql = "SELECT config_name, config_value, is_dynamic
            FROM " . CONFIG_TABLE . "
            WHERE config_name LIKE 'antispamguard_%'
            ORDER BY config_name ASC";
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $config_value = $row['config_value'];
            if ($row['config_name'] === 'antispamguard_sfs_api_key')
            {
                $config_value = ((string) $config_value !== '') ? '[redacted:' . $this->mask_secret((string) $config_value) . ']' : '';
            }

            fputcsv($output, array(
                $row['config_name'],
                $config_value,
                (int) $row['is_dynamic'],
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        garbage_collection();
        exit_handler();
    }

    protected function export_slowspam_activity_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
    {
        $filename = 'antispamguard_slowspam_activity_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array(
            'activity_id',
            'ip',
            'user_id',
            'action_type',
            'created_at',
        ));

        $sql = 'SELECT *
            FROM ' . $table_prefix . 'antispamguard_activity_log
            ORDER BY created_at DESC';
        $result = $db->sql_query_limit($sql, 5000);

        while ($row = $db->sql_fetchrow($result))
        {
            fputcsv($output, array(
                (int) $row['activity_id'],
                $row['ip'],
                (int) $row['user_id'],
                $row['action_type'],
                !empty($row['created_at']) ? $user->format_date((int) $row['created_at']) : '',
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        garbage_collection();
        exit_handler();
    }

    protected function export_ip_rate_limit_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
    {
        $filename = 'antispamguard_ip_rate_limit_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for spreadsheet compatibility.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array(
            'ip',
            'hits',
            'first_hit',
            'last_hit',
            'expires_at',
            'expired',
        ));

        $now = time();

        $sql = 'SELECT *
            FROM ' . $table_prefix . 'antispamguard_ip_rate
            ORDER BY hits DESC, last_hit DESC';
        $result = $db->sql_query_limit($sql, 5000);

        while ($row = $db->sql_fetchrow($result))
        {
            fputcsv($output, array(
                $row['ip'],
                (int) $row['hits'],
                !empty($row['first_hit']) ? $user->format_date((int) $row['first_hit']) : '',
                !empty($row['last_hit']) ? $user->format_date((int) $row['last_hit']) : '',
                !empty($row['expires_at']) ? $user->format_date((int) $row['expires_at']) : '',
                ((int) $row['expires_at'] <= $now) ? 1 : 0,
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        garbage_collection();
        exit_handler();
    }
}
