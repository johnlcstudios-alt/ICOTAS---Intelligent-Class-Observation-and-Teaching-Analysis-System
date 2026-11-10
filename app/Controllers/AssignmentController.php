<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Academic;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\NotificationService;

final class AssignmentController extends BaseController
{
    private Evaluation $evaluations;
    private Academic $academic;
    private User $users;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
        $this->academic = new Academic();
        $this->users = new User();
    }

    public function index(): void
    {
        $filters = [
            'search' => $this->input('search', ''),
            'status' => $this->input('status', ''),
            'instructor_id' => $this->input('instructor_id', ''),
        ];
        $this->view('dean/assignments/index', [
            'pageTitle' => 'Evaluation Assignments',
            'assignments' => $this->evaluations->assignmentListing($this->demo(), $filters),
            'filters' => $filters,
            'instructors' => $this->users->instructors($this->demo(), false),
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $this->view('dean/assignments/form', $this->formData(null));
    }

    public function edit(string $id): void
    {
        $assignment = $this->evaluations->getAssignment((int) $id);
        if (!$assignment || !$this->inScope($assignment)) {
            abort(404);
        }
        $this->view('dean/assignments/form', $this->formData($assignment));
    }

    public function store(): void
    {
        $this->requirePost();
        $data = $this->validated();
        $data['is_demo'] = $this->demo() ? 1 : 0;
        $data['created_by'] = (int) Auth::id();
        $id = Database::insert(
            'INSERT INTO evaluation_assignments
             (title, survey_version_id, instructor_id, course_id, section_id, academic_year_id, semester_id,
              evaluation_type, instructions, opens_at, closes_at, status, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $data['title'], $data['survey_version_id'], $data['instructor_id'], $data['course_id'],
                $data['section_id'], $data['academic_year_id'], $data['semester_id'], $data['evaluation_type'],
                $data['instructions'], $data['opens_at'], $data['closes_at'], $data['status'], $data['is_demo'], $data['created_by'],
            ]
        );
        $this->audit('assignment.create', 'evaluation_assignment', $id, 'Created evaluation assignment ' . $data['title']);
        (new NotificationService())->newAssignment((int) $data['instructor_id'], $data['title'], $id, $this->demo());
        $this->success('Evaluation assignment created. Create a QR session when ready.', 'dean/assignments');
    }

    public function update(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $assignment = $this->evaluations->getAssignment($id);
        if (!$assignment || !$this->inScope($assignment)) {
            abort(404);
        }
        $data = $this->validated();
        $sessionCount = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_sessions WHERE assignment_id = ?', [$id], 0);
        if ($sessionCount > 0 && (int) $data['survey_version_id'] !== (int) $assignment['survey_version_id']) {
            $this->backWithError('A questionnaire version cannot be swapped after QR sessions have been created. Archive this assignment and create a new one.', 'dean/assignments/' . $id . '/edit');
        }
        Database::execute(
            'UPDATE evaluation_assignments SET title = ?, survey_version_id = ?, instructor_id = ?, course_id = ?,
                    section_id = ?, academic_year_id = ?, semester_id = ?, evaluation_type = ?, instructions = ?,
                    opens_at = ?, closes_at = ?, status = ?, updated_at = NOW() WHERE id = ?',
            [
                $data['title'], $data['survey_version_id'], $data['instructor_id'], $data['course_id'],
                $data['section_id'], $data['academic_year_id'], $data['semester_id'], $data['evaluation_type'],
                $data['instructions'], $data['opens_at'], $data['closes_at'], $data['status'], $id,
            ]
        );
        $this->audit('assignment.update', 'evaluation_assignment', $id, 'Updated evaluation assignment');
        $this->success('Evaluation assignment updated.', 'dean/assignments');
    }

    public function destroy(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $assignment = $this->evaluations->getAssignment($id);
        if (!$assignment || !$this->inScope($assignment)) {
            abort(404);
        }
        $sessions = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_sessions WHERE assignment_id = ?', [$id], 0);
        if ($sessions > 0) {
            $this->backWithError('This assignment already has QR sessions and response records. Close or archive it instead of deleting.', 'dean/assignments');
        }
        Database::execute('DELETE FROM evaluation_assignments WHERE id = ?', [$id]);
        $this->audit('assignment.delete', 'evaluation_assignment', $id, 'Deleted evaluation assignment');
        $this->success('Evaluation assignment deleted.', 'dean/assignments');
    }

    private function formData(?array $assignment): array
    {
        $demo = $assignment ? (int) $assignment['is_demo'] === 1 : $this->demo();
        $versions = Database::fetchAll(
            'SELECT v.id, v.version_number, s.title, s.category FROM survey_versions v
             JOIN surveys s ON s.id = v.survey_id
             WHERE v.status = "published" AND s.is_demo = ? AND s.status <> "archived"
             ORDER BY s.title, v.version_number DESC',
            [$demo ? 1 : 0]
        );
        return [
            'pageTitle' => $assignment ? 'Edit Evaluation Assignment' : 'New Evaluation Assignment',
            'assignment' => $assignment,
            'versions' => $versions,
            'instructors' => $this->users->instructors($demo, true),
            'courses' => $this->academic->courses($demo, null, null, true),
            'sections' => $this->academic->sections($demo, null, true),
            'years' => $this->academic->academicYears(),
            'semesters' => $this->academic->semesters(),
            'demo' => $demo,
        ];
    }

    private function validated(): array
    {
        $data = [
            'title' => $this->postString('title'),
            'survey_version_id' => $this->intInput('survey_version_id'),
            'instructor_id' => $this->intInput('instructor_id'),
            'course_id' => $this->intInput('course_id'),
            'section_id' => $this->intInput('section_id'),
            'academic_year_id' => $this->intInput('academic_year_id'),
            'semester_id' => $this->intInput('semester_id'),
            'evaluation_type' => $this->postString('evaluation_type', 'student_feedback'),
            'instructions' => $this->postString('instructions'),
            'opens_at' => $this->postString('opens_at'),
            'closes_at' => $this->postString('closes_at'),
            'status' => $this->postString('status', 'draft'),
        ];
        $back = $this->postString('_return', 'dean/assignments/create');
        if ($data['title'] === '') {
            $this->backWithError('Assignment title is required.', $back);
        }
        if ($data['survey_version_id'] <= 0) {
            $this->backWithError('Choose a published questionnaire version.', $back);
        }
        $version = Database::fetch('SELECT v.*, s.is_demo FROM survey_versions v JOIN surveys s ON s.id = v.survey_id WHERE v.id = ?', [$data['survey_version_id']]);
        if (!$version || $version['status'] !== 'published' || (int) $version['is_demo'] !== ($this->demo() ? 1 : 0)) {
            $this->backWithError('The selected questionnaire version is not published in the active data scope.', $back);
        }
        if ($data['instructor_id'] <= 0 || !$this->users->findWithRelations($data['instructor_id'])) {
            $this->backWithError('Choose a valid instructor.', $back);
        }
        $opens = strtotime($data['opens_at']);
        $closes = strtotime($data['closes_at']);
        if (!$opens || !$closes || $closes <= $opens) {
            $this->backWithError('The closing date-time must be after the opening date-time.', $back);
        }
        $data['opens_at'] = date('Y-m-d H:i:s', $opens);
        $data['closes_at'] = date('Y-m-d H:i:s', $closes);
        if (!in_array($data['status'], ['draft', 'scheduled', 'active', 'closed', 'archived'], true)) {
            $data['status'] = 'draft';
        }
        if (!in_array($data['evaluation_type'], ['student_feedback', 'peer_observation', 'self_assessment', 'supervisor'], true)) {
            $data['evaluation_type'] = 'student_feedback';
        }
        $semesterYear = Database::scalar('SELECT academic_year_id FROM semesters WHERE id = ?', [$data['semester_id']]);
        if ((int) $semesterYear !== $data['academic_year_id']) {
            $this->backWithError('The selected semester does not belong to the selected academic year.', $back);
        }
        return $data;
    }
}
