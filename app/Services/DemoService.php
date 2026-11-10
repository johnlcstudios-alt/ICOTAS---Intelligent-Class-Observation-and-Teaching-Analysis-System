<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Models\UAT;

/**
 * Optional fictional demonstration dataset. Every record is flagged is_demo and
 * is inaccessible from live-scope analytics. Reset removes only flagged demo
 * records; real institutional records are never deleted by this service.
 */
final class DemoService
{
    private array $roles = [];
    private string $passwordHash = '';
    private array $years = [];
    private array $semesters = [];
    private array $departments = [];
    private array $programs = [];
    private array $courses = [];
    private array $sections = [];
    private array $students = [];
    private array $instructors = [];

    public function seed(): array
    {
        @set_time_limit(300);
        $this->passwordHash = password_hash('Demo@12345', PASSWORD_DEFAULT);
        $stats = [
            'departments' => 0, 'programs' => 0, 'courses' => 0, 'sections' => 0,
            'instructors' => 0, 'students' => 0, 'sessions' => 0, 'responses' => 0,
            'uat_respondents' => 0,
        ];

        Database::transaction(function () use (&$stats) {
            $this->resetInternal();
            $this->loadRoles();
            $this->ensureAcademicPeriods();
            $this->createAcademicStructure($stats);
            $this->createUsers($stats);
            $this->createTeachingLoad($stats);
            $this->createDemoSurvey();
            $this->createSessionsAndResponses($stats);
            $this->createObservations();
            $this->createUat($stats);
            $this->createNotifications();
        });

        // Run after commit: these compute on the seeded records and write transparent results.
        try {
            (new PredictionService())->generateForAll(true);
            (new RecommendationService())->generateAll(true);
        } catch (\Throwable $e) {
            error_log('Demo prediction/recommendation generation: ' . $e->getMessage());
        }

        \App\Core\Setting::set('demo_mode', '1', 'demo', \App\Core\Auth::id());
        \App\Core\Setting::set('demo_generated_at', date('Y-m-d H:i:s'), 'demo', \App\Core\Auth::id());
        (new AuditService())->record('demo.seed', 'system', null, 'Generated the synthetic demonstration dataset');

        return $stats;
    }

    public function reset(): array
    {
        @set_time_limit(300);
        Database::transaction(function () {
            $this->resetInternal();
        });
        (new AuditService())->record('demo.reset', 'system', null, 'Removed the synthetic demonstration dataset');
        return ['removed' => true];
    }

    private function resetInternal(): void
    {
        $demoUsers = '(SELECT id FROM users WHERE is_demo = 1)';
        Database::execute('UPDATE departments SET head_user_id = NULL WHERE head_user_id IN ' . $demoUsers);
        Database::execute('DELETE FROM report_history WHERE requested_by IN ' . $demoUsers);
        Database::execute('DELETE FROM uat_surveys WHERE is_demo = 1');
        Database::execute('DELETE FROM class_observations WHERE is_demo = 1');
        Database::execute('DELETE FROM evaluation_sessions WHERE is_demo = 1');
        Database::execute('DELETE FROM evaluation_assignments WHERE is_demo = 1');
        Database::execute('DELETE FROM surveys WHERE is_demo = 1');
        Database::execute('DELETE FROM class_schedules WHERE is_demo = 1');
        Database::execute('DELETE FROM instructor_assignments WHERE is_demo = 1');
        Database::execute('DELETE FROM student_enrollments WHERE is_demo = 1');
        Database::execute('DELETE FROM courses WHERE is_demo = 1');
        Database::execute('DELETE FROM sections WHERE is_demo = 1');
        Database::execute('DELETE FROM programs WHERE is_demo = 1');
        Database::execute('DELETE FROM departments WHERE is_demo = 1');
        Database::execute('DELETE FROM users WHERE is_demo = 1');
        Database::execute("DELETE FROM notifications WHERE type = 'demo'");
    }

    private function loadRoles(): void
    {
        $roles = Database::fetchAll('SELECT id, slug FROM roles');
        foreach ($roles as $role) {
            $this->roles[$role['slug']] = (int) $role['id'];
        }
        foreach (['dean', 'academic_head', 'instructor', 'student'] as $slug) {
            if (!isset($this->roles[$slug])) {
                $id = Database::insert('INSERT INTO roles (slug, name) VALUES (?, ?)', [$slug, ucwords(str_replace('_', ' ', $slug))]);
                $this->roles[$slug] = (int) $id;
            }
        }
    }

