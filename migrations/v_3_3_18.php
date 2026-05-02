<?php
/**
 * AntiSpam Guard 3.3.18 - Repair/create global ACP Extensions tab.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_18 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.18', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_17');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_acp_extensions_tab'))),
            array('config.update', array('antispamguard_version', '3.3.18')),
        );
    }

    /**
     * Some development/uninstall cycles can leave phpBB without the global
     * ACP_CAT_DOT_MODS category. If that category is missing, extension ACP
     * modules may be installed but no "Extensions" tab is rendered.
     */
    public function repair_acp_extensions_tab()
    {
        $module_table = $this->table_prefix . 'modules';

        $dotmods_id = $this->get_acp_module_id('ACP_CAT_DOT_MODS');
        if (!$dotmods_id)
        {
            $dotmods_id = $this->insert_acp_root_category('ACP_CAT_DOT_MODS');
        }

        if (!$dotmods_id)
        {
            return;
        }

        $antispamguard_id = $this->get_acp_module_id('ACP_ANTISPAMGUARD_TITLE');
        if ($antispamguard_id)
        {
            $sql = 'UPDATE ' . $module_table . '
                SET parent_id = ' . (int) $dotmods_id . ',
                    module_enabled = 1,
                    module_display = 1
                WHERE module_id = ' . (int) $antispamguard_id;
            $this->db->sql_query($sql);
        }

        $sql = 'UPDATE ' . $module_table . "
            SET module_enabled = 1,
                module_display = 1
            WHERE module_class = 'acp'
                AND module_langname = 'ACP_CAT_DOT_MODS'";
        $this->db->sql_query($sql);

        $this->rebuild_acp_nested_set();
    }

    protected function get_acp_module_id($langname)
    {
        $module_table = $this->table_prefix . 'modules';

        $sql = 'SELECT module_id
            FROM ' . $module_table . "
            WHERE module_class = 'acp'
                AND module_langname = '" . $this->db->sql_escape($langname) . "'
            ORDER BY module_id ASC";
        $result = $this->db->sql_query_limit($sql, 1);
        $module_id = (int) $this->db->sql_fetchfield('module_id');
        $this->db->sql_freeresult($result);

        return $module_id;
    }

    protected function insert_acp_root_category($langname)
    {
        $module_table = $this->table_prefix . 'modules';

        $sql = 'SELECT MAX(right_id) AS max_right
            FROM ' . $module_table . "
            WHERE module_class = 'acp'";
        $result = $this->db->sql_query($sql);
        $max_right = (int) $this->db->sql_fetchfield('max_right');
        $this->db->sql_freeresult($result);

        $data = array(
            'module_enabled'  => 1,
            'module_display'  => 1,
            'module_basename' => '',
            'module_class'    => 'acp',
            'parent_id'       => 0,
            'left_id'         => $max_right + 1,
            'right_id'        => $max_right + 2,
            'module_langname' => $langname,
            'module_mode'     => '',
            'module_auth'     => '',
        );

        $sql = 'INSERT INTO ' . $module_table . ' ' . $this->db->sql_build_array('INSERT', $data);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_nextid();
    }

    /**
     * Rebuilds left_id/right_id for ACP modules from parent_id relationships.
     * Existing order is preserved by old left_id, then module_id. This keeps the
     * global ACP tree consistent after recreating or moving ACP_CAT_DOT_MODS.
     */
    protected function rebuild_acp_nested_set()
    {
        $module_table = $this->table_prefix . 'modules';

        $sql = 'SELECT module_id, parent_id, left_id
            FROM ' . $module_table . "
            WHERE module_class = 'acp'
            ORDER BY parent_id ASC, left_id ASC, module_id ASC";
        $result = $this->db->sql_query($sql);

        $children = array();
        while ($row = $this->db->sql_fetchrow($result))
        {
            $parent_id = (int) $row['parent_id'];
            if (!isset($children[$parent_id]))
            {
                $children[$parent_id] = array();
            }

            $children[$parent_id][] = array(
                'module_id' => (int) $row['module_id'],
                'left_id'   => (int) $row['left_id'],
            );
        }
        $this->db->sql_freeresult($result);

        foreach ($children as $parent_id => $items)
        {
            usort($children[$parent_id], function ($a, $b) {
                if ($a['left_id'] === $b['left_id'])
                {
                    return $a['module_id'] - $b['module_id'];
                }

                return $a['left_id'] - $b['left_id'];
            });
        }

        $counter = 1;
        if (isset($children[0]))
        {
            foreach ($children[0] as $root)
            {
                $this->assign_nested_set_values($root['module_id'], $children, $counter, $module_table);
            }
        }
    }

    protected function assign_nested_set_values($module_id, array $children, &$counter, $module_table)
    {
        $left_id = $counter++;

        if (isset($children[$module_id]))
        {
            foreach ($children[$module_id] as $child)
            {
                $this->assign_nested_set_values($child['module_id'], $children, $counter, $module_table);
            }
        }

        $right_id = $counter++;

        $sql = 'UPDATE ' . $module_table . '
            SET left_id = ' . (int) $left_id . ',
                right_id = ' . (int) $right_id . '
            WHERE module_id = ' . (int) $module_id;
        $this->db->sql_query($sql);
    }
}
