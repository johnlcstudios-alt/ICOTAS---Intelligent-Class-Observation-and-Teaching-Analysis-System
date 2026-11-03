<?php

namespace App\Models;

/**
 * Class Record tallies (academic performance summary per class offering).
 * Deliberately separate from COF observation scores.
 */
final class ClassRecord extends BaseModel
{
    protected static string $table = 'class_records';
    protected static string $primaryKey = 'record_id';

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT r.*, c.subject_code, c.subject_name, c.section_name,
                    c.academic_year, c.semester, c.faculty_id,
                    u.full_name AS faculty_name,
                    d.department_name, d.department_code,
                    p.academic_year AS period_year, p.semester AS period_semester
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = r.period_id
             {$where}
             ORDER BY r.updated_at DESC, r.record_id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar(
            "SELECT COUNT(*) FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = r.period_id
             {$where}",
            $params
        );
    }

    private static function filterSql(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(c.subject_code LIKE ? OR c.subject_name LIKE ? OR c.section_name LIKE ? OR u.full_name LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        foreach ([
            ['faculty_id', 'c.faculty_id'],
            ['department_id', 'c.department_id'],
            ['class_id', 'r.class_id'],
            ['period_id', 'r.period_id'],
            ['record_status', 'r.record_status'],
        ] as [$key, $column]) {
            if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? null) !== null) {
                $conditions[] = $column . ' = ?';
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['academic_year'])) {
            $conditions[] = 'c.academic_year = ?';
            $params[] = $filters['academic_year'];
        }
        if (!empty($filters['semester'])) {
            $conditions[] = 'c.semester = ?';
            $params[] = $filters['semester'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function findDetailed(int $id): ?array
    {
        return static::fetchOne(
            'SELECT r.*, c.subject_code, c.subject_name, c.section_name,
                    c.academic_year, c.semester, c.faculty_id,
                    u.full_name AS faculty_name, u.employee_no,
                    d.department_name, d.department_code,
                    p.academic_year AS period_year, p.semester AS period_semester
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = r.period_id
             WHERE r.record_id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findForClassPeriod(int $classId, int $periodId): ?array
    {
        return static::fetchOne(
            'SELECT * FROM class_records WHERE class_id = ? AND period_id = ? LIMIT 1',
            [$classId, $periodId]
        );
    }
    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function recordRevision(int $recordId, array $snapshot, ?int $userId, ?string $reason = null): void
    {
        $stmt = static::pdo()->prepare(
            'INSERT INTO class_record_revisions (record_id, snapshot_json, changed_by, reason)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $recordId,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $userId,
            $reason,
        ]);
    }

    public static function revisions(int $recordId): array
    {
        return static::fetchAll(
            'SELECT rev.*, u.full_name AS changed_by_name
             FROM class_record_revisions rev
             LEFT JOIN users u ON u.user_id = rev.changed_by
             WHERE rev.record_id = ?
             ORDER BY rev.created_at DESC, rev.revision_id DESC',
            [$recordId]
        );
    }

    public static function statistics(array $filters = []): array
    {
        [$where, $params] = self::filterSql($filters);
        $row = static::fetchOne(
            "SELECT COUNT(*) AS record_count,
                    COALESCE(SUM(r.total_students), 0) AS total_students,
                    COALESCE(SUM(r.passed_count), 0) AS passed_students,
                    COALESCE(SUM(r.failed_count), 0) AS failed_students,
                    ROUND(AVG(r.average_grade), 2) AS average_grade
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = r.period_id
             {$where}",
            $params
        ) ?? [];

        $total = (int) ($row['total_students'] ?? 0);
        $passed = (int) ($row['passed_students'] ?? 0);
        $failed = (int) ($row['failed_students'] ?? 0);

        return [
            'record_count'    => (int) ($row['record_count'] ?? 0),
            'total_students'  => $total,
            'passed_students' => $passed,
            'failed_students' => $failed,
            'passing_rate'    => $total > 0 ? round(($passed / $total) * 100, 2) : 0.0,
            'failure_rate'    => $total > 0 ? round(($failed / $total) * 100, 2) : 0.0,
            'average_grade'   => $row['average_grade'] !== null ? (float) $row['average_grade'] : null,
        ];
    }

    public static function byPeriod(): array
    {
        return static::fetchAll(
            "SELECT p.period_id, p.academic_year, p.semester,
                    COUNT(r.record_id) AS record_count,
                    COALESCE(SUM(r.total_students), 0) AS total_students,
                    COALESCE(SUM(r.passed_count), 0) AS passed_count,
                    COALESCE(SUM(r.failed_count), 0) AS failed_count,
                    ROUND(AVG(r.average_grade), 2) AS average_grade
             FROM class_records r
             JOIN academic_periods p ON p.period_id = r.period_id
             GROUP BY p.period_id, p.academic_year, p.semester
             ORDER BY p.period_start ASC"
        );
    }

    public static function byAcademicYear(): array
    {
        return static::fetchAll(
            "SELECT c.academic_year,
                    COUNT(r.record_id) AS record_count,
                    COALESCE(SUM(r.total_students), 0) AS total_students,
                    COALESCE(SUM(r.passed_count), 0) AS passed_count,
                    COALESCE(SUM(r.failed_count), 0) AS failed_count,
                    ROUND(AVG(r.average_grade), 2) AS average_grade
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             GROUP BY c.academic_year
             ORDER BY c.academic_year ASC"
        );
    }

    public static function byDepartment(): array
    {
        return static::fetchAll(
            "SELECT d.department_name, d.department_code,
                    COUNT(r.record_id) AS record_count,
                    COALESCE(SUM(r.total_students), 0) AS total_students,
                    COALESCE(SUM(r.passed_count), 0) AS passed_count,
                    ROUND(AVG(r.average_grade), 2) AS average_grade
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN departments d ON d.department_id = c.department_id
             GROUP BY d.department_id, d.department_name, d.department_code
             ORDER BY d.department_name"
        );
    }

    public static function byFaculty(): array
    {
        return static::fetchAll(
            "SELECT u.user_id, u.full_name,
                    COUNT(r.record_id) AS record_count,
                    COALESCE(SUM(r.total_students), 0) AS total_students,
                    COALESCE(SUM(r.passed_count), 0) AS passed_count,
                    COALESCE(SUM(r.failed_count), 0) AS failed_count,
                    ROUND(AVG(r.average_grade), 2) AS average_grade
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             GROUP BY u.user_id, u.full_name
             ORDER BY u.full_name"
        );
    }

    public static function allForExport(array $filters = []): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT c.subject_code, c.subject_name, c.section_name, c.academic_year, c.semester,
                    u.full_name AS faculty_name, d.department_name,
                    r.total_students, r.passed_count, r.failed_count, r.average_grade,
                    r.remarks, r.record_status, r.created_at, r.updated_at
             FROM class_records r
             JOIN classes c ON c.class_id = r.class_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = r.period_id
             {$where}
             ORDER BY c.academic_year DESC, c.semester ASC, c.subject_code ASC",
            $params
        );
    }
}
