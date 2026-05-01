<?php
/**
 * AntiSpam Guard 2.9.4 - SFS cache TTL setting.
 *
 * Note: the SFS cache table is created in v_2_7_2. This migration must not
 * create the same table again, otherwise fresh installs can fail with a
 * duplicate-table error and upgraded installs may keep an incompatible schema.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_9_4 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.9.4', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_9_3');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_sfs_cache_ttl', 86400)),
            array('config.update', array('antispamguard_version', '2.9.4')),
        );
    }
}
