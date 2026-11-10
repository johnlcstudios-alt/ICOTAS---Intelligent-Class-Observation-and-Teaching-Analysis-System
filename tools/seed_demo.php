<?php
/**
 * CLI utility: generate or reset the synthetic demonstration dataset.
 *
 * Usage:
 *   php tools/seed_demo.php generate
 *   php tools/seed_demo.php reset
 *   php tools/seed_demo.php status
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This utility may only be run from the command line.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$command = strtolower($argv[1] ?? 'status');

try {
    if ($command === 'generate') {
        $stats = (new App\Services\DemoService())->seed();
        echo "Demo dataset generated.\n";
        foreach ($stats as $key => $value) {
            echo '  ' . str_pad(str_replace('_', ' ', $key), 22) . $value . "\n";
        }
        echo "Demo password for every seeded account: Demo@12345\n";
    } elseif ($command === 'reset') {
        (new App\Services\DemoService())->reset();
        App\Core\Setting::set('demo_mode', '0', 'demo', null);
        echo "Demo dataset removed. Live data was not touched.\n";
    } else {
        $scope = App\Core\Setting::demoMode() ? 1 : 0;
        echo 'Demo mode: ' . ($scope ? 'ON' : 'OFF') . "\n";
        foreach (['users', 'departments', 'programs', 'courses', 'sections', 'surveys', 'evaluation_sessions', 'evaluation_responses', 'uat_surveys'] as $table) {
            if ($table === 'evaluation_responses') {
                $count = App\Core\Database::scalar(
                    'SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_sessions es ON es.id = r.evaluation_session_id WHERE es.is_demo = ?',
                    [$scope],
                    0
                );
            } else {
                $count = App\Core\Database::scalar('SELECT COUNT(*) FROM ' . $table . ' WHERE is_demo = ?', [$scope], 0);
            }
            echo '  ' . str_pad(str_replace('_', ' ', $table), 22) . (int) $count . "\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
