<?php

namespace App\Models;

final class Department extends BaseModel
{
    protected static string $table = 'departments';
    protected static string $primaryKey = 'department_id';

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT d.*,
                    (SELECT COUNT(*) FROM users u WHERE u.department_id = d.department_id AND u.role = 'Faculty') AS faculty_count,
                    (SELECT COUNT(*) FROM classes c WHERE c.department_id = d.department_id AND c.status = 'Active') AS class_count,
                    (SELECT ROUND(AVG(o.overall_avg), 2)
                       FROM observations o
                       JOIN classes c2 ON c2.class_id = o.class_id
                      WHERE c2.department_id = d.department_id AND o.status = 'Published') AS average_overall
             FROM departments d
             {$where}
             ORDER BY d.department_name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar("SELECT COUNT(*) FROM departments d {$where}", $params);
    }

    private static function filterSql(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(d.department_name LIKE ? OR d.department_code LIKE ?)';
            array_push($params, $term, $term);
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function options(bool $activeOnly = false): array
    {
        $sql = 'SELECT department_id, department_name, department_code, status FROM departments';
        if ($activeOnly) {
            $sql .= " WHERE status = 'Active'";
        }
        $sql .= ' ORDER BY department_name';
        return static::fetchAll($sql);
    }

    public static function hasFaculty(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM users WHERE department_id = ? AND role = ?', [$id, 'Faculty']) > 0;
    }

    public static function hasClasses(int $id): bool
    {
        return (int) static::scalar('SELECT COUNT(*) FROM classes WHERE department_id = ?', [$id]) > 0;
    }

    public static function hasActiveFaculty(int $id): bool
    {
        return (int) static::scalar(
            "SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'Faculty' AND account_status = 'Active'",
            [$id]
        ) > 0;
    }

    public static function hasActiveClasses(int $id): bool
    {
        return (int) static::scalar(
            "SELECT COUNT(*) FROM classes WHERE department_id = ? AND status = 'Active'",
            [$id]
        ) > 0;
    }

    public static function findByNameOrCode(string $name, string $code, ?int $exceptId = null): ?array
    {
        $sql = 'SELECT * FROM departments WHERE (department_name = ? OR department_code = ?)';
        $params = [$name, $code];
        if ($exceptId !== null) {
            $sql .= ' AND department_id <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        return static::fetchOne($sql, $params);
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }
}
