<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Setting;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Academic;
use App\Models\User;

/**
 * Generic, definition-driven management of academic structure. Definitions are
 * declared server-side, so an unknown {entity} can never reach arbitrary SQL.
 */
final class AcademicController extends BaseController
{
    private Academic $academic;
    private User $users;

    public function __construct()
    {
        $this->academic = new Academic();
        $this->users = new User();
    }

    public function index(string $entity): void
    {
        $definition = $this->definition($entity);
        $where = $definition['where'];
        $params = $definition['params'];
        $search = trim((string) $this->input('search', ''));
        if ($search !== '' && !empty($definition['search'])) {
            $parts = [];
            foreach ($definition['search'] as $column) {
                $parts[] = $column . ' LIKE ?';
                $params[] = '%' . $search . '%';
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM ' . $definition['from'] . ' WHERE ' . $where,
            $params,
            0
        );
        $page = $this->page();
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT ' . $definition['select'] . ' FROM ' . $definition['from']
            . ' WHERE ' . $where . ' ORDER BY ' . $definition['order']
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        foreach ($rows as &$row) {
            if (isset($definition['decorate'])) {
                $row = $definition['decorate']($row);
            }
        }
        unset($row);

        $this->view('dean/academic/index', [
            'pageTitle' => $definition['title'],
            'entity' => $entity,
            'definition' => $definition,
            'pagination' => [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
            'search' => $search,
            'demo' => $this->demo(),
        ]);
    }

    public function create(string $entity): void
    {
        $definition = $this->definition($entity);
        $this->view('dean/academic/form', [
            'pageTitle' => 'New ' . $definition['singular'],
            'entity' => $entity,
            'definition' => $definition,
            'record' => null,
            'fields' => $this->formFields($definition, null),
            'demo' => $this->demo(),
        ]);
    }

    public function store(string $entity): void
    {
        $this->requirePost();
        $definition = $this->definition($entity);
        $data = $this->validated($definition, null);
        $data = $this->applyScope($definition, $data);

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $definition['table'] . ' (' . implode(', ', $columns)
            . ($definition['timestamps'] ? ', created_at, updated_at' : '')
            . ') VALUES (' . $placeholders . ($definition['timestamps'] ? ', NOW(), NOW()' : '') . ')';
        try {
            $id = Database::insert($sql, array_values($data));
        } catch (\Throwable $e) {
            Session::set('_old', $this->inputAll($definition));
            $this->error('The record could not be saved. Check for duplicate codes or invalid references.', 'dean/academic/' . $entity . '/create');
        }
        $this->afterSave($definition, $id, $data);
        $this->audit($definition['table'] . '.create', $definition['table'], $id, 'Created ' . $definition['singular']);
        if ($entity === 'enrollments') {
            $this->success($definition['singular'] . ' added. The student can now be included when QR sessions are created.', 'dean/academic/' . $entity);
        }
        $this->success($definition['singular'] . ' created.', 'dean/academic/' . $entity);
    }

    public function edit(string $entity, string $id): void
    {
        $definition = $this->definition($entity);
        $record = Database::fetch('SELECT * FROM ' . $definition['table'] . ' WHERE id = ? LIMIT 1', [(int) $id]);
        if (!$record || ($definition['scoped'] && (int) $record['is_demo'] !== ($this->demo() ? 1 : 0))) {
            abort(404);
        }
        $this->view('dean/academic/form', [
            'pageTitle' => 'Edit ' . $definition['singular'],
            'entity' => $entity,
            'definition' => $definition,
            'record' => $record,
            'fields' => $this->formFields($definition, $record),
            'demo' => $this->demo(),
        ]);
    }

    public function update(string $entity, string $id): void
    {
        $this->requirePost();
        $definition = $this->definition($entity);
        $id = (int) $id;
        $record = Database::fetch('SELECT * FROM ' . $definition['table'] . ' WHERE id = ? LIMIT 1', [$id]);
        if (!$record || ($definition['scoped'] && (int) $record['is_demo'] !== ($this->demo() ? 1 : 0))) {
            abort(404);
        }
        $data = $this->validated($definition, $id);
        unset($data['is_demo']);
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = ?';
        }
        if ($definition['timestamps']) {
            $sets[] = 'updated_at = NOW()';
        }
        try {
            Database::execute(
                'UPDATE ' . $definition['table'] . ' SET ' . implode(', ', $sets) . ' WHERE id = ?',
                array_merge(array_values($data), [$id])
            );
        } catch (\Throwable $e) {
            Session::set('_old', $this->inputAll($definition));
            $this->error('The changes could not be saved. Check for duplicate codes or invalid references.', 'dean/academic/' . $entity . '/' . $id . '/edit');
        }
        $this->afterSave($definition, $id, $data);
        $this->audit($definition['table'] . '.update', $definition['table'], $id, 'Updated ' . $definition['singular']);
        $this->success($definition['singular'] . ' updated.', 'dean/academic/' . $entity);
    }

    public function destroy(string $entity, string $id): void
    {
        $this->requirePost();
        $definition = $this->definition($entity);
        $id = (int) $id;
        $record = Database::fetch('SELECT * FROM ' . $definition['table'] . ' WHERE id = ? LIMIT 1', [$id]);
        if (!$record || ($definition['scoped'] && (int) $record['is_demo'] !== ($this->demo() ? 1 : 0))) {
            abort(404);
        }
        if (isset($definition['guard'])) {
            $block = $definition['guard']($id, $record);
            if ($block !== null) {
                $this->error($block, 'dean/academic/' . $entity);
            }
        }
        try {
            Database::execute('DELETE FROM ' . $definition['table'] . ' WHERE id = ?', [$id]);
        } catch (\Throwable $e) {
            $this->error('This record is still referenced by other data and cannot be deleted.', 'dean/academic/' . $entity);
        }
        $this->audit($definition['table'] . '.delete', $definition['table'], $id, 'Deleted ' . $definition['singular']);
        $this->success($definition['singular'] . ' deleted.', 'dean/academic/' . $entity);
    }

    private function definition(string $entity): array
    {
        // Route segments are kebab-case; accept underscores too for robustness.
        $entity = str_replace('_', '-', strtolower(trim($entity)));
        $aliases = [
            'class-schedules' => 'schedules',
            'student-enrollments' => 'enrollments',
            'teaching-loads' => 'teaching-assignments',
        ];
        $entity = $aliases[$entity] ?? $entity;

        $demo = $this->demo();
        $scope = $demo ? 1 : 0;
        $departments = $this->pairs($this->academic->departments($demo, true), 'id', static fn ($r) => $r['code'] . ' — ' . $r['name']);
        $programs = $this->pairs($this->academic->programs($demo, null, true), 'id', static fn ($r) => $r['code'] . ' — ' . $r['name']);
        $courses = $this->pairs($this->academic->courses($demo, null, null, true), 'id', static fn ($r) => $r['code'] . ' — ' . $r['title']);
        $sections = $this->pairs($this->academic->sections($demo, null, true), 'id', static fn ($r) => $r['code'] . ' — ' . ($r['program_code'] ?? ''));
        $instructors = $this->pairs($this->users->instructors($demo), 'id', static fn ($r) => trim($r['last_name'] . ', ' . $r['first_name']));
        $students = $this->pairs($this->users->students($demo), 'id', static fn ($r) => trim($r['last_name'] . ', ' . $r['first_name']) . ' (' . $r['student_number'] . ')');
        $years = $this->pairs($this->academic->academicYearOptions(), 'id', static fn ($r) => $r['name']);
        $semesters = $this->pairs($this->academic->semesterOptions(), 'id', static fn ($r) => $r['name'] . ' — ' . ($r['academic_year_id'] ? 'AY #' . $r['academic_year_id'] : ''));
        $heads = $this->pairs(
            Database::fetchAll(
                "SELECT u.id, u.first_name, u.last_name, r.slug FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.slug IN ('dean','academic_head','instructor') AND u.is_demo = ? AND u.status = 'active'
                 ORDER BY u.last_name",
                [$scope]
            ),
            'id',
            static fn ($r) => trim($r['last_name'] . ', ' . $r['first_name'])
        );

        $statusField = ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ]];

