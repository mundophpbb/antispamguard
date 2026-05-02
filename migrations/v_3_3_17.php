<?php
/**
 * AntiSpam Guard 3.3.17 - Reorder ACP modules.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_17 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.17', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_16');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'reorder_acp_modules'))),
            array('config.update', array('antispamguard_version', '3.3.17')),
        );
    }

    /**
     * Reorders only the AntiSpam Guard ACP children by reassigning their existing
     * nested-set slots. This preserves the global phpBB module tree and avoids
     * deleting/recreating modules already installed by previous migrations.
     */
    public function reorder_acp_modules()
    {
        $module_table = $this->table_prefix . 'modules';
        $desired_order = array('settings', 'sfs', 'logs', 'stats', 'about');
        $basename = '\\mundophpbb\\antispamguard\\acp\\main_module';

        $sql = 'SELECT module_id
            FROM ' . $module_table . "
            WHERE module_class = 'acp'
                AND module_langname = 'ACP_ANTISPAMGUARD_TITLE'";
        $result = $this->db->sql_query($sql);
        $parent_id = (int) $this->db->sql_fetchfield('module_id');
        $this->db->sql_freeresult($result);

        if (!$parent_id)
        {
            return;
        }

        $sql = 'SELECT module_id, module_mode, left_id, right_id
            FROM ' . $module_table . '
            WHERE module_class = \'acp\'
                AND parent_id = ' . (int) $parent_id . "
                AND module_basename = '" . $this->db->sql_escape($basename) . "'
            ORDER BY left_id ASC";
        $result = $this->db->sql_query($sql);

        $modules_by_mode = array();
        $slots = array();

        while ($row = $this->db->sql_fetchrow($result))
        {
            $modules_by_mode[$row['module_mode']] = array(
                'module_id' => (int) $row['module_id'],
                'left_id'   => (int) $row['left_id'],
                'right_id'  => (int) $row['right_id'],
            );
            $slots[] = array(
                'left_id'  => (int) $row['left_id'],
                'right_id' => (int) $row['right_id'],
            );
        }
        $this->db->sql_freeresult($result);

        if (count($slots) < 2)
        {
            return;
        }

        usort($slots, function ($a, $b) {
            return $a['left_id'] - $b['left_id'];
        });

        $ordered_module_ids = array();
        foreach ($desired_order as $mode)
        {
            if (isset($modules_by_mode[$mode]))
            {
                $ordered_module_ids[] = $modules_by_mode[$mode]['module_id'];
            }
        }

        // Keep any unexpected future modes after the known modes, preserving their current order.
        foreach ($modules_by_mode as $mode => $module)
        {
            if (!in_array($mode, $desired_order, true))
            {
                $ordered_module_ids[] = $module['module_id'];
            }
        }

        foreach ($ordered_module_ids as $index => $module_id)
        {
            if (!isset($slots[$index]))
            {
                break;
            }

            $sql = 'UPDATE ' . $module_table . '
                SET left_id = ' . (int) $slots[$index]['left_id'] . ',
                    right_id = ' . (int) $slots[$index]['right_id'] . '
                WHERE module_id = ' . (int) $module_id;
            $this->db->sql_query($sql);
        }
    }
}
