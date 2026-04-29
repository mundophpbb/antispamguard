<?php
/**
 * AntiSpam Guard ACP info.
 */

namespace mundophpbb\antispamguard\acp;

class main_info
{
    public function module()
    {
        return array(
            'filename' => '\\mundophpbb\\antispamguard\\acp\\main_module',
            'title'    => 'ACP_ANTISPAMGUARD_TITLE',
            'modes'    => array(
                'settings' => array(
                    'title' => 'ACP_ANTISPAMGUARD_SETTINGS',
                    'auth'  => 'ext_mundophpbb/antispamguard && acl_a_antispamguard_manage',
                    'cat'   => array('ACP_ANTISPAMGUARD_TITLE'),
                ),
                'stats' => array(
                    'title' => 'ACP_ANTISPAMGUARD_STATS',
                    'auth'  => 'ext_mundophpbb/antispamguard && acl_a_antispamguard_manage',
                    'cat'   => array('ACP_ANTISPAMGUARD_TITLE'),
                ),
                'logs' => array(
                    'title' => 'ACP_ANTISPAMGUARD_LOGS',
                    'auth'  => 'ext_mundophpbb/antispamguard && acl_a_antispamguard_manage',
                    'cat'   => array('ACP_ANTISPAMGUARD_TITLE'),
                ),
                'about' => array(
                    'title' => 'ACP_ANTISPAMGUARD_ABOUT',
                    'auth'  => 'ext_mundophpbb/antispamguard && acl_a_antispamguard_manage',
                    'cat'   => array('ACP_ANTISPAMGUARD_TITLE'),
                ),
            ),
        );
    }
}
