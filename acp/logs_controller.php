<?php
/**
 * AntiSpam Guard — ACP logs and statistics controller.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

class logs_controller
{
    public $u_action;

    /** @var settings_helper */
    protected $settings_helper;

    /** @var pagination_helper */
    protected $pagination;

    /** @var sfs_controller */
    protected $sfs_controller;

    public function __construct($u_action, settings_helper $settings_helper = null, pagination_helper $pagination = null, sfs_controller $sfs_controller = null)
    {
        $this->u_action = $u_action;
        $this->settings_helper = $settings_helper ?: new settings_helper();
        $this->pagination = $pagination ?: new pagination_helper();
        $this->sfs_controller = $sfs_controller ?: new sfs_controller($u_action, $this->settings_helper, $this->pagination);
    }

    public function set_sfs_controller(sfs_controller $sfs_controller)
    {
        $this->sfs_controller = $sfs_controller;
    }
    public function show_stats($db, $template, $table_prefix)
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
        $ip_rep_threshold = isset($config['antispamguard_ip_reputation_threshold']) ? (int) $config['antispamguard_ip_reputation_threshold'] : 5;

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

    public function count_logs($db, $table, $where = '')
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

    public function assign_group_stats($db, $template, $table, $column, $block_name, $fallback_label, $grand_total = 0, $user = null)
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

    public function get_top_stat($db, $table, $column, $fallback_label, $user = null)
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

    public function assign_daily_stats($db, $template, $table, $days = 7)
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
    public function show_logs($db, $request, $template, $user, $table_prefix)
    {
        global $config;

        $table = $table_prefix . 'antispamguard_log';

        $filter_form = $request->variable('filter_form', '');
        $filter_reason = $request->variable('filter_reason', '');
        $sfs_filter_action = $request->variable('sfs_filter_action', '');
        $sfs_filter_blocked = $request->variable('sfs_filter_blocked', '');
        $sfs_filter_review = $request->variable('sfs_filter_review', '');
        $sfs_filter_query = trim($request->variable('sfs_filter_query', '', true));
        $sfs_filter_query = $this->settings_helper->truncate_for_storage($sfs_filter_query, 100);
        $start = max(0, $request->variable('start', 0));
        $per_page = 25;

        if (!in_array($filter_form, array('', 'register', 'post', 'contact', 'pm'), true))
        {
            $filter_form = '';
        }

        if (!in_array($filter_reason, array('', 'honeypot', 'timestamp', 'timestamp_too_fast', 'timestamp_expired', 'ip_reputation', 'content_filter', 'too_many_urls', 'ip_rate_limit', 'ip_blacklist', 'sfs_reputation', 'simulation_honeypot', 'simulation_timestamp', 'simulation_content_filter', 'simulation_too_many_urls', 'simulation_ip_rate_limit', 'simulation_ip_blacklist', 'simulation_sfs_reputation', 'subnet_abuse', 'random_gmail', 'simulation_subnet_abuse', 'simulation_random_gmail', 'possible_false_positive', 'simulation_multiple'), true))
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

        if (!in_array($sfs_filter_review, array('', 'pending', 'reported', 'blocked', 'reported_blocked', 'allowed'), true))
        {
            $sfs_filter_review = '';
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

        if ($sfs_filter_review !== '')
        {
            $filter_params .= '&amp;sfs_filter_review=' . urlencode($sfs_filter_review);
        }

        if ($sfs_filter_query !== '')
        {
            $filter_params .= '&amp;sfs_filter_query=' . urlencode($sfs_filter_query);
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

        $this->sfs_controller->assign_sfs_logs($db, $request, $template, $user, $table_prefix, $this->u_action . $filter_params);

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
        $pagination = $this->pagination->build_pagination($base_url, $total_logs, $per_page, $start);
        $page_number = $this->pagination->build_page_number($user, $total_logs, $per_page, $start);

        $template->assign_vars(array(
            'S_LOGS' => true,
            'S_HAS_LOGS' => $has_logs,
            'S_HAS_IP_REPUTATION' => $has_ip_reputation,
            'S_FILTER_ACTIVE' => ($filter_form !== '' || $filter_reason !== ''),
            'U_ACTION' => $this->u_action,
            'FILTER_FORM' => $filter_form,
            'FILTER_REASON' => $filter_reason,
            'TOTAL_LOGS' => $total_logs,
            'PAGE_NUMBER' => $page_number,
            'PAGINATION' => $pagination,
            'ANTISPAMGUARD_LOG_RETENTION_DAYS' => isset($config['antispamguard_log_retention_days']) ? (int) $config['antispamguard_log_retention_days'] : 30,
        ));
    }
    public function format_log_value($value, $type, $user = null)
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
                'possible_false_positive' => 'ACP_ANTISPAMGUARD_REASON_POSSIBLE_FALSE_POSITIVE',
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

    public function build_log_filter_where($db, $filter_form = '', $filter_reason = '')
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
    public function export_logs_csv($db, $table, $filter_form = '', $filter_reason = '')
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

        garbage_collection();
        exit_handler();
    }

    public function prune_old_logs($db, $table, $retention_days)
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
    public function export_ip_reputation_csv(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
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

    public function format_ip_reputation_reason($reason, \phpbb\user $user)
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
}
