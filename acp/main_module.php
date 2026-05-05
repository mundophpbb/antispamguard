<?php
/**
 * AntiSpam Guard ACP module.
 */

namespace mundophpbb\antispamguard\acp;

class main_module
{
    public $u_action;

    public function main($id, $mode)
    {
        global $config, $db, $request, $template, $user, $table_prefix;

        $user->add_lang_ext('mundophpbb/antispamguard', 'acp');

        $this->tpl_name = 'acp_antispamguard';
        $this->page_title = $user->lang('ACP_ANTISPAMGUARD_TITLE');

        add_form_key('mundophpbb_antispamguard');

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
            $removed_logs = 0;

            if ($retention_days > 0)
            {
                $cutoff = $now - ($retention_days * 86400);

                $sql = 'DELETE FROM ' . $table_prefix . 'antispamguard_sfs_log
                    WHERE created_at < ' . (int) $cutoff;
                $db->sql_query($sql);
                $removed_logs = (int) $db->sql_affectedrows();
            }

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

            $this->export_sfs_logs_csv($db, $user, $table_prefix, $request);
            return;
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

            $submit_audit_id = $this->record_sfs_submission($db, $table_prefix, $user, $submit_ip, $submit_email, $submit_username, $submit_evidence, $submit_source, $submit_source_log_id, $submit_result);

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

            $match = $this->test_ip_whitelist_match($test_ip, $whitelist);

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
                'SFS_MANUAL_TEST_ACTION_MODE' => isset($decision['action_mode']) ? $this->format_sfs_action_mode($decision['action_mode'], $user) : '',
                'SFS_MANUAL_TEST_LOGGED' => (!empty($decision['log_written']) || !empty($decision['logged'])),
                'SFS_MANUAL_TEST_LOG_ID' => isset($decision['log_id']) ? (int) $decision['log_id'] : 0,
                'SFS_MANUAL_TEST_LOG_STATUS' => (!empty($decision['log_written']) || !empty($decision['logged'])) ? $user->lang('ACP_ANTISPAMGUARD_SFS_MANUAL_TEST_LOGGED') : $user->lang('ACP_ANTISPAMGUARD_SFS_MANUAL_TEST_NOT_LOGGED'),
                'SFS_MANUAL_TEST_STATUS' => isset($decision['status']) ? $this->format_sfs_status($decision['status'], $user) : '',
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
            $config->set('antispamguard_sfs_min_confidence', max(0, min(100, $request->variable('antispamguard_sfs_min_confidence', 50))));
            $config->set('antispamguard_sfs_min_frequency', max(1, $request->variable('antispamguard_sfs_min_frequency', 3)));
            $config->set('antispamguard_sfs_block_multiple_hits', $request->variable('antispamguard_sfs_block_multiple_hits', 0));
            $config->set('antispamguard_sfs_log_all_checks', $request->variable('antispamguard_sfs_log_all_checks', 1));
            $config->set('antispamguard_sfs_debug_log_all', $request->variable('antispamguard_sfs_debug_log_all', 0));
            $config->set('antispamguard_sfs_debug_localhost_only', $request->variable('antispamguard_sfs_debug_localhost_only', 1));
            $config->set('antispamguard_sfs_cleanup_interval', max(3600, $request->variable('antispamguard_sfs_cleanup_interval', 86400)));
            $config->set('antispamguard_sfs_log_retention_days', max(0, $request->variable('antispamguard_sfs_log_retention_days', 90)));
            $config->set('antispamguard_sfs_cache_ttl', max(60, $request->variable('antispamguard_sfs_cache_ttl', 86400)));
            $config->set('antispamguard_sfs_whitelist_ips', $request->variable('antispamguard_sfs_whitelist_ips', '', true));
            $config->set('antispamguard_sfs_whitelist_emails', $request->variable('antispamguard_sfs_whitelist_emails', '', true));
            $config->set('antispamguard_sfs_whitelist_usernames', $request->variable('antispamguard_sfs_whitelist_usernames', '', true));
            $config->set('antispamguard_ip_reputation_weight_sfs', max(0, $request->variable('antispamguard_ip_reputation_weight_sfs', 5)));
            $config->set('antispamguard_decision_weight_sfs', max(0, $request->variable('antispamguard_decision_weight_sfs', 50)));

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
            $this->show_sfs($db, $request, $template, $user, $config, $table_prefix);
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
            $config->set('antispamguard_rate_limit_enabled', $request->variable('antispamguard_rate_limit_enabled', 0));
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
            $config->set('antispamguard_ip_reputation_threshold', max(1, $request->variable('antispamguard_ip_reputation_threshold', 5)));
            $config->set('antispamguard_ip_reputation_decay_interval', max(0, $request->variable('antispamguard_ip_reputation_decay_interval', 600)));
            $config->set('antispamguard_ip_reputation_ttl', max(3600, $request->variable('antispamguard_ip_reputation_ttl', 86400)));
            $config->set('antispamguard_ip_reputation_cleanup_interval', max(3600, $request->variable('antispamguard_ip_reputation_cleanup_interval', 86400)));
            $config->set('antispamguard_ip_reputation_weight_honeypot', max(0, $request->variable('antispamguard_ip_reputation_weight_honeypot', 3)));
            $config->set('antispamguard_ip_reputation_weight_timestamp_fast', max(0, $request->variable('antispamguard_ip_reputation_weight_timestamp_fast', 2)));
            $config->set('antispamguard_ip_reputation_weight_timestamp_expired', max(0, $request->variable('antispamguard_ip_reputation_weight_timestamp_expired', 1)));

            $config->set('antispamguard_ip_reputation_weight_rate_limit', max(0, $request->variable('antispamguard_ip_reputation_weight_rate_limit', 3)));
            $config->set('antispamguard_ip_rate_limit_enabled', $request->variable('antispamguard_ip_rate_limit_enabled', 1));
            $config->set('antispamguard_ip_rate_limit_window', max(1, $request->variable('antispamguard_ip_rate_limit_window', 60)));
            $config->set('antispamguard_ip_rate_limit_max_hits', max(1, $request->variable('antispamguard_ip_rate_limit_max_hits', 5)));
            $config->set('antispamguard_ip_rate_limit_cleanup_interval', max(300, $request->variable('antispamguard_ip_rate_limit_cleanup_interval', 3600)));
            $ip_rate_limit_action = $request->variable('antispamguard_ip_rate_limit_action', 'block');
            if (!in_array($ip_rate_limit_action, array('block', 'score', 'log_only'), true))
            {
                $ip_rate_limit_action = 'block';
            }
            $config->set('antispamguard_ip_rate_limit_action', $ip_rate_limit_action);
            $config->set('antispamguard_decision_engine_enabled', $request->variable('antispamguard_decision_engine_enabled', 1));
            $config->set('antispamguard_decision_score_log', max(0, $request->variable('antispamguard_decision_score_log', 30)));
            $config->set('antispamguard_decision_score_block', max(1, $request->variable('antispamguard_decision_score_block', 60)));
            $config->set('antispamguard_decision_weight_honeypot', max(0, $request->variable('antispamguard_decision_weight_honeypot', 100)));
            $config->set('antispamguard_decision_weight_timestamp_fast', max(0, $request->variable('antispamguard_decision_weight_timestamp_fast', 30)));
            $config->set('antispamguard_decision_weight_timestamp_expired', max(0, $request->variable('antispamguard_decision_weight_timestamp_expired', 15)));
            $config->set('antispamguard_decision_weight_rate_limit', max(0, $request->variable('antispamguard_decision_weight_rate_limit', 40)));

            $config->set('antispamguard_decision_weight_ip_reputation', max(0, $request->variable('antispamguard_decision_weight_ip_reputation', 1)));
            $config->set('antispamguard_slowspam_enabled', $request->variable('antispamguard_slowspam_enabled', 1));
            $config->set('antispamguard_slowspam_window', max(60, $request->variable('antispamguard_slowspam_window', 1800)));
            $config->set('antispamguard_slowspam_threshold', max(1, $request->variable('antispamguard_slowspam_threshold', 8)));
            $config->set('antispamguard_slowspam_prune_after', max(3600, $request->variable('antispamguard_slowspam_prune_after', 86400)));
            $config->set('antispamguard_slowspam_cleanup_interval', max(3600, $request->variable('antispamguard_slowspam_cleanup_interval', 86400)));
            $config->set('antispamguard_alerts_enabled', $request->variable('antispamguard_alerts_enabled', 1));
            $config->set('antispamguard_alerts_retention', max(3600, $request->variable('antispamguard_alerts_retention', 604800)));
            $config->set('antispamguard_decision_weight_slowspam', max(0, $request->variable('antispamguard_decision_weight_slowspam', 35)));
            $config->set('antispamguard_trusted_ip_whitelist', $request->variable('antispamguard_trusted_ip_whitelist', '', true));
            $config->set('antispamguard_autoban_enabled', $request->variable('antispamguard_autoban_enabled', 0));
            $config->set('antispamguard_autoban_threshold', max(1, $request->variable('antispamguard_autoban_threshold', 120)));
            $config->set('antispamguard_autoban_duration', max(60, $request->variable('antispamguard_autoban_duration', 86400)));
            $config->set('antispamguard_shadowban_enabled', $request->variable('antispamguard_shadowban_enabled', 0));
            $config->set('antispamguard_shadowban_threshold', max(1, $request->variable('antispamguard_shadowban_threshold', 80)));
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

        $ip_reputation_threshold = isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 5;
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

        $register_notice_text = isset($config['antispamguard_register_notice_text']) ? (string) $config['antispamguard_register_notice_text'] : '';
        if (trim($register_notice_text) === '')
        {
            $register_notice_text = $this->get_default_register_notice_text($user);
        }
        $register_notice_text = $this->sanitize_register_notice_text($register_notice_text, $user);

        $template->assign_vars(array(
            'S_SETTINGS' => true,
            'SFS_DEBUG_LOG_ALL' => !empty($config['antispamguard_sfs_debug_log_all']),
            'SFS_DEBUG_LOCALHOST_ONLY' => !isset($config['antispamguard_sfs_debug_localhost_only']) || !empty($config['antispamguard_sfs_debug_localhost_only']),
            'SFS_LOG_ALL_CHECKS' => !isset($config['antispamguard_sfs_log_all_checks']) || !empty($config['antispamguard_sfs_log_all_checks']),
            'SFS_CACHE_TTL' => isset($config['antispamguard_sfs_cache_ttl']) ? (int) $config['antispamguard_sfs_cache_ttl'] : 86400,
            'AUTOBAN_ENABLED' => !empty($config['antispamguard_autoban_enabled']),
            'AUTOBAN_THRESHOLD' => isset($config['antispamguard_autoban_threshold']) ? (int) $config['antispamguard_autoban_threshold'] : 120,
            'AUTOBAN_DURATION' => isset($config['antispamguard_autoban_duration']) ? (int) $config['antispamguard_autoban_duration'] : 86400,
            'SHADOWBAN_ENABLED' => !empty($config['antispamguard_shadowban_enabled']),
            'SHADOWBAN_THRESHOLD' => isset($config['antispamguard_shadowban_threshold']) ? (int) $config['antispamguard_shadowban_threshold'] : 80,
            'DECISION_ENGINE_ENABLED' => !isset($config['antispamguard_decision_engine_enabled']) || !empty($config['antispamguard_decision_engine_enabled']),
            'DECISION_SCORE_LOG' => isset($config['antispamguard_decision_score_log']) ? (int) $config['antispamguard_decision_score_log'] : 30,
            'DECISION_SCORE_BLOCK' => isset($config['antispamguard_decision_score_block']) ? (int) $config['antispamguard_decision_score_block'] : 60,
            'DECISION_WEIGHT_HONEYPOT' => isset($config['antispamguard_decision_weight_honeypot']) ? (int) $config['antispamguard_decision_weight_honeypot'] : 100,
            'DECISION_WEIGHT_TIMESTAMP_FAST' => isset($config['antispamguard_decision_weight_timestamp_fast']) ? (int) $config['antispamguard_decision_weight_timestamp_fast'] : 30,
            'DECISION_WEIGHT_TIMESTAMP_EXPIRED' => isset($config['antispamguard_decision_weight_timestamp_expired']) ? (int) $config['antispamguard_decision_weight_timestamp_expired'] : 15,
            'DECISION_WEIGHT_RATE_LIMIT' => isset($config['antispamguard_decision_weight_rate_limit']) ? (int) $config['antispamguard_decision_weight_rate_limit'] : 40,
            'DECISION_WEIGHT_SFS' => isset($config['antispamguard_decision_weight_sfs']) ? (int) $config['antispamguard_decision_weight_sfs'] : 50,
            'DECISION_WEIGHT_IP_REPUTATION' => isset($config['antispamguard_decision_weight_ip_reputation']) ? (int) $config['antispamguard_decision_weight_ip_reputation'] : 1,
            'DECISION_WEIGHT_SLOWSPAM' => isset($config['antispamguard_decision_weight_slowspam']) ? (int) $config['antispamguard_decision_weight_slowspam'] : 35,
            'SLOWSPAM_ENABLED' => !isset($config['antispamguard_slowspam_enabled']) || !empty($config['antispamguard_slowspam_enabled']),
            'SLOWSPAM_WINDOW' => isset($config['antispamguard_slowspam_window']) ? (int) $config['antispamguard_slowspam_window'] : 1800,
            'SLOWSPAM_THRESHOLD' => isset($config['antispamguard_slowspam_threshold']) ? (int) $config['antispamguard_slowspam_threshold'] : 8,
            'SLOWSPAM_PRUNE_AFTER' => isset($config['antispamguard_slowspam_prune_after']) ? (int) $config['antispamguard_slowspam_prune_after'] : 86400,
            'SLOWSPAM_CLEANUP_INTERVAL' => isset($config['antispamguard_slowspam_cleanup_interval']) ? (int) $config['antispamguard_slowspam_cleanup_interval'] : 86400,
            'ALERTS_ENABLED' => !isset($config['antispamguard_alerts_enabled']) || !empty($config['antispamguard_alerts_enabled']),
            'ALERTS_RETENTION' => isset($config['antispamguard_alerts_retention']) ? (int) $config['antispamguard_alerts_retention'] : 604800,
            'U_ACTION' => $this->u_action,
            'IP_WHITELIST_MODE' => isset($config['antispamguard_ip_whitelist_mode']) ? $config['antispamguard_ip_whitelist_mode'] : 'partial',
            'TRUSTED_IP_WHITELIST' => isset($config['antispamguard_ip_whitelist']) ? $config['antispamguard_ip_whitelist'] : (isset($config['antispamguard_trusted_ip_whitelist']) ? $config['antispamguard_trusted_ip_whitelist'] : ''),
            'SFS_CACHE_TOTAL' => $sfs_cache_total,
            'SFS_CACHE_EXPIRED' => $sfs_cache_expired,
            'SFS_LOGS_TOTAL' => $sfs_logs_total,
            'SFS_CLEANUP_INTERVAL' => isset($config['antispamguard_sfs_cleanup_interval']) ? (int) $config['antispamguard_sfs_cleanup_interval'] : 86400,
            'SFS_LOG_RETENTION_DAYS' => isset($config['antispamguard_sfs_log_retention_days']) ? (int) $config['antispamguard_sfs_log_retention_days'] : 90,
            'SFS_CLEANUP_LAST_GC' => !empty($config['antispamguard_sfs_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_sfs_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'ANTISPAMGUARD_ENABLED' => !empty($config['antispamguard_enabled']),
            'ANTISPAMGUARD_REGISTER_NOTICE_ENABLED' => !empty($config['antispamguard_register_notice_enabled']),
            'ANTISPAMGUARD_REGISTER_NOTICE_TEXT' => $register_notice_text,
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
            'SFS_API_KEY_CONFIGURED' => !empty($config['antispamguard_sfs_api_key']),
            'SFS_API_KEY_MASKED' => !empty($config['antispamguard_sfs_api_key']) ? $this->mask_secret((string) $config['antispamguard_sfs_api_key']) : '',
            'ANTISPAMGUARD_SFS_LOG_ENABLED' => !isset($config['antispamguard_sfs_log_enabled']) || !empty($config['antispamguard_sfs_log_enabled']),
            'ANTISPAMGUARD_SFS_LOG_ONLY_BLOCKED' => !empty($config['antispamguard_sfs_log_only_blocked']),
            'ANTISPAMGUARD_SFS_MIN_CONFIDENCE' => isset($config['antispamguard_sfs_min_confidence']) ? (int) $config['antispamguard_sfs_min_confidence'] : 50,
            'ANTISPAMGUARD_SFS_MIN_FREQUENCY' => isset($config['antispamguard_sfs_min_frequency']) ? (int) $config['antispamguard_sfs_min_frequency'] : 3,
            'ANTISPAMGUARD_SFS_BLOCK_MULTIPLE_HITS' => !isset($config['antispamguard_sfs_block_multiple_hits']) || !empty($config['antispamguard_sfs_block_multiple_hits']),
        ));
    }

    protected function show_sfs($db, $request, $template, $user, $config, $table_prefix)
    {
        $sfs_cache_total = 0;
        $sfs_cache_expired = 0;
        $sfs_logs_total = 0;

        $sql = 'SELECT COUNT(cache_id) AS total_cache FROM ' . $table_prefix . 'antispamguard_sfs_cache';
        $result = $db->sql_query($sql);
        $sfs_cache_total = (int) $db->sql_fetchfield('total_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(cache_id) AS expired_cache FROM ' . $table_prefix . 'antispamguard_sfs_cache WHERE expires_at <= ' . time();
        $result = $db->sql_query($sql);
        $sfs_cache_expired = (int) $db->sql_fetchfield('expired_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table_prefix . 'antispamguard_sfs_log';
        $result = $db->sql_query($sql);
        $sfs_logs_total = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $this->assign_sfs_logs($db, $request, $template, $user, $table_prefix);
        $this->assign_sfs_submission_logs($db, $template, $user, $table_prefix);
        $sfs_submit_prefill = $this->get_sfs_submit_prefill($db, $request, $user, $table_prefix);

        $template->assign_vars(array(
            'S_SFS' => true,
            'U_ACTION' => $this->u_action,
            'SFS_DEBUG_LOG_ALL' => !empty($config['antispamguard_sfs_debug_log_all']),
            'SFS_DEBUG_LOCALHOST_ONLY' => !isset($config['antispamguard_sfs_debug_localhost_only']) || !empty($config['antispamguard_sfs_debug_localhost_only']),
            'SFS_LOG_ALL_CHECKS' => !isset($config['antispamguard_sfs_log_all_checks']) || !empty($config['antispamguard_sfs_log_all_checks']),
            'SFS_CACHE_TTL' => isset($config['antispamguard_sfs_cache_ttl']) ? (int) $config['antispamguard_sfs_cache_ttl'] : 86400,
            'SFS_CACHE_TOTAL' => $sfs_cache_total,
            'SFS_CACHE_EXPIRED' => $sfs_cache_expired,
            'SFS_LOGS_TOTAL' => $sfs_logs_total,
            'SFS_CLEANUP_INTERVAL' => isset($config['antispamguard_sfs_cleanup_interval']) ? (int) $config['antispamguard_sfs_cleanup_interval'] : 86400,
            'SFS_LOG_RETENTION_DAYS' => isset($config['antispamguard_sfs_log_retention_days']) ? (int) $config['antispamguard_sfs_log_retention_days'] : 90,
            'SFS_CLEANUP_LAST_GC' => !empty($config['antispamguard_sfs_cleanup_last_gc']) ? $user->format_date((int) $config['antispamguard_sfs_cleanup_last_gc']) : $user->lang('ACP_ANTISPAMGUARD_SFS_CLEANUP_NEVER'),
            'ANTISPAMGUARD_SFS_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
            'SFS_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? (string) $config['antispamguard_sfs_action_mode'] : 'block',
            'SFS_API_KEY_CONFIGURED' => !empty($config['antispamguard_sfs_api_key']),
            'SFS_API_KEY_MASKED' => !empty($config['antispamguard_sfs_api_key']) ? $this->mask_secret((string) $config['antispamguard_sfs_api_key']) : '',
            'SFS_SUBMIT_PREFILL_IP' => $sfs_submit_prefill['ip'],
            'SFS_SUBMIT_PREFILL_EMAIL' => $sfs_submit_prefill['email'],
            'SFS_SUBMIT_PREFILL_USERNAME' => $sfs_submit_prefill['username'],
            'SFS_SUBMIT_PREFILL_EVIDENCE' => $sfs_submit_prefill['evidence'],
            'SFS_SUBMIT_PREFILL_SOURCE' => $sfs_submit_prefill['source'],
            'SFS_SUBMIT_PREFILL_SOURCE_LOG_ID' => $sfs_submit_prefill['source_log_id'],
            'S_SFS_SUBMIT_PREFILLED' => !empty($sfs_submit_prefill['prefilled']),
            'ANTISPAMGUARD_SFS_LOG_ENABLED' => !isset($config['antispamguard_sfs_log_enabled']) || !empty($config['antispamguard_sfs_log_enabled']),
            'ANTISPAMGUARD_SFS_LOG_ONLY_BLOCKED' => !empty($config['antispamguard_sfs_log_only_blocked']),
            'ANTISPAMGUARD_SFS_MIN_CONFIDENCE' => isset($config['antispamguard_sfs_min_confidence']) ? (int) $config['antispamguard_sfs_min_confidence'] : 50,
            'ANTISPAMGUARD_SFS_MIN_FREQUENCY' => isset($config['antispamguard_sfs_min_frequency']) ? (int) $config['antispamguard_sfs_min_frequency'] : 3,
            'ANTISPAMGUARD_SFS_BLOCK_MULTIPLE_HITS' => !isset($config['antispamguard_sfs_block_multiple_hits']) || !empty($config['antispamguard_sfs_block_multiple_hits']),
            'SFS_WHITELIST_IPS' => isset($config['antispamguard_sfs_whitelist_ips']) ? (string) $config['antispamguard_sfs_whitelist_ips'] : '',
            'SFS_WHITELIST_EMAILS' => isset($config['antispamguard_sfs_whitelist_emails']) ? (string) $config['antispamguard_sfs_whitelist_emails'] : '',
            'SFS_WHITELIST_USERNAMES' => isset($config['antispamguard_sfs_whitelist_usernames']) ? (string) $config['antispamguard_sfs_whitelist_usernames'] : '',
            'IP_REPUTATION_WEIGHT_SFS' => isset($config['antispamguard_ip_reputation_weight_sfs']) ? (int) $config['antispamguard_ip_reputation_weight_sfs'] : 5,
            'DECISION_WEIGHT_SFS' => isset($config['antispamguard_decision_weight_sfs']) ? (int) $config['antispamguard_decision_weight_sfs'] : 50,
            'SFS_DIAG_ENABLED' => !empty($config['antispamguard_sfs_enabled']),
            'SFS_DIAG_ACTION_MODE' => isset($config['antispamguard_sfs_action_mode']) ? $config['antispamguard_sfs_action_mode'] : 'block',
            'SFS_DIAG_CACHE_TOTAL' => $sfs_cache_total,
            'SFS_DIAG_LOGS_TOTAL' => $sfs_logs_total,
        ));
    }

    protected function assign_sfs_logs($db, $request, $template, $user, $table_prefix)
    {
        $sfs_filter_action = $request->variable('sfs_filter_action', '');
        $sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');
        $sfs_start = max(0, $request->variable('sfs_start', 0));
        $sfs_per_page = 25;

        if (!in_array($sfs_filter_action, array('', 'block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
        {
            $sfs_filter_action = '';
        }

        if (!in_array($sfs_filter_blocked, array('', '1', '0'), true))
        {
            $sfs_filter_blocked = '';
        }

        $sfs_table = $table_prefix . 'antispamguard_sfs_log';
        $sfs_where = array();

        if ($sfs_filter_blocked !== '')
        {
            $sfs_where[] = 'blocked = ' . (int) $sfs_filter_blocked;
        }

        $sfs_where_sql = !empty($sfs_where) ? ' WHERE ' . implode(' AND ', $sfs_where) : '';

        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table;
        $result = $db->sql_query($sql);
        $total_sfs_logs = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $total_sfs_logs_filtered = 0;
        $has_sfs_logs = false;
        $sfs_rows_rendered = 0;
        $sfs_seen_filtered = 0;

        if ($sfs_filter_action === '')
        {
            $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table . $sfs_where_sql;
            $result = $db->sql_query($sql);
            $total_sfs_logs_filtered = (int) $db->sql_fetchfield('total_logs');
            $db->sql_freeresult($result);

            if ($sfs_start >= $total_sfs_logs_filtered && $total_sfs_logs_filtered > 0)
            {
                $sfs_start = max(0, floor(($total_sfs_logs_filtered - 1) / $sfs_per_page) * $sfs_per_page);
            }

            $sql = 'SELECT * FROM ' . $sfs_table . $sfs_where_sql . ' ORDER BY created_at DESC';
            $result = $db->sql_query_limit($sql, $sfs_per_page, $sfs_start);
        }
        else
        {
            $sql = 'SELECT * FROM ' . $sfs_table . $sfs_where_sql . ' ORDER BY created_at DESC';
            $result = $db->sql_query($sql);
        }

        while ($sfs_row = $db->sql_fetchrow($result))
        {
            $details = json_decode($sfs_row['details_json'], true);
            if (!is_array($details))
            {
                $details = array();
            }

            $detail_parts = array();
            $decision_meta = isset($details['_decision']) && is_array($details['_decision']) ? $details['_decision'] : array();
            $action_mode = isset($decision_meta['action_mode']) ? (string) $decision_meta['action_mode'] : 'block';
            $matched = !empty($decision_meta['matched']);

            if ($sfs_filter_action !== '' && $action_mode !== $sfs_filter_action)
            {
                continue;
            }

            if ($sfs_filter_action !== '')
            {
                $total_sfs_logs_filtered++;

                if ($sfs_seen_filtered < $sfs_start)
                {
                    $sfs_seen_filtered++;
                    continue;
                }
            }

            if ($sfs_rows_rendered >= $sfs_per_page)
            {
                if ($sfs_filter_action === '')
                {
                    break;
                }

                continue;
            }

            $has_sfs_logs = true;
            $sfs_rows_rendered++;
            $sfs_seen_filtered++;

            foreach ($details as $detail_type => $detail_data)
            {
                if ($detail_type === '_decision' || !is_array($detail_data))
                {
                    continue;
                }

                $detail_parts[] = strtoupper($detail_type) . ': '
                    . 'confidence=' . (isset($detail_data['confidence']) ? $detail_data['confidence'] : 0)
                    . ', frequency=' . (isset($detail_data['frequency']) ? $detail_data['frequency'] : 0)
                    . ', cached=' . (!empty($detail_data['cached']) ? $user->lang('YES') : $user->lang('NO'));
            }

            $template->assign_block_vars('sfs_logs', array(
                'ID' => (int) $sfs_row['log_id'],
                'TIME' => $user->format_date((int) $sfs_row['created_at']),
                'SOURCE' => $sfs_row['check_source'],
                'IP' => $sfs_row['user_ip'],
                'USERNAME' => $sfs_row['username'],
                'EMAIL' => $sfs_row['user_email'],
                'LISTED_COUNT' => (int) $sfs_row['listed_count'],
                'STRONG_HIT' => !empty($sfs_row['strong_hit']) ? $user->lang('YES') : $user->lang('NO'),
                'BLOCKED' => !empty($sfs_row['blocked']) ? $user->lang('YES') : $user->lang('NO'),
                'ACTION_MODE' => $this->format_sfs_action_mode($action_mode, $user),
                'MATCHED' => $matched ? $user->lang('YES') : $user->lang('NO'),
                'DETAILS' => !empty($detail_parts) ? implode('; ', $detail_parts) : '',
                'U_REPORT_PREFILL' => $this->append_url_param($this->get_sfs_mode_url(), 'sfs_prefill_log_id', (int) $sfs_row['log_id']),
                'S_CAN_PREFILL_REPORT' => ($sfs_row['user_ip'] !== '' || $sfs_row['user_email'] !== '' || $sfs_row['username'] !== ''),
            ));
        }
        $db->sql_freeresult($result);

        $filter_params = '';
        if ($sfs_filter_action !== '')
        {
            $filter_params .= '&amp;sfs_filter_action=' . urlencode($sfs_filter_action);
        }
        if ($sfs_filter_blocked !== '')
        {
            $filter_params .= '&amp;sfs_filter_blocked=' . urlencode($sfs_filter_blocked);
        }

        $base_url = $this->u_action . $filter_params;
        $sfs_pagination = $this->build_pagination($base_url, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start, 'sfs_start');
        $sfs_page_number = $this->build_page_number($user, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start);

        $template->assign_vars(array(
            'S_HAS_SFS_LOGS' => $has_sfs_logs,
            'TOTAL_SFS_LOGS' => $total_sfs_logs,
            'TOTAL_SFS_LOGS_FILTERED' => $total_sfs_logs_filtered,
            'SFS_FILTER_ACTION' => $sfs_filter_action,
            'SFS_FILTER_BLOCKED' => $sfs_filter_blocked,
            'S_SFS_FILTER_ACTIVE' => ($sfs_filter_action !== '' || $sfs_filter_blocked !== ''),
            'SFS_PAGINATION' => $sfs_pagination,
            'SFS_PAGE_NUMBER' => $sfs_page_number,
        ));
    }
    protected function get_sfs_mode_url()
    {
        if (strpos($this->u_action, 'mode=') !== false)
        {
            return preg_replace('/mode=[^&;]+/', 'mode=sfs', $this->u_action, 1);
        }

        return $this->u_action . (strpos($this->u_action, '?') === false ? '?' : '&amp;') . 'mode=sfs';
    }

    protected function append_url_param($url, $name, $value)
    {
        return $url . (strpos($url, '?') === false ? '?' : '&amp;') . urlencode($name) . '=' . urlencode((string) $value);
    }

    protected function get_sfs_submit_prefill($db, $request, $user, $table_prefix)
    {
        $prefill = array(
            'ip' => '',
            'email' => '',
            'username' => '',
            'evidence' => '',
            'source' => 'manual_acp',
            'source_log_id' => 0,
            'prefilled' => false,
        );

        $log_id = max(0, $request->variable('sfs_prefill_log_id', 0));
        if ($log_id <= 0)
        {
            return $prefill;
        }

        $sql = 'SELECT log_id, created_at, check_source, user_ip, user_email, username
            FROM ' . $table_prefix . 'antispamguard_sfs_log
            WHERE log_id = ' . (int) $log_id;
        $result = $db->sql_query_limit($sql, 1);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$row)
        {
            return $prefill;
        }

        $prefill['ip'] = (string) $row['user_ip'];
        $prefill['email'] = (string) $row['user_email'];
        $prefill['username'] = (string) $row['username'];
        $prefill['source'] = 'sfs_log';
        $prefill['source_log_id'] = (int) $row['log_id'];
        $prefill['evidence'] = $user->lang('ACP_ANTISPAMGUARD_SFS_SUBMIT_EVIDENCE_FROM_LOG', (int) $row['log_id'], (string) $row['check_source'], $user->format_date((int) $row['created_at']));
        $prefill['prefilled'] = true;

        return $prefill;
    }

    protected function record_sfs_submission($db, $table_prefix, $user, $ip, $email, $username, $evidence, $source, $source_log_id, array $result)
    {
        $table = $table_prefix . 'antispamguard_sfs_submit_log';
        $status = !empty($result['success']) ? 'success' : (!empty($result['status']) ? (string) $result['status'] : 'failed');
        $response_text = '';

        if (!empty($result['response']))
        {
            $response_text = (string) $result['response'];
        }
        elseif (!empty($result['message']))
        {
            $response_text = (string) $result['message'];
        }

        $data = array(
            'user_id' => isset($user->data['user_id']) ? (int) $user->data['user_id'] : 0,
            'admin_username' => isset($user->data['username']) ? (string) $user->data['username'] : '',
            'admin_ip' => isset($user->ip) ? (string) $user->ip : '',
            'spammer_ip' => (string) $ip,
            'spammer_email' => (string) $email,
            'spammer_username' => (string) $username,
            'evidence' => (string) $evidence,
            'source' => (string) $source,
            'source_log_id' => (int) $source_log_id,
            'status' => $status,
            'response_text' => $response_text,
            'created_at' => time(),
        );

        $sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $data);
        $db->sql_query($sql);

        return (int) $db->sql_nextid();
    }

    protected function assign_sfs_submission_logs($db, $template, $user, $table_prefix)
    {
        $table = $table_prefix . 'antispamguard_sfs_submit_log';
        $has_submission_logs = false;
        $total_submission_logs = 0;

        $sql = 'SELECT COUNT(submit_id) AS total_logs FROM ' . $table;
        $result = $db->sql_query($sql);
        $total_submission_logs = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $sql = 'SELECT * FROM ' . $table . '
            ORDER BY created_at DESC, submit_id DESC';
        $result = $db->sql_query_limit($sql, 25);

        while ($row = $db->sql_fetchrow($result))
        {
            $has_submission_logs = true;
            $template->assign_block_vars('sfs_submission_logs', array(
                'ID' => (int) $row['submit_id'],
                'TIME' => $user->format_date((int) $row['created_at']),
                'ADMIN' => (string) $row['admin_username'],
                'IP' => (string) $row['spammer_ip'],
                'USERNAME' => (string) $row['spammer_username'],
                'EMAIL' => (string) $row['spammer_email'],
                'SOURCE' => (string) $row['source'],
                'SOURCE_LOG_ID' => (int) $row['source_log_id'],
                'STATUS' => (string) $row['status'],
                'RESPONSE' => (string) $row['response_text'],
            ));
        }
        $db->sql_freeresult($result);

        $template->assign_vars(array(
            'S_HAS_SFS_SUBMISSION_LOGS' => $has_submission_logs,
            'TOTAL_SFS_SUBMISSION_LOGS' => $total_submission_logs,
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
        $items = preg_split('/[\r\n,]+/', (string) $raw_keywords);
        $keywords = array();

        foreach ($items as $item)
        {
            $keyword = trim($item);
            if ($keyword !== '')
            {
                $keywords[$keyword] = $keyword;
            }
        }

        ksort($keywords);

        return implode("\n", $keywords);
    }

    protected function normalize_ip_list($raw_ips)
    {
        $items = preg_split('/[\r\n,]+/', (string) $raw_ips);
        $ips = array();

        foreach ($items as $item)
        {
            $ip = trim($item);
            if ($ip === '')
            {
                continue;
            }

            if (strpos($ip, '/') !== false)
            {
                list($address, $prefix) = explode('/', $ip, 2);
                $address = trim($address);
                $prefix = trim($prefix);
                if (filter_var($address, FILTER_VALIDATE_IP) && ctype_digit($prefix))
                {
                    $max_prefix = (strpos($address, ':') !== false) ? 128 : 32;
                    $prefix = (int) $prefix;
                    if ($prefix >= 0 && $prefix <= $max_prefix)
                    {
                        $ips[$address . '/' . $prefix] = $address . '/' . $prefix;
                    }
                }
            }
            else if (filter_var($ip, FILTER_VALIDATE_IP))
            {
                $ips[$ip] = $ip;
            }
        }

        ksort($ips);

        return implode("\n", $ips);
    }

    protected function normalize_group_ids($raw_group_ids)
    {
        $items = preg_split('/[^0-9]+/', (string) $raw_group_ids);
        $group_ids = array();

        foreach ($items as $item)
        {
            $group_id = (int) $item;
            if ($group_id > 0)
            {
                $group_ids[$group_id] = $group_id;
            }
        }

        sort($group_ids);

        return implode(',', $group_ids);
    }

    protected function sanitize_secret($value, $max_length)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\x00-\x20\x7F]/', '', $value);

        return $this->truncate_for_storage($value, (int) $max_length);
    }

    protected function mask_secret($value)
    {
        $value = trim((string) $value);
        $length = strlen($value);

        if ($length === 0)
        {
            return '';
        }

        if ($length <= 4)
        {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)) . substr($value, -4);
    }

    protected function get_settings_keys()
    {
        return array(
            'antispamguard_enabled',
            'antispamguard_register_notice_enabled',
            'antispamguard_register_notice_text',
            'antispamguard_hp_name',
            'antispamguard_protect_posts',
            'antispamguard_protect_contact',
            'antispamguard_protect_pm',
            'antispamguard_posts_guests_only',
            'antispamguard_bypass_group_ids',
            'antispamguard_content_filter_enabled',
            'antispamguard_blocked_keywords',
            'antispamguard_max_urls',
            'antispamguard_ip_whitelist',
            'antispamguard_ip_blacklist',
            'antispamguard_rate_limit_enabled',
            'antispamguard_rate_limit_max_attempts',
            'antispamguard_rate_limit_window',
            'antispamguard_log_retention_enabled',
            'antispamguard_log_retention_days',
            'antispamguard_silent_mode',
            'antispamguard_simulation_mode',
            'antispamguard_min_seconds',
            'antispamguard_max_seconds',
        );
    }

    protected function get_extension_version($config = null)
    {
        $composer_file = dirname(__DIR__) . '/composer.json';

        if (is_file($composer_file) && is_readable($composer_file))
        {
            $composer = json_decode((string) file_get_contents($composer_file), true);

            if (is_array($composer) && !empty($composer['version']))
            {
                return (string) $composer['version'];
            }
        }

        if ($config !== null && isset($config['antispamguard_version']) && (string) $config['antispamguard_version'] !== '')
        {
            return (string) $config['antispamguard_version'];
        }

        return 'unknown';
    }

    protected function export_settings_json($config)
    {
        $data = array(
            'extension' => 'mundophpbb/antispamguard',
            'version' => $this->get_extension_version($config),
            'exported_at' => gmdate('c'),
            'settings' => array(),
        );

        foreach ($this->get_settings_keys() as $key)
        {
            if (isset($config[$key]))
            {
                $data['settings'][$key] = (string) $config[$key];
            }
        }

        while (ob_get_level())
        {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="antispamguard_settings_' . gmdate('Y-m-d_H-i-s') . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function import_settings_json($config, $raw_settings)
    {
        $raw_settings = trim((string) $raw_settings);
        if ($raw_settings === '')
        {
            return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_EMPTY', 'count' => 0);
        }

        $data = json_decode($raw_settings, true);
        if (!is_array($data) || !isset($data['settings']) || !is_array($data['settings']))
        {
            return array('success' => false, 'message' => 'ACP_ANTISPAMGUARD_IMPORT_INVALID', 'count' => 0);
        }

        $allowed = array_flip($this->get_settings_keys());
        $imported = 0;

        foreach ($data['settings'] as $key => $value)
        {
            if (!isset($allowed[$key]))
            {
                continue;
            }

            $value = is_array($value) ? '' : (string) $value;

            switch ($key)
            {
                case 'antispamguard_hp_name':
                    $value = trim($value);
                    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,30}$/', $value))
                    {
                        $value = 'homepage';
                    }
                break;

                case 'antispamguard_bypass_group_ids':
                    $value = $this->normalize_group_ids($value);
                break;

                case 'antispamguard_register_notice_text':
                    $value = $this->sanitize_register_notice_text($value, null);
                break;

                case 'antispamguard_blocked_keywords':
                    $value = $this->normalize_blocked_keywords($value);
                break;

                case 'antispamguard_ip_whitelist':
                case 'antispamguard_ip_blacklist':
                    $value = $this->normalize_ip_list($value);
                break;

                case 'antispamguard_max_urls':
                    $value = max(0, (int) $value);
                break;

                case 'antispamguard_rate_limit_max_attempts':
                    $value = max(1, (int) $value);
                break;

                case 'antispamguard_rate_limit_window':
                    $value = max(60, (int) $value);
                break;

                case 'antispamguard_log_retention_days':
                    $value = max(1, (int) $value);
                break;

                case 'antispamguard_min_seconds':
                    $value = max(0, (int) $value);
                break;

                case 'antispamguard_max_seconds':
                    $value = max(10, (int) $value);
                break;

                case 'antispamguard_enabled':
                case 'antispamguard_register_notice_enabled':
                case 'antispamguard_protect_posts':
                case 'antispamguard_protect_contact':
                case 'antispamguard_protect_pm':
                case 'antispamguard_posts_guests_only':
                case 'antispamguard_content_filter_enabled':
                case 'antispamguard_rate_limit_enabled':
                case 'antispamguard_log_retention_enabled':
                case 'antispamguard_silent_mode':
                case 'antispamguard_simulation_mode':
                    $value = !empty($value) ? 1 : 0;
                break;
            }

            $config->set($key, $value);
            $imported++;
        }

        return array('success' => true, 'message' => '', 'count' => $imported);
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

    protected function show_stats($db, $template, $table_prefix)
    {
        global $user;

        $table = $table_prefix . 'antispamguard_log';
        $now = time();

        $total_logs = $this->count_logs($db, $table);
        $total_24h = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 86400));
        $total_7d = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 604800));
        $total_30d = $this->count_logs($db, $table, 'log_time >= ' . (int) ($now - 2592000));

        $top_reason = $this->get_top_stat($db, $table, 'reason', 'unknown', $user);
        $top_form = $this->get_top_stat($db, $table, 'form_type', 'register', $user);

        $timestamp_total = $this->count_logs($db, $table, "reason = 'timestamp'");
        $timestamp_too_fast = $this->count_logs($db, $table, "reason = 'timestamp_too_fast'");
        $timestamp_expired = $this->count_logs($db, $table, "reason = 'timestamp_expired'");
        $timestamp_combined = $timestamp_total + $timestamp_too_fast + $timestamp_expired;

        $ip_rep_table = $table_prefix . 'antispamguard_ip_score';
        $ip_rep_total = 0;
        $ip_rep_blocked = 0;
        $ip_rep_threshold = isset($this->config['antispamguard_ip_reputation_threshold']) ? (int) $this->config['antispamguard_ip_reputation_threshold'] : 5;

        $sql = 'SELECT COUNT(score_id) AS total_scores
            FROM ' . $ip_rep_table;
        $result = $db->sql_query($sql);
        $ip_rep_total = (int) $db->sql_fetchfield('total_scores');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(score_id) AS blocked_scores
            FROM ' . $ip_rep_table . '
            WHERE score >= ' . (int) $ip_rep_threshold;
        $result = $db->sql_query($sql);
        $ip_rep_blocked = (int) $db->sql_fetchfield('blocked_scores');
        $db->sql_freeresult($result);

        $sfs_log_table = $table_prefix . 'antispamguard_sfs_log';
        $sfs_cache_table = $table_prefix . 'antispamguard_sfs_cache';

        $sfs_total_logs = $this->count_logs($db, $sfs_log_table);
        $sfs_blocked_logs = $this->count_logs($db, $sfs_log_table, 'blocked = 1');
        $sfs_suspect_logs = $this->count_logs($db, $sfs_log_table, 'blocked = 0');
        $sfs_strong_hits = $this->count_logs($db, $sfs_log_table, 'strong_hit = 1');
        $sfs_24h = $this->count_logs($db, $sfs_log_table, 'created_at >= ' . (int) ($now - 86400));
        $sfs_7d = $this->count_logs($db, $sfs_log_table, 'created_at >= ' . (int) ($now - 604800));

        $sfs_cache_total = 0;
        $sfs_cache_positive = 0;
        $sfs_cache_expired = 0;

        $sql = 'SELECT COUNT(cache_id) AS total_cache
            FROM ' . $sfs_cache_table;
        $result = $db->sql_query($sql);
        $sfs_cache_total = (int) $db->sql_fetchfield('total_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(cache_id) AS positive_cache
            FROM ' . $sfs_cache_table . '
            WHERE is_listed = 1';
        $result = $db->sql_query($sql);
        $sfs_cache_positive = (int) $db->sql_fetchfield('positive_cache');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(cache_id) AS expired_cache
            FROM ' . $sfs_cache_table . '
            WHERE expires_at <= ' . (int) $now;
        $result = $db->sql_query($sql);
        $sfs_cache_expired = (int) $db->sql_fetchfield('expired_cache');
        $db->sql_freeresult($result);

        $sfs_block_rate = ($sfs_total_logs > 0) ? (int) round(($sfs_blocked_logs / $sfs_total_logs) * 100) : 0;

        $this->assign_group_stats($db, $template, $table, 'form_type', 'stats_forms', 'register', $total_logs, $user);
        $this->assign_group_stats($db, $template, $table, 'reason', 'stats_reasons', 'unknown', $total_logs, $user);
        $this->assign_daily_stats($db, $template, $table, 7);

        $template->assign_vars(array(
            'S_STATS' => true,
            'U_ACTION' => $this->u_action,
            'ANTISPAMGUARD_STATS_TOTAL' => $total_logs,
            'ANTISPAMGUARD_STATS_24H' => $total_24h,
            'ANTISPAMGUARD_STATS_7D' => $total_7d,
            'ANTISPAMGUARD_STATS_30D' => $total_30d,
            'ANTISPAMGUARD_STATS_TOP_REASON' => $top_reason['label'],
            'ANTISPAMGUARD_STATS_TOP_REASON_TOTAL' => $top_reason['total'],
            'ANTISPAMGUARD_STATS_TOP_FORM' => $top_form['label'],
            'ANTISPAMGUARD_STATS_TOP_FORM_TOTAL' => $top_form['total'],
            'ANTISPAMGUARD_STATS_TIMESTAMP_TOTAL' => $timestamp_total,
            'ANTISPAMGUARD_STATS_TIMESTAMP_TOO_FAST' => $timestamp_too_fast,
            'ANTISPAMGUARD_STATS_TIMESTAMP_EXPIRED' => $timestamp_expired,
            'ANTISPAMGUARD_STATS_TIMESTAMP_COMBINED' => $timestamp_combined,
            'ANTISPAMGUARD_STATS_IP_REP_TOTAL' => $ip_rep_total,
            'ANTISPAMGUARD_STATS_IP_REP_BLOCKED' => $ip_rep_blocked,
            'ANTISPAMGUARD_SFS_STATS_TOTAL_LOGS' => $sfs_total_logs,
            'ANTISPAMGUARD_SFS_STATS_BLOCKED_LOGS' => $sfs_blocked_logs,
            'ANTISPAMGUARD_SFS_STATS_SUSPECT_LOGS' => $sfs_suspect_logs,
            'ANTISPAMGUARD_SFS_STATS_STRONG_HITS' => $sfs_strong_hits,
            'ANTISPAMGUARD_SFS_STATS_24H' => $sfs_24h,
            'ANTISPAMGUARD_SFS_STATS_7D' => $sfs_7d,
            'ANTISPAMGUARD_SFS_STATS_CACHE_TOTAL' => $sfs_cache_total,
            'ANTISPAMGUARD_SFS_STATS_CACHE_POSITIVE' => $sfs_cache_positive,
            'ANTISPAMGUARD_SFS_STATS_CACHE_EXPIRED' => $sfs_cache_expired,
            'ANTISPAMGUARD_SFS_STATS_BLOCK_RATE' => $sfs_block_rate,
            'S_HAS_SFS_STATS' => ($sfs_total_logs > 0 || $sfs_cache_total > 0),
            'S_HAS_STATS' => ($total_logs > 0),
        ));
    }

    protected function count_logs($db, $table, $where = '')
    {
        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table;
        if ($where !== '')
        {
            $sql .= ' WHERE ' . $where;
        }

        $result = $db->sql_query($sql);
        $total = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        return $total;
    }

    protected function assign_group_stats($db, $template, $table, $column, $block_name, $fallback_label, $grand_total = 0, $user = null)
    {
        $sql = 'SELECT ' . $column . ' AS stat_label, COUNT(log_id) AS stat_total
            FROM ' . $table . '
            GROUP BY ' . $column . '
            ORDER BY stat_total DESC, stat_label ASC';
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $total = (int) $row['stat_total'];
            $percent = ($grand_total > 0) ? (int) round(($total / $grand_total) * 100) : 0;
            $label = isset($row['stat_label']) && $row['stat_label'] !== '' ? $row['stat_label'] : $fallback_label;
            $label = $this->format_log_value($label, $column, $user);
            $template->assign_block_vars($block_name, array(
                'LABEL' => $label,
                'TOTAL' => $total,
                'PERCENT' => $percent,
            ));
        }

        $db->sql_freeresult($result);
    }

    protected function get_top_stat($db, $table, $column, $fallback_label, $user = null)
    {
        $sql = 'SELECT ' . $column . ' AS stat_label, COUNT(log_id) AS stat_total
            FROM ' . $table . '
            GROUP BY ' . $column . '
            ORDER BY stat_total DESC, stat_label ASC';
        $result = $db->sql_query_limit($sql, 1);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$row)
        {
            return array('label' => '-', 'total' => 0);
        }

        return array(
            'label' => $this->format_log_value((isset($row['stat_label']) && $row['stat_label'] !== '') ? $row['stat_label'] : $fallback_label, $column, $user),
            'total' => (int) $row['stat_total'],
        );
    }

    protected function assign_daily_stats($db, $template, $table, $days = 7)
    {
        global $user;

        $days = max(1, (int) $days);
        $today_start = strtotime(gmdate('Y-m-d 00:00:00'));
        $start_time = $today_start - (($days - 1) * 86400);
        $daily = array();
        $max_total = 0;

        for ($i = 0; $i < $days; $i++)
        {
            $day_start = $start_time + ($i * 86400);
            $daily[gmdate('Y-m-d', $day_start)] = array('label' => $user->format_date($day_start, 'd/m'), 'total' => 0);
        }

        $sql = 'SELECT log_time FROM ' . $table . ' WHERE log_time >= ' . (int) $start_time;
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $key = gmdate('Y-m-d', (int) $row['log_time']);
            if (isset($daily[$key]))
            {
                $daily[$key]['total']++;
                if ($daily[$key]['total'] > $max_total)
                {
                    $max_total = $daily[$key]['total'];
                }
            }
        }
        $db->sql_freeresult($result);

        foreach ($daily as $item)
        {
            $percent = ($max_total > 0) ? max(4, (int) round(($item['total'] / $max_total) * 100)) : 0;
            $template->assign_block_vars('stats_daily', array(
                'LABEL' => $item['label'],
                'TOTAL' => $item['total'],
                'PERCENT' => $percent,
            ));
        }
    }

