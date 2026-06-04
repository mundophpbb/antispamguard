<?php

abstract class TestCase
{
    public $passed = 0;
    public $failed = 0;

    abstract public function run();

    protected function assertTrue($condition, $message)
    {
        if ($condition)
        {
            $this->passed++;
            return;
        }

        $this->failed++;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }

    protected function assertSame($expected, $actual, $message)
    {
        $this->assertTrue($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}