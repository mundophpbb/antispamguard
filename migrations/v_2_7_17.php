<?php
/**
 * AntiSpam Guard 2.7.17 - Timestamp maximum form age.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_7_17 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['antispamguard_version']) && version_compare($this->config['antispamguard_version'], '2.7.17', '>=');
    }

    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_2_7_16');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_max_form_age', 3600)),
            array('config.update', array('antispamguard_version', '2.7.17')),
        );
    }
}