    protected function show_logs($db, $request, $template, $user, $table_prefix)
    {
        $table = $table_prefix . 'antispamguard_log';

        $filter_form = $request->variable('filter_form', '');
        $filter_reason = $request->variable('filter_reason', '');
        $sfs_filter_action = $request->variable('sfs_filter_action', '');
        $sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');
        $start = max(0, $request->variable('start', 0));
        $per_page = 25;

        if (!in_array($filter_form, array('', 'register', 'post', 'contact', 'pm'), true))
        {
            $filter_form = '';
        }

        if (!in_array($filter_reason, array('', 'honeypot', 'timestamp', 'timestamp_too_fast', 'timestamp_expired', 'ip_reputation', 'content_filter', 'too_many_urls', 'ip_rate_limit', 'ip_blacklist', 'sfs_reputation', 'simulation_honeypot', 'simulation_timestamp', 'simulation_content_filter', 'simulation_too_many_urls', 'simulation_ip_rate_limit', 'simulation_ip_blacklist', 'simulation_sfs_reputation', 'subnet_abuse', 'random_gmail', 'simulation_subnet_abuse', 'simulation_random_gmail', 'simulation_multiple'), true))
        {
            $filter_reason = '';
        }

        if (!in_array($sfs_filter_action, array('', 'block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
        {
            $sfs_filter_action = '';
        }

        if (!in_array($sfs_filter_blocked, array('', '1', '0'), true))
        {
            $sfs_filter_blocked = '';
        }

        if ($request->is_set_post('export_csv'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $this->export_logs_csv($db, $table, $filter_form, $filter_reason);
        }

        if ($request->is_set_post('delete_marked') || $request->is_set_post('delete_filtered') || $request->is_set_post('clear_logs'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            if ($request->is_set_post('clear_logs'))
            {
                $db->sql_query('DELETE FROM ' . $table);
                trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_CLEARED') . adm_back_link($this->u_action));
            }

            if ($request->is_set_post('delete_filtered'))
            {
                $where_delete = $this->build_log_filter_where($db, $filter_form, $filter_reason);

                if ($where_delete === '')
                {
                    trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTER_REQUIRED') . adm_back_link($this->u_action), E_USER_WARNING);
                }

                $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . $where_delete;
                $result = $db->sql_query($sql);
                $deleted = (int) $db->sql_fetchfield('total_logs');
                $db->sql_freeresult($result);

                if ($deleted > 0)
                {
                    $db->sql_query('DELETE FROM ' . $table . $where_delete);
                }

                trigger_error($user->lang('ACP_ANTISPAMGUARD_FILTERED_LOGS_DELETED', $deleted) . adm_back_link($this->u_action));
            }

            $marked = $request->variable('mark', array(0));
            $log_ids = array();

            foreach ($marked as $log_id)
            {
                $log_id = (int) $log_id;
                if ($log_id > 0)
                {
                    $log_ids[] = $log_id;
                }
            }

            if (empty($log_ids))
            {
                trigger_error($user->lang('ACP_ANTISPAMGUARD_NO_LOG_SELECTED') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $db->sql_in_set('log_id', $log_ids);
            $db->sql_query($sql);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_DELETED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('prune_old_logs'))
        {
            if (!check_form_key('mundophpbb_antispamguard'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            global $config;

            $retention_days = isset($config['antispamguard_log_retention_days']) ? max(1, (int) $config['antispamguard_log_retention_days']) : 30;
            $deleted = $this->prune_old_logs($db, $table, $retention_days);

            trigger_error($user->lang('ACP_ANTISPAMGUARD_LOGS_PRUNED', $deleted, $retention_days) . adm_back_link($this->u_action));
        }

        $filter_params = '';

        if ($filter_form !== '')
        {
            $filter_params .= '&amp;filter_form=' . urlencode($filter_form);
        }

        if ($filter_reason !== '')
        {
            $filter_params .= '&amp;filter_reason=' . urlencode($filter_reason);
        }

        if ($sfs_filter_action !== '')
        {
            $filter_params .= '&amp;sfs_filter_action=' . urlencode($sfs_filter_action);
        }

        if ($sfs_filter_blocked !== '')
        {
            $filter_params .= '&amp;sfs_filter_blocked=' . urlencode($sfs_filter_blocked);
        }

        $where_sql = $this->build_log_filter_where($db, $filter_form, $filter_reason);

        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . $where_sql;
        $result = $db->sql_query($sql);
        $total_logs = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        if ($start >= $total_logs && $total_logs > 0)
        {
            $start = max(0, floor(($total_logs - 1) / $per_page) * $per_page);
        }

        $sql = 'SELECT * FROM ' . $table . $where_sql . ' ORDER BY log_time DESC';
        $result = $db->sql_query_limit($sql, $per_page, $start);

        $has_logs = false;
        while ($row = $db->sql_fetchrow($result))
        {
            $has_logs = true;
            $template->assign_block_vars('logs', array(
                'ID'         => (int) $row['log_id'],
                'TIME'       => $user->format_date((int) $row['log_time']),
                'IP'         => $row['user_ip'],
                'USERNAME'   => $row['username'],
                'EMAIL'      => $row['email'],
                'FORM_TYPE'  => $this->format_log_value(isset($row['form_type']) ? $row['form_type'] : 'register', 'form_type', $user),
                'REASON'     => $this->format_log_value($row['reason'], 'reason', $user),
                'USER_AGENT' => $row['user_agent'],
                'RISK_SCORE' => isset($row['risk_score']) ? (int) $row['risk_score'] : 0,
                'RISK_LEVEL' => isset($row['risk_level']) ? $row['risk_level'] : '',
                'ACTION' => isset($row['action']) ? $row['action'] : '',
                'MATCHED_RULES' => isset($row['matched_rules']) ? $row['matched_rules'] : '',
            ));
        }
        $db->sql_freeresult($result);

        $sfs_table = $table_prefix . 'antispamguard_sfs_log';
        $sfs_start = max(0, $request->variable('sfs_start', 0));
        $sfs_per_page = 25;
        $total_sfs_logs = 0;
        $total_sfs_logs_filtered = 0;
        $has_sfs_logs = false;
        $sfs_rows_rendered = 0;
        $sfs_seen_filtered = 0;
        $sfs_where = array();

        if ($sfs_filter_blocked !== '')
        {
            $sfs_where[] = 'blocked = ' . (int) $sfs_filter_blocked;
        }

        $sfs_where_sql = !empty($sfs_where) ? ' WHERE ' . implode(' AND ', $sfs_where) : '';

        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table;
        $result = $db->sql_query($sql);
        $total_sfs_logs = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        if ($sfs_filter_action === '')
        {
            $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $sfs_table . $sfs_where_sql;
            $result = $db->sql_query($sql);
            $total_sfs_logs_filtered = (int) $db->sql_fetchfield('total_logs');
            $db->sql_freeresult($result);

            if ($sfs_start >= $total_sfs_logs_filtered && $total_sfs_logs_filtered > 0)
            {
                $sfs_start = max(0, floor(($total_sfs_logs_filtered - 1) / $sfs_per_page) * $sfs_per_page);
            }

            $sql = 'SELECT *
                FROM ' . $sfs_table . $sfs_where_sql . '
                ORDER BY created_at DESC';
            $result = $db->sql_query_limit($sql, $sfs_per_page, $sfs_start);
        }
        else
        {
            // action_mode is stored inside details_json, so it must be filtered in PHP.
            // Iterate the result set once to count accurately and render the requested page.
            $sql = 'SELECT *
                FROM ' . $sfs_table . $sfs_where_sql . '
                ORDER BY created_at DESC';
            $result = $db->sql_query($sql);
        }

        while ($sfs_row = $db->sql_fetchrow($result))
        {
            $details = json_decode($sfs_row['details_json'], true);
            if (!is_array($details))
            {
                $details = array();
            }

            $detail_parts = array();
            $decision_meta = isset($details['_decision']) && is_array($details['_decision']) ? $details['_decision'] : array();
            $action_mode = isset($decision_meta['action_mode']) ? (string) $decision_meta['action_mode'] : 'block';
            $matched = !empty($decision_meta['matched']);

            if ($sfs_filter_action !== '' && $action_mode !== $sfs_filter_action)
            {
                continue;
            }

            if ($sfs_filter_action !== '')
            {
                $total_sfs_logs_filtered++;

                if ($sfs_seen_filtered < $sfs_start)
                {
                    $sfs_seen_filtered++;
                    continue;
                }
            }

            if ($sfs_rows_rendered >= $sfs_per_page)
            {
                if ($sfs_filter_action === '')
                {
                    break;
                }

                continue;
            }

            $has_sfs_logs = true;
            $sfs_rows_rendered++;
            $sfs_seen_filtered++;

            foreach ($details as $detail_type => $detail_data)
            {
                if ($detail_type === '_decision' || !is_array($detail_data))
                {
                    continue;
                }

                $detail_parts[] = strtoupper($detail_type) . ': '
                    . 'confidence=' . (isset($detail_data['confidence']) ? $detail_data['confidence'] : 0)
                    . ', frequency=' . (isset($detail_data['frequency']) ? $detail_data['frequency'] : 0)
                    . ', cached=' . (!empty($detail_data['cached']) ? $user->lang('YES') : $user->lang('NO'));
            }

            $template->assign_block_vars('sfs_logs', array(
                'ID' => (int) $sfs_row['log_id'],
                'TIME' => $user->format_date((int) $sfs_row['created_at']),
                'SOURCE' => $sfs_row['check_source'],
                'IP' => $sfs_row['user_ip'],
                'USERNAME' => $sfs_row['username'],
                'EMAIL' => $sfs_row['user_email'],
                'LISTED_COUNT' => (int) $sfs_row['listed_count'],
                'STRONG_HIT' => !empty($sfs_row['strong_hit']) ? $user->lang('YES') : $user->lang('NO'),
                'BLOCKED' => !empty($sfs_row['blocked']) ? $user->lang('YES') : $user->lang('NO'),
                'ACTION_MODE' => $this->format_sfs_action_mode($action_mode, $user),
                'MATCHED' => $matched ? $user->lang('YES') : $user->lang('NO'),
                'DETAILS' => !empty($detail_parts) ? implode('; ', $detail_parts) : '',
                'U_REPORT_PREFILL' => $this->append_url_param($this->get_sfs_mode_url(), 'sfs_prefill_log_id', (int) $sfs_row['log_id']),
                'S_CAN_PREFILL_REPORT' => ($sfs_row['user_ip'] !== '' || $sfs_row['user_email'] !== '' || $sfs_row['username'] !== ''),
            ));
        }
        $db->sql_freeresult($result);

        if ($sfs_filter_action !== '' && $sfs_start >= $total_sfs_logs_filtered && $total_sfs_logs_filtered > 0)
        {
            $sfs_start = max(0, floor(($total_sfs_logs_filtered - 1) / $sfs_per_page) * $sfs_per_page);
        }

        $ip_rep_table = $table_prefix . 'antispamguard_ip_score';
        $has_ip_reputation = false;
        $ip_reputation_threshold = isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 5;

        $sql = 'SELECT *
            FROM ' . $ip_rep_table . '
            ORDER BY score DESC, last_update DESC';
        $result = $db->sql_query_limit($sql, 25);

        while ($ip_rep_row = $db->sql_fetchrow($result))
        {
            $has_ip_reputation = true;

            $template->assign_block_vars('ip_reputation_rows', array(
                'IP' => $ip_rep_row['ip'],
                'SCORE' => (int) $ip_rep_row['score'],
                'HITS' => (int) $ip_rep_row['hits'],
                'LAST_REASON' => $this->format_ip_reputation_reason($ip_rep_row['last_reason'], $user),
                'FIRST_SEEN' => !empty($ip_rep_row['first_seen']) ? $user->format_date((int) $ip_rep_row['first_seen']) : '',
                'LAST_UPDATE' => !empty($ip_rep_row['last_update']) ? $user->format_date((int) $ip_rep_row['last_update']) : '',
                'EXPIRES_AT' => !empty($ip_rep_row['expires_at']) ? $user->format_date((int) $ip_rep_row['expires_at']) : '',
                'S_BLOCKED' => ((int) $ip_rep_row['score'] >= $ip_reputation_threshold),
            ));
        }
        $db->sql_freeresult($result);

        $base_url = $this->u_action . $filter_params;
        $pagination = $this->build_pagination($base_url, $total_logs, $per_page, $start);
        $page_number = $this->build_page_number($user, $total_logs, $per_page, $start);
        $sfs_pagination = $this->build_pagination($base_url, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start, 'sfs_start');
        $sfs_page_number = $this->build_page_number($user, $total_sfs_logs_filtered, $sfs_per_page, $sfs_start);

        $template->assign_vars(array(
            'S_LOGS' => true,
            'S_HAS_LOGS' => $has_logs,
            'S_HAS_SFS_LOGS' => $has_sfs_logs,
            'S_HAS_IP_REPUTATION' => $has_ip_reputation,
            'TOTAL_SFS_LOGS' => $total_sfs_logs,
            'TOTAL_SFS_LOGS_FILTERED' => $total_sfs_logs_filtered,
            'SFS_FILTER_ACTION' => $sfs_filter_action,
            'SFS_FILTER_BLOCKED' => $sfs_filter_blocked,
            'S_FILTER_ACTIVE' => ($filter_form !== '' || $filter_reason !== ''),
            'S_SFS_FILTER_ACTIVE' => ($sfs_filter_action !== '' || $sfs_filter_blocked !== ''),
            'U_ACTION' => $this->u_action,
            'FILTER_FORM' => $filter_form,
            'FILTER_REASON' => $filter_reason,
            'TOTAL_LOGS' => $total_logs,
            'PAGE_NUMBER' => $page_number,
            'PAGINATION' => $pagination,
            'SFS_PAGINATION' => $sfs_pagination,
            'SFS_PAGE_NUMBER' => $sfs_page_number,
            'ANTISPAMGUARD_LOG_RETENTION_DAYS' => isset($GLOBALS['config']['antispamguard_log_retention_days']) ? (int) $GLOBALS['config']['antispamguard_log_retention_days'] : 30,
        ));
    }

    protected function format_log_value($value, $type, $user = null)
    {
        if ($user === null)
        {
            global $user;
        }

        $map = array(
            'form_type' => array(
                'register' => 'ACP_ANTISPAMGUARD_FORM_REGISTER',
                'post' => 'ACP_ANTISPAMGUARD_FORM_POST',
                'contact' => 'ACP_ANTISPAMGUARD_FORM_CONTACT',
                'pm' => 'ACP_ANTISPAMGUARD_FORM_PM',
            ),
            'reason' => array(
                'honeypot' => 'ACP_ANTISPAMGUARD_REASON_HONEYPOT',
                'timestamp' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP',
                'content_filter' => 'ACP_ANTISPAMGUARD_REASON_CONTENT_FILTER',
                'too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_TOO_MANY_URLS',
                'ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_IP_RATE_LIMIT',
                'subnet_abuse' => 'ACP_ANTISPAMGUARD_REASON_SUBNET_ABUSE',
                'random_gmail' => 'ACP_ANTISPAMGUARD_REASON_RANDOM_GMAIL',
                'ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_IP_BLACKLIST',
                'ip_reputation' => 'ACP_ANTISPAMGUARD_REASON_IP_REPUTATION',
                'combined_decision' => 'ACP_ANTISPAMGUARD_REASON_COMBINED_DECISION',
                'slow_spam' => 'ACP_ANTISPAMGUARD_REASON_SLOW_SPAM',
                'sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
                'simulation_honeypot' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_HONEYPOT',
                'simulation_timestamp' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP',
                'simulation_timestamp_too_fast' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP_TOO_FAST',
                'simulation_timestamp_expired' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP_EXPIRED',
                'simulation_content_filter' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_CONTENT_FILTER',
                'simulation_too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TOO_MANY_URLS',
                'simulation_ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_RATE_LIMIT',
                'simulation_subnet_abuse' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_SUBNET_ABUSE',
                'simulation_random_gmail' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_RANDOM_GMAIL',
                'simulation_ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_BLACKLIST',
                'simulation_sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_SFS_REPUTATION',
                'simulation_multiple' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_MULTIPLE',
                'unknown' => 'ACP_ANTISPAMGUARD_REASON_UNKNOWN',
            ),
        );

        if (isset($map[$type][$value]))
        {
            return $user->lang($map[$type][$value]);
        }

        return $value;
    }

    protected function build_log_filter_where($db, $filter_form = '', $filter_reason = '')
    {
        $where = array();

        if ($filter_form !== '')
        {
            $where[] = "form_type = '" . $db->sql_escape($filter_form) . "'";
        }

        if ($filter_reason !== '')
        {
            $where[] = "reason " . $db->sql_like_expression($db->get_any_char() . $db->sql_escape($filter_reason) . $db->get_any_char());
        }

        return empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
    }
    protected function export_logs_csv($db, $table, $filter_form = '', $filter_reason = '')
    {
        $where_sql = $this->build_log_filter_where($db, $filter_form, $filter_reason);
        $filename = 'antispamguard_logs_' . gmdate('Y-m-d_H-i-s') . '.csv';

        while (ob_get_level())
        {
            @ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array('log_id', 'log_time', 'user_ip', 'username', 'email', 'form_type', 'reason', 'risk_score', 'risk_level', 'action', 'matched_rules', 'user_agent'));

        $sql = 'SELECT * FROM ' . $table . $where_sql . ' ORDER BY log_time DESC';
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            fputcsv($output, array(
                (int) $row['log_id'],
                gmdate('Y-m-d H:i:s', (int) $row['log_time']),
                isset($row['user_ip']) ? $row['user_ip'] : '',
                isset($row['username']) ? $row['username'] : '',
                isset($row['email']) ? $row['email'] : '',
                isset($row['form_type']) ? $row['form_type'] : 'register',
                isset($row['reason']) ? $row['reason'] : '',
                isset($row['risk_score']) ? (int) $row['risk_score'] : 0,
                isset($row['risk_level']) ? $row['risk_level'] : '',
                isset($row['action']) ? $row['action'] : '',
                isset($row['matched_rules']) ? $row['matched_rules'] : '',
                isset($row['user_agent']) ? $row['user_agent'] : '',
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        exit;
    }

    protected function prune_old_logs($db, $table, $retention_days)
    {
        $cutoff = time() - ((int) $retention_days * 86400);

        $sql = 'SELECT COUNT(log_id) AS total_logs FROM ' . $table . ' WHERE log_time < ' . (int) $cutoff;
        $result = $db->sql_query($sql);
        $deleted = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        if ($deleted > 0)
        {
            $db->sql_query('DELETE FROM ' . $table . ' WHERE log_time < ' . (int) $cutoff);
        }

        return $deleted;
    }

    protected function build_page_number($user, $total_logs, $per_page, $start)
    {
        if ($total_logs <= 0)
        {
            return '';
        }

        $current_page = (int) floor($start / $per_page) + 1;
        $total_pages = (int) ceil($total_logs / $per_page);

        return $user->lang('PAGE_OF', $current_page, $total_pages);
    }

    protected function build_pagination($base_url, $total_logs, $per_page, $start, $start_param = 'start')
    {
        if ($total_logs <= $per_page)
        {
            return '';
        }

        $total_pages = (int) ceil($total_logs / $per_page);
        $current_page = (int) floor($start / $per_page) + 1;
        $current_page = max(1, min($current_page, $total_pages));
        $links = array();
        $separator = (strpos($base_url, '?') === false) ? '?' : '&amp;';

        $make_url = function ($page) use ($base_url, $separator, $per_page, $start_param)
        {
            $page_start = ($page - 1) * $per_page;
            return $base_url . $separator . rawurlencode($start_param) . '=' . $page_start;
        };

        if ($current_page > 1)
        {
            $links[] = '<a class="asg-page-prev" href="' . $make_url($current_page - 1) . '">&lsaquo;</a>';
        }

        $pages = array(1, $total_pages, $current_page - 1, $current_page, $current_page + 1);

        if ($current_page <= 3)
        {
            $pages[] = 2;
            $pages[] = 3;
        }

        if ($current_page >= ($total_pages - 2))
        {
            $pages[] = $total_pages - 1;
            $pages[] = $total_pages - 2;
        }

        $pages = array_unique(array_filter($pages, function ($page) use ($total_pages)
        {
            return $page >= 1 && $page <= $total_pages;
        }));

        sort($pages);

        $previous_page = 0;

        foreach ($pages as $page)
        {
            if ($previous_page && $page > ($previous_page + 1))
            {
                $links[] = '<span class="asg-page-gap">&hellip;</span>';
            }

            if ($page === $current_page)
            {
                $links[] = '<span class="asg-page-current">' . $page . '</span>';
            }
            else
            {
                $links[] = '<a href="' . $make_url($page) . '">' . $page . '</a>';
            }

            $previous_page = $page;
        }

        if ($current_page < $total_pages)
        {
            $links[] = '<a class="asg-page-next" href="' . $make_url($current_page + 1) . '">&rsaquo;</a>';
        }

        return implode(' ', $links);
    }
    protected function test_ip_whitelist_match($ip, $list)
    {
        $ip = trim((string) $ip);

        if ($ip === '' || trim((string) $list) === '')
        {
            return array('matched' => false, 'entry' => '');
        }

        $entries = preg_split('/\r\n|\r|\n/', (string) $list);

        foreach ($entries as $entry)
        {
            $entry = trim($entry);

            if ($entry === '' || strpos($entry, '#') === 0)
            {
                continue;
            }

            if ($this->test_ip_entry_matches($ip, $entry))
            {
                return array('matched' => true, 'entry' => $entry);
            }
        }

        return array('matched' => false, 'entry' => '');
    }

    protected function test_ip_entry_matches($ip, $entry)
    {
        if ($entry === $ip)
        {
            return true;
        }

        if (strpos($entry, '*') !== false)
        {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($entry, '/')) . '$/i';

            return (bool) preg_match($pattern, $ip);
        }

        if (strpos($entry, '/') !== false)
        {
            return $this->test_ip_cidr_matches($ip, $entry);
        }

        return false;
    }

    protected function test_ip_cidr_matches($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2)
        {
            return false;
        }

        $subnet = trim($parts[0]);
        $bits = (int) trim($parts[1]);

        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin))
        {
            return false;
        }

        $length = strlen($ip_bin);
        $max_bits = $length * 8;

        if ($bits < 0 || $bits > $max_bits)
        {
            return false;
        }

        $full_bytes = (int) floor($bits / 8);
        $remaining_bits = $bits % 8;

        if ($full_bytes > 0 && substr($ip_bin, 0, $full_bytes) !== substr($subnet_bin, 0, $full_bytes))
        {
            return false;
        }

        if ($remaining_bits === 0)
        {
            return true;
        }

        $mask = (0xff << (8 - $remaining_bits)) & 0xff;

        return ((ord($ip_bin[$full_bytes]) & $mask) === (ord($subnet_bin[$full_bytes]) & $mask));
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

    protected function export_ip_reputation_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
    {
        $filename = 'antispamguard_ip_reputation_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array(
            'ip',
            'score',
            'hits',
            'last_reason',
            'first_seen',
            'last_update',
            'expires_at',
            'blocked_by_threshold',
        ));

        $threshold_sql = 'SELECT config_value
            FROM ' . CONFIG_TABLE . "
            WHERE config_name = 'antispamguard_ip_reputation_threshold'";
        $threshold_result = $db->sql_query($threshold_sql);
        $threshold = (int) $db->sql_fetchfield('config_value');
        $db->sql_freeresult($threshold_result);

        if ($threshold <= 0)
        {
            $threshold = 5;
        }

        $sql = 'SELECT *
            FROM ' . $table_prefix . 'antispamguard_ip_score
            ORDER BY score DESC, last_update DESC';
        $result = $db->sql_query_limit($sql, 5000);

        while ($row = $db->sql_fetchrow($result))
        {
            fputcsv($output, array(
                $row['ip'],
                (int) $row['score'],
                (int) $row['hits'],
                $row['last_reason'],
                !empty($row['first_seen']) ? $user->format_date((int) $row['first_seen']) : '',
                !empty($row['last_update']) ? $user->format_date((int) $row['last_update']) : '',
                !empty($row['expires_at']) ? $user->format_date((int) $row['expires_at']) : '',
                ((int) $row['score'] >= $threshold) ? 1 : 0,
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        garbage_collection();
        exit_handler();
    }

    protected function format_ip_reputation_reason($reason, \phpbb\user $user)
    {
        $map = array(
            'honeypot' => 'ACP_ANTISPAMGUARD_REASON_HONEYPOT',
            'timestamp' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP',
            'timestamp_too_fast' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_TOO_FAST',
            'timestamp_expired' => 'ACP_ANTISPAMGUARD_REASON_TIMESTAMP_EXPIRED',
            'content_filter' => 'ACP_ANTISPAMGUARD_REASON_CONTENT_FILTER',
            'too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_TOO_MANY_URLS',
            'ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_IP_RATE_LIMIT',
            'ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_IP_BLACKLIST',
            'ip_reputation' => 'ACP_ANTISPAMGUARD_REASON_IP_REPUTATION',
            'sfs_reputation' => 'ACP_ANTISPAMGUARD_REASON_SFS_REPUTATION',
        );

        if (isset($map[$reason]) && isset($user->lang[$map[$reason]]))
        {
            return $user->lang($map[$reason]);
        }

        return (string) $reason;
    }

    protected function format_sfs_action_mode($mode, \phpbb\user $user)
    {
        switch ($mode)
        {
            case 'soft':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_SOFT');
            case 'log_only':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_LOG_ONLY');
            case 'disabled':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_DISABLED');
            case 'whitelist':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_WHITELIST');
            case 'block':
            default:
                return $user->lang('ACP_ANTISPAMGUARD_SFS_ACTION_BLOCK');
        }
    }

    protected function format_sfs_status($status, \phpbb\user $user)
    {
        switch ($status)
        {
            case 'sfs_disabled':
            case 'sfs_disabled_manual_check':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_DISABLED');
            case 'whitelisted':
                return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_WHITELISTED');
            case 'checked':
            default:
                return $user->lang('ACP_ANTISPAMGUARD_SFS_STATUS_CHECKED');
        }
    }

    protected function export_sfs_logs_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix, \phpbb\request\request_interface $request)
    {
        $sfs_filter_action = $request->variable('sfs_filter_action', '');
        $sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');

        if (!in_array($sfs_filter_action, array('', 'block', 'soft', 'log_only', 'whitelist', 'disabled'), true))
        {
            $sfs_filter_action = '';
        }

        if (!in_array($sfs_filter_blocked, array('', '1', '0'), true))
        {
            $sfs_filter_blocked = '';
        }

        $filename = 'antispamguard_sfs_logs_' . gmdate('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for better spreadsheet compatibility.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array(
            'log_id',
            'created_at',
            'source',
            'ip',
            'username',
            'email',
            'listed_count',
            'strong_hit',
            'blocked',
            'action_mode',
            'matched',
            'details',
        ));

        $where = array();

        if ($sfs_filter_blocked !== '')
        {
            $where[] = 'blocked = ' . (int) $sfs_filter_blocked;
        }

        $where_sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        $sql = 'SELECT *
            FROM ' . $table_prefix . 'antispamguard_sfs_log' . $where_sql . '
            ORDER BY created_at DESC';
        $result = $db->sql_query_limit($sql, 1000);

        while ($row = $db->sql_fetchrow($result))
        {
            $details = json_decode($row['details_json'], true);
            $decision_meta = is_array($details) && isset($details['_decision']) && is_array($details['_decision']) ? $details['_decision'] : array();
            $action_mode = isset($decision_meta['action_mode']) ? (string) $decision_meta['action_mode'] : 'block';
            $matched = !empty($decision_meta['matched']) ? 1 : 0;

            if ($sfs_filter_action !== '' && $action_mode !== $sfs_filter_action)
            {
                continue;
            }

            fputcsv($output, array(
                (int) $row['log_id'],
                $user->format_date((int) $row['created_at']),
                $row['check_source'],
                $row['user_ip'],
                $row['username'],
                $row['user_email'],
                (int) $row['listed_count'],
                !empty($row['strong_hit']) ? 1 : 0,
                !empty($row['blocked']) ? 1 : 0,
                $action_mode,
                $matched,
                $row['details_json'],
            ));
        }

        $db->sql_freeresult($result);
        fclose($output);
        garbage_collection();
        exit_handler();
    }


}
