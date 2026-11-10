<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Academic;
use App\Models\User;
use App\Services\NotificationService;

final class ObservationController extends BaseController
{
    private Academic $academic;
    private User $users;

    public function __construct()
    {
        $this->academic = new Academic();
        $this->users = new User();
    }

    public function index(): void
    {
        $filters = [
            'search' => $this->input('search', ''),
            'status' => $this->input('status', ''),
        ];
        $where = ['o.is_demo = ?'];
        $params = [$this->demo() ? 1 : 0];
        if ($filters['status'] !== '') {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['search'] !== '') {
            $where[] = '(c.code LIKE ? OR sec.code LIKE ? OR i.last_name LIKE ? OR o.strengths LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $rows = Database::fetchAll(
            'SELECT o.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                    i.first_name AS instructor_first, i.last_name AS instructor_last,
                    ob.first_name AS observer_first, ob.last_name AS observer_last,
                    ay.name AS academic_year_name, sem.name AS semester_name
             FROM class_observations o
             JOIN courses c ON c.id = o.course_id
             JOIN sections sec ON sec.id = o.section_id
             JOIN users i ON i.id = o.instructor_id
             JOIN users ob ON ob.id = o.observer_id
             JOIN academic_years ay ON ay.id = o.academic_year_id
             JOIN semesters sem ON sem.id = o.semester_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.observation_date DESC, o.id DESC LIMIT 200',
            $params
        );

        $this->view('dean/observations/index', [
            'pageTitle' => 'Class Observations',
            'observations' => $rows,
            'filters' => $filters,
            'demo' => $this->demo(),
        ]);
    }

    public function create(): void
    {
        $this->view('dean/observations/form', $this->formData(null));
    }

    public function store(): void
    {
        $this->requirePost();
        $data = $this->validated();
        $id = Database::insert(
            'INSERT INTO class_observations
             (assignment_id, observer_id, instructor_id, course_id, section_id, academic_year_id, semester_id,
              observation_date, observation_type, strengths, areas_for_improvement, recommendations, overall_rating,
              status, is_demo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $data['assignment_id'] ?: null, (int) Auth::id(), $data['instructor_id'], $data['course_id'],
                $data['section_id'], $data['academic_year_id'], $data['semester_id'], $data['observation_date'],
                $data['observation_type'], $data['strengths'], $data['areas_for_improvement'], $data['recommendations'],
                $data['overall_rating'], $data['status'], $this->demo() ? 1 : 0,
            ]
        );
        $this->audit('observation.create', 'class_observation', $id, 'Recorded a class observation');
        (new NotificationService())->toUser(
            (int) $data['instructor_id'],
            'observation',
            'New class observation recorded',
            'A class observation was recorded for your class. Open your observations page to review the feedback.',
            'instructor/observations'
        );
        $this->success('Class observation recorded.', 'dean/observations');
    }

    public function edit(string $id): void
    {
        $observation = $this->findObservation((int) $id);
        $this->view('dean/observations/form', $this->formData($observation));
    }

    public function update(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $observation = $this->findObservation($id);
        $data = $this->validated();
        Database::execute(
            'UPDATE class_observations SET assignment_id = ?, instructor_id = ?, course_id = ?, section_id = ?,
                    academic_year_id = ?, semester_id = ?, observation_date = ?, observation_type = ?,
                    strengths = ?, areas_for_improvement = ?, recommendations = ?, overall_rating = ?, status = ?,
                    updated_at = NOW() WHERE id = ?',
            [
                $data['assignment_id'] ?: null, $data['instructor_id'], $data['course_id'], $data['section_id'],
                $data['academic_year_id'], $data['semester_id'], $data['observation_date'], $data['observation_type'],
                $data['strengths'], $data['areas_for_improvement'], $data['recommendations'], $data['overall_rating'],
                $data['status'], $id,
            ]
        );
        $this->audit('observation.update', 'class_observation', $id, 'Updated a class observation');
        $this->success('Class observation updated.', 'dean/observations');
    }

    public function destroy(string $id): void
    {
        $this->requirePost();
        $id = (int) $id;
        $this->findObservation($id);
        Database::execute('DELETE FROM class_observations WHERE id = ?', [$id]);
        $this->audit('observation.delete', 'class_observation', $id, 'Deleted a class observation');
        $this->success('Class observation deleted.', 'dean/observations');
    }

