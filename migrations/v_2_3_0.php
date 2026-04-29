<?php
/**
 * AntiSpam Guard 2.3.0 migration.
 */

namespace mundophpbb\antispamguard\migrations;

class v_2_3_0 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return array('\\mundophpbb\\antispamguard\\migrations\\v_2_2_0');
    }

    public function update_data()
    {
        return array();
    }
}