        $definitions = [
            'departments' => [
                'title' => 'Departments', 'singular' => 'Department', 'table' => 'departments',
                'select' => 'd.*, TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) AS head_name,
                             (SELECT COUNT(*) FROM programs p2 WHERE p2.department_id = d.id) AS program_count,
                             (SELECT COUNT(*) FROM courses c2 WHERE c2.department_id = d.id) AS course_count',
                'from' => 'departments d LEFT JOIN users u ON u.id = d.head_user_id',
                'where' => 'd.is_demo = ?', 'params' => [$scope], 'search' => ['d.code', 'd.name'],
                'order' => 'd.name', 'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Code', 'key' => 'code'], ['label' => 'Department', 'key' => 'name'],
                    ['label' => 'Head', 'key' => 'head_name'], ['label' => 'Programs', 'key' => 'program_count'],
                    ['label' => 'Courses', 'key' => 'course_count'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'code' => ['label' => 'Department code', 'type' => 'text', 'required' => true, 'max' => 20, 'col' => 4],
                    'name' => ['label' => 'Department name', 'type' => 'text', 'required' => true, 'max' => 150, 'col' => 8],
                    'head_user_id' => ['label' => 'Department head', 'type' => 'select', 'options' => $heads, 'nullable' => true, 'col' => 6, 'empty' => 'No head assigned'],
                    'status' => array_merge($statusField, ['col' => 6]),
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM programs WHERE department_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM courses WHERE department_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM users WHERE department_id = ?', [$id], 0);
                    return $refs > 0 ? 'Move or remove the linked programs, courses and users before deleting this department.' : null;
                },
            ],
            'programs' => [
                'title' => 'Programs', 'singular' => 'Program', 'table' => 'programs',
                'select' => 'p.*, d.code AS department_code, d.name AS department_name,
                             (SELECT COUNT(*) FROM sections s2 WHERE s2.program_id = p.id) AS section_count',
                'from' => 'programs p JOIN departments d ON d.id = p.department_id',
                'where' => 'p.is_demo = ?', 'params' => [$scope], 'search' => ['p.code', 'p.name', 'd.code'],
                'order' => 'p.code', 'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Code', 'key' => 'code'], ['label' => 'Program', 'key' => 'name'],
                    ['label' => 'Department', 'key' => 'department_code'], ['label' => 'Sections', 'key' => 'section_count'],
                    ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'department_id' => ['label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $departments, 'col' => 6],
                    'code' => ['label' => 'Program code', 'type' => 'text', 'required' => true, 'max' => 30, 'col' => 3],
                    'status' => array_merge($statusField, ['col' => 3]),
                    'name' => ['label' => 'Program name', 'type' => 'text', 'required' => true, 'max' => 180, 'col' => 12],
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM sections WHERE program_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM courses WHERE program_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM users WHERE program_id = ?', [$id], 0);
                    return $refs > 0 ? 'Remove the linked sections, courses and users first.' : null;
                },
            ],
            'academic-years' => [
                'title' => 'Academic Years', 'singular' => 'Academic Year', 'table' => 'academic_years',
                'select' => 'ay.*, (SELECT COUNT(*) FROM semesters s2 WHERE s2.academic_year_id = ay.id) AS semester_count',
                'from' => 'academic_years ay', 'where' => '1 = 1', 'params' => [], 'search' => ['ay.name'],
                'order' => 'ay.start_date DESC, ay.name DESC', 'scoped' => false, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Academic year', 'key' => 'name'], ['label' => 'Start', 'key' => 'start_date', 'type' => 'date'],
                    ['label' => 'End', 'key' => 'end_date', 'type' => 'date'], ['label' => 'Semesters', 'key' => 'semester_count'],
                    ['label' => 'Current', 'key' => 'is_current', 'type' => 'current'],
                ],
                'fields' => [
                    'name' => ['label' => 'Academic year', 'type' => 'text', 'required' => true, 'max' => 30, 'col' => 4, 'placeholder' => '2026-2027'],
                    'start_date' => ['label' => 'Start date', 'type' => 'date', 'col' => 3],
                    'end_date' => ['label' => 'End date', 'type' => 'date', 'col' => 3],
                    'is_current' => ['label' => 'Set as current academic year', 'type' => 'checkbox', 'col' => 2],
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM semesters WHERE academic_year_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM evaluation_assignments WHERE academic_year_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM class_schedules WHERE academic_year_id = ?', [$id], 0);
                    return $refs > 0 ? 'This academic year has semesters, schedules or evaluation records and cannot be deleted.' : null;
                },
            ],
            'semesters' => [
                'title' => 'Semesters', 'singular' => 'Semester', 'table' => 'semesters',
                'select' => 'sem.*, ay.name AS academic_year_name',
                'from' => 'semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id',
                'where' => '1 = 1', 'params' => [], 'search' => ['sem.name', 'ay.name'],
                'order' => 'ay.start_date DESC, sem.sequence', 'scoped' => false, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Semester', 'key' => 'name'], ['label' => 'Academic year', 'key' => 'academic_year_name'],
                    ['label' => 'Sequence', 'key' => 'sequence'], ['label' => 'Current', 'key' => 'is_current', 'type' => 'current'],
                ],
                'fields' => [
                    'academic_year_id' => ['label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $years, 'col' => 5],
                    'name' => ['label' => 'Semester name', 'type' => 'text', 'required' => true, 'max' => 40, 'col' => 4],
                    'sequence' => ['label' => 'Sequence', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 9, 'col' => 3],
                    'start_date' => ['label' => 'Start date', 'type' => 'date', 'col' => 3],
                    'end_date' => ['label' => 'End date', 'type' => 'date', 'col' => 3],
                    'is_current' => ['label' => 'Set as current semester', 'type' => 'checkbox', 'col' => 3],
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_assignments WHERE semester_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM class_schedules WHERE semester_id = ?', [$id], 0);
                    return $refs > 0 ? 'This semester has schedules or evaluation records and cannot be deleted.' : null;
                },
            ],
            'courses' => [
                'title' => 'Courses', 'singular' => 'Course', 'table' => 'courses',
                'select' => 'c.*, d.code AS department_code, p.code AS program_code',
                'from' => 'courses c JOIN departments d ON d.id = c.department_id LEFT JOIN programs p ON p.id = c.program_id',
                'where' => 'c.is_demo = ?', 'params' => [$scope], 'search' => ['c.code', 'c.title', 'd.code'],
                'order' => 'c.code', 'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Code', 'key' => 'code'], ['label' => 'Course', 'key' => 'title'],
                    ['label' => 'Department', 'key' => 'department_code'], ['label' => 'Program', 'key' => 'program_code'],
                    ['label' => 'Units', 'key' => 'units'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'department_id' => ['label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $departments, 'col' => 5],
                    'program_id' => ['label' => 'Program', 'type' => 'select', 'options' => $programs, 'nullable' => true, 'col' => 4, 'empty' => 'General education / no program'],
                    'code' => ['label' => 'Course code', 'type' => 'text', 'required' => true, 'max' => 30, 'col' => 3],
                    'title' => ['label' => 'Course title', 'type' => 'text', 'required' => true, 'max' => 180, 'col' => 9],
                    'units' => ['label' => 'Units', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 12, 'step' => '0.5', 'col' => 3],
                    'status' => array_merge($statusField, ['col' => 3]),
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12, 'rows' => 3],
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_assignments WHERE course_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM student_enrollments WHERE course_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM class_schedules WHERE course_id = ?', [$id], 0);
                    return $refs > 0 ? 'This course is used by schedules, enrollments or evaluations and cannot be deleted.' : null;
                },
            ],
            'sections' => [
                'title' => 'Sections', 'singular' => 'Section', 'table' => 'sections',
                'select' => 's.*, p.code AS program_code, p.name AS program_name',
                'from' => 'sections s JOIN programs p ON p.id = s.program_id',
                'where' => 's.is_demo = ?', 'params' => [$scope], 'search' => ['s.code', 's.name', 'p.code'],
                'order' => 's.code', 'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Code', 'key' => 'code'], ['label' => 'Name', 'key' => 'name'],
                    ['label' => 'Program', 'key' => 'program_code'], ['label' => 'Year level', 'key' => 'year_level'],
                    ['label' => 'Capacity', 'key' => 'capacity'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'program_id' => ['label' => 'Program', 'type' => 'select', 'required' => true, 'options' => $programs, 'col' => 6],
                    'code' => ['label' => 'Section code', 'type' => 'text', 'required' => true, 'max' => 30, 'col' => 3],
                    'year_level' => ['label' => 'Year level', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 5, 'col' => 3],
                    'name' => ['label' => 'Display name', 'type' => 'text', 'max' => 100, 'col' => 6],
                    'capacity' => ['label' => 'Capacity', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 500, 'col' => 3],
                    'status' => array_merge($statusField, ['col' => 3]),
                ],
                'guard' => static function (int $id): ?string {
                    $refs = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_assignments WHERE section_id = ?', [$id], 0)
                        + (int) Database::scalar('SELECT COUNT(*) FROM student_enrollments WHERE section_id = ?', [$id], 0);
                    return $refs > 0 ? 'This section is used by enrollments or evaluations and cannot be deleted.' : null;
                },
            ],
            'schedules' => [
                'title' => 'Class Schedules', 'singular' => 'Class Schedule', 'table' => 'class_schedules',
                'select' => 'cs.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                             TRIM(CONCAT(i.first_name, " ", i.last_name)) AS instructor_name,
                             ay.name AS academic_year_name, sem.name AS semester_name',
                'from' => 'class_schedules cs JOIN courses c ON c.id = cs.course_id
                           JOIN sections sec ON sec.id = cs.section_id JOIN users i ON i.id = cs.instructor_id
                           JOIN academic_years ay ON ay.id = cs.academic_year_id JOIN semesters sem ON sem.id = cs.semester_id',
                'where' => 'cs.is_demo = ?', 'params' => [$scope],
                'search' => ['c.code', 'c.title', 'sec.code', 'i.last_name', 'cs.room'],
                'order' => 'FIELD(cs.day_of_week, "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), cs.start_time',
                'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Course', 'key' => 'course_code'], ['label' => 'Section', 'key' => 'section_code'],
                    ['label' => 'Instructor', 'key' => 'instructor_name'], ['label' => 'Day', 'key' => 'day_of_week'],
                    ['label' => 'Time', 'key' => 'time_range'], ['label' => 'Room', 'key' => 'room'],
                    ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'decorate' => static function (array $row): array {
                    $row['time_range'] = date('g:i A', strtotime((string) $row['start_time'])) . '–' . date('g:i A', strtotime((string) $row['end_time']));
                    return $row;
                },
                'fields' => [
                    'course_id' => ['label' => 'Course', 'type' => 'select', 'required' => true, 'options' => $courses, 'col' => 6],
                    'section_id' => ['label' => 'Section', 'type' => 'select', 'required' => true, 'options' => $sections, 'col' => 6],
                    'instructor_id' => ['label' => 'Instructor', 'type' => 'select', 'required' => true, 'options' => $instructors, 'col' => 6],
                    'academic_year_id' => ['label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $years, 'col' => 3],
                    'semester_id' => ['label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesters, 'col' => 3],
                    'day_of_week' => ['label' => 'Day of week', 'type' => 'select', 'required' => true, 'col' => 3, 'options' => $this->dayOptions()],
                    'room' => ['label' => 'Room', 'type' => 'text', 'max' => 80, 'col' => 3],
                    'start_time' => ['label' => 'Start time', 'type' => 'time', 'required' => true, 'col' => 3],
                    'end_time' => ['label' => 'End time', 'type' => 'time', 'required' => true, 'col' => 3],
                    'status' => array_merge($statusField, ['col' => 3]),
                ],
            ],
            'teaching-assignments' => [
                'title' => 'Instructor Assignments', 'singular' => 'Instructor Assignment', 'table' => 'instructor_assignments',
                'select' => 'ia.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                             TRIM(CONCAT(i.first_name, " ", i.last_name)) AS instructor_name,
                             ay.name AS academic_year_name, sem.name AS semester_name',
                'from' => 'instructor_assignments ia JOIN courses c ON c.id = ia.course_id
                           JOIN sections sec ON sec.id = ia.section_id JOIN users i ON i.id = ia.instructor_id
                           JOIN academic_years ay ON ay.id = ia.academic_year_id JOIN semesters sem ON sem.id = ia.semester_id',
                'where' => 'ia.is_demo = ?', 'params' => [$scope],
                'search' => ['c.code', 'sec.code', 'i.last_name', 'ay.name'],
                'order' => 'ay.start_date DESC, c.code', 'scoped' => true, 'timestamps' => true,
                'columns' => [
                    ['label' => 'Instructor', 'key' => 'instructor_name'], ['label' => 'Course', 'key' => 'course_code'],
                    ['label' => 'Section', 'key' => 'section_code'], ['label' => 'Academic year', 'key' => 'academic_year_name'],
                    ['label' => 'Semester', 'key' => 'semester_name'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'instructor_id' => ['label' => 'Instructor', 'type' => 'select', 'required' => true, 'options' => $instructors, 'col' => 6],
                    'course_id' => ['label' => 'Course', 'type' => 'select', 'required' => true, 'options' => $courses, 'col' => 6],
                    'section_id' => ['label' => 'Section', 'type' => 'select', 'required' => true, 'options' => $sections, 'col' => 4],
                    'academic_year_id' => ['label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $years, 'col' => 4],
                    'semester_id' => ['label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesters, 'col' => 4],
                    'status' => array_merge($statusField, ['col' => 12]),
                ],
            ],
            'enrollments' => [
                'title' => 'Student Enrollments', 'singular' => 'Enrollment', 'table' => 'student_enrollments',
                'select' => 'e.*, u.student_number, TRIM(CONCAT(u.last_name, ", ", u.first_name)) AS student_name,
                             c.code AS course_code, sec.code AS section_code, ay.name AS academic_year_name, sem.name AS semester_name',
                'from' => 'student_enrollments e JOIN users u ON u.id = e.student_id
                           JOIN courses c ON c.id = e.course_id JOIN sections sec ON sec.id = e.section_id
                           JOIN academic_years ay ON ay.id = e.academic_year_id JOIN semesters sem ON sem.id = e.semester_id',
                'where' => 'e.is_demo = ?', 'params' => [$scope],
                'search' => ['u.last_name', 'u.first_name', 'u.student_number', 'c.code', 'sec.code'],
                'order' => 'u.last_name, u.first_name, c.code', 'scoped' => true, 'timestamps' => false,
                'columns' => [
                    ['label' => 'Student', 'key' => 'student_name'], ['label' => 'Student no.', 'key' => 'student_number'],
                    ['label' => 'Course', 'key' => 'course_code'], ['label' => 'Section', 'key' => 'section_code'],
                    ['label' => 'Academic year', 'key' => 'academic_year_name'], ['label' => 'Semester', 'key' => 'semester_name'],
                    ['label' => 'Status', 'key' => 'status', 'type' => 'status'],
                ],
                'fields' => [
                    'student_id' => ['label' => 'Student', 'type' => 'select', 'required' => true, 'options' => $students, 'col' => 6],
                    'course_id' => ['label' => 'Course', 'type' => 'select', 'required' => true, 'options' => $courses, 'col' => 6],
                    'section_id' => ['label' => 'Section', 'type' => 'select', 'required' => true, 'options' => $sections, 'col' => 4],
                    'academic_year_id' => ['label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $years, 'col' => 4],
                    'semester_id' => ['label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesters, 'col' => 4],
                    'status' => ['label' => 'Enrollment status', 'type' => 'select', 'required' => true, 'col' => 12, 'options' => [
                        ['value' => 'enrolled', 'label' => 'Enrolled'],
                        ['value' => 'dropped', 'label' => 'Dropped'],
                        ['value' => 'completed', 'label' => 'Completed'],
                    ]],
                ],
            ],
        ];

        if (!isset($definitions[$entity])) {
            abort(404, 'Unknown academic module.');
        }
        return $definitions[$entity];
    }

    private function formFields(array $definition, ?array $record): array
    {
        $fields = [];
        foreach ($definition['fields'] as $name => $config) {
            $value = old($name, $record[$name] ?? ($config['type'] === 'checkbox' ? 0 : ''));
            if ($record === null && $value === '' && isset($config['default'])) {
                $value = $config['default'];
            }
            if ($name === 'sequence' && $record === null && $value === '') {
                $value = 1;
            }
            if ($name === 'capacity' && $record === null && $value === '') {
                $value = 45;
            }
            if ($name === 'units' && $record === null && $value === '') {
                $value = 3;
            }
            if ($name === 'status' && $record === null && $value === '') {
                $value = 'active';
            }
            $config['value'] = $value;
            $config['name'] = $name;
            $fields[] = $config;
        }
        return $fields;
    }

    private function validated(array $definition, ?int $id): array
    {
        $data = [];
        $rules = [];
        foreach ($definition['fields'] as $name => $config) {
            if ($config['type'] === 'checkbox') {
                $data[$name] = $this->input($name) ? 1 : 0;
                continue;
            }
            $value = $this->input($name, '');
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === '' && !empty($config['nullable'])) {
                $value = null;
            }
            $data[$name] = $value;

            $rule = [];
            $rule[] = !empty($config['required']) ? 'required' : 'nullable';
            if (($config['type'] === 'text' || $config['type'] === 'textarea') && !empty($config['max'])) {
                $rule[] = 'max:' . $config['max'];
            }
            if ($config['type'] === 'number') {
                $rule[] = 'numeric';
                if (isset($config['min'])) {
                    $rule[] = 'min:' . $config['min'];
                }
                if (isset($config['max'])) {
                    $rule[] = 'max:' . $config['max'];
                }
            }
            if ($config['type'] === 'date') {
                $rule[] = 'date';
            }
            if ($config['type'] === 'select' && !empty($config['options'])) {
                $allowed = array_map(static fn ($o) => (string) $o['value'], $config['options']);
                if (!empty($config['nullable'])) {
                    $allowed[] = '';
                }
                $rule[] = 'in:' . implode(',', $allowed);
            }
            if (in_array($config['type'], ['date', 'time'], true) && $value === null) {
                $rule = ['nullable'];
            }
            $rules[$name] = implode('|', $rule);
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            Session::set('_old', $this->inputAll($definition));
            $back = $id ? 'dean/academic/' . $definition['table'] . '/' . $id . '/edit' : 'dean/academic/' . $definition['table'] . '/create';
            $this->error((string) $validator->firstError(), $back);
        }
        $clean = $validator->validated();
        foreach ($definition['fields'] as $name => $config) {
            if ($config['type'] === 'checkbox') {
                $clean[$name] = $data[$name];
            }
        }

        if ($definition['table'] === 'class_schedules') {
            if (strtotime((string) $clean['end_time']) <= strtotime((string) $clean['start_time'])) {
                Session::set('_old', $this->inputAll($definition));
                $this->error('The class end time must be after the start time.', 'dean/academic/schedules/' . ($id ? $id . '/edit' : 'create'));
            }
            $year = Database::scalar('SELECT academic_year_id FROM semesters WHERE id = ?', [(int) $clean['semester_id']]);
            if ((int) $year !== (int) $clean['academic_year_id']) {
                Session::set('_old', $this->inputAll($definition));
                $this->error('The selected semester does not belong to the selected academic year.', 'dean/academic/schedules/' . ($id ? $id . '/edit' : 'create'));
            }
        }
        if ($definition['table'] === 'student_enrollments') {
            $year = Database::scalar('SELECT academic_year_id FROM semesters WHERE id = ?', [(int) $clean['semester_id']]);
            if ((int) $year !== (int) $clean['academic_year_id']) {
                Session::set('_old', $this->inputAll($definition));
                $this->error('The selected semester does not belong to the selected academic year.', 'dean/academic/enrollments/' . ($id ? $id . '/edit' : 'create'));
            }
        }
        return $clean;
    }

    private function applyScope(array $definition, array $data): array
    {
        if ($definition['scoped']) {
            $data['is_demo'] = $this->demo() ? 1 : 0;
        } else {
            unset($data['is_demo']);
        }
        return $data;
    }

    private function afterSave(array $definition, int $id, array $data): void
    {
        if ($definition['table'] === 'academic_years' && !empty($data['is_current'])) {
            Database::execute('UPDATE academic_years SET is_current = 0 WHERE id <> ?', [$id]);
            Setting::set('current_academic_year_id', (string) $id, 'academic', (int) Auth::id());
        }
        if ($definition['table'] === 'semesters' && !empty($data['is_current'])) {
            Database::execute('UPDATE semesters SET is_current = 0 WHERE id <> ?', [$id]);
            Setting::set('current_semester_id', (string) $id, 'academic', (int) Auth::id());
        }
    }

    private function pairs(array $rows, string $valueKey, callable $label): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['value' => (string) $row[$valueKey], 'label' => $label($row)];
        }
        return $out;
    }

    private function dayOptions(): array
    {
        $out = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $out[] = ['value' => $day, 'label' => $day];
        }
        return $out;
    }

    private function inputAll(array $definition): array
    {
        $data = [];
        foreach (array_keys($definition['fields']) as $name) {
            $data[$name] = $this->input($name, '');
        }
        return $data;
    }
}
