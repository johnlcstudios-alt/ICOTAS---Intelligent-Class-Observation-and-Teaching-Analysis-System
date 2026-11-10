<?php
/**
 * CLI utility: seed the institutional survey template library and the default
 * ISO/IEC 25010 UAT form. This creates definitions only, never responses or
 * demonstration records.
 *
 * Usage:
 *   php tools/seed_defaults.php
 *   php tools/seed_defaults.php status
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This utility may only be run from the command line.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$command = strtolower($argv[1] ?? 'seed');

try {
    if ($command === 'status') {
        $templates = (int) App\Core\Database::scalar(
            'SELECT COUNT(*) FROM surveys WHERE is_template = 1 AND is_demo = 0',
            [],
            0
        );
        $uat = (int) App\Core\Database::scalar(
            'SELECT COUNT(*) FROM uat_surveys WHERE is_demo = 0 AND title = ?',
            ['ISO/IEC 25010 User Acceptance Evaluation'],
            0
        );
        echo "Survey templates: $templates\n";
        echo "ISO/IEC 25010 UAT form: " . ($uat > 0 ? 'present' : 'missing') . "\n";
    } else {
        $created = (new App\Services\DefaultDataService())->seed();
        if (empty($created)) {
            echo "Default definitions already present. Nothing was duplicated.\n";
        } else {
            echo "Default definitions created:\n";
            foreach ($created as $item) {
                echo '  - ' . $item . "\n";
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
