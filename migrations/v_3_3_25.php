<?php
/**
 * AntiSpam Guard 3.3.25 - Merge near-duplicate main logs with different reasons.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_25 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.25', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_22');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'merge_near_duplicate_main_logs'))),
            array('config.update', array('antispamguard_version', '3.3.25')),
        );
    }

    public function merge_near_duplicate_main_logs()
    {
        $table = $this->table_prefix . 'antispamguard_log';

        if (!$this->db_tools->sql_table_exists($table))
        {
            return;
        }

        $clusters = array();

        $sql = 'SELECT log_id, log_time, user_ip, username, email, form_type, reason, user_agent
            FROM ' . $table . '
            ORDER BY user_ip ASC, username ASC, email ASC, form_type ASC, user_agent ASC, log_time ASC, log_id ASC';
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result))
        {
            $identity = implode("\x1F", array(
                (string) $row['user_ip'],
                (string) $row['username'],
                (string) $row['email'],
                (string) $row['form_type'],
                (string) $row['user_agent'],
            ));

            if (!isset($clusters[$identity]))
            {
                $clusters[$identity] = array();
            }

            $placed = false;
            foreach ($clusters[$identity] as $idx => $cluster)
            {
                if (abs((int) $row['log_time'] - (int) $cluster['time']) <= 5)
                {
                    $clusters[$identity][$idx]['rows'][] = $row;
                    $clusters[$identity][$idx]['reasons'] = $this->merge_reasons($clusters[$identity][$idx]['reasons'], $row['reason']);

                    if ((int) $row['log_time'] > (int) $clusters[$identity][$idx]['time'])
                    {
                        $clusters[$identity][$idx]['time'] = (int) $row['log_time'];
                    }

                    $placed = true;
                    break;
                }
            }

            if (!$placed)
            {
                $clusters[$identity][] = array(
                    'time' => (int) $row['log_time'],
                    'rows' => array($row),
                    'reasons' => $this->merge_reasons('', $row['reason']),
                );
            }
        }
        $this->db->sql_freeresult($result);

        foreach ($clusters as $identity_clusters)
        {
            foreach ($identity_clusters as $cluster)
            {
                if (count($cluster['rows']) < 2)
                {
                    continue;
                }

                $survivor = $this->select_survivor_row($cluster['rows']);
                $delete_ids = array();

                foreach ($cluster['rows'] as $row)
                {
                    if ((int) $row['log_id'] !== (int) $survivor['log_id'])
                    {
                        $delete_ids[] = (int) $row['log_id'];
                    }
                }

                $merged_reason = $this->truncate((string) $cluster['reasons'], 255);
                if ($merged_reason !== (string) $survivor['reason'])
                {
                    $sql = 'UPDATE ' . $table . "
                        SET reason = '" . $this->db->sql_escape($merged_reason) . "'
                        WHERE log_id = " . (int) $survivor['log_id'];
                    $this->db->sql_query($sql);
                }

                if (!empty($delete_ids))
                {
                    $sql = 'DELETE FROM ' . $table . '
                        WHERE ' . $this->db->sql_in_set('log_id', $delete_ids);
                    $this->db->sql_query($sql);
                }
            }
        }
    }

    protected function select_survivor_row(array $rows)
    {
        $survivor = $rows[0];
        $best_count = $this->count_reasons($survivor['reason']);

        foreach ($rows as $row)
        {
            $count = $this->count_reasons($row['reason']);

            if ($count > $best_count || ($count === $best_count && (int) $row['log_id'] > (int) $survivor['log_id']))
            {
                $survivor = $row;
                $best_count = $count;
            }
        }

        return $survivor;
    }

    protected function count_reasons($reason)
    {
        $parts = array_filter(array_map('trim', explode(',', (string) $reason)));
        return count(array_unique($parts));
    }

    protected function merge_reasons($existing, $new)
    {
        $parts = array();

        foreach (array($existing, $new) as $reason)
        {
            foreach (explode(',', (string) $reason) as $part)
            {
                $part = trim($part);

                if ($part !== '' && !in_array($part, $parts, true))
                {
                    $parts[] = $part;
                }
            }
        }

        return implode(',', $parts);
    }

    protected function truncate($value, $length)
    {
        $value = (string) $value;

        if (function_exists('utf8_substr'))
        {
            return utf8_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }
}
