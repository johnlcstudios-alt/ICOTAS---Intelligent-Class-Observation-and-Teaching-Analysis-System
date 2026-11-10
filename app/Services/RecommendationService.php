<?php

namespace App\Services;

use App\Core\Database;

/**
 * Evidence-linked pedagogical recommendations. Every recommendation names the
 * observed concern, the supporting aggregate, and the reason for the action.
 */
final class RecommendationService
{
    private AIEngine $engine;
    private AnalyticsService $analytics;

    public function __construct()
    {
        $this->engine = new AIEngine();
        $this->analytics = new AnalyticsService();
    }

    public function generateForInstructor(int $instructorId, bool $demo): int
    {
        $categories = $this->analytics->instructorCategories($instructorId, $demo);
        $model = new \App\Models\Analytics();
        $themes = $model->themeCounts(['instructor_id' => $instructorId], $demo, 'negative');
        $created = 0;

        $candidates = [];
        foreach ($themes as $theme) {
            if ((int) $theme['count'] < 1) {
                continue;
            }
            $playbook = $this->engine->playbook($theme['theme']);
            $candidates[$theme['theme']] = [
                'theme' => $theme['theme'],
                'concern' => $playbook['concern'],
                'supporting_metric' => $theme['count'] . ' negative mention(s) in anonymous written feedback',
                'recommendation' => $playbook['recommendation'],
                'reason' => $playbook['reason'],
                'priority' => (int) $theme['count'] >= 3 ? 'high' : 'medium',
            ];
        }
        foreach ($categories as $row) {
            $average = $row['average'] !== null ? (float) $row['average'] : null;
            if ($average === null || $average >= 3.5 || isset($candidates[$row['category']])) {
                continue;
            }
            $playbook = $this->engine->playbook($row['category']);
            $candidates[$row['category']] = [
                'theme' => $row['category'],
                'concern' => $playbook['concern'],
                'supporting_metric' => 'Category average ' . number_format($average, 2) . '/5.00 from ' . (int) $row['response_count'] . ' ratings',
                'recommendation' => $playbook['recommendation'],
                'reason' => $playbook['reason'],
                'priority' => $average < 3.0 ? 'high' : 'medium',
            ];
        }

        foreach ($candidates as $candidate) {
            $existing = Database::fetch(
                'SELECT id FROM instructor_recommendations
                 WHERE instructor_id = ? AND theme = ? AND status IN ("open","acknowledged","in_progress")
                 ORDER BY id DESC LIMIT 1',
                [$instructorId, $candidate['theme']]
            );
            if ($existing) {
                Database::execute(
                    'UPDATE instructor_recommendations SET concern = ?, supporting_metric = ?, recommendation = ?, reason = ?, priority = ?, updated_at = NOW() WHERE id = ?',
                    [$candidate['concern'], $candidate['supporting_metric'], $candidate['recommendation'], $candidate['reason'], $candidate['priority'], $existing['id']]
                );
            } else {
                Database::insert(
                    'INSERT INTO instructor_recommendations
                     (instructor_id, theme, concern, supporting_metric, recommendation, reason, priority, status, source, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, "open", "rule_based", NOW(), NOW())',
                    [$instructorId, $candidate['theme'], $candidate['concern'], $candidate['supporting_metric'], $candidate['recommendation'], $candidate['reason'], $candidate['priority']]
                );
                $created++;
            }
        }
        return $created;
    }

    public function generateAll(bool $demo): int
    {
        $instructors = Database::fetchAll(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "instructor" AND u.is_demo = ? AND u.status = "active"',
            [$demo ? 1 : 0]
        );
        $created = 0;
        foreach ($instructors as $instructor) {
            $created += $this->generateForInstructor((int) $instructor['id'], $demo);
        }
        return $created;
    }

    public function forInstructor(int $instructorId): array
    {
        return Database::fetchAll(
            'SELECT * FROM instructor_recommendations WHERE instructor_id = ? ORDER BY FIELD(status, "open","acknowledged","in_progress","resolved"), FIELD(priority, "high","medium","low"), updated_at DESC',
            [$instructorId]
        );
    }

    public function all(bool $demo, string $status = ''): array
    {
        $sql = 'SELECT ir.*, u.first_name, u.last_name, d.code AS department_code
                FROM instructor_recommendations ir
                JOIN users u ON u.id = ir.instructor_id
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE u.is_demo = ?';
        $params = [$demo ? 1 : 0];
        if ($status !== '') {
            $sql .= ' AND ir.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(ir.priority, "high","medium","low"), FIELD(ir.status, "open","acknowledged","in_progress","resolved"), ir.updated_at DESC';
        return Database::fetchAll($sql, $params);
    }

    public function updateStatus(int $id, string $status, int $instructorId, bool $isAdmin): bool
    {
        if (!in_array($status, ['open', 'acknowledged', 'in_progress', 'resolved'], true)) {
            return false;
        }
        if ($isAdmin) {
            return Database::execute('UPDATE instructor_recommendations SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]) > 0;
        }
        return Database::execute(
            'UPDATE instructor_recommendations SET status = ?, updated_at = NOW() WHERE id = ? AND instructor_id = ?',
            [$status, $id, $instructorId]
        ) > 0;
    }
}
