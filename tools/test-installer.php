<?php

/**
 * Development-only installer validation.
 *
 * Runs install.php in-process against a scratch database, verifies the created
 * schema, administrator, settings and lock file, then restores config/local.php.
 *
 * Usage: php tools/test-installer.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . '/config/local.php';
$lockPath = $root . '/storage/installed.lock';
$backupPath = $root . '/config/local.php.installer-test-backup';
$database = 'icotas_installer_test';

if (is_file($lockPath)) {
    fwrite(STDERR, "Refusing to run: storage/installed.lock exists. Remove it only on a disposable copy.\n");
    exit(2);
}
if (!is_file($configPath)) {
    fwrite(STDERR, "config/local.php is missing; this test expects an existing local configuration to restore.\n");
    exit(2);
}
if (!copy($configPath, $backupPath)) {
    fwrite(STDERR, "Could not back up config/local.php.\n");
    exit(2);
}

$failures = 0;
function check(bool $condition, string $label, string $detail = ''): void
{
    global $failures;
    if ($condition) {
        echo "  PASS  $label\n";
        return;
    }
    $failures++;
    echo "  FAIL  $label" . ($detail !== '' ? "  [$detail]" : '') . "\n";
}

try {
    // Fresh scratch database.
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("DROP DATABASE IF EXISTS `$database`");
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('icotas_installer');
        session_start();
    }
    $_SESSION['installer_csrf'] = 'installer-test-token';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_POST = [
        'csrf_token' => 'installer-test-token',
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_name' => $database,
        'db_user' => 'root',
        'db_pass' => '',
        'site_url' => '',
        'first_name' => 'Installer',
        'last_name' => 'Test',
        'username' => 'install.dean',
        'email' => 'install.dean@example.test',
        'institution_name' => 'ACLC College Tacloban City',
        'system_name' => 'ICOTAS',
        'password' => 'Installer@12345',
        'password_confirm' => 'Installer@12345',
    ];

    ob_start();
    require $root . '/install.php';
    $html = (string) ob_get_clean();

    check(empty($errors), 'installer reports no errors', implode(' | ', array_map('strval', $errors ?? [])));
    check(($installed ?? false) === true, 'installer reports success');
    check(str_contains($html, 'Installation complete') || str_contains($html, 'installed successfully'), 'success screen rendered');
    check(is_file($lockPath), 'storage/installed.lock written');

    $check = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$database;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $tables = (int) $check->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
    check($tables >= 40, 'schema tables created', (string) $tables);

    $dean = $check->query('SELECT u.*, r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "dean" LIMIT 1')->fetch();
    check($dean !== false, 'first Dean account created');
    check(($dean['username'] ?? '') === 'install.dean', 'Dean username stored');
    check((int) ($dean['is_demo'] ?? 1) === 0, 'installed Dean is a live account');
    check(password_verify('Installer@12345', (string) ($dean['password_hash'] ?? '')), 'Dean password verifies');

    $institution = (string) $check->query('SELECT setting_value FROM system_settings WHERE setting_key = "institution_name"')->fetchColumn();
    check($institution === 'ACLC College Tacloban City', 'institution setting written', $institution);
    $demoMode = (string) $check->query('SELECT setting_value FROM system_settings WHERE setting_key = "demo_mode"')->fetchColumn();
    check($demoMode === '0', 'demo mode defaults off', $demoMode);

    $surveys = (int) $check->query('SELECT COUNT(*) FROM surveys')->fetchColumn();
    check($surveys > 0, 'default survey definitions seeded', (string) $surveys);
    $uat = (int) $check->query('SELECT COUNT(*) FROM uat_surveys')->fetchColumn();
    check($uat > 0, 'default UAT survey seeded', (string) $uat);

    $local = require $configPath;
    check(($local['database']['database'] ?? '') === $database, 'config/local.php points at the installed database');
    check(strlen((string) ($local['app']['key'] ?? '')) === 64, 'application key generated');
    check(($local['app']['environment'] ?? '') === 'production', 'production environment set');
} catch (Throwable $e) {
    $failures++;
    echo '  FAIL  installer validation threw: ' . $e->getMessage() . "\n";
} finally {
    // Restore the developer configuration and remove the installer lock.
    if (is_file($backupPath)) {
        @rename($backupPath, $configPath);
    }
    @unlink($lockPath);
    @unlink($root . '/config/local.php.tmp');
    try {
        $cleanup = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $cleanup->exec("DROP DATABASE IF EXISTS `$database`");
    } catch (Throwable $e) {
        echo '  WARN  scratch database cleanup failed: ' . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('-', 58) . "\n";
echo $failures === 0 ? "Installer validation passed.\n" : "Installer validation failed: $failures check(s).\n";
exit($failures === 0 ? 0 : 1);
