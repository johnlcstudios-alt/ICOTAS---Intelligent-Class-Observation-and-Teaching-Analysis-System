<?php

namespace App\Models;

/**
 * Users model. Exactly two roles exist: Dean and Faculty.
 */
final class User extends BaseModel
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'user_id';

    public static function findForSession(int $id): ?array
    {
        return static::fetchOne(
            'SELECT u.user_id, u.full_name, u.username, u.email, u.role, u.department_id,
                    u.employee_no, u.contact_no, u.account_status, u.must_change_password,
                    u.last_login_at, u.created_at, d.department_name, d.department_code
             FROM users u
             LEFT JOIN departments d ON d.department_id = u.department_id
             WHERE u.user_id = ? LIMIT 1',
            [$id]
        );
    }

    public static function findWithDepartment(int $id): ?array
    {
        return static::findForSession($id);
    }

    public static function findByLogin(string $identifier): ?array
    {
        return static::fetchOne(
            'SELECT u.*, d.department_name
             FROM users u
             LEFT JOIN departments d ON d.department_id = u.department_id
             WHERE u.username = ? OR u.email = ?
             LIMIT 1',
            [$identifier, $identifier]
        );
    }

    public static function findFaculty(int $id): ?array
    {
        return static::fetchOne(
            "SELECT u.*, d.department_name, d.department_code
             FROM users u
             LEFT JOIN departments d ON d.department_id = u.department_id
             WHERE u.user_id = ? AND u.role = 'Faculty' LIMIT 1",
            [$id]
        );
    }

    public static function facultyOptions(bool $activeOnly = true): array
    {
        $sql = "SELECT user_id, full_name, employee_no, department_id
                FROM users WHERE role = 'Faculty'";
        if ($activeOnly) {
            $sql .= " AND account_status = 'Active'";
        }
        $sql .= ' ORDER BY full_name ASC';
        return static::fetchAll($sql);
    }

    public static function paginateFaculty(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::facultyFilterSql($filters);
        return static::fetchAll(
            "SELECT u.user_id, u.full_name, u.username, u.email, u.employee_no, u.contact_no,
                    u.account_status, u.created_at, u.last_login_at,
                    d.department_name, d.department_code,
                    (SELECT COUNT(*) FROM observations o
                     WHERE o.faculty_id = u.user_id AND o.status = 'Published') AS published_count
             FROM users u
             LEFT JOIN departments d ON d.department_id = u.department_id
             {$where}
             ORDER BY u.full_name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFaculty(array $filters): int
    {
        [$where, $params] = self::facultyFilterSql($filters);
        return (int) static::scalar(
            "SELECT COUNT(*) FROM users u LEFT JOIN departments d ON d.department_id = u.department_id {$where}",
            $params
        );
    }

    private static function facultyFilterSql(array $filters): array
    {
        $conditions = ["u.role = 'Faculty'"];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.employee_no LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if (($filters['department_id'] ?? '') !== '' && ($filters['department_id'] ?? null) !== null) {
            $conditions[] = 'u.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'u.account_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) { // department code convenience filter
            $conditions[] = 'd.department_code = ?';
            $params[] = $filters['keyword'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function create(array $data): int
    {
        return static::insert($data);
    }

    public static function setStatus(int $id, string $status): bool
    {
        return static::updateById($id, ['account_status' => $status]);
    }

    public static function setPassword(int $id, string $hash, bool $mustChange = false): bool
    {
        return static::updateById($id, [
            'password_hash'       => $hash,
            'must_change_password'=> $mustChange ? 1 : 0,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function touchLogin(int $id): void
    {
        static::updateById($id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public static function deanExists(): bool
    {
        return (int) static::scalar("SELECT COUNT(*) FROM users WHERE role = 'Dean'") > 0;
    }

    public static function dean(): ?array
    {
        return static::fetchOne("SELECT * FROM users WHERE role = 'Dean' LIMIT 1");
    }

    public static function countFacultyTotal(): int
    {
        return (int) static::scalar("SELECT COUNT(*) FROM users WHERE role = 'Faculty'");
    }

    public static function byDepartment(): array
    {
        return static::fetchAll(
            "SELECT d.department_id, d.department_name, d.department_code, COUNT(u.user_id) AS faculty_count
             FROM departments d
             LEFT JOIN users u ON u.department_id = d.department_id AND u.role = 'Faculty'
             GROUP BY d.department_id, d.department_name, d.department_code
             ORDER BY d.department_name"
        );
    }
}
