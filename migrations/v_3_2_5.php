<?php
/**
 * AntiSpam Guard 3.2.5 - Slow spam CSV export.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_2_5 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.2.5', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_2_4');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '3.2.5')),
        );
    }
}
