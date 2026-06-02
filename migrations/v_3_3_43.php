<?php
/**
 * AntiSpam Guard 3.3.43 — repair missing admin permission and module auth.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_43 extends \mundophpbb\antispamguard\migrations\v_0_1_0
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version'])
            && version_compare($this->config['antispamguard_version'], '3.3.43', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_42');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'repair_admin_permission'))),
            array('custom', array(array($this, 'repair_module_auth'))),
            array('config.update', array('antispamguard_version', '3.3.43')),
        );
    }

    public function repair_admin_permission()
    {
        if (!$this->permission_tool->exists('a_antispamguard_manage', true))
        {
            $this->permission_tool->add('a_antispamguard_manage', true);
        }

        $this->permission_tool->permission_set('ROLE_ADMIN_FULL', 'a_antispamguard_manage');
        $this->permission_tool->permission_set('ROLE_ADMIN_STANDARD', 'a_antispamguard_manage');
    }

    public function repair_module_auth()
    {
        $auth = 'ext_mundophpbb/antispamguard && (acl_a_board || acl_a_antispamguard_manage)';

        $sql = 'UPDATE ' . $this->table_prefix . "modules
            SET module_auth = '" . $this->db->sql_escape($auth) . "'
            WHERE module_langname LIKE 'ACP_ANTISPAMGUARD_%'
                OR module_langname = 'ACP_ANTISPAMGUARD_TITLE'";
        $this->db->sql_query($sql);
    }

    public function revert_schema()
    {
        return array();
    }
}