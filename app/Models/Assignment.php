<?php

namespace App\Models;

/**
 * Dean-planned classroom observation assignments.
 */
final class Assignment extends BaseModel
{
    protected static string $table = 'observation_assignments';
    protected static string $primaryKey = 'assignment_id';

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT a.*, u.full_name AS faculty_name,
                    c.subject_code, c.subject_name, c.section_name,
                    d.department_name, d.department_code
             FROM observation_assignments a
             JOIN users u ON u.user_id = a.faculty_id
             JOIN classes c ON c.class_id = a.class_id
             JOIN departments d ON d.department_id = c.department_id
             {$where}
             ORDER BY a.due_date ASC, a.assignment_id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar(
            "SELECT COUNT(*) FROM observation_assignments a
             JOIN users u ON u.user_id = a.faculty_id
             JOIN classes c ON c.class_id = a.class_id
             JOIN departments d ON d.department_id = c.department_id
             {$where}",
            $params
        );
    }

    private static function filterSql(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if (!empty($filters['status'])) {
            $conditions[] = 'a.assignment_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['faculty_id'])) {
            $conditions[] = 'a.faculty_id = ?';
            $params[] = (int) $filters['faculty_id'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(u.full_name LIKE ? OR c.subject_code LIKE ? OR c.section_name LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function findDetailed(int $id): ?array
    {
        return static::fetchOne(
            'SELECT a.*, u.full_name AS faculty_name, c.subject_code, c.subject_name,
                    c.section_name, c.faculty_id, d.department_name
             FROM observation_assignments a
             JOIN users u ON u.user_id = a.faculty_id
             JOIN classes c ON c.class_id = a.class_id
             JOIN departments d ON d.department_id = c.department_id
             WHERE a.assignment_id = ? LIMIT 1',
            [$id]
        );
    }

    public static function markObservationCreated(int $assignmentId, int $observationId): void
    {
        static::updateById($assignmentId, [
            'assignment_status' => 'Completed',
            'observation_id'    => $observationId,
        ]);
    }

    public static function pending(): array
    {
        return static::fetchAll(
            "SELECT a.*, u.full_name AS faculty_name, c.subject_code, c.section_name
             FROM observation_assignments a
             JOIN users u ON u.user_id = a.faculty_id
             JOIN classes c ON c.class_id = a.class_id
             WHERE a.assignment_status = 'Pending'
             ORDER BY a.due_date ASC
             LIMIT 10"
        );
    }

    public static function countPending(): int
    {
        return (int) static::scalar("SELECT COUNT(*) FROM observation_assignments WHERE assignment_status = 'Pending'");
    }
}