    private function ensureAcademicPeriods(): void
    {
        $yearDefs = [
            ['2023-2024', '2023-08-01', '2024-06-30'],
            ['2024-2025', '2024-08-01', '2025-06-30'],
            ['2025-2026', '2025-08-01', '2026-06-30'],
            ['2026-2027', '2026-08-01', '2027-06-30'],
        ];
        foreach ($yearDefs as $position => $def) {
            $existing = Database::fetch('SELECT * FROM academic_years WHERE name = ?', [$def[0]]);
            if ($existing) {
                $id = (int) $existing['id'];
            } else {
                $id = (int) Database::insert(
                    'INSERT INTO academic_years (name, start_date, end_date, is_current, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())',
                    [$def[0], $def[1], $def[2]]
                );
            }
            $this->years[$def[0]] = $id;
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM academic_years WHERE is_current = 1', [], 0) === 0) {
            Database::execute('UPDATE academic_years SET is_current = 0');
            Database::execute('UPDATE academic_years SET is_current = 1 WHERE id = ?', [$this->years['2026-2027']]);
        }

        foreach ($this->years as $yearName => $yearId) {
            foreach ([['1st Semester', 1], ['2nd Semester', 2]] as $sem) {
                $existing = Database::fetch('SELECT id FROM semesters WHERE academic_year_id = ? AND name = ?', [$yearId, $sem[0]]);
                if ($existing) {
                    $sid = (int) $existing['id'];
                } else {
                    $sid = (int) Database::insert(
                        'INSERT INTO semesters (academic_year_id, name, sequence, start_date, end_date, is_current, created_at, updated_at)
                         VALUES (?, ?, ?, NULL, NULL, 0, NOW(), NOW())',
                        [$yearId, $sem[0], $sem[1]]
                    );
                }
                $this->semesters[$yearName . '|' . $sem[1]] = $sid;
            }
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM semesters WHERE is_current = 1', [], 0) === 0) {
            Database::execute('UPDATE semesters SET is_current = 0');
            Database::execute('UPDATE semesters SET is_current = 1 WHERE id = ?', [$this->semesters['2026-2027|1']]);
        }
    }

    private function createAcademicStructure(array &$stats): void
    {
        $departments = [
            'CCS' => 'College of Computer Studies',
            'CBA' => 'College of Business Administration',
            'COED' => 'College of Education',
            'CHTM' => 'College of Hospitality and Tourism Management',
        ];
        foreach ($departments as $key => $name) {
            $id = (int) Database::insert(
                'INSERT INTO departments (code, name, status, is_demo, created_at, updated_at) VALUES (?, ?, "active", 1, NOW(), NOW())',
                ['DM-' . $key, $name]
            );
            $this->departments[$key] = $id;
            $stats['departments']++;
        }

        $programs = [
            ['BSIT', 'Bachelor of Science in Information Technology', 'CCS'],
            ['BSCS', 'Bachelor of Science in Computer Science', 'CCS'],
            ['BSBA', 'Bachelor of Science in Business Administration', 'CBA'],
            ['BEED', 'Bachelor of Elementary Education', 'COED'],
            ['BSED', 'Bachelor of Secondary Education', 'COED'],
            ['BSHM', 'Bachelor of Science in Hospitality Management', 'CHTM'],
        ];
        foreach ($programs as $program) {
            $id = (int) Database::insert(
                'INSERT INTO programs (department_id, code, name, status, is_demo, created_at, updated_at) VALUES (?, ?, ?, "active", 1, NOW(), NOW())',
                [$this->departments[$program[2]], 'DM-' . $program[0], $program[1]]
            );
            $this->programs[$program[0]] = $id;
            $stats['programs']++;
        }

        $courses = [
            ['IT101', 'Introduction to Computing', 'CCS', 'BSIT'],
            ['IT102', 'Data Structures and Algorithms', 'CCS', 'BSIT'],
            ['CS101', 'Computer Programming 1', 'CCS', 'BSCS'],
            ['CS102', 'Discrete Mathematics', 'CCS', 'BSCS'],
            ['BA101', 'Principles of Management', 'CBA', 'BSBA'],
            ['BA102', 'Financial Accounting', 'CBA', 'BSBA'],
            ['ED101', 'Foundations of Education', 'COED', 'BEED'],
            ['ED102', 'Assessment in Learning', 'COED', 'BSED'],
            ['HM101', 'Introduction to Hospitality', 'CHTM', 'BSHM'],
            ['HM102', 'Food and Beverage Management', 'CHTM', 'BSHM'],
            ['GE101', 'Purposive Communication', 'CBA', 'BSBA'],
            ['GE102', 'Science, Technology and Society', 'CCS', 'BSIT'],
        ];
        foreach ($courses as $course) {
            $id = (int) Database::insert(
                'INSERT INTO courses (department_id, program_id, code, title, units, status, is_demo, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 3.0, "active", 1, NOW(), NOW())',
                [$this->departments[$course[2]], $this->programs[$course[3]], 'DM-' . $course[0], $course[1]]
            );
            $this->courses[$course[0]] = $id;
            $stats['courses']++;
        }

        $sections = [
            'IT-1A' => ['BSIT', 1], 'IT-2A' => ['BSIT', 2], 'CS-1A' => ['BSCS', 1], 'CS-2A' => ['BSCS', 2],
            'BA-1A' => ['BSBA', 1], 'BA-2A' => ['BSBA', 2], 'ED-1A' => ['BEED', 1], 'ED-2A' => ['BSED', 2],
            'HM-1A' => ['BSHM', 1],
        ];
        foreach ($sections as $code => $info) {
            $id = (int) Database::insert(
                'INSERT INTO sections (program_id, code, name, year_level, capacity, status, is_demo, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 45, "active", 1, NOW(), NOW())',
                [$this->programs[$info[0]], 'DM-' . $code, $code, $info[1]]
            );
            $this->sections[$code] = $id;
            $stats['sections']++;
        }
    }

