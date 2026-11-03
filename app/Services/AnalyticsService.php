<?php

namespace App\Services;

use App\Models\Observation;

/**
 * Database-backed analytics. Every number here comes from real rows; there
 * are no hardcoded dashboard statistics. Score analytics use Published
 * observations only, while workflow counts include all statuses.
 */
final class AnalyticsService
{
    private const STATUSES = ['Draft', 'Submitted', 'Published', 'Returned for Correction', 'Archived'];

    /**
     * @return array{where:string,params:array}
     */
    private function where(array $f, bool $publishedOnly = true): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($publishedOnly) {
            $conditions[] = "o.status = 'Published'";
        }
        if (!empty($f['faculty_id'])) {
            $conditions[] = 'o.faculty_id = ?';
            $params[] = (int) $f['faculty_id'];
        }
        if (!empty($f['department_id'])) {
            $conditions[] = 'c.department_id = ?';
            $params[] = (int) $f['department_id'];
        }
        if (!empty($f['class_id'])) {
            $conditions[] = 'o.class_id = ?';
            $params[] = (int) $f['class_id'];
        }
        if (!empty($f['period_id'])) {
            $conditions[] = 'o.period_id = ?';
            $params[] = (int) $f['period_id'];
        }
        if (!empty($f['academic_year'])) {
            $conditions[] = 'o.academic_year = ?';
            $params[] = $f['academic_year'];
        }
        if (!empty($f['semester'])) {
            $conditions[] = 'o.semester = ?';
            $params[] = $f['semester'];
        }
        if (!empty($f['subject_code'])) {
            $conditions[] = 'c.subject_code = ?';
            $params[] = $f['subject_code'];
        }
        if (!empty($f['section_name'])) {
            $conditions[] = 'c.section_name = ?';
            $params[] = $f['section_name'];
        }
        if (!empty($f['date_from'])) {
            $conditions[] = 'o.observation_date >= ?';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $conditions[] = 'o.observation_date <= ?';
            $params[] = $f['date_to'];
        }

