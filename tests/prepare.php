<?php

/**
 * Rebuilds the isolated automated-test database from database/schema.sql and
 * seeds the fictional demo dataset. Safe to run repeatedly: it only ever drops
 * the database named by ICOTAS_TEST_DB (default icotas_test).
 *
 * Usage: php tests/prepare.php
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\DemoService;

$db = config('database');
$target = (string) $db['database'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $target)) {
    fwrite(STDERR, "Unsafe test database name: $target\n");
    exit(1);
}
if ($target === 'icotas') {
    fwrite(STDERR, "Refusing to drop the configured application database 'icotas'.\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], (int) $db['port'], $db['charset'] ?? 'utf8mb4');
$pdo = new PDO($dsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "Rebuilding test database '$target'...\n";
$pdo->exec("DROP DATABASE IF EXISTS `$target`");
$pdo->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdo->exec("USE `$target`");
$schema = file_get_contents(BASE_PATH . '/database/schema.sql');
if ($schema === false || trim($schema) === '') {
    fwrite(STDERR, "database/schema.sql is missing or empty.\n");
    exit(1);
}
$pdo->exec($schema);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Schema imported. Seeding demo data...\n";
$stats = (new DemoService())->seed();
foreach ($stats as $key => $value) {
    printf("  %-18s %s\n", $key, $value);
}
echo "Test database ready.\n";
