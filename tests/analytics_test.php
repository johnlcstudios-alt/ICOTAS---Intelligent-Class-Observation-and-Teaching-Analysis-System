<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\AnalyticsService;

test('dean analytics totals reconcile with source records', function (): void {
    $dashboard = (new AnalyticsService())->deanDashboard(true);

    $responses = (int) Database::scalar(
        'SELECT COUNT(*) FROM evaluation_responses r
         JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
         WHERE es.is_demo = 1',
        [],
        0
    );
    assertSame($responses, (int) $dashboard['counts']['responses'], 'dashboard response count');
    assertSame($responses, (int) $dashboard['analytics_responses'], 'filtered analytics response count');

    $rating = Database::fetch(
        'SELECT COUNT(ra.id) AS rating_count, ROUND(AVG(ra.numeric_value), 2) AS average
         FROM response_answers ra
         JOIN evaluation_responses r ON r.id = ra.response_id
         JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
         WHERE es.is_demo = 1 AND ra.numeric_value IS NOT NULL'
    );
    assertSame((int) $rating['rating_count'], (int) $dashboard['rating_count'], 'rating count');
    assertNear((float) $rating['average'], $dashboard['average'] !== null ? (float) $dashboard['average'] : null, 0.011, 'overall average');
});

test('participation rate is derived from eligible and submitted rows', function (): void {
    $dashboard = (new AnalyticsService())->deanDashboard(true);
    $eligible = (int) $dashboard['eligible'];
    $submitted = (int) $dashboard['submitted'];

    $expected = $eligible > 0 ? round($submitted / $eligible * 100, 1) : 0.0;
    assertSame($expected, (float) $dashboard['participation']);
    assertTrue($submitted <= $eligible, 'submitted cannot exceed eligible');
});

test('category averages stay on the normalized 1-5 scale', function (): void {
    $dashboard = (new AnalyticsService())->deanDashboard(true);
    assertTrue(count($dashboard['categories']) > 0, 'expected demo category averages');

    foreach ($dashboard['categories'] as $category) {
        $average = (float) $category['average'];
        assertTrue($average >= 1.0 && $average <= 5.0, 'category average out of range: ' . $category['category']);
        assertGreaterThan(0, (int) $category['response_count']);
    }
});

test('live scope is isolated from the demo dataset', function (): void {
    $live = (new AnalyticsService())->deanDashboard(false);
    assertSame(0, (int) $live['counts']['responses'], 'live response count must be zero while only demo data exists');
    assertSame(0, (int) $live['counts']['instructors'], 'live instructor count');
    assertSame(0, (int) $live['counts']['students'], 'live student count');
});

test('instructor analytics are restricted to that instructor', function (): void {
    $instructor = Database::fetch(
        'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.slug = "instructor" AND u.is_demo = 1 ORDER BY u.id LIMIT 1'
    );
    assertTrue($instructor !== null, 'expected a demo instructor');

    $dashboard = (new AnalyticsService())->instructorDashboard((int) $instructor['id'], true);
    $expected = (int) Database::scalar(
        'SELECT COUNT(DISTINCT r.id) FROM evaluation_responses r
         JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
         JOIN evaluation_assignments a ON a.id = es.assignment_id
         WHERE es.is_demo = 1 AND a.instructor_id = ?',
        [(int) $instructor['id']],
        0
    );
    assertSame($expected, (int) $dashboard['responses'], 'instructor response count');
});
