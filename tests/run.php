<?php

/**
 * Minimal dependency-free test runner.
 *
 * Usage:
 *   php tests/run.php              # rebuild test DB, then run every *_test.php
 *   php tests/run.php --keep-db    # reuse the current test DB
 *   php tests/run.php ai qr        # only files whose name matches a filter
 */

declare(strict_types=1);

// Buffer output so session_start()/session_regenerate_id() in the CLI test
// harness never run after headers have been sent.
ob_start();
register_shutdown_function(static function (): void {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});

$argv = $_SERVER['argv'];
$keepDb = in_array('--keep-db', $argv, true);
$filters = array_values(array_filter(array_slice($argv, 1), static fn ($a) => !str_starts_with($a, '--')));

if (!$keepDb) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/prepare.php'), $code);
    if ($code !== 0) {
        fwrite(STDERR, "Test database preparation failed.\n");
        exit($code);
    }
    echo "\n";
}

require __DIR__ . '/bootstrap.php';

$GLOBALS['__tests'] = [];
$GLOBALS['__failures'] = [];

function test(string $name, callable $body): void
{
    $GLOBALS['__tests'][] = [$name, $body];
}

function fail(string $message): void
{
    throw new RuntimeException($message);
}

function assertTrue($condition, string $message = 'Expected condition to be true'): void
{
    if (!$condition) {
        fail($message);
    }
}

function assertFalse($condition, string $message = 'Expected condition to be false'): void
{
    if ($condition) {
        fail($message);
    }
}

function assertSame($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = $message !== '' ? $message . ': ' : '';
        fail($detail . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertNotSame($unexpected, $actual, string $message = ''): void
{
    if ($unexpected === $actual) {
        $detail = $message !== '' ? $message . ': ' : '';
        fail($detail . 'did not expect ' . var_export($unexpected, true));
    }
}

function assertNear(float $expected, ?float $actual, float $delta = 0.001, string $message = ''): void
{
    if ($actual === null || abs($expected - $actual) > $delta) {
        $detail = $message !== '' ? $message . ': ' : '';
        fail($detail . 'expected ~' . $expected . ' (±' . $delta . '), got ' . var_export($actual, true));
    }
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        $detail = $message !== '' ? $message . ': ' : '';
        fail($detail . "expected to find '$needle'");
    }
}

function assertGreaterThan($minimum, $actual, string $message = ''): void
{
    if (!($actual > $minimum)) {
        $detail = $message !== '' ? $message . ': ' : '';
        fail($detail . 'expected > ' . var_export($minimum, true) . ', got ' . var_export($actual, true));
    }
}

$files = glob(__DIR__ . '/*_test.php') ?: [];
sort($files);
foreach ($files as $file) {
    $base = basename($file, '_test.php');
    if ($filters !== []) {
        $matched = false;
        foreach ($filters as $filter) {
            if (str_contains($base, $filter)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            continue;
        }
    }
    require $file;
}

$passed = 0;
$failed = 0;
$started = microtime(true);

foreach ($GLOBALS['__tests'] as [$name, $body]) {
    try {
        $body();
        $passed++;
        echo "  PASS  $name\n";
    } catch (Throwable $e) {
        $failed++;
        $GLOBALS['__failures'][] = [$name, $e];
        echo "  FAIL  $name\n";
        echo '        ' . str_replace("\n", "\n        ", $e->getMessage()) . "\n";
        if ($e->getFile() !== '' && $e->getFile() !== __FILE__) {
            echo '        at ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }
}

$elapsed = number_format(microtime(true) - $started, 2);
echo "\n" . str_repeat('-', 58) . "\n";
echo "Tests: " . ($passed + $failed) . "  Passed: $passed  Failed: $failed  Time: {$elapsed}s\n";

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($GLOBALS['__failures'] as [$name, $e]) {
        echo "  - $name: " . explode("\n", $e->getMessage())[0] . "\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
