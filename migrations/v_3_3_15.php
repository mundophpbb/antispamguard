<?php
/**
 * AntiSpam Guard 3.3.15 - Registration notice visual fine tuning.
 */

namespace mundophpbb\antispamguard\migrations;

class v_3_3_15 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '3.3.15', '>=');
    }

    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_3_3_14');
    }

    public function update_data()
    {
        return array(
            array('config.update', array('antispamguard_version', '3.3.15')),
        );
    }
}
