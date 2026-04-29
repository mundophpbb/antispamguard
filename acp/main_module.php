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
            $config->set('antispamguard_hp_name', $field_name);
            $config->set('antispamguard_protect_posts', $request->variable('antispamguard_protect_posts', 0));
            $config->set('antispamguard_protect_contact', $request->variable('antispamguard_protect_contact', 0));
            $config->set('antispamguard_protect_pm', $request->variable('antispamguard_protect_pm', 0));
            $config->set('antispamguard_posts_guests_only', $request->variable('antispamguard_posts_guests_only', 1));
            $config->set('antispamguard_bypass_group_ids', $this->normalize_group_ids($request->variable('antispamguard_bypass_group_ids', '')));
            $config->set('antispamguard_content_filter_enabled', $request->variable('antispamguard_content_filter_enabled', 0));
            $config->set('antispamguard_blocked_keywords', $this->normalize_blocked_keywords($request->variable('antispamguard_blocked_keywords', '', true)));
            $config->set('antispamguard_max_urls', max(0, $request->variable('antispamguard_max_urls', 0)));
            $config->set('antispamguard_ip_whitelist', $this->normalize_ip_list($request->variable('antispamguard_ip_whitelist', '', true)));
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

            trigger_error($user->lang('ACP_ANTISPAMGUARD_SAVED') . adm_back_link($this->u_action));
        }

        $template->assign_vars(array(
            'S_SETTINGS' => true,
            'U_ACTION' => $this->u_action,
            'ANTISPAMGUARD_ENABLED' => !empty($config['antispamguard_enabled']),
            'ANTISPAMGUARD_HP_NAME' => isset($config['antispamguard_hp_name']) ? $config['antispamguard_hp_name'] : 'homepage',
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
        ));
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

    protected function get_settings_keys()
    {
        return array(
            'antispamguard_enabled',
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

    protected function export_settings_json($config)
    {
        $data = array(
            'extension' => 'mundophpbb/antispamguard',
            'version' => isset($config['antispamguard_version']) ? $config['antispamguard_version'] : '2.1.0',
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

        $template->assign_vars(array(
            'S_ABOUT' => true,
            'ANTISPAMGUARD_VERSION' => '2.4.0',
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
        $start = max(0, $request->variable('start', 0));
        $per_page = 25;

        if (!in_array($filter_form, array('', 'register', 'post', 'contact', 'pm'), true))
        {
            $filter_form = '';
        }

        if (!in_array($filter_reason, array('', 'honeypot', 'timestamp', 'content_filter', 'too_many_urls', 'ip_rate_limit', 'ip_blacklist', 'simulation_honeypot', 'simulation_timestamp', 'simulation_content_filter', 'simulation_too_many_urls', 'simulation_ip_rate_limit', 'simulation_ip_blacklist', 'simulation_multiple'), true))
        {
            $filter_reason = '';
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
            ));
        }
        $db->sql_freeresult($result);

        $base_url = $this->u_action . $filter_params;
        $pagination = $this->build_pagination($base_url, $total_logs, $per_page, $start);
        $page_number = $this->build_page_number($user, $total_logs, $per_page, $start);

        $template->assign_vars(array(
            'S_LOGS' => true,
            'S_HAS_LOGS' => $has_logs,
            'S_FILTER_ACTIVE' => ($filter_form !== '' || $filter_reason !== ''),
            'U_ACTION' => $this->u_action,
            'FILTER_FORM' => $filter_form,
            'FILTER_REASON' => $filter_reason,
            'TOTAL_LOGS' => $total_logs,
            'PAGE_NUMBER' => $page_number,
            'PAGINATION' => $pagination,
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
                'ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_IP_BLACKLIST',
                'simulation_honeypot' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_HONEYPOT',
                'simulation_timestamp' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TIMESTAMP',
                'simulation_content_filter' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_CONTENT_FILTER',
                'simulation_too_many_urls' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_TOO_MANY_URLS',
                'simulation_ip_rate_limit' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_RATE_LIMIT',
                'simulation_ip_blacklist' => 'ACP_ANTISPAMGUARD_REASON_SIMULATION_IP_BLACKLIST',
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
        fputcsv($output, array('log_id', 'log_time', 'user_ip', 'username', 'email', 'form_type', 'reason', 'user_agent'));

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

    protected function build_pagination($base_url, $total_logs, $per_page, $start)
    {
        if ($total_logs <= $per_page)
        {
            return '';
        }

        $total_pages = (int) ceil($total_logs / $per_page);
        $current_page = (int) floor($start / $per_page) + 1;
        $links = array();

        for ($page = 1; $page <= $total_pages; $page++)
        {
            $page_start = ($page - 1) * $per_page;

            if ($page === $current_page)
            {
                $links[] = '<strong>' . $page . '</strong>';
            }
            else
            {
                $separator = (strpos($base_url, '?') === false) ? '?' : '&amp;';
                $url = $base_url . $separator . 'start=' . $page_start;
                $links[] = '<a href="' . $url . '">' . $page . '</a>';
            }
        }

        return implode(' ', $links);
    }
}
