<?php

namespace App\Models;

/**
 * Class offering = subject + section + assigned faculty + academic period.
 */
final class ClassModel extends BaseModel
{
    protected static string $table = 'classes';
    protected static string $primaryKey = 'class_id';

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT c.*, d.department_name, d.department_code,
                    u.full_name AS faculty_name, u.employee_no,
                    p.academic_year AS period_year, p.semester AS period_semester,
                    (SELECT COUNT(*) FROM observations o WHERE o.class_id = c.class_id) AS observation_count,
                    (SELECT COUNT(*) FROM class_records r WHERE r.class_id = c.class_id) AS record_count
             FROM classes c
             JOIN departments d ON d.department_id = c.department_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN academic_periods p ON p.period_id = c.period_id
             {$where}
             ORDER BY c.status ASC, c.academic_year DESC, c.subject_code ASC, c.section_name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar(
            "SELECT COUNT(*) FROM classes c
             JOIN departments d ON d.department_id = c.department_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN academic_periods p ON p.period_id = c.period_id
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
        foreach ([['faculty_id', 'c.faculty_id'], ['department_id', 'c.department_id'], ['period_id', 'c.period_id']] as [$key, $column]) {
            if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? null) !== null) {
                $conditions[] = $column . ' = ?';
                $params[] = (int) $filters[$key];
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
        if (!empty($filters['status'])) {
            $conditions[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function findDetailed(int $id): ?array
    {
        return static::fetchOne(
            'SELECT c.*, d.department_name, d.department_code,
                    u.full_name AS faculty_name, u.email AS faculty_email, u.employee_no,
                    p.academic_year AS period_year, p.semester AS period_semester
             FROM classes c
             JOIN departments d ON d.department_id = c.department_id
             JOIN users u ON u.user_id = c.faculty_id
             JOIN academic_periods p ON p.period_id = c.period_id
             WHERE c.class_id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findOffering(int $periodId, string $subjectCode, string $sectionName): ?array
    {
        return static::fetchOne(
            'SELECT * FROM classes WHERE period_id = ? AND subject_code = ? AND section_name = ? LIMIT 1',
            [$periodId, $subjectCode, $sectionName]
        );
    }

    /**
     * Active class options, optionally limited to one faculty member.
     */
    public static function options(?int $facultyId = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT c.class_id, c.subject_code, c.subject_name, c.section_name,
                       c.faculty_id, c.department_id, c.period_id, c.academic_year, c.semester,
                       c.status, u.full_name AS faculty_name, d.department_code
                FROM classes c
                JOIN users u ON u.user_id = c.faculty_id
                JOIN departments d ON d.department_id = c.department_id
                WHERE 1 = 1';
        $params = [];
        if ($activeOnly) {
            $sql .= " AND c.status = 'Active'";
        }
        if ($facultyId !== null) {
            $sql .= ' AND c.faculty_id = ?';
            $params[] = $facultyId;
        }
        $sql .= ' ORDER BY c.academic_year DESC, c.subject_code ASC, c.section_name ASC';
        return static::fetchAll($sql, $params);
    }

    public static function forFaculty(int $facultyId): array
    {
        return static::fetchAll(
            "SELECT c.*, d.department_name, d.department_code,
                    p.academic_year AS period_year, p.semester AS period_semester
             FROM classes c
             JOIN departments d ON d.department_id = c.department_id
             JOIN academic_periods p ON p.period_id = c.period_id
             WHERE c.faculty_id = ?
             ORDER BY c.status ASC, c.academic_year DESC, c.subject_code ASC",
            [$facultyId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function hasObservations(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM observations WHERE class_id = ?', [$id]) > 0;
    }

    public static function hasRecords(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM class_records WHERE class_id = ?', [$id]) > 0;
    }

    public static function countActive(): int
    {
        return (int) static::scalar("SELECT COUNT(*) FROM classes WHERE status = 'Active'");
    }

    public static function byDepartment(): array
    {
        return static::fetchAll(
            "SELECT d.department_id, d.department_name, d.department_code,
                    COUNT(c.class_id) AS class_count,
                    COUNT(DISTINCT c.faculty_id) AS faculty_count
             FROM departments d
             LEFT JOIN classes c ON c.department_id = d.department_id AND c.status = 'Active'
             GROUP BY d.department_id, d.department_name, d.department_code
             ORDER BY d.department_name"
        );
    }

    public static function subjectOptions(): array
    {
        return static::fetchAll(
            "SELECT DISTINCT subject_code, subject_name FROM classes ORDER BY subject_code ASC"
        );
    }

    public static function sectionOptions(): array
    {
        return static::fetchAll(
            "SELECT DISTINCT section_name FROM classes ORDER BY section_name ASC"
        );
    }
}
