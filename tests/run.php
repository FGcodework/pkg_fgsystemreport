<?php
/**
 * Dependency-free test runner:  php tests/run.php
 *
 * Every tests/*Test.php returns an array  name => callable.  A test fails by
 * throwing (use the assert*() helpers).  Exit code 1 when anything failed, so
 * it works in CI as is.
 */

require __DIR__ . '/bootstrap.php';

function assertSame($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg !== '' ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrue($value, string $msg = ''): void
{
    assertSame(true, (bool) $value, $msg);
}

function assertCount(int $n, $arr, string $msg = ''): void
{
    assertSame($n, count($arr), $msg);
}

/** Temporary directory removed at the end of the run. */
function tmpDir(): string
{
    static $made = [];
    static $registered = false;

    $d = sys_get_temp_dir() . '/fgsr_test_' . getmypid() . '_' . count($made);
    mkdir($d, 0777, true);
    $made[] = $d;

    if (!$registered) {
        $registered = true;
        register_shutdown_function(static function () use (&$made) {
            foreach ($made as $dir) {
                exec('rm -rf ' . escapeshellarg($dir));
            }
        });
    }

    return $d;
}

$pass = 0;
$fail = 0;

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    $tests = require $file;

    foreach ($tests as $name => $fn) {
        try {
            $fn();
            $pass++;
            echo '  ok   ' . basename($file, '.php') . ' :: ' . $name . "\n";
        } catch (Throwable $e) {
            $fail++;
            echo '  FAIL ' . basename($file, '.php') . ' :: ' . $name . "\n       " . $e->getMessage() . "\n";
        }
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
