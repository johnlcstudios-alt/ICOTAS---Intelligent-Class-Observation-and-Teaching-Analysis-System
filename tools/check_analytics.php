<?php
/**
 * CLI utility: exercise every Analytics aggregate and recommendation generation.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This utility may only be run from the command line.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Analytics;
use App\Models\User;
use App\Services\RecommendationService;

$analytics = new Analytics();
$demo = App\Core\Setting::demoMode();

$checks = [
    'counts' => fn () => $analytics->filteredCounts([], $demo),
    'rating' => fn () => $analytics->filteredRating([], $demo),
    'categories' => fn () => $analytics->categoryAverages([], $demo),
    'sentiment' => fn () => $analytics->sentimentDistribution([], $demo),
    'themes' => fn () => $analytics->themeCounts([], $demo),
    'distribution' => fn () => $analytics->ratingDistribution([], $demo),
    'session_trend' => fn () => $analytics->sessionTrend([], $demo),
    'semester_trend' => fn () => $analytics->semesterTrend([], $demo),
    'instructors' => fn () => $analytics->instructorComparison([], $demo),
    'departments' => fn () => $analytics->departmentComparison([], $demo),
    'comments' => fn () => $analytics->comments([], $demo, 3),
    'texts' => fn () => $analytics->responseTexts([], $demo, 3),
];

$failed = 0;
foreach ($checks as $name => $fn) {
    try {
        $result = $fn();
        $count = is_array($result) ? count($result) : 1;
        echo str_pad($name, 16) . "OK ($count rows)\n";
    } catch (Throwable $e) {
        $failed++;
        echo str_pad($name, 16) . 'FAIL: ' . $e->getMessage() . "\n";
    }
}

try {
    $instructor = (new User())->findByLogin('demo.instructor.01');
    if ($instructor) {
        $prediction = (new App\Services\PredictionService())->generate((int) $instructor['id'], $demo);
        echo str_pad('prediction', 16) . 'OK (' . ($prediction['status'] ?? 'unknown') . ")\n";
    }
} catch (Throwable $e) {
    $failed++;
    echo str_pad('prediction', 16) . 'FAIL: ' . $e->getMessage() . "\n";
}

try {
    $created = (new RecommendationService())->generateAll(true);
    echo str_pad('recommendations', 16) . 'OK (' . $created . " created)\n";
} catch (Throwable $e) {
    $failed++;
    echo str_pad('recommendations', 16) . 'FAIL: ' . $e->getMessage() . "\n";
}

exit($failed > 0 ? 1 : 0);
