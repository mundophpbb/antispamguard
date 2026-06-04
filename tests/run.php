<?php
/**
 * Run: php tests/run.php
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/FormGuardTest.php';
require __DIR__ . '/IpMatcherTest.php';
require __DIR__ . '/DecisionEngineTest.php';
require __DIR__ . '/SettingsHelperTest.php';
require __DIR__ . '/RegistrationPolicyTest.php';

$tests = array(
    new FormGuardTest(),
    new IpMatcherTest(),
    new DecisionEngineTest(),
    new SettingsHelperTest(),
    new RegistrationPolicyTest(),
);

$total_passed = 0;
$total_failed = 0;

foreach ($tests as $test)
{
    $class = get_class($test);
    echo $class . '...' . PHP_EOL;
    $test->run();
    $total_passed += $test->passed;
    $total_failed += $test->failed;
}

echo PHP_EOL . 'Passed: ' . $total_passed . ', Failed: ' . $total_failed . PHP_EOL;

exit($total_failed > 0 ? 1 : 0);