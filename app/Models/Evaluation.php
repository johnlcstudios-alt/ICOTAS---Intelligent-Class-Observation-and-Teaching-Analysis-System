<?php

namespace App\Models;

use App\Core\Database;

final class Evaluation extends Model
{
    protected string $table = 'evaluation_assignments';

    public function assignmentListing(bool $demo, array $filters = []): array
    {
        $where = ['a.is_demo = ?'];
        $params = [$demo ? 1 : 0];
        if (!empty($filters['instructor_id'])) {
            $where[] = 'a.instructor_id = ?';
            $params[] = (int) $filters['instructor_id'];
        }
        if (!empty($filters['semester_id'])) {
            $where[] = 'a.semester_id = ?';
            $params[] = (int) $filters['semester_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(a.title LIKE ? OR c.code LIKE ? OR sec.code LIKE ? OR i.last_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return Database::fetchAll(
            'SELECT a.*, sv.version_number, s.title AS survey_title, s.category AS survey_category,
                    i.first_name, i.last_name, c.code AS course_code, c.title AS course_title,
                    sec.code AS section_code, ay.name AS academic_year_name, sem.name AS semester_name,
                    (SELECT COUNT(*) FROM evaluation_sessions es WHERE es.assignment_id = a.id) AS session_count,
                    (SELECT COUNT(*) FROM evaluation_responses r
                       JOIN evaluation_sessions es ON es.id = r.evaluation_session_id
                       WHERE es.assignment_id = a.id) AS response_count
             FROM evaluation_assignments a
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.opens_at DESC, a.id DESC',
            $params
        );
    }

    public function getAssignment(int $id): ?array
    {
        return Database::fetch(
            'SELECT a.*, sv.version_number, sv.status AS version_status, s.title AS survey_title,
                    s.category AS survey_category, i.first_name, i.last_name, c.code AS course_code,
                    c.title AS course_title, sec.code AS section_code, ay.name AS academic_year_name,
                    sem.name AS semester_name,
                    (SELECT COUNT(*) FROM evaluation_sessions es WHERE es.assignment_id = a.id) AS session_count
             FROM evaluation_assignments a
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             WHERE a.id = ? LIMIT 1',
            [$id]
        );
    }

    public function sessions(bool $demo, array $filters = []): array
    {
        $where = ['es.is_demo = ?'];
        $params = [$demo ? 1 : 0];
        if (!empty($filters['instructor_id'])) {
            $where[] = 'a.instructor_id = ?';
            $params[] = (int) $filters['instructor_id'];
        }
        if (!empty($filters['assignment_id'])) {
            $where[] = 'es.assignment_id = ?';
            $params[] = (int) $filters['assignment_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'es.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(es.session_code LIKE ? OR a.title LIKE ? OR c.code LIKE ? OR sec.code LIKE ? OR i.last_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return Database::fetchAll(
            'SELECT es.*, a.title, a.instructor_id, a.survey_version_id, a.course_id, a.section_id,
                    a.academic_year_id, a.semester_id, sv.version_number, s.title AS survey_title,
                    i.first_name, i.last_name, c.code AS course_code, sec.code AS section_code,
                    ay.name AS academic_year_name, sem.name AS semester_name,
                    (SELECT COUNT(*) FROM evaluation_eligibility_tokens t WHERE t.evaluation_session_id = es.id) AS eligible_count,
                    (SELECT COUNT(*) FROM evaluation_participation ep WHERE ep.evaluation_session_id = es.id AND ep.status = "submitted") AS submitted_count,
                    (SELECT COUNT(*) FROM evaluation_responses r WHERE r.evaluation_session_id = es.id) AS response_count,
                    (SELECT ROUND(AVG(ra.numeric_value), 2) FROM response_answers ra
                       JOIN evaluation_responses r ON r.id = ra.response_id
                       WHERE r.evaluation_session_id = es.id) AS average_rating
             FROM evaluation_sessions es
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY es.opens_at DESC, es.id DESC',
            $params
        );
    }

    public function getSession(int $id): ?array
    {
        return Database::fetch(
            'SELECT es.*, a.title, a.instructions, a.instructor_id, a.survey_version_id, a.course_id,
                    a.section_id, a.academic_year_id, a.semester_id, a.evaluation_type,
                    sv.version_number, s.title AS survey_title, s.description AS survey_description,
                    s.category AS survey_category, i.first_name, i.last_name, c.code AS course_code,
                    c.title AS course_title, sec.code AS section_code, ay.name AS academic_year_name,
                    sem.name AS semester_name
             FROM evaluation_sessions es
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             WHERE es.id = ? LIMIT 1',
            [$id]
        );
    }

    public function resolveByToken(string $token): ?array
    {
        $row = Database::fetch('SELECT * FROM evaluation_sessions WHERE token_hash = ? LIMIT 1', [hash('sha256', $token)]);
        return $row ? $this->getSession((int) $row['id']) : null;
    }

    public function resolveByCode(string $code): ?array
    {
        $row = Database::fetch('SELECT * FROM evaluation_sessions WHERE session_code = ? LIMIT 1', [strtoupper(trim($code))]);
        return $row ? $this->getSession((int) $row['id']) : null;
    }

    public function eligibleStudents(array $assignment): array
    {
        return Database::fetchAll(
            'SELECT DISTINCT u.* FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN student_enrollments e ON e.student_id = u.id
             WHERE r.slug = "student" AND u.status = "active"
               AND e.course_id = ? AND e.section_id = ?
               AND e.academic_year_id = ? AND e.semester_id = ?
               AND e.status = "enrolled"
             ORDER BY u.last_name, u.first_name',
            [$assignment['course_id'], $assignment['section_id'], $assignment['academic_year_id'], $assignment['semester_id']]
        );
    }

    public function isStudentEligible(array $assignment, int $studentId): bool
    {
        return (bool) Database::scalar(
            'SELECT COUNT(*) FROM student_enrollments
             WHERE student_id = ? AND course_id = ? AND section_id = ? AND academic_year_id = ? AND semester_id = ? AND status = "enrolled"',
            [$studentId, $assignment['course_id'], $assignment['section_id'], $assignment['academic_year_id'], $assignment['semester_id']],
            0
        );
    }

    public function participation(int $sessionId, int $studentId): ?array
    {
        return Database::fetch(
            'SELECT * FROM evaluation_participation WHERE evaluation_session_id = ? AND student_id = ? LIMIT 1',
            [$sessionId, $studentId]
        );
    }

    public function eligibilityByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT t.*, es.assignment_id FROM evaluation_eligibility_tokens t
             JOIN evaluation_sessions es ON es.id = t.evaluation_session_id
             WHERE t.token_hash = ? LIMIT 1',
            [hash('sha256', $token)]
        );
    }

    public function createParticipationRows(int $sessionId, array $students, array $assignment): void
    {
        foreach ($students as $student) {
            Database::execute(
                'INSERT IGNORE INTO evaluation_participation
                 (evaluation_session_id, student_id, status, created_at) VALUES (?, ?, "pending", NOW())',
                [$sessionId, $student['id']]
            );
            $token = \App\Core\Security::randomToken(24);
            Database::execute(
                'INSERT IGNORE INTO evaluation_eligibility_tokens
                 (evaluation_session_id, student_id, token_hash, created_at) VALUES (?, ?, ?, NOW())',
                [$sessionId, $student['id'], hash('sha256', $token)]
            );
        }
    }

    public function ensureParticipation(int $sessionId, int $studentId, array $assignment): void
    {
        if ($this->isStudentEligible($assignment, $studentId)) {
            Database::execute(
                'INSERT IGNORE INTO evaluation_participation
                 (evaluation_session_id, student_id, status, created_at) VALUES (?, ?, "pending", NOW())',
                [$sessionId, $studentId]
            );
            if ((int) Database::scalar(
                'SELECT COUNT(*) FROM evaluation_eligibility_tokens WHERE evaluation_session_id = ? AND student_id = ?',
                [$sessionId, $studentId],
                0
            ) === 0) {
                Database::execute(
                    'INSERT INTO evaluation_eligibility_tokens (evaluation_session_id, student_id, token_hash, created_at)
                     VALUES (?, ?, ?, NOW())',
                    [$sessionId, $studentId, hash('sha256', \App\Core\Security::randomToken(24))]
                );
            }
        }
    }

    public function sessionStats(int $sessionId): array
    {
        $eligible = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_eligibility_tokens WHERE evaluation_session_id = ?', [$sessionId], 0);
        $submitted = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_participation WHERE evaluation_session_id = ? AND status = "submitted"', [$sessionId], 0);
        $responses = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = ?', [$sessionId], 0);
        return [
            'eligible' => $eligible,
            'submitted' => $submitted,
            'responses' => $responses,
            'rate' => $eligible > 0 ? round($submitted / $eligible * 100, 1) : 0.0,
        ];
    }

    public function availableSessionsForStudent(int $studentId, bool $demo): array
    {
        return Database::fetchAll(
            'SELECT es.*, a.title, a.instructor_id, a.survey_version_id, a.course_id, a.section_id,
                    a.academic_year_id, a.semester_id, s.title AS survey_title, i.first_name, i.last_name,
                    c.code AS course_code, sec.code AS section_code, ay.name AS academic_year_name,
                    sem.name AS semester_name, ep.status AS participation_status, ep.submitted_at,
                    (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_version_id = a.survey_version_id) AS question_count
             FROM evaluation_sessions es
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             LEFT JOIN evaluation_participation ep ON ep.evaluation_session_id = es.id AND ep.student_id = ?
             JOIN student_enrollments en ON en.student_id = ?
               AND en.course_id = a.course_id AND en.section_id = a.section_id
               AND en.academic_year_id = a.academic_year_id AND en.semester_id = a.semester_id
               AND en.status = "enrolled"
             WHERE es.status IN ("scheduled", "active")
               AND es.is_demo = ?
               AND NOW() BETWEEN es.opens_at AND es.expires_at
             ORDER BY es.expires_at ASC',
            [$studentId, $studentId, $demo ? 1 : 0]
        );
    }

    public function studentHistory(int $studentId, bool $demo): array
    {
        return Database::fetchAll(
            'SELECT ep.*, es.session_code, es.opens_at, es.expires_at, a.title, s.title AS survey_title,
                    i.first_name, i.last_name, c.code AS course_code, sec.code AS section_code,
                    ay.name AS academic_year_name, sem.name AS semester_name
             FROM evaluation_participation ep
             JOIN evaluation_sessions es ON es.id = ep.evaluation_session_id
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN survey_versions sv ON sv.id = a.survey_version_id
             JOIN surveys s ON s.id = sv.survey_id
             JOIN users i ON i.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN academic_years ay ON ay.id = a.academic_year_id
             JOIN semesters sem ON sem.id = a.semester_id
             WHERE ep.student_id = ? AND es.is_demo = ?
             ORDER BY es.opens_at DESC LIMIT 100',
            [$studentId, $demo ? 1 : 0]
        );
    }

    public function sessionsForInstructor(int $instructorId, bool $demo): array
    {
        return $this->sessions($demo, ['instructor_id' => $instructorId]);
    }

    public function assignmentOptions(bool $demo): array
    {
        return Database::fetchAll(
            'SELECT a.id, a.title, a.instructor_id, c.code AS course_code, sec.code AS section_code
             FROM evaluation_assignments a
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             WHERE a.is_demo = ? ORDER BY a.opens_at DESC',
            [$demo ? 1 : 0]
        );
    }
}
