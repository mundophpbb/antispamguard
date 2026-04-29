<?php
/**
 * AntiSpam Guard 2.3.2 migration.
 *
 * Updates the stored extension version after the diagnostics hardening patch.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_3_2 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.3.2', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_3_1');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '2.3.2')),
        );
    }
}
