<?php

namespace App\Models;

/**
 * Observation records and their lifecycle.
 * Statuses: Draft, Submitted, Published, Returned for Correction, Archived.
 */
final class Observation extends BaseModel
{
    protected static string $table = 'observations';
    protected static string $primaryKey = 'observation_id';

    public const DRAFT = 'Draft';
    public const SUBMITTED = 'Submitted';
    public const PUBLISHED = 'Published';
    public const RETURNED = 'Returned for Correction';
    public const ARCHIVED = 'Archived';

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT o.*, u.full_name AS faculty_name, u.employee_no,
                    c.subject_code, c.subject_name, c.section_name,
                    d.department_name, d.department_code,
                    p.academic_year AS period_year,
                    (SELECT COUNT(*) FROM observation_ratings r WHERE r.observation_id = o.observation_id) AS rating_count,
                    (SELECT COUNT(*) FROM ratee_comments rc WHERE rc.observation_id = o.observation_id) AS comment_count
             FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = o.period_id
             {$where}
             ORDER BY o.observation_date DESC, o.observation_id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar(
            "SELECT COUNT(*) FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = o.period_id
             {$where}",
            $params
        );
    }

    private static function filterSql(array $filters, bool $forcePublished = false): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($forcePublished) {
            $conditions[] = "o.status = 'Published'";
        } elseif (!empty($filters['status'])) {
            $conditions[] = 'o.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['faculty_id'] ?? '') !== '' && ($filters['faculty_id'] ?? null) !== null) {
            $conditions[] = 'o.faculty_id = ?';
            $params[] = (int) $filters['faculty_id'];
        }
        if (($filters['department_id'] ?? '') !== '' && ($filters['department_id'] ?? null) !== null) {
            $conditions[] = 'c.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (($filters['class_id'] ?? '') !== '' && ($filters['class_id'] ?? null) !== null) {
            $conditions[] = 'o.class_id = ?';
            $params[] = (int) $filters['class_id'];
        }
        if (($filters['period_id'] ?? '') !== '' && ($filters['period_id'] ?? null) !== null) {
            $conditions[] = 'o.period_id = ?';
            $params[] = (int) $filters['period_id'];
        }
        if (!empty($filters['academic_year'])) {
            $conditions[] = 'o.academic_year = ?';
            $params[] = $filters['academic_year'];
        }
        if (!empty($filters['semester'])) {
            $conditions[] = 'o.semester = ?';
            $params[] = $filters['semester'];
        }
        if (!empty($filters['subject_code'])) {
            $conditions[] = 'c.subject_code = ?';
            $params[] = $filters['subject_code'];
        }
        if (!empty($filters['section_name'])) {
            $conditions[] = 'c.section_name = ?';
            $params[] = $filters['section_name'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(u.full_name LIKE ? OR c.subject_code LIKE ? OR c.subject_name LIKE ? OR o.lesson_topic LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'o.observation_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'o.observation_date <= ?';
            $params[] = $filters['date_to'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function findDetailed(int $id): ?array
    {
        return static::fetchOne(
            'SELECT o.*, u.full_name AS faculty_name, u.email AS faculty_email,
                    u.employee_no, u.department_id AS faculty_department_id,
                    c.subject_code, c.subject_name, c.section_name, c.department_id,
                    c.faculty_id AS class_faculty_id,
                    d.department_name, d.department_code,
                    p.academic_year AS period_year, p.semester AS period_semester,
                    obs.full_name AS observer_name,
                    (SELECT COUNT(*) FROM observation_ratings r WHERE r.observation_id = o.observation_id) AS rating_count
             FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = o.period_id
             JOIN users obs ON obs.user_id = o.observer_id
             WHERE o.observation_id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findForFaculty(int $observationId, int $facultyId): ?array
    {
        $row = static::findDetailed($observationId);
        if ($row === null || (int) $row['faculty_id'] !== $facultyId) {
            return null;
        }
        return $row;
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function setStatus(int $id, string $status, array $extra = []): bool
    {
        return static::updateById($id, array_merge(['status' => $status], $extra));
    }

    public static function publishedForFaculty(int $facultyId, int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT o.*, c.subject_code, c.subject_name, c.section_name,
                       d.department_name, p.academic_year AS period_year,
                       (SELECT COUNT(*) FROM faculty_acknowledgements a
                         WHERE a.observation_id = o.observation_id AND a.faculty_id = o.faculty_id) AS acknowledged,
                       (SELECT COUNT(*) FROM ratee_comments rc
                         WHERE rc.observation_id = o.observation_id) AS comment_count
                FROM observations o
                JOIN classes c ON c.class_id = o.class_id
                JOIN departments d ON d.department_id = c.department_id
                JOIN academic_periods p ON p.period_id = o.period_id
                WHERE o.faculty_id = ? AND o.status = 'Published'
                ORDER BY o.observation_date DESC, o.observation_id DESC";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        return static::fetchAll($sql, [$facultyId]);
    }

    public static function countPublishedForFaculty(int $facultyId): int
    {
        return (int) static::scalar(
            "SELECT COUNT(*) FROM observations WHERE faculty_id = ? AND status = 'Published'",
            [$facultyId]
        );
    }

    public static function latestPublishedForFaculty(int $facultyId): ?array
    {
        return static::fetchOne(
            "SELECT o.*, c.subject_code, c.subject_name, c.section_name
             FROM observations o
             JOIN classes c ON c.class_id = o.class_id
             WHERE o.faculty_id = ? AND o.status = 'Published'
             ORDER BY o.observation_date DESC, o.observation_id DESC LIMIT 1",
            [$facultyId]
        );
    }

    public static function statsForFaculty(int $facultyId): array
    {
        return static::fetchOne(
            "SELECT COUNT(*) AS total_published,
                    ROUND(AVG(teaching_competence_avg), 2) AS avg_teaching,
                    ROUND(AVG(personality_avg), 2) AS avg_personality,
                    ROUND(AVG(overall_avg), 2) AS avg_overall,
                    MIN(observation_date) AS first_date,
                    MAX(observation_date) AS last_date
             FROM observations
             WHERE faculty_id = ? AND status = 'Published'",
            [$facultyId]
        ) ?? [
            'total_published' => 0,
            'avg_teaching'    => null,
            'avg_personality' => null,
            'avg_overall'     => null,
            'first_date'      => null,
            'last_date'       => null,
        ];
    }

    public static function statsForDean(): array
    {
        return static::fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'Published') AS published,
                    SUM(status = 'Submitted') AS submitted,
                    SUM(status = 'Draft') AS draft,
                    SUM(status = 'Returned for Correction') AS returned,
                    SUM(status = 'Archived') AS archived,
                    ROUND(AVG(CASE WHEN status = 'Published' THEN teaching_competence_avg END), 2) AS avg_teaching,
                    ROUND(AVG(CASE WHEN status = 'Published' THEN personality_avg END), 2) AS avg_personality,
                    ROUND(AVG(CASE WHEN status = 'Published' THEN overall_avg END), 2) AS avg_overall
             FROM observations"
        ) ?? [];
    }

    public static function trendForFaculty(int $facultyId): array
    {
        return static::fetchAll(
            "SELECT o.observation_id, o.observation_date, o.academic_year, o.semester,
                    o.teaching_competence_avg, o.personality_avg, o.overall_avg,
                    c.subject_code, c.section_name, o.lesson_topic
             FROM observations o
             JOIN classes c ON c.class_id = o.class_id
             WHERE o.faculty_id = ? AND o.status = 'Published'
             ORDER BY o.observation_date ASC, o.observation_id ASC",
            [$facultyId]
        );
    }

    public static function recentActivity(int $limit = 8): array
    {
        return static::fetchAll(
            "SELECT o.observation_id, o.status, o.observation_date, o.published_at, o.submitted_at,
                    o.overall_avg, u.full_name AS faculty_name,
                    c.subject_code, c.section_name
             FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             ORDER BY o.updated_at DESC, o.observation_id DESC
             LIMIT {$limit}"
        );
    }

    public static function pendingReviewCount(): int
    {
        return (int) static::scalar("SELECT COUNT(*) FROM observations WHERE status = 'Submitted'");
    }

    /**
     * Distinct historical periods used by the prediction engine.
     * One row per (academic_year, semester) containing the period average.
     */
    public static function periodAveragesForFaculty(int $facultyId): array
    {
        return static::fetchAll(
            "SELECT MIN(p.period_start) AS period_start,
                    CONCAT(o.academic_year, '|', o.semester) AS period_key,
                    o.academic_year,
                    o.semester,
                    COUNT(*) AS observation_count,
                    ROUND(AVG(o.teaching_competence_avg), 4) AS teaching_avg,
                    ROUND(AVG(o.personality_avg), 4) AS personality_avg,
                    ROUND(AVG(o.overall_avg), 4) AS overall_avg
             FROM observations o
             JOIN academic_periods p ON p.period_id = o.period_id
             WHERE o.faculty_id = ? AND o.status = 'Published'
             GROUP BY o.academic_year, o.semester
             ORDER BY MIN(p.period_start) ASC, o.academic_year ASC",
            [$facultyId]
        );
    }

    public static function countByStatusForFaculty(int $facultyId): array
    {
        return static::fetchAll(
            'SELECT status, COUNT(*) AS total FROM observations WHERE faculty_id = ? GROUP BY status',
            [$facultyId]
        );
    }

    public static function monthlyActivity(int $months = 12): array
    {
        return static::fetchAll(
            "SELECT DATE_FORMAT(observation_date, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(status = 'Published') AS published
             FROM observations
             WHERE observation_date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)
             GROUP BY DATE_FORMAT(observation_date, '%Y-%m')
             ORDER BY month ASC"
        );
    }

    /**
     * Full result set for CSV export / printable reports.
     */
    public static function allFiltered(array $filters): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT o.*, u.full_name AS faculty_name, u.employee_no,
                    c.subject_code, c.subject_name, c.section_name,
                    d.department_name, d.department_code,
                    p.academic_year AS period_year
             FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = o.period_id
             {$where}
             ORDER BY o.observation_date DESC, o.observation_id DESC
             LIMIT 5000",
            $params
        );
    }

    public static function allPublishedWithClass(): array
    {
        return static::fetchAll(
            "SELECT o.*, u.full_name AS faculty_name, c.subject_code, c.section_name,
                    d.department_name, d.department_code
             FROM observations o
             JOIN users u ON u.user_id = o.faculty_id
             JOIN classes c ON c.class_id = o.class_id
             JOIN departments d ON d.department_id = c.department_id
             WHERE o.status = 'Published'
             ORDER BY o.observation_date DESC, o.observation_id DESC"
        );
    }
}
