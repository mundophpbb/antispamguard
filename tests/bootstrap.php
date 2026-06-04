<?php
/**
 * AntiSpam Guard test bootstrap (no phpBB required).
 */

$ext_root = dirname(__DIR__);

spl_autoload_register(function ($class) use ($ext_root) {
    $prefix = 'mundophpbb\\antispamguard\\';
    if (strpos($class, $prefix) !== 0)
    {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $ext_root . '/' . $relative . '.php';

    if (is_file($path))
    {
        require $path;
    }
});

require __DIR__ . '/TestCase.php';
require __DIR__ . '/ConfigStub.php';