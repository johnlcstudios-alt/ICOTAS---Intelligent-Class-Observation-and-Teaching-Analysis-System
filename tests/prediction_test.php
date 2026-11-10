<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\PredictionService;

test('least-squares slope is exact for a straight line', function (): void {
    $service = new PredictionService();
    assertNear(1.0, $service->slope([1.0, 2.0, 3.0, 4.0]), 0.0001);
    assertNear(-0.5, $service->slope([4.0, 3.5, 3.0, 2.5]), 0.0001);
    assertNear(0.0, $service->slope([3.0, 3.0, 3.0]), 0.0001);
    assertSame(0.0, $service->slope([4.0]));
    assertSame(0.0, $service->slope([]));
});

test('weighted moving average favors the most recent periods', function (): void {
    $service = new PredictionService();
    assertNear(14 / 6, $service->weightedMovingAverage([1.0, 2.0, 3.0], 3), 0.0001);
    assertNear(3.0, $service->weightedMovingAverage([3.0], 4), 0.0001);
    assertSame(null, $service->weightedMovingAverage([]));
});

test('insufficient history produces an explicit insufficiency result, not a fake forecast', function (): void {
    $instructor = Database::fetch(
        'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.slug = "instructor" AND u.is_demo = 1 ORDER BY u.id LIMIT 1'
    );
    assertTrue($instructor !== null);

    // Querying the live scope for a demo instructor yields no comparable periods.
    $result = (new PredictionService())->generate((int) $instructor['id'], false);

    assertSame('insufficient_data', $result['risk_level']);
    assertSame('insufficient', $result['data_status']);
    assertSame(null, $result['forecast_average']);
    assertSame(null, $result['slope']);
    assertContains('Insufficient comparable historical data', $result['explanation']);
    assertTrue(str_contains($result['explanation'], 'No risk indicator is inferred'));
});

test('prediction configuration is sourced from transparent thresholds', function (): void {
    $config = (new PredictionService())->config();
    assertSame(5, $config['min_responses']);
    assertSame(2, $config['min_periods']);
    assertSame(8, $config['window_periods']);
    assertNear(0.08, $config['improving_slope']);
    assertNear(-0.08, $config['declining_slope']);
    assertNear(0.30, $config['negative_ratio']);
});

test('generated demo predictions include their method and supporting metrics', function (): void {
    $row = Database::fetch(
        'SELECT * FROM prediction_results pr
         JOIN users u ON u.id = pr.instructor_id
         WHERE u.is_demo = 1 ORDER BY pr.id DESC LIMIT 1'
    );
    assertTrue($row !== null, 'demo seeding should generate predictions');
    assertContains('Weighted moving average', $row['method']);
    assertTrue(in_array($row['risk_level'], ['improving', 'stable', 'needs_attention', 'insufficient_data'], true));
    $metrics = json_decode((string) $row['supporting_metrics'], true);
    assertTrue(is_array($metrics), 'supporting metrics must be valid JSON');
    assertTrue(array_key_exists('periods_available', $metrics));
    $thresholds = json_decode((string) $row['threshold_config'], true);
    assertTrue(is_array($thresholds) && array_key_exists('min_periods', $thresholds));
});