    public function instructorIndex(): void
    {
        $rows = Database::fetchAll(
            'SELECT o.*, c.code AS course_code, c.title AS course_title, sec.code AS section_code,
                    ob.first_name AS observer_first, ob.last_name AS observer_last,
                    ay.name AS academic_year_name, sem.name AS semester_name
             FROM class_observations o
             JOIN courses c ON c.id = o.course_id
             JOIN sections sec ON sec.id = o.section_id
             JOIN users ob ON ob.id = o.observer_id
             JOIN academic_years ay ON ay.id = o.academic_year_id
             JOIN semesters sem ON sem.id = o.semester_id
             WHERE o.instructor_id = ? AND o.is_demo = ?
             ORDER BY o.observation_date DESC, o.id DESC LIMIT 100',
            [(int) Auth::id(), $this->demo() ? 1 : 0]
        );

        $this->view('instructor/observations', [
            'pageTitle' => 'My Class Observations',
            'observations' => $rows,
            'demo' => $this->demo(),
        ]);
    }

    private function findObservation(int $id): array
    {
        $observation = Database::fetch('SELECT * FROM class_observations WHERE id = ? LIMIT 1', [$id]);
        if (!$observation || !$this->inScope($observation)) {
            abort(404);
        }
        return $observation;
    }

    private function formData(?array $observation): array
    {
        $demo = $observation ? (int) $observation['is_demo'] === 1 : $this->demo();
        return [
            'pageTitle' => $observation ? 'Edit Class Observation' : 'New Class Observation',
            'observation' => $observation,
            'instructors' => $this->users->instructors($demo, true),
            'courses' => $this->academic->courses($demo, null, null, true),
            'sections' => $this->academic->sections($demo, null, true),
            'years' => $this->academic->academicYears(),
            'semesters' => $this->academic->semesters(),
            'assignments' => Database::fetchAll(
                'SELECT a.id, a.title FROM evaluation_assignments a WHERE a.is_demo = ? ORDER BY a.opens_at DESC',
                [$demo ? 1 : 0]
            ),
            'demo' => $demo,
        ];
    }

    private function validated(): array
    {
        $data = [
            'assignment_id' => $this->intInput('assignment_id'),
            'instructor_id' => $this->intInput('instructor_id'),
            'course_id' => $this->intInput('course_id'),
            'section_id' => $this->intInput('section_id'),
            'academic_year_id' => $this->intInput('academic_year_id'),
            'semester_id' => $this->intInput('semester_id'),
            'observation_date' => $this->postString('observation_date'),
            'observation_type' => $this->postString('observation_type', 'classroom_observation'),
            'strengths' => $this->postString('strengths'),
            'areas_for_improvement' => $this->postString('areas_for_improvement'),
            'recommendations' => $this->postString('recommendations'),
            'overall_rating' => $this->postString('overall_rating'),
            'status' => $this->postString('status', 'draft'),
        ];
        $back = $this->postString('_return', 'dean/observations/create');
        if ($data['instructor_id'] <= 0 || $data['course_id'] <= 0 || $data['section_id'] <= 0) {
            $this->backWithError('Choose the instructor, course and section being observed.', $back);
        }
        if (!$data['observation_date'] || !strtotime($data['observation_date'])) {
            $this->backWithError('Enter a valid observation date.', $back);
        }
        if ($data['overall_rating'] !== '') {
            $rating = (float) $data['overall_rating'];
            if ($rating < 1 || $rating > 5) {
                $this->backWithError('The overall observation rating must be between 1.00 and 5.00.', $back);
            }
            $data['overall_rating'] = number_format($rating, 2, '.', '');
        } else {
            $data['overall_rating'] = null;
        }
        if (!in_array($data['status'], ['draft', 'completed', 'shared'], true)) {
            $data['status'] = 'draft';
        }
        $semesterYear = Database::scalar('SELECT academic_year_id FROM semesters WHERE id = ?', [$data['semester_id']]);
        if ((int) $semesterYear !== $data['academic_year_id']) {
            $this->backWithError('The selected semester does not belong to the selected academic year.', $back);
        }
        return $data;
    }
}
