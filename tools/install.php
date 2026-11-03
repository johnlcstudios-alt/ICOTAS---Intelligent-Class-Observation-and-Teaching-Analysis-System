<?php
/**
 * ICOTAS installation helper.
 *
 * Run from the application root:
 *     php tools/install.php
 *
 * This script does not destroy existing data. It prepares the local
 * environment, validates the schema file, checks database reachability,
 * and generates the one-time setup token used by /setup when no Dean
 * exists yet. It is deliberately read-only and never modifies the schema.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$storage = $root . '/storage';
$logDir = $storage . '/logs';
$cacheDir = $storage . '/cache';
$reportDir = $storage . '/reports';
$uploadDir = $root . '/public/uploads';
$tokenFile = $storage . '/setup_token.txt';
$schemaFile = $root . '/database/schema.sql';

echo "ICOTAS installer helper\n";
echo str_repeat('-', 60) . "\n";

$steps = [];

// 1. Storage directories
foreach ([$storage, $logDir, $cacheDir, $reportDir, $uploadDir] as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0775, true)) {
            $steps[] = "created: $dir";
        } else {
            echo "FAILED to create: $dir\n";
            exit(1);
        }
    } else {
        $steps[] = "exists: $dir";
    }
}

// 2. Schema file presence
if (!is_file($schemaFile)) {
    echo "MISSING schema file: $schemaFile\n";
    exit(1);
}
$steps[] = "schema present: database/schema.sql (" . number_format(filesize($schemaFile)) . " bytes)";

// 3. Quick database connectivity probe using the current .env settings.
try {
    require $root . '/app/bootstrap.php';
    $db = App\Core\Database::connection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $steps[] = "database reachable via PDO ($driver)";
} catch (\Throwable $e) {
    echo "DATABASE NOT REACHABLE YET\n";
    echo "Reason: " . $e->getMessage() . "\n";
    echo "Continue setup after configuring .env and importing the schema.\n";
}

// 4. Setup token generation (only useful when no Dean exists)
$desiredToken = true;
if ($desiredToken) {
    $token = bin2hex(random_bytes(32));
    if (file_put_contents($tokenFile, $token) !== false) {
        chmod($tokenFile, 0640);
        $steps[] = "setup token generated: storage/setup_token.txt";
        echo "One-time setup token: $token\n";
        echo "Use this token only once at /setup when no Dean account exists.\n";
    } else {
        echo "FAILED to write setup token file.\n";
    }
}

// 5. Recommended next actions
echo "\nRecommended next steps:\n";
echo "  1. Create the dedicated database (MySQL/MariaDB):\n";
echo "     CREATE DATABASE icotas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "  2. Import the schema:\n";
echo "     mysql -u root icotas_db < database/schema.sql\n";
echo "  3. If no Dean exists yet, visit /setup and supply the setup token above.\n";
echo "  4. Confirm .env values for your environment, then restart PHP/web server if needed.\n";

echo "\nCompleted checks:\n";
foreach ($steps as $step) {
    echo "  - $step\n";
}
