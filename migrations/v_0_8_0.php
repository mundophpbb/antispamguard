<?php
/**
 * AntiSpam Guard 0.8.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_0_8_0 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return array('\mundophpbb\antispamguard\migrations\v_0_7_0');
    }

    public function update_data()
    {
        return array(
            array('config.add', array('antispamguard_bypass_group_ids', '')),
        );
    }
}
