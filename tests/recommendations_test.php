<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\AIEngine;
use App\Services\RecommendationService;
use App\Services\SurveyService;

/**
 * Evidence-linked pedagogical recommendations.
 *
 * The Dean must be able to see why every recommendation exists, and the system
 * must never invent a concern that the aggregates do not support. These tests
 * cover the playbook content, the evidence linkage stored with each row, the
 * idempotency of regeneration, and the ownership rules on status changes.
 */

function openRecommendationCount(int $instructorId): int
{
    return (int) Database::scalar(
        "SELECT COUNT(*) FROM instructor_recommendations WHERE instructor_id = ? AND status IN ('open','acknowledged','in_progress')",
        [$instructorId],
        0
    );
}

test('every teaching theme resolves to a complete recommendation playbook', function (): void {
    $engine = new AIEngine();
    foreach (SurveyService::THEMES as $theme) {
        $playbook = $engine->playbook($theme);
        assertTrue(trim((string) ($playbook['concern'] ?? '')) !== '', 'concern present for ' . $theme);
        assertTrue(trim((string) ($playbook['recommendation'] ?? '')) !== '', 'recommendation present for ' . $theme);
        assertTrue(trim((string) ($playbook['reason'] ?? '')) !== '', 'reason present for ' . $theme);
    }

    $unknown = $engine->playbook('A theme that does not exist');
    assertTrue(trim((string) ($unknown['concern'] ?? '')) !== '', 'an unknown theme still returns a safe generic playbook');
    assertTrue(trim((string) ($unknown['recommendation'] ?? '')) !== '', 'the generic playbook is actionable');
});

test('stored recommendations cite the aggregate that produced them', function (): void {
    $service = new RecommendationService();
    $rows = $service->all(true);
    assertTrue($rows !== [], 'demo seeding produces recommendations');

    $statuses = ['open', 'acknowledged', 'in_progress', 'resolved'];
    $priorities = ['high', 'medium', 'low'];
    foreach ($rows as $row) {
        assertTrue(trim((string) $row['theme']) !== '', 'the theme is named');
        assertTrue(trim((string) $row['concern']) !== '', 'the concern is stated');
        assertTrue(trim((string) $row['recommendation']) !== '', 'the recommendation is stated');
        assertTrue(trim((string) $row['reason']) !== '', 'the reason is stated');
        assertTrue(in_array((string) $row['status'], $statuses, true), 'the status is valid');
        assertTrue(in_array((string) $row['priority'], $priorities, true), 'the priority is valid');
        assertSame('rule_based', (string) $row['source'], 'the source is labelled as rule-based');

        $metric = (string) $row['supporting_metric'];
        assertTrue(trim($metric) !== '', 'the supporting metric is recorded');
        $isThemeEvidence = (bool) preg_match('/\d+ negative mention/', $metric);
        $isCategoryEvidence = (bool) preg_match('/Category average ([0-9]\.[0-9]{2})\/5\.00 from (\d+) ratings/', $metric, $m);
        assertTrue($isThemeEvidence || $isCategoryEvidence, 'the metric names a theme count or a category average: ' . $metric);
        if ($isCategoryEvidence) {
            assertTrue((float) $m[1] < 3.5, 'a category-driven recommendation cites an average below the 3.50 threshold: ' . $m[1]);
            assertTrue((int) $m[2] > 0, 'a category-driven recommendation cites a non-zero rating count');
        }
    }
});

test('regenerating recommendations refreshes open items instead of duplicating them', function (): void {
    $service = new RecommendationService();
    $instructorId = (int) Database::scalar(
        "SELECT instructor_id FROM instructor_recommendations ir
         JOIN users u ON u.id = ir.instructor_id
         WHERE u.is_demo = 1 AND ir.status IN ('open','acknowledged','in_progress')
         GROUP BY instructor_id ORDER BY COUNT(*) DESC LIMIT 1",
        [],
        0
    );
    assertTrue($instructorId > 0, 'demo data contains an instructor with open recommendations');

    $before = openRecommendationCount($instructorId);
    $service->generateForInstructor($instructorId, true);
    $afterFirst = openRecommendationCount($instructorId);
    $createdSecond = $service->generateForInstructor($instructorId, true);
    $afterSecond = openRecommendationCount($instructorId);

    assertTrue($afterFirst >= $before, 'generation never removes an existing recommendation');
    assertSame($afterFirst, $afterSecond, 'a second run does not duplicate open recommendations');
    assertSame(0, $createdSecond, 'the second run only refreshes rows that already exist');

    $distinctThemes = (int) Database::scalar(
        "SELECT COUNT(DISTINCT theme) FROM instructor_recommendations WHERE instructor_id = ? AND status IN ('open','acknowledged','in_progress')",
        [$instructorId],
        0
    );
    assertSame($afterFirst, $distinctThemes, 'at most one open recommendation exists per theme');

    $rows = $service->forInstructor($instructorId);
    assertTrue($rows !== [], 'recommendations are listed for the instructor');
    foreach ($rows as $row) {
        assertSame($instructorId, (int) $row['instructor_id'], 'the listing is scoped to the instructor');
    }
});

test('instructor recommendations are private and status changes are ownership-checked', function (): void {
    $service = new RecommendationService();
    $instructorIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        Database::fetchAll(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = "instructor" AND u.is_demo = 1 ORDER BY u.id LIMIT 2'
        )
    );
    assertSame(2, count($instructorIds), 'two demo instructors exist for the ownership check');
    [$owner, $other] = $instructorIds;

    $row = Database::fetch(
        "SELECT * FROM instructor_recommendations WHERE instructor_id = ? ORDER BY id LIMIT 1",
        [$owner]
    );
    assertTrue($row !== null, 'the owner has a recommendation');
    $originalStatus = (string) $row['status'];

    $otherRows = $service->forInstructor($other);
    foreach ($otherRows as $otherRow) {
        assertFalse((int) $otherRow['id'] === (int) $row['id'], 'another instructor never sees this recommendation');
    }

    assertFalse($service->updateStatus((int) $row['id'], 'not-a-status', $owner, false), 'an unknown status is refused');
    assertFalse($service->updateStatus((int) $row['id'], 'acknowledged', $other, false), 'a different instructor cannot change the status');
    assertSame($originalStatus, (string) Database::fetch('SELECT status FROM instructor_recommendations WHERE id = ?', [$row['id']])['status'], 'the refused change left the status untouched');

    assertTrue($service->updateStatus((int) $row['id'], 'in_progress', $owner, false), 'the owner can advance the status');
    assertSame('in_progress', (string) Database::fetch('SELECT status FROM instructor_recommendations WHERE id = ?', [$row['id']])['status'], 'the owner change persisted');

    assertTrue($service->updateStatus((int) $row['id'], 'resolved', 0, true), 'an administrator can resolve any recommendation');
    assertSame('resolved', (string) Database::fetch('SELECT status FROM instructor_recommendations WHERE id = ?', [$row['id']])['status'], 'the administrator change persisted');

    // Restore the seeded value so later checks see the original demo dataset.
    Database::execute('UPDATE instructor_recommendations SET status = ? WHERE id = ?', [$originalStatus, (int) $row['id']]);
    assertSame($originalStatus, (string) Database::fetch('SELECT status FROM instructor_recommendations WHERE id = ?', [$row['id']])['status'], 'the demo status is restored');
});
