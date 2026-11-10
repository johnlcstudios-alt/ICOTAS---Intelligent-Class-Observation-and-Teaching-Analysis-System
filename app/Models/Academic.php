<?php

namespace App\Models;

use App\Core\Database;

/**
 * Shared lookups and management queries for academic entities.
 */
final class Academic
{
    public function departments(bool $demo = false, bool $activeOnly = false): array
    {
        $sql = 'SELECT d.*, u.first_name, u.last_name,
                  (SELECT COUNT(*) FROM programs p WHERE p.department_id = d.id) AS program_count
                FROM departments d
                LEFT JOIN users u ON u.id = d.head_user_id
                WHERE d.is_demo = ?';
        if ($activeOnly) {
            $sql .= " AND d.status = 'active'";
        }
        $sql .= ' ORDER BY d.name';
        return Database::fetchAll($sql, [$demo ? 1 : 0]);
    }

    public function programs(bool $demo = false, ?int $departmentId = null, bool $activeOnly = false): array
    {
        $sql = 'SELECT p.*, d.code AS department_code, d.name AS department_name
                FROM programs p JOIN departments d ON d.id = p.department_id
                WHERE p.is_demo = ?';
        $params = [$demo ? 1 : 0];
        if ($departmentId) {
            $sql .= ' AND p.department_id = ?';
            $params[] = $departmentId;
        }
        if ($activeOnly) {
            $sql .= " AND p.status = 'active'";
        }
        $sql .= ' ORDER BY p.name';
        return Database::fetchAll($sql, $params);
    }

    public function academicYears(): array
    {
        return Database::fetchAll('SELECT * FROM academic_years ORDER BY start_date DESC, name DESC');
    }

    public function semesters(?int $yearId = null): array
    {
        $sql = 'SELECT s.*, ay.name AS academic_year_name FROM semesters s
                JOIN academic_years ay ON ay.id = s.academic_year_id';
        $params = [];
        if ($yearId) {
            $sql .= ' WHERE s.academic_year_id = ?';
            $params[] = $yearId;
        }
        $sql .= ' ORDER BY ay.start_date DESC, s.sequence ASC';
        return Database::fetchAll($sql, $params);
    }

    public function currentYear(): ?array
    {
        return Database::fetch("SELECT * FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1")
            ?? Database::fetch('SELECT * FROM academic_years ORDER BY start_date DESC LIMIT 1');
    }

    public function currentSemester(): ?array
    {
        return Database::fetch(
            "SELECT s.*, ay.name AS academic_year_name FROM semesters s
             JOIN academic_years ay ON ay.id = s.academic_year_id
             WHERE s.is_current = 1 ORDER BY s.id DESC LIMIT 1"
        ) ?? Database::fetch(
            'SELECT s.*, ay.name AS academic_year_name FROM semesters s
             JOIN academic_years ay ON ay.id = s.academic_year_id
             ORDER BY ay.start_date DESC, s.sequence DESC LIMIT 1'
        );
    }

    public function courses(bool $demo = false, ?int $departmentId = null, ?int $programId = null, bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*, d.code AS department_code, p.code AS program_code
                FROM courses c
                JOIN departments d ON d.id = c.department_id
                LEFT JOIN programs p ON p.id = c.program_id
                WHERE c.is_demo = ?';
        $params = [$demo ? 1 : 0];
        if ($departmentId) {
            $sql .= ' AND c.department_id = ?';
            $params[] = $departmentId;
        }
        if ($programId) {
            $sql .= ' AND c.program_id = ?';
            $params[] = $programId;
        }
        if ($activeOnly) {
            $sql .= " AND c.status = 'active'";
        }
        $sql .= ' ORDER BY c.code';
        return Database::fetchAll($sql, $params);
    }

    public function sections(bool $demo = false, ?int $programId = null, bool $activeOnly = false): array
    {
        $sql = 'SELECT s.*, p.code AS program_code, p.name AS program_name, d.code AS department_code
                FROM sections s
                JOIN programs p ON p.id = s.program_id
                JOIN departments d ON d.id = p.department_id
                WHERE s.is_demo = ?';
        $params = [$demo ? 1 : 0];
        if ($programId) {
            $sql .= ' AND s.program_id = ?';
            $params[] = $programId;
        }
        if ($activeOnly) {
            $sql .= " AND s.status = 'active'";
        }
        $sql .= ' ORDER BY s.code';
        return Database::fetchAll($sql, $params);
    }

    public function schedules(bool $demo = false): array
    {
        return Database::fetchAll(
            'SELECT cs.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                    u.first_name, u.last_name, ay.name AS academic_year_name, sem.name AS semester_name
             FROM class_schedules cs
             JOIN courses c ON c.id = cs.course_id
             JOIN sections sec ON sec.id = cs.section_id
             JOIN users u ON u.id = cs.instructor_id
             JOIN academic_years ay ON ay.id = cs.academic_year_id
             JOIN semesters sem ON sem.id = cs.semester_id
             WHERE cs.is_demo = ? AND cs.status = "active"
             ORDER BY ay.start_date DESC, c.code, cs.start_time',
            [$demo ? 1 : 0]
        );
    }

