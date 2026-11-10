<?php

/**
 * Test bootstrap. Forces an isolated database before the app boots so no test
 * can read or write the configured institutional database.
 */

declare(strict_types=1);

$testDatabase = getenv('ICOTAS_TEST_DB') ?: 'icotas_test';
putenv('ICOTAS_DB_DATABASE=' . $testDatabase);

require dirname(__DIR__) . '/app/bootstrap.php';

if (config('database.database') !== $testDatabase) {
    fwrite(STDERR, "Refusing to run: database scope is '" . config('database.database') . "', expected '$testDatabase'.\n");
    exit(1);
}