        return ['where' => 'WHERE ' . implode(' AND ', $conditions), 'params' => $params];
    }

    private const JOINS = 'FROM observations o
        JOIN users u ON u.user_id = o.faculty_id
        JOIN classes c ON c.class_id = o.class_id
        JOIN departments d ON d.department_id = c.department_id
        JOIN academic_periods p ON p.period_id = o.period_id';

    public function summary(array $filters = []): array
    {
        $w = $this->where($filters, false);
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total,
                    SUM(o.status = \'Published\') AS published,
                    SUM(o.status = \'Submitted\') AS submitted,
                    SUM(o.status = \'Draft\') AS draft,
                    SUM(o.status = \'Returned for Correction\') AS returned,
                    SUM(o.status = \'Archived\') AS archived,
                    ROUND(AVG(CASE WHEN o.status = \'Published\' THEN o.teaching_competence_avg END), 2) AS avg_teaching,
                    ROUND(AVG(CASE WHEN o.status = \'Published\' THEN o.personality_avg END), 2) AS avg_personality,
                    ROUND(AVG(CASE WHEN o.status = \'Published\' THEN o.overall_avg END), 2) AS avg_overall,
                    COUNT(DISTINCT o.faculty_id) AS faculty_count
             ' . self::JOINS . ' ' . $w['where'],
            $w['params']
        ) ?? [];

        return [
            'total'         => (int) ($row['total'] ?? 0),
            'published'     => (int) ($row['published'] ?? 0),
            'submitted'     => (int) ($row['submitted'] ?? 0),
            'draft'         => (int) ($row['draft'] ?? 0),
            'returned'      => (int) ($row['returned'] ?? 0),
            'archived'      => (int) ($row['archived'] ?? 0),
            'pending'       => (int) ($row['submitted'] ?? 0) + (int) ($row['draft'] ?? 0),
            'faculty_count' => (int) ($row['faculty_count'] ?? 0),
            'avg_teaching'  => $row['avg_teaching'] !== null ? (float) $row['avg_teaching'] : null,
            'avg_personality'=> $row['avg_personality'] !== null ? (float) $row['avg_personality'] : null,
            'avg_overall'   => $row['avg_overall'] !== null ? (float) $row['avg_overall'] : null,
        ];
    }

    public function statusCounts(array $filters = []): array
    {
        $w = $this->where($filters, false);
        $rows = $this->fetchAll(
            'SELECT o.status, COUNT(*) AS total ' . self::JOINS . ' ' . $w['where'] . ' GROUP BY o.status',
            $w['params']
        );
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['total'];
        }
        return $out;
    }

    public function byFaculty(array $filters = []): array
    {
        $w = $this->where($filters, true);
        return $this->fetchAll(
            'SELECT u.user_id, u.full_name, d.department_code,
                    COUNT(*) AS observation_count,
                    ROUND(AVG(o.teaching_competence_avg), 2) AS avg_teaching,
                    ROUND(AVG(o.personality_avg), 2) AS avg_personality,
                    ROUND(AVG(o.overall_avg), 2) AS avg_overall
             ' . self::JOINS . ' ' . $w['where'] . '
             GROUP BY u.user_id, u.full_name, d.department_code
             ORDER BY avg_overall DESC, u.full_name ASC',
            $w['params']
        );
    }

    public function byDepartment(array $filters = []): array
    {
        $w = $this->where($filters, true);
        return $this->fetchAll(
            'SELECT d.department_id, d.department_name, d.department_code,
                    COUNT(*) AS observation_count,
                    COUNT(DISTINCT o.faculty_id) AS faculty_count,
                    ROUND(AVG(o.teaching_competence_avg), 2) AS avg_teaching,
                    ROUND(AVG(o.personality_avg), 2) AS avg_personality,
                    ROUND(AVG(o.overall_avg), 2) AS avg_overall
             ' . self::JOINS . ' ' . $w['where'] . '
             GROUP BY d.department_id, d.department_name, d.department_code
             ORDER BY avg_overall DESC, d.department_name ASC',
            $w['params']
        );
    }

    public function byPeriod(array $filters = []): array
    {
        $w = $this->where($filters, true);
        return $this->fetchAll(
            'SELECT p.period_id, p.academic_year, p.semester, p.period_start, p.status,
                    COUNT(*) AS observation_count,
                    ROUND(AVG(o.teaching_competence_avg), 2) AS avg_teaching,
                    ROUND(AVG(o.personality_avg), 2) AS avg_personality,
                    ROUND(AVG(o.overall_avg), 2) AS avg_overall
             ' . self::JOINS . ' ' . $w['where'] . '
             GROUP BY p.period_id, p.academic_year, p.semester, p.period_start, p.status
             ORDER BY p.period_start ASC',
            $w['params']
        );
    }

    public function byAcademicYear(array $filters = []): array
    {
        $w = $this->where($filters, true);
        return $this->fetchAll(
            'SELECT o.academic_year,
                    COUNT(*) AS observation_count,
                    ROUND(AVG(o.teaching_competence_avg), 2) AS avg_teaching,
                    ROUND(AVG(o.personality_avg), 2) AS avg_personality,
                    ROUND(AVG(o.overall_avg), 2) AS avg_overall
             ' . self::JOINS . ' ' . $w['where'] . '
             GROUP BY o.academic_year
             ORDER BY o.academic_year ASC',
            $w['params']
        );
    }

    public function monthlyActivity(array $filters = []): array
    {
        $w = $this->where($filters, false);
        $rows = $this->fetchAll(
            "SELECT DATE_FORMAT(o.observation_date, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(o.status = 'Published') AS published,
                    ROUND(AVG(CASE WHEN o.status = 'Published' THEN o.overall_avg END), 2) AS avg_overall
             " . self::JOINS . ' ' . $w['where'] . '
             GROUP BY DATE_FORMAT(o.observation_date, \'%Y-%m\')
             ORDER BY month ASC',
            $w['params']
        );

        $activity = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = date('Y-m', strtotime('-' . $i . ' months'));
            $activity[$key] = ['month' => $key, 'total' => 0, 'published' => 0, 'avg_overall' => null];
        }
        foreach ($rows as $row) {
            if (isset($activity[$row['month']])) {
                $activity[$row['month']] = [
                    'month'       => $row['month'],
                    'total'       => (int) $row['total'],
                    'published'   => (int) $row['published'],
                    'avg_overall' => $row['avg_overall'] !== null ? (float) $row['avg_overall'] : null,
                ];
            }
        }
        return array_values($activity);
    }

    public function distribution(array $filters = []): array
    {
        $w = $this->where($filters, true);
        $rows = $this->fetchAll(
            'SELECT r.category, r.score, COUNT(*) AS total
             FROM observation_ratings r
             JOIN observations o ON o.observation_id = r.observation_id
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = o.period_id
             ' . $w['where'] . '
             GROUP BY r.category, r.score
             ORDER BY r.category, r.score',
            $w['params']
        );

        $out = [
            'Teaching Competence' => array_fill(1, 5, 0),
            'Personality'         => array_fill(1, 5, 0),
        ];
        foreach ($rows as $row) {
            $out[$row['category']][(int) $row['score']] = (int) $row['total'];
        }
        return $out;
    }

    public function facultyComparison(array $filters = []): array
    {
        return $this->byFaculty($filters);
    }

    /**
     * Chronological trend of published period averages for charting.
     */
    public function periodTrend(array $filters = []): array
    {
        $rows = $this->byPeriod($filters);
        return array_map(static fn (array $r): array => [
            'label'        => $r['academic_year'] . ' · ' . semester_abbr((string) $r['semester']),
            'period_id'    => (int) $r['period_id'],
            'teaching'     => $r['avg_teaching'] !== null ? (float) $r['avg_teaching'] : null,
            'personality'  => $r['avg_personality'] !== null ? (float) $r['avg_personality'] : null,
            'overall'      => $r['avg_overall'] !== null ? (float) $r['avg_overall'] : null,
            'observations' => (int) $r['observation_count'],
        ], $rows);
    }

    public function facultyDashboard(int $facultyId): array
    {
        $stats = Observation::statsForFaculty($facultyId);
        $trend = Observation::trendForFaculty($facultyId);
        $points = array_map(static fn (array $row): array => [
            'label'       => date('M j, Y', strtotime((string) $row['observation_date'])),
            'date'        => (string) $row['observation_date'],
            'teaching'    => $row['teaching_competence_avg'] !== null ? (float) $row['teaching_competence_avg'] : null,
            'personality' => $row['personality_avg'] !== null ? (float) $row['personality_avg'] : null,
            'overall'     => $row['overall_avg'] !== null ? (float) $row['overall_avg'] : null,
            'subject'     => (string) $row['subject_code'],
            'topic'       => (string) $row['lesson_topic'],
        ], $trend);

        $distribution = ['Teaching Competence' => array_fill(1, 5, 0), 'Personality' => array_fill(1, 5, 0)];
        $rows = \App\Models\ObservationRating::categoryDistribution($facultyId);
        foreach ($rows as $row) {
            $distribution[$row['category']][(int) $row['score']] = (int) $row['total'];
        }

        return [
            'stats'        => $stats,
            'trend'        => $points,
            'distribution' => $distribution,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = \App\Core\Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->fetchAll($sql, $params);
        return $row[0] ?? null;
    }
}
