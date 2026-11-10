<?php

namespace App\Models;

use App\Core\Database;

final class User extends Model
{
    protected string $table = 'users';

    public function findByLogin(string $login): ?array
    {
        return Database::fetch(
            'SELECT u.*, r.slug AS role, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.username) = ? OR LOWER(u.email) = ? LIMIT 1',
            [strtolower($login), strtolower($login)]
        );
    }

    public function findWithRelations(int $id): ?array
    {
        return Database::fetch(
            'SELECT u.*, r.slug AS role, r.name AS role_name,
                    d.name AS department_name, d.code AS department_code,
                    p.name AS program_name, p.code AS program_code,
                    s.code AS section_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN sections s ON s.id = u.section_id
             WHERE u.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Paginated account browser for the Dean / Academic Head.
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'r.slug = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'u.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.student_number LIKE ? OR u.employee_number LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if (isset($filters['is_demo'])) {
            $where[] = 'u.is_demo = ?';
            $params[] = (int) $filters['is_demo'];
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE ' . $clause,
            $params,
            0
        );
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            'SELECT u.*, r.slug AS role, r.name AS role_name, d.code AS department_code, p.code AS program_code, s.code AS section_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN sections s ON s.id = u.section_id
             WHERE ' . $clause . '
             ORDER BY u.last_name ASC, u.first_name ASC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function roleId(string $slug): ?int
    {
        $id = Database::scalar('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug]);
        return $id !== null ? (int) $id : null;
    }

    public function createAccount(array $data): int
    {
        return Database::insert(
            'INSERT INTO users
             (role_id, username, email, password_hash, first_name, middle_name, last_name, suffix,
              employee_number, student_number, department_id, program_id, section_id, year_level, phone,
              avatar, status, is_demo, password_changed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
            [
                $data['role_id'], $data['username'], $data['email'], $data['password_hash'],
                $data['first_name'], $data['middle_name'] ?? null, $data['last_name'], $data['suffix'] ?? null,
                $data['employee_number'] ?? null, $data['student_number'] ?? null,
                $data['department_id'] ?? null, $data['program_id'] ?? null, $data['section_id'] ?? null,
                $data['year_level'] ?? null, $data['phone'] ?? null, $data['avatar'] ?? null,
                $data['status'] ?? 'active', $data['is_demo'] ?? 0,
            ]
        );
    }

    public function updateAccount(int $id, array $data): void
    {
        Database::execute(
            'UPDATE users SET role_id = ?, username = ?, email = ?, first_name = ?, middle_name = ?, last_name = ?,
                    suffix = ?, employee_number = ?, student_number = ?, department_id = ?, program_id = ?,
                    section_id = ?, year_level = ?, phone = ?, status = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['role_id'], $data['username'], $data['email'], $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['suffix'] ?? null,
                $data['employee_number'] ?? null, $data['student_number'] ?? null,
                $data['department_id'] ?? null, $data['program_id'] ?? null,
                $data['section_id'] ?? null, $data['year_level'] ?? null,
                $data['phone'] ?? null, $data['status'] ?? 'active', $id,
            ]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
    }

    public function resetPassword(int $id, string $password): void
    {
        Database::execute(
            'UPDATE users SET password_hash = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
        Database::execute('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
    }

    public function countsByRole(bool $demo = false): array
    {
        $rows = Database::fetchAll(
            'SELECT r.slug, COUNT(u.id) AS total
             FROM roles r LEFT JOIN users u ON u.role_id = r.id AND u.is_demo = ?
             GROUP BY r.slug',
            [$demo ? 1 : 0]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['slug']] = (int) $row['total'];
        }
        return $out;
    }

    public function instructors(bool $demo = false, bool $activeOnly = true): array
    {
        return Database::fetchAll(
            'SELECT u.*, d.code AS department_code FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE r.slug = ? AND u.is_demo = ?' . ($activeOnly ? " AND u.status = 'active'" : '') . '
             ORDER BY u.last_name, u.first_name',
            ['instructor', $demo ? 1 : 0]
        );
    }

    public function students(bool $demo = false, bool $activeOnly = true): array
    {
        return Database::fetchAll(
            'SELECT u.*, p.code AS program_code, s.code AS section_code FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN sections s ON s.id = u.section_id
             WHERE r.slug = ? AND u.is_demo = ?' . ($activeOnly ? " AND u.status = 'active'" : '') . '
             ORDER BY u.last_name, u.first_name',
            ['student', $demo ? 1 : 0]
        );
    }

    public function activeSessionCount(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND last_activity > (NOW() - INTERVAL 30 MINUTE)',
            [$userId],
            0
        );
    }
}
