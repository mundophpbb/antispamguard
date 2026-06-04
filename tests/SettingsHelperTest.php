<?php

use mundophpbb\antispamguard\acp\settings_helper;

class SettingsHelperTest extends TestCase
{
    public function run()
    {
        $helper = new settings_helper();

        $this->assertSame("a\nb", $helper->normalize_blocked_keywords("b, a , a"), 'keywords sorted');
        $this->assertSame('2,5,9', $helper->normalize_group_ids('2 5 9 2'), 'group ids');
        $this->assertSame('*******2345', $helper->mask_secret('secret12345'), 'mask secret');

        $keys = $helper->get_settings_keys();
        $this->assertTrue(count($keys) >= 20, 'has settings keys');
        $this->assertTrue(strpos($helper->get_extension_version(), '3.3.') === 0, 'extension version from composer');
    }
}