    private function createUsers(array &$stats): void
    {
        $deanId = $this->createUser(
            'dean', 'demo.dean', 'dean.demo@aclc.test', 'Teresa', 'M.', 'Villareal', '1',
            'College Dean', null, null
        );
        $head1 = $this->createUser('academic_head', 'demo.head.ccs', 'head.ccs@aclc.test', 'Roberto', 'S.', 'Cruz', '2', null, $this->departments['CCS'], null);
        $head2 = $this->createUser('academic_head', 'demo.head.cba', 'head.cba@aclc.test', 'Cristina', 'P.', 'Ramos', '3', null, $this->departments['CBA'], null);

        $instructorDefs = [
            ['Maria', 'Santos', 'CCS', 'BSIT', 'IT101', 'IT-1A', 'improving'],
            ['Carlo', 'Reyes', 'CCS', 'BSIT', 'IT102', 'IT-2A', 'stable'],
            ['Ana', 'Dela Cruz', 'CCS', 'BSCS', 'CS101', 'CS-1A', 'high'],
            ['Miguel', 'Flores', 'CCS', 'BSCS', 'CS102', 'CS-2A', 'declining'],
            ['Liza', 'Mendoza', 'CBA', 'BSBA', 'BA101', 'BA-1A', 'stable'],
            ['Ramon', 'Villanueva', 'CBA', 'BSBA', 'BA102', 'BA-2A', 'high'],
            ['Grace', 'Navarro', 'COED', 'BEED', 'ED101', 'ED-1A', 'improving'],
            ['Paulo', 'Aquino', 'COED', 'BSED', 'ED102', 'ED-2A', 'declining'],
            ['Sofia', 'Lim', 'CHTM', 'BSHM', 'HM101', 'HM-1A', 'stable'],
            ['Daniel', 'Ocampo', 'CHTM', 'BSHM', 'HM102', 'HM-1A', 'low'],
            ['Elena', 'Bautista', 'CBA', 'BSBA', 'GE101', 'BA-1A', 'high'],
            ['Mark', 'Gonzales', 'CCS', 'BSIT', 'GE102', 'IT-1A', 'stable'],
        ];
        foreach ($instructorDefs as $index => $def) {
            $id = $this->createUser(
                'instructor',
                'demo.instructor.' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                strtolower($def[0] . '.' . $def[1]) . '@aclc.test',
                $def[0], '', $def[1],
                (string) (100 + $index + 1),
                'Instructor',
                $this->departments[$def[2]],
                $this->programs[$def[3]]
            );
            $this->instructors[] = [
                'id' => $id, 'first' => $def[0], 'last' => $def[1], 'dept' => $def[2],
                'program' => $def[3], 'course' => $def[4], 'section' => $def[5], 'profile' => $def[6],
            ];
            $stats['instructors']++;
        }

        $firstNames = ['Aaron', 'Bianca', 'Caleb', 'Diana', 'Ethan', 'Faith', 'Gabriel', 'Hazel', 'Ian', 'Jasmine', 'Kyle', 'Lorna', 'Mico', 'Nina', 'Oscar', 'Patricia', 'Quincy', 'Rhea', 'Samuel', 'Trisha'];
        $lastNames = ['Abalos', 'Bernales', 'Castro', 'Dizon', 'Estrada', 'Fajardo', 'Garcia', 'Hernandez', 'Ignacio', 'Javier', 'Katigbak', 'Lopez', 'Mercado', 'Navarro', 'Ocampo', 'Pascual', 'Quinto', 'Rosales', 'Salazar', 'Tolentino'];
        $sectionCodes = array_keys($this->sections);
        $studentNumber = 1;
        foreach ($sectionCodes as $sectionIndex => $sectionCode) {
            $programCode = null;
            foreach (['BSIT' => ['IT-1A', 'IT-2A'], 'BSCS' => ['CS-1A', 'CS-2A'], 'BSBA' => ['BA-1A', 'BA-2A'], 'BEED' => ['ED-1A'], 'BSED' => ['ED-2A'], 'BSHM' => ['HM-1A']] as $program => $codes) {
                if (in_array($sectionCode, $codes, true)) {
                    $programCode = $program;
                    break;
                }
            }
            $deptCode = ['BSIT' => 'CCS', 'BSCS' => 'CCS', 'BSBA' => 'CBA', 'BEED' => 'COED', 'BSED' => 'COED', 'BSHM' => 'CHTM'][$programCode];
            $sectionStudents = [];
            for ($i = 0; $i < 10; $i++) {
                $first = $firstNames[($sectionIndex * 3 + $i) % count($firstNames)];
                $last = $lastNames[($sectionIndex * 7 + $i) % count($lastNames)];
                $id = $this->createUser(
                    'student',
                    'demo.student.' . str_pad((string) $studentNumber, 3, '0', STR_PAD_LEFT),
                    'student' . str_pad((string) $studentNumber, 3, '0', STR_PAD_LEFT) . '@student.aclc.test',
                    $first, '', $last, null, null,
                    $this->departments[$deptCode],
                    $this->programs[$programCode],
                    $this->sections[$sectionCode],
                    1,
                    '2026-' . str_pad((string) $studentNumber, 4, '0', STR_PAD_LEFT)
                );
                $sectionStudents[] = $id;
                $studentNumber++;
                $stats['students']++;
            }
            $this->students[$sectionCode] = $sectionStudents;
        }

        Database::execute('UPDATE departments SET head_user_id = ? WHERE id = ?', [$head1, $this->departments['CCS']]);
        Database::execute('UPDATE departments SET head_user_id = ? WHERE id = ?', [$head2, $this->departments['CBA']]);
    }

    private function createUser(string $role, string $username, string $email, string $first, string $middle, string $last, ?string $employee, ?string $title, ?int $departmentId, ?int $programId, ?int $sectionId = null, ?int $yearLevel = null, ?string $studentNumber = null): int
    {
        return (int) Database::insert(
            'INSERT INTO users
             (role_id, username, email, password_hash, first_name, middle_name, last_name, employee_number, student_number,
              department_id, program_id, section_id, year_level, status, is_demo, password_changed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", 1, NOW(), NOW(), NOW())',
            [
                $this->roles[$role], $username, $email, $this->passwordHash, $first, $middle, $last,
                $employee, $studentNumber, $departmentId, $programId, $sectionId, $yearLevel,
            ]
        );
    }

    private function createTeachingLoad(array &$stats): void
    {
        $periods = $this->periods();
        foreach ($this->instructors as $instructor) {
            $sectionStudents = $this->students[$instructor['section']];
            foreach ($periods as $period) {
                Database::execute(
                    'INSERT INTO instructor_assignments
                     (instructor_id, course_id, section_id, academic_year_id, semester_id, status, is_demo, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, "active", 1, NOW(), NOW())',
                    [$instructor['id'], $this->courses[$instructor['course']], $this->sections[$instructor['section']], $period['year_id'], $period['semester_id']]
                );
                foreach ($sectionStudents as $studentId) {
                    Database::execute(
                        'INSERT IGNORE INTO student_enrollments
                         (student_id, course_id, section_id, academic_year_id, semester_id, status, is_demo, enrolled_at)
                         VALUES (?, ?, ?, ?, ?, "enrolled", 1, NOW())',
                        [$studentId, $this->courses[$instructor['course']], $this->sections[$instructor['section']], $period['year_id'], $period['semester_id']]
                    );
                }
            }
        }
        $current = $this->periods()[6];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        foreach ($this->instructors as $index => $instructor) {
            Database::execute(
                'INSERT INTO class_schedules
                 (course_id, section_id, instructor_id, academic_year_id, semester_id, day_of_week, start_time, end_time, room, status, is_demo, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active", 1, NOW(), NOW())',
                [
                    $this->courses[$instructor['course']], $this->sections[$instructor['section']], $instructor['id'],
                    $current['year_id'], $current['semester_id'], $days[$index % 5],
                    sprintf('%02d:00:00', 8 + ($index % 6)), sprintf('%02d:00:00', 9 + ($index % 6)),
                    'Room ' . (200 + $index),
                ]
            );
        }
    }

    private function createDemoSurvey(): void
    {
        $surveyId = (int) Database::insert(
            'INSERT INTO surveys (title, description, category, status, is_template, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, ?, "active", 0, 1, NULL, NOW(), NOW())',
            ['Teaching Effectiveness Evaluation — Demonstration', 'Synthetic demonstration questionnaire. Every response generated from this survey carries the demonstration flag.', 'Teaching Effectiveness']
        );
        $versionId = (int) Database::insert(
            'INSERT INTO survey_versions (survey_id, version_number, status, instructions, rating_labels, created_by, published_at, created_at, updated_at)
             VALUES (?, 1, "published", ?, ?, NULL, NOW(), NOW(), NOW())',
            [
                $surveyId,
                'Rate each statement from Strongly Disagree (1) to Strongly Agree (5). Your response is anonymous. Do not include names or identifying details.',
                json_encode((new SurveyService())->defaultRatingLabels(), JSON_UNESCAPED_UNICODE),
            ]
        );
        $questions = [
            ['The instructor explains lesson content clearly.', 'Teaching Clarity', 'agreement'],
            ['The instructor communicates instructions and expectations clearly.', 'Communication', 'agreement'],
            ['The instructor uses activities that keep students engaged.', 'Classroom Engagement', 'agreement'],
            ['The lesson pacing allows me to follow the discussion.', 'Lesson Pacing', 'agreement'],
            ['The instructor uses varied and effective teaching strategies.', 'Teaching Methodology', 'agreement'],
            ['The instructor responds helpfully to student questions and concerns.', 'Instructor Responsiveness', 'agreement'],
            ['The instructor treats students with respect and encourages interaction.', 'Classroom Interaction', 'agreement'],
            ['I understand the subject better because of this class.', 'Subject Comprehension', 'agreement'],
            ['Overall, I am satisfied with the quality of instruction in this class.', 'Overall Satisfaction', 'agreement'],
        ];
        foreach ($questions as $index => $question) {
            $questionId = (int) Database::insert(
                'INSERT INTO survey_questions
                 (survey_version_id, question_text, question_type, category, order_index, is_required, min_value, max_value, min_label, max_label, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, 1, 5, "Strongly Disagree", "Strongly Agree", NOW())',
                [$versionId, $question[0], $question[2], $question[1], $index + 1]
            );
            $this->demoQuestionIds[] = ['id' => $questionId, 'category' => $question[1]];
        }
        $this->demoQuestionIds[] = ['id' => $this->insertTextQuestion($versionId, 'What did this instructor do particularly well?', 10), 'category' => 'text_positive'];
        $this->demoQuestionIds[] = ['id' => $this->insertTextQuestion($versionId, 'What specific improvement would help your learning?', 11), 'category' => 'text_improvement'];
        $this->demoSurveyId = $surveyId;
        $this->demoVersionId = $versionId;
    }

    private array $demoQuestionIds = [];
    private int $demoSurveyId = 0;
    private int $demoVersionId = 0;

    private function insertTextQuestion(int $versionId, string $text, int $index): int
    {
        return (int) Database::insert(
            'INSERT INTO survey_questions
             (survey_version_id, question_text, question_type, category, order_index, is_required, created_at)
             VALUES (?, ?, "paragraph", "General", ?, 0, NOW())',
            [$versionId, $text, $index]
        );
    }

    private function createSessionsAndResponses(array &$stats): void
    {
        $periods = $this->periods();
        $profiles = [
            'improving' => ['intercept' => 3.18, 'slope' => 0.16, 'pacing' => -0.15],
            'declining' => ['intercept' => 4.42, 'slope' => -0.16, 'pacing' => -0.45],
            'stable' => ['intercept' => 4.03, 'slope' => 0.0, 'pacing' => -0.10],
            'high' => ['intercept' => 4.56, 'slope' => 0.01, 'pacing' => 0.0],
            'low' => ['intercept' => 3.12, 'slope' => 0.0, 'pacing' => -0.35],
        ];
        $categoryAdjustments = [
            'Teaching Clarity' => 0.05, 'Communication' => 0.0, 'Classroom Engagement' => -0.08,
            'Lesson Pacing' => -0.2, 'Teaching Methodology' => -0.05, 'Instructor Responsiveness' => 0.05,
            'Classroom Interaction' => 0.1, 'Subject Comprehension' => -0.05, 'Overall Satisfaction' => 0.15,
        ];
        mt_srand(20261110);

        foreach ($this->instructors as $instructorIndex => $instructor) {
            $sectionStudents = $this->students[$instructor['section']];
            foreach ($periods as $periodIndex => $period) {
                $isCurrent = $periodIndex === 6;
                $active = $isCurrent && $instructorIndex < 4;
                $open = $period['opens_at'];
                $expires = $period['expires_at'];
                if ($active) {
                    $open = date('Y-m-d H:i:s', time() - 7200);
                    $expires = date('Y-m-d H:i:s', time() + 86400);
                }
                $title = 'Teaching Effectiveness Evaluation — ' . $instructor['course'] . ' (' . $period['year'] . ' ' . $period['semester'] . ')';
                $assignmentId = (int) Database::insert(
                    'INSERT INTO evaluation_assignments
                     (title, survey_version_id, instructor_id, course_id, section_id, academic_year_id, semester_id,
                      evaluation_type, instructions, opens_at, closes_at, status, is_demo, created_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, "student_feedback", ?, ?, ?, ?, 1, NULL, NOW(), NOW())',
                    [
                        $title, $this->demoVersionId, $instructor['id'], $this->courses[$instructor['course']],
                        $this->sections[$instructor['section']], $period['year_id'], $period['semester_id'],
                        'Anonymous student feedback for the demonstration dataset.',
                        $open, $expires, $active ? 'active' : 'closed',
                    ]
                );
                $sessionCode = 'DEMO' . str_pad((string) ($instructorIndex * 7 + $periodIndex + 1), 4, '0', STR_PAD_LEFT);
                $token = Security::randomToken(32);
                $status = $active ? 'active' : 'closed';
                $sessionId = (int) Database::insert(
                    'INSERT INTO evaluation_sessions
                     (assignment_id, session_code, token_hash, token_cipher, status, opens_at, expires_at, closed_at, is_demo, created_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NOW(), NOW())',
                    [
                        $assignmentId, $sessionCode, hash('sha256', $token), Security::encrypt($token), $status,
                        $open, $expires, $active ? null : $expires,
                    ]
                );
                $stats['sessions']++;
                foreach ($sectionStudents as $studentId) {
                    Database::execute(
                        'INSERT INTO evaluation_participation (evaluation_session_id, student_id, status, created_at) VALUES (?, ?, "pending", NOW())',
                        [$sessionId, $studentId]
                    );
                    Database::execute(
                        'INSERT INTO evaluation_eligibility_tokens (evaluation_session_id, student_id, token_hash, created_at) VALUES (?, ?, ?, NOW())',
                        [$sessionId, $studentId, hash('sha256', Security::randomToken(24))]
                    );
                }

                $profile = $profiles[$instructor['profile']];
                $targetBase = $profile['intercept'] + ($profile['slope'] * $periodIndex);
                $responseCount = $active ? mt_rand(3, 6) : ($periodIndex === 6 ? mt_rand(7, 9) : mt_rand(8, 10));
                $sample = $sectionStudents;
                shuffle($sample);
                $sample = array_slice($sample, 0, min($responseCount, count($sample)));

                foreach ($sample as $studentId) {
                    $responseId = $this->insertDemoResponse($sessionId, $assignmentId, $instructor, $period, $targetBase, $categoryAdjustments, $profile);
                    Database::execute(
                        'UPDATE evaluation_participation SET status = "submitted", submitted_at = ? WHERE evaluation_session_id = ? AND student_id = ?',
                        [$period['response_time'], $sessionId, $studentId]
                    );
                    Database::execute(
                        'UPDATE evaluation_eligibility_tokens SET used_at = ? WHERE evaluation_session_id = ? AND student_id = ?',
                        [$period['response_time'], $sessionId, $studentId]
                    );
                    $stats['responses']++;
                    try {
                        (new AIEngine())->analyzeAndStore($responseId, $this->lastComments);
                    } catch (\Throwable $e) {
                        error_log('Demo AI analysis failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    private string $lastComments = '';

    private function insertDemoResponse(int $sessionId, int $assignmentId, array $instructor, array $period, float $targetBase, array $adjustments, array $profile): int
    {
        $submitted = $period['response_time'];
        $responseId = (int) Database::insert(
            'INSERT INTO evaluation_responses
             (evaluation_session_id, survey_version_id, submitted_at, submitted_on, response_status, sentiment_status, source, created_at)
             VALUES (?, ?, ?, ?, "submitted", "pending", "demo", NOW())',
            [$sessionId, $this->demoVersionId, $submitted, substr($submitted, 0, 10)]
        );

        $ratings = [];
        foreach ($this->demoQuestionIds as $question) {
            if ($question['category'] === 'text_positive' || $question['category'] === 'text_improvement') {
                continue;
            }
            $target = $targetBase + ($adjustments[$question['category']] ?? 0) + (mt_rand(-55, 55) / 100);
            if ($question['category'] === 'Lesson Pacing') {
                $target += $profile['pacing'];
            }
            $target = max(1.05, min(4.95, $target));
            $base = (int) floor($target);
            $fraction = $target - $base;
            $rating = $base + ((mt_rand(1, 100) / 100) <= $fraction ? 1 : 0);
            $rating = max(1, min(5, $rating));
            $ratings[$question['category']] = $rating;
            Database::execute(
                'INSERT INTO response_answers (response_id, question_id, answer_value, numeric_value, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [$responseId, $question['id'], (string) $rating, $rating]
            );
        }

        $positive = [
            'The instructor explains lessons clearly and gives helpful examples.',
            'Communication is clear and the instructions are easy to follow.',
            'Class activities are engaging and encourage students to participate.',
            'The pace is comfortable and there are short checks for understanding.',
            'The teaching strategies are organized and effective.',
            'The instructor is approachable and responds to questions.',
            'The instructor treats students with respect and encourages discussion.',
            'The learning materials support the lessons and make the subject easier to understand.',
            'Assessments are fair and the grading criteria are explained.',
            'I understand the subject better because of this class.',
            'Malinaw ang paliwanag ng instructor at madaling maintindihan ang mga halimbawa.',
        ];
        $negative = [
            'The discussion sometimes moves too fast, especially for difficult topics.',
            'Some concepts are not clear and need more examples.',
            'Instructions for activities are sometimes confusing.',
            'It is hard to stay engaged during long lectures.',
            'The learning materials are outdated and difficult to follow.',
            'Assessment expectations are unclear and feedback is delayed.',
            'Questions are sometimes ignored or answered too briefly.',
            'The teaching method is repetitive and needs more variety.',
            'I still struggle to understand the major topics.',
            'Class participation feels intimidating and less inclusive.',
            'Masyadong mabilis ang discussion kaya mahirap sundan ang ilang paksa.',
        ];
        $mixed = [
            'The instructor explains clearly, but sometimes the discussion moves too fast.',
            'The activities are engaging, however the instructions can be confusing.',
            'The instructor is approachable, but feedback on assessments could be clearer.',
            'The lessons are organized, although more examples would help with difficult topics.',
        ];

        $average = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 3.0;
        if ($average >= 4.15) {
            $strength = $positive[array_rand($positive)];
            $improvement = mt_rand(1, 100) <= 35 ? $mixed[array_rand($mixed)] : '';
        } elseif ($average >= 3.55) {
            $strength = $positive[array_rand($positive)];
            $improvement = $mixed[array_rand($mixed)];
        } else {
            $strength = mt_rand(1, 100) <= 40 ? $positive[array_rand($positive)] : '';
            $improvement = $negative[array_rand($negative)];
        }
        $this->lastComments = trim($strength . ($strength !== '' && $improvement !== '' ? "\n" : '') . $improvement);
        foreach ($this->demoQuestionIds as $question) {
            if ($question['category'] === 'text_positive' && $strength !== '') {
                Database::execute(
                    'INSERT INTO response_answers (response_id, question_id, answer_value, numeric_value, created_at) VALUES (?, ?, ?, NULL, NOW())',
                    [$responseId, $question['id'], $strength]
                );
            }
            if ($question['category'] === 'text_improvement' && $improvement !== '') {
                Database::execute(
                    'INSERT INTO response_answers (response_id, question_id, answer_value, numeric_value, created_at) VALUES (?, ?, ?, NULL, NOW())',
                    [$responseId, $question['id'], $improvement]
                );
            }
        }
        return $responseId;
    }

    private function createObservations(): void
    {
        $period = $this->periods()[6];
        foreach (array_slice($this->instructors, 0, 5) as $index => $instructor) {
            Database::insert(
                'INSERT INTO class_observations
                 (assignment_id, observer_id, instructor_id, course_id, section_id, academic_year_id, semester_id,
                  observation_date, observation_type, strengths, areas_for_improvement, recommendations, overall_rating,
                  status, is_demo, created_at, updated_at)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, "classroom_observation", ?, ?, ?, ?, "submitted", 1, NOW(), NOW())',
                [
                    $this->instructorsUserId('demo.dean'), $instructor['id'], $this->courses[$instructor['course']],
                    $this->sections[$instructor['section']], $period['year_id'], $period['semester_id'],
                    date('Y-m-d', strtotime('-' . (10 + $index * 3) . ' days')),
                    'Clear lesson structure and positive classroom climate.',
                    'Increase wait time after asking questions so more students can respond.',
                    'Use a short exit ticket to check understanding before the next lesson.',
                    number_format(4.0 + ($index * 0.1), 2, '.', ''),
                ]
            );
        }
    }

    private function createUat(array &$stats): void
    {
        $surveyId = (int) Database::insert(
            'INSERT INTO uat_surveys (title, description, status, opens_at, closes_at, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, "active", ?, ?, 1, NULL, NOW(), NOW())',
            [
                'ISO/IEC 25010 UAT — Demonstration Dataset',
                'Synthetic UAT responses for interface demonstration only. These are not previously reported research results.',
                date('Y-m-d H:i:s', time() - 86400), date('Y-m-d H:i:s', time() + 86400),
            ]
        );
        (new UatService())->seedStandardQuestions($surveyId);
        $questions = Database::fetchAll('SELECT * FROM uat_questions WHERE uat_survey_id = ? ORDER BY id', [$surveyId]);
        $names = [
            ['Alexis Tan', 'IT Professional', 4.25], ['Bianca Lim', 'Faculty', 4.42],
            ['Carlo Uy', 'Student', 4.10], ['Divina Sy', 'IT Professional', 4.55],
            ['Eduardo King', 'Academic Head', 4.35], ['Faith Co', 'Faculty', 4.48],
            ['Gabriel Ngo', 'Student', 4.20], ['Hannah Chua', 'IT Professional', 4.40],
        ];
        foreach ($names as $index => $respondent) {
            $token = Security::randomToken(24);
            $respondentId = (int) Database::insert(
                'INSERT INTO uat_respondents
                 (uat_survey_id, user_id, name, respondent_type, access_token_hash, access_token_cipher, status, submitted_at, created_at)
                 VALUES (?, NULL, ?, ?, ?, ?, "submitted", NOW(), NOW())',
                [$surveyId, $respondent[0], $respondent[1], hash('sha256', $token), Security::encrypt($token)]
            );
            $responseId = (int) Database::insert(
                'INSERT INTO uat_responses
                 (uat_survey_id, uat_respondent_id, respondent_name, respondent_type, submitted_at, source)
                 VALUES (?, ?, ?, ?, NOW(), "demo")',
                [$surveyId, $respondentId, $respondent[0], $respondent[1]]
            );
            foreach ($questions as $qIndex => $question) {
                $target = $respondent[2] + ((($qIndex % 7) - 3) * 0.08) + ((mt_rand(-15, 15)) / 100);
                $rating = max(1, min(5, (int) round($target)));
                Database::execute(
                    'INSERT INTO uat_answers (uat_response_id, uat_question_id, rating) VALUES (?, ?, ?)',
                    [$responseId, $question['id'], $rating]
                );
            }
            $stats['uat_respondents']++;
        }
    }

    private function createNotifications(): void
    {
        $dean = Database::fetch('SELECT id FROM users WHERE username = "demo.dean"');
        if ($dean) {
            Database::execute(
                'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES
                 (?, "evaluation_open", "Four evaluation sessions are active", "Active QR evaluation sessions are open for demonstration classes.", "dean/sessions", 0, NOW()),
                 (?, "report", "Executive report is ready", "The synthetic executive report can be generated from Reports.", "dean/reports", 0, NOW() - INTERVAL 2 HOUR),
                 (?, "system", "Demonstration mode is enabled", "All analytics and reports are using synthetic demonstration records only.", "dean/settings", 1, NOW() - INTERVAL 1 DAY)',
                [$dean['id'], $dean['id'], $dean['id']]
            );
        }
        foreach (array_slice($this->instructors, 0, 4) as $instructor) {
            Database::execute(
                'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                 VALUES (?, "evaluation_open", "An evaluation session is open", "Students can submit anonymous feedback for your class in the demonstration dataset.", "instructor/evaluations", 0, NOW())',
                [$instructor['id']]
            );
        }
        $students = Database::fetchAll('SELECT id FROM users WHERE is_demo = 1 AND role_id = ? ORDER BY id LIMIT 6', [$this->roles['student']]);
        foreach ($students as $student) {
            Database::execute(
                'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                 VALUES (?, "evaluation_open", "Evaluation available", "A class evaluation is open. Scan a QR code or enter the session code.", "student/evaluations", 0, NOW())',
                [$student['id']]
            );
        }
    }

    private function instructorsUserId(string $username): int
    {
        static $ids = [];
        if (!isset($ids[$username])) {
            $ids[$username] = (int) Database::scalar('SELECT id FROM users WHERE username = ?', [$username], 0);
        }
        return $ids[$username];
    }

    private function periods(): array
    {
        return [
            ['year' => '2023-2024', 'semester' => '1st Semester', 'year_id' => $this->years['2023-2024'], 'semester_id' => $this->semesters['2023-2024|1'], 'opens_at' => '2023-09-18 08:00:00', 'expires_at' => '2023-09-29 17:00:00', 'response_time' => '2023-09-22 10:00:00'],
            ['year' => '2023-2024', 'semester' => '2nd Semester', 'year_id' => $this->years['2023-2024'], 'semester_id' => $this->semesters['2023-2024|2'], 'opens_at' => '2024-02-19 08:00:00', 'expires_at' => '2024-03-01 17:00:00', 'response_time' => '2024-02-23 10:00:00'],
            ['year' => '2024-2025', 'semester' => '1st Semester', 'year_id' => $this->years['2024-2025'], 'semester_id' => $this->semesters['2024-2025|1'], 'opens_at' => '2024-09-16 08:00:00', 'expires_at' => '2024-09-27 17:00:00', 'response_time' => '2024-09-20 10:00:00'],
            ['year' => '2024-2025', 'semester' => '2nd Semester', 'year_id' => $this->years['2024-2025'], 'semester_id' => $this->semesters['2024-2025|2'], 'opens_at' => '2025-02-17 08:00:00', 'expires_at' => '2025-02-28 17:00:00', 'response_time' => '2025-02-21 10:00:00'],
            ['year' => '2025-2026', 'semester' => '1st Semester', 'year_id' => $this->years['2025-2026'], 'semester_id' => $this->semesters['2025-2026|1'], 'opens_at' => '2025-09-15 08:00:00', 'expires_at' => '2025-09-26 17:00:00', 'response_time' => '2025-09-19 10:00:00'],
            ['year' => '2025-2026', 'semester' => '2nd Semester', 'year_id' => $this->years['2025-2026'], 'semester_id' => $this->semesters['2025-2026|2'], 'opens_at' => '2026-02-16 08:00:00', 'expires_at' => '2026-02-27 17:00:00', 'response_time' => '2026-02-20 10:00:00'],
            ['year' => '2026-2027', 'semester' => '1st Semester', 'year_id' => $this->years['2026-2027'], 'semester_id' => $this->semesters['2026-2027|1'], 'opens_at' => '2026-11-02 08:00:00', 'expires_at' => '2026-11-06 17:00:00', 'response_time' => '2026-11-04 10:00:00'],
        ];
    }
}
