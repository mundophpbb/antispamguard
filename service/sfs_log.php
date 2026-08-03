<?php
/**
 * AntiSpam Guard - StopForumSpam log service.
 */

namespace mundophpbb\antispamguard\service;

class sfs_log
{
    protected $db;
    protected $table;

    public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db = $db;
        $this->table = $table_prefix . 'antispamguard_sfs_log';
    }

    public function add($source, $ip, $email, $username, array $decision)
    {
        $details = isset($decision['results']) ? $decision['results'] : array();
        $details['_decision'] = array(
            'action_mode' => isset($decision['action_mode']) ? $decision['action_mode'] : 'block',
            'matched' => !empty($decision['matched']),
            'soft' => !empty($decision['soft']),
            'log_only' => !empty($decision['log_only']),
            'review_only' => !empty($decision['review_only']),
            'hard_identity_match' => !empty($decision['hard_identity_match']),
            'listed_identifiers' => isset($decision['listed_identifiers']) ? array_values((array) $decision['listed_identifiers']) : array(),
            'strong_identifiers' => isset($decision['strong_identifiers']) ? array_values((array) $decision['strong_identifiers']) : array(),
            'debug' => !empty($decision['debug']),
            'debug_status' => isset($decision['debug_status']) ? $decision['debug_status'] : '',
            'status' => isset($decision['status']) ? $decision['status'] : '',
            'sfs_enabled' => !isset($decision['sfs_enabled']) || !empty($decision['sfs_enabled']),
        );

        $data = array(
            'check_source' => (string) $source,
            'user_ip' => (string) $ip,
            'user_email' => (string) $email,
            'username' => (string) $username,
            'listed_count' => isset($decision['listed_count']) ? (int) $decision['listed_count'] : 0,
            'strong_hit' => !empty($decision['strong_hit']) ? 1 : 0,
            'blocked' => !empty($decision['block']) ? 1 : 0,
            'action_mode' => isset($decision['action_mode']) ? (string) $decision['action_mode'] : 'block',
            'submission_key' => isset($decision['submission_key']) ? (string) $decision['submission_key'] : '',
            'details_json' => json_encode($details),
            'created_at' => time(),
        );

        $existing_log_id = $this->merge_recent_related_log($data);
        if ($existing_log_id)
        {
            return (int) $existing_log_id;
        }

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_nextid();
    }

    /**
     * Merge SFS checks that belong to the same form submission/person.
     *
     * A registration can be logged more than once while phpBB validates the
     * request. Early rows may only contain the IP, then a later row contains
     * the submitted username/email. Keeping them as separate rows creates a
     * false conflict in ACP review, so the sparse row is enriched instead of
     * creating a second visible candidate.
     */
    protected function merge_recent_related_log(array $data)
    {
        $submission_key = isset($data['submission_key']) ? trim((string) $data['submission_key']) : '';
        $window_start = max(0, (int) $data['created_at'] - 30);

        $where = "check_source = '" . $this->db->sql_escape($data['check_source']) . "'";
        if ($submission_key !== '')
        {
            $where .= " AND submission_key = '" . $this->db->sql_escape($submission_key) . "'";
        }
        else
        {
            // Compatibility fallback for manual/legacy calls.  Keep the
            // correlation window short to avoid joining different people
            // behind the same carrier-grade NAT or corporate proxy.
            $where .= ' AND created_at >= ' . (int) $window_start;
            $where .= " AND user_ip = '" . $this->db->sql_escape($data['user_ip']) . "'";
        }

        $sql = 'SELECT *
            FROM ' . $this->table . '
            WHERE ' . $where . '
            ORDER BY created_at DESC, log_id DESC';
        $result = $this->db->sql_query_limit($sql, 25);

        $best = false;
        while ($row = $this->db->sql_fetchrow($result))
        {
            if ($this->is_same_submission($row, $data))
            {
                $best = $row;
                break;
            }
        }
        $this->db->sql_freeresult($result);

        if (!$best)
        {
            return 0;
        }

        $merged = array(
            'user_email' => ((string) $best['user_email'] !== '') ? (string) $best['user_email'] : (string) $data['user_email'],
            'username' => ((string) $best['username'] !== '') ? (string) $best['username'] : (string) $data['username'],
            'listed_count' => max((int) $best['listed_count'], (int) $data['listed_count']),
            'strong_hit' => (!empty($best['strong_hit']) || !empty($data['strong_hit'])) ? 1 : 0,
            'blocked' => (!empty($best['blocked']) || !empty($data['blocked'])) ? 1 : 0,
            'action_mode' => $this->strongest_action_mode((string) $best['action_mode'], (string) $data['action_mode']),
            'submission_key' => ((string) $best['submission_key'] !== '') ? (string) $best['submission_key'] : (string) $data['submission_key'],
            'details_json' => $this->merge_details_json((string) $best['details_json'], (string) $data['details_json']),
            'created_at' => max((int) $best['created_at'], (int) $data['created_at']),
        );

        $sql = 'UPDATE ' . $this->table . ' SET ' . $this->db->sql_build_array('UPDATE', $merged) . '
            WHERE log_id = ' . (int) $best['log_id'];
        $this->db->sql_query($sql);

        return (int) $best['log_id'];
    }

    protected function is_same_submission(array $existing, array $incoming)
    {
        $existing_key = isset($existing['submission_key']) ? trim((string) $existing['submission_key']) : '';
        $incoming_key = isset($incoming['submission_key']) ? trim((string) $incoming['submission_key']) : '';

        if ($existing_key !== '' || $incoming_key !== '')
        {
            return $existing_key !== '' && hash_equals($existing_key, $incoming_key);
        }

        $existing_email = strtolower(trim((string) $existing['user_email']));
        $incoming_email = strtolower(trim((string) $incoming['user_email']));
        $existing_username = strtolower(trim((string) $existing['username']));
        $incoming_username = strtolower(trim((string) $incoming['username']));

        if ($existing_email !== '' && $incoming_email !== '' && $existing_email !== $incoming_email)
        {
            return false;
        }

        if ($existing_username !== '' && $incoming_username !== '' && $existing_username !== $incoming_username)
        {
            return false;
        }

        return true;
    }

    protected function strongest_action_mode($a, $b)
    {
        $rank = array(
            'disabled' => 0,
            'whitelist' => 1,
            'log_only' => 2,
            'soft' => 3,
            'block' => 4,
        );

        $a = isset($rank[$a]) ? $a : 'log_only';
        $b = isset($rank[$b]) ? $b : 'log_only';

        return ($rank[$b] > $rank[$a]) ? $b : $a;
    }

    protected function merge_details_json($existing_json, $incoming_json)
    {
        $existing = json_decode((string) $existing_json, true);
        $incoming = json_decode((string) $incoming_json, true);

        if (!is_array($existing))
        {
            $existing = array();
        }
        if (!is_array($incoming))
        {
            $incoming = array();
        }

        foreach ($incoming as $key => $incoming_value)
        {
            if (!isset($existing[$key]) || !is_array($existing[$key]) || !is_array($incoming_value))
            {
                $existing[$key] = $incoming_value;
                continue;
            }

            $existing[$key] = array_merge($existing[$key], $incoming_value);

            if (isset($incoming_value['confidence'], $existing[$key]['confidence']))
            {
                $existing[$key]['confidence'] = max((float) $existing[$key]['confidence'], (float) $incoming_value['confidence']);
            }
            if (isset($incoming_value['frequency'], $existing[$key]['frequency']))
            {
                $existing[$key]['frequency'] = max((int) $existing[$key]['frequency'], (int) $incoming_value['frequency']);
            }
            if (isset($incoming_value['is_listed'], $existing[$key]['is_listed']))
            {
                $existing[$key]['is_listed'] = !empty($existing[$key]['is_listed']) || !empty($incoming_value['is_listed']);
            }
            if (isset($incoming_value['cached'], $existing[$key]['cached']))
            {
                $existing[$key]['cached'] = !empty($existing[$key]['cached']) && !empty($incoming_value['cached']);
            }
        }

        return json_encode($existing);
    }
}