    public function instructorAssignments(bool $demo = false, ?int $instructorId = null): array
    {
        $sql = 'SELECT ia.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                       u.first_name, u.last_name, ay.name AS academic_year_name, sem.name AS semester_name
                FROM instructor_assignments ia
                JOIN courses c ON c.id = ia.course_id
                JOIN sections sec ON sec.id = ia.section_id
                JOIN users u ON u.id = ia.instructor_id
                JOIN academic_years ay ON ay.id = ia.academic_year_id
                JOIN semesters sem ON sem.id = ia.semester_id
                WHERE ia.is_demo = ? AND ia.status = "active"';
        $params = [$demo ? 1 : 0];
        if ($instructorId) {
            $sql .= ' AND ia.instructor_id = ?';
            $params[] = $instructorId;
        }
        $sql .= ' ORDER BY ay.start_date DESC, c.code';
        return Database::fetchAll($sql, $params);
    }

    public function enrollments(bool $demo = false, ?int $sectionId = null, ?int $studentId = null): array
    {
        $sql = 'SELECT e.*, u.first_name, u.last_name, u.student_number, c.code AS course_code,
                       sec.code AS section_code, ay.name AS academic_year_name, sem.name AS semester_name
                FROM student_enrollments e
                JOIN users u ON u.id = e.student_id
                JOIN courses c ON c.id = e.course_id
                JOIN sections sec ON sec.id = e.section_id
                JOIN academic_years ay ON ay.id = e.academic_year_id
                JOIN semesters sem ON sem.id = e.semester_id
                WHERE e.is_demo = ?';
        $params = [$demo ? 1 : 0];
        if ($sectionId) {
            $sql .= ' AND e.section_id = ?';
            $params[] = $sectionId;
        }
        if ($studentId) {
            $sql .= ' AND e.student_id = ?';
            $params[] = $studentId;
        }
        $sql .= ' ORDER BY ay.start_date DESC, u.last_name, u.first_name';
        return Database::fetchAll($sql, $params);
    }

    public function departmentOptions(bool $demo): array
    {
        return Database::fetchAll('SELECT id, code, name FROM departments WHERE is_demo = ? ORDER BY name', [$demo ? 1 : 0]);
    }

    public function programOptions(bool $demo): array
    {
        return Database::fetchAll('SELECT id, code, name, department_id FROM programs WHERE is_demo = ? ORDER BY code', [$demo ? 1 : 0]);
    }

    public function courseOptions(bool $demo): array
    {
        return Database::fetchAll('SELECT id, code, title, department_id, program_id FROM courses WHERE is_demo = ? ORDER BY code', [$demo ? 1 : 0]);
    }

    public function sectionOptions(bool $demo): array
    {
        return Database::fetchAll('SELECT id, code, program_id, year_level FROM sections WHERE is_demo = ? ORDER BY code', [$demo ? 1 : 0]);
    }

    public function academicYearOptions(): array
    {
        return Database::fetchAll('SELECT id, name, is_current FROM academic_years ORDER BY start_date DESC');
    }

    public function semesterOptions(?int $yearId = null): array
    {
        if ($yearId) {
            return Database::fetchAll('SELECT id, name, academic_year_id, sequence, is_current FROM semesters WHERE academic_year_id = ? ORDER BY sequence', [$yearId]);
        }
        return Database::fetchAll('SELECT id, name, academic_year_id, sequence, is_current FROM semesters ORDER BY academic_year_id DESC, sequence');
    }

    public function paginate(string $entity, bool $demo, int $page = 1, int $perPage = 15): array
    {
        $definitions = [
            'departments' => [
                'table' => 'departments',
                'select' => 'd.*, (SELECT COUNT(*) FROM programs p WHERE p.department_id = d.id) AS child_count',
                'from' => 'departments d',
                'where' => 'd.is_demo = ?',
                'params' => [$demo ? 1 : 0],
                'order' => 'd.name',
            ],
            'programs' => [
                'table' => 'programs',
                'select' => 'p.*, d.code AS parent_code, d.name AS parent_name',
                'from' => 'programs p JOIN departments d ON d.id = p.department_id',
                'where' => 'p.is_demo = ?',
                'params' => [$demo ? 1 : 0],
                'order' => 'p.code',
            ],
            'courses' => [
                'table' => 'courses',
                'select' => 'c.*, d.code AS parent_code, p.code AS program_code',
                'from' => 'courses c JOIN departments d ON d.id = c.department_id LEFT JOIN programs p ON p.id = c.program_id',
                'where' => 'c.is_demo = ?',
                'params' => [$demo ? 1 : 0],
                'order' => 'c.code',
            ],
            'sections' => [
                'table' => 'sections',
                'select' => 's.*, p.code AS parent_code, p.name AS program_name',
                'from' => 'sections s JOIN programs p ON p.id = s.program_id',
                'where' => 's.is_demo = ?',
                'params' => [$demo ? 1 : 0],
                'order' => 's.code',
            ],
            'academic_years' => [
                'table' => 'academic_years',
                'select' => 'ay.*, (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id = ay.id) AS child_count',
                'from' => 'academic_years ay',
                'where' => '1 = 1',
                'params' => [],
                'order' => 'ay.start_date DESC',
            ],
            'semesters' => [
                'table' => 'semesters',
                'select' => 'sem.*, ay.name AS parent_name',
                'from' => 'semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id',
                'where' => '1 = 1',
                'params' => [],
                'order' => 'ay.start_date DESC, sem.sequence',
            ],
        ];

        $def = $definitions[$entity] ?? null;
        if (!$def) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 1];
        }

        $total = (int) Database::scalar('SELECT COUNT(*) FROM ' . $def['from'] . ' WHERE ' . $def['where'], $def['params'], 0);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT ' . $def['select'] . ' FROM ' . $def['from'] . ' WHERE ' . $def['where']
            . ' ORDER BY ' . $def['order'] . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        return [
            'rows' => Database::fetchAll($sql, $def['params']),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
