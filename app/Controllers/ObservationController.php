<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\COFCriterion;
use App\Models\ClassModel;
use App\Models\Observation;
use App\Models\ObservationAmendment;
use App\Models\ObservationRating;
use App\Models\RateeComment;
use App\Models\Acknowledgement;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\ObservationService;

final class ObservationController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'status', 'faculty_id', 'department_id', 'period_id',
            'academic_year', 'semester', 'date_from', 'date_to',
        ]);
        $page = $this->pageNumber();
        $total = Observation::countFiltered($filters);
        $pagination = paginate($total, $page);

        $this->render('dean/observations/index', [
            'pageTitle'    => 'Classroom Observations',
            'pageSubtitle' => 'Create, complete, review and publish classroom observation records.',
            'activeNav'    => 'observations',
            'rows'         => Observation::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'faculty'      => User::facultyOptions(false),
            'departments'  => \App\Models\Department::options(),
            'periods'      => AcademicPeriod::options(),
            'counts'       => Observation::statsForDean(),
        ]);
    }

    public function create(): void
    {
        (new AuthorizationService())->requireDean();

        $this->render('dean/observations/create', [
            'pageTitle'    => 'New Classroom Observation',
            'pageSubtitle' => 'Select the faculty member, class and observation details, then open the COF.',
            'activeNav'    => 'observations',
            'faculty'      => User::facultyOptions(false),
            'classes'      => ClassModel::options(null, true),
            'assignments'  => Assignment::pending(),
            'periods'      => AcademicPeriod::options(),
        ]);
    }

    public function store(): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $data = [
            'faculty_id'       => trim((string) Request::input('faculty_id', '')),
            'class_id'         => trim((string) Request::input('class_id', '')),
            'assignment_id'    => trim((string) Request::input('assignment_id', '')),
            'observation_date' => trim((string) Request::input('observation_date', '')),
            'lesson_topic'     => trim((string) Request::input('lesson_topic', '')),
            'observer_comments'=> trim((string) Request::input('observer_comments', '')),
        ];

        $validated = $this->validate($data, [
            'faculty_id'       => 'required|integer|exists:users,user_id',
            'class_id'         => 'required|integer|exists:classes,class_id',
            'assignment_id'    => 'nullable|integer|exists:observation_assignments,assignment_id',
            'observation_date' => 'required|date',
            'lesson_topic'     => 'required|max:255',
            'observer_comments'=> 'nullable|max:5000',
        ], [
            'faculty_id'       => 'Faculty member',
            'class_id'         => 'Class',
            'assignment_id'    => 'Assignment',
            'observation_date' => 'Observation date',
            'lesson_topic'     => 'Lesson topic',
            'observer_comments'=> 'Observer comments',
        ], '/dean/observations/create');

        $faculty = User::findFaculty((int) $validated['faculty_id']);
        if ($faculty === null || $faculty['account_status'] !== 'Active') {
            Flash::error('The selected faculty member is not active.');
            redirect('/dean/observations/create');
        }

        $class = ClassModel::findDetailed((int) $validated['class_id']);
        if ($class === null) {
            abort(404, 'Class not found.');
        }
        if ((int) $class['faculty_id'] !== (int) $validated['faculty_id']) {
            Flash::withErrors(['class_id' => 'The selected class is not assigned to that faculty member.'], $data);
            Flash::error('Faculty and class assignment do not match.');
            redirect('/dean/observations/create');
        }
        if ($class['status'] !== 'Active') {
            Flash::error('Archived classes cannot receive new observations.');
            redirect('/dean/observations/create');
        }

        $period = AcademicPeriod::find((int) $class['period_id']);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }
        if ($validated['observation_date'] < $period['period_start'] || $validated['observation_date'] > $period['period_end']) {
            Flash::withErrors([
                'observation_date' => 'The observation date must fall within the class academic period (' . fmt_date($period['period_start']) . ' – ' . fmt_date($period['period_end']) . ').',
            ], $data);
            Flash::error('Observation date is outside the academic period.');
            redirect('/dean/observations/create');
        }

        try {
            $id = (new ObservationService())->createDraft([
                'faculty_id'        => (int) $validated['faculty_id'],
                'class_id'          => (int) $validated['class_id'],
                'assignment_id'     => $validated['assignment_id'] !== '' ? (int) $validated['assignment_id'] : null,
                'observation_date'  => $validated['observation_date'],
                'lesson_topic'      => $validated['lesson_topic'],
                'observer_comments' => $validated['observer_comments'],
            ], $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/observations/create');
        }

        Flash::success('Observation draft created. Complete the 20-item COF below.');
        redirect('/dean/observations/' . $id . '/edit');
    }

    public function show(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $observation = Observation::findDetailed((int) $id);
        if ($observation === null) {
            abort(404, 'Observation not found.');
        }

        $this->render('dean/observations/show', [
            'pageTitle'    => 'Observation — ' . $observation['faculty_name'],
            'pageSubtitle' => $observation['subject_code'] . ' · ' . $observation['section_name'] . ' · ' . fmt_date($observation['observation_date']),
            'activeNav'    => 'observations',
            'observation'  => $observation,
            'scores'       => ObservationRating::scoreMap((int) $observation['observation_id']),
            'criteria'     => COFCriterion::byCategory(),
            'grouped'      => $this->groupedRatings((int) $observation['observation_id']),
            'comments'     => RateeComment::forObservation((int) $observation['observation_id']),
            'acknowledgement' => Acknowledgement::forObservation((int) $observation['observation_id'], (int) $observation['faculty_id']),
            'amendments'   => ObservationAmendment::forObservation((int) $observation['observation_id']),
            'labels'       => rating_labels(),
        ]);
    }

    public function edit(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $observation = Observation::findDetailed((int) $id);
        if ($observation === null) {
            abort(404, 'Observation not found.');
        }
        if ($observation['status'] === Observation::PUBLISHED) {
            Flash::warning('Published observations are amended through the documented amendment workflow.');
            redirect('/dean/observations/' . $observation['observation_id'] . '/amend');
        }
        if (!in_array($observation['status'], [Observation::DRAFT, Observation::RETURNED], true)) {
            Flash::error('This observation cannot be edited while it is ' . $observation['status'] . '.');
            redirect('/dean/observations/' . $observation['observation_id']);
        }

        $stored = ObservationRating::scoreMap((int) $observation['observation_id']);
        $oldRatings = old('ratings', []);
        $scores = $stored;
        if (is_array($oldRatings) && $oldRatings !== []) {
            foreach ($oldRatings as $number => $value) {
                if (is_numeric($value)) {
                    $scores[(int) $number] = (int) $value;
                }
            }
        }

        $this->render('dean/observations/cof', [
            'pageTitle'    => 'COF — ' . $observation['faculty_name'],
            'pageSubtitle' => 'Classroom Observation Form (Version 1, Series 2012) — 20 items',
            'activeNav'    => 'observations',
            'observation'  => $observation,
            'scores'       => $scores,
            'criteria'     => COFCriterion::byCategory(),
            'labels'       => rating_labels(),
            'cofVerified'  => (string) setting('cof_verified', '0') === '1',
            'amendMode'    => false,
        ]);
    }

    public function saveDraft(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $observation = Observation::findDetailed((int) $id);
        if ($observation === null) {
            abort(404, 'Observation not found.');
        }

        $ratings = $this->ratingsFromRequest();
        $comments = (string) Request::input('observer_comments', '');

        try {
            $result = (new ObservationService())->saveDraft((int) $observation['observation_id'], $ratings, $comments, $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/observations/' . $observation['observation_id'] . '/edit');
        }

        if (!$result['valid']) {
            Flash::withErrors(['ratings' => implode(' ', $result['errors'])], ['ratings' => $ratings]);
            Flash::error('Some ratings could not be saved. ' . implode(' ', $result['errors']));
            redirect('/dean/observations/' . $observation['observation_id'] . '/edit');
        }

        if ($result['warnings'] !== []) {
            Flash::warning(implode(' ', $result['warnings']));
        }
        Flash::success('Draft saved. You may continue editing later.');
        redirect('/dean/observations/' . $observation['observation_id'] . '/edit');
    }

    public function submit(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $observation = Observation::findDetailed((int) $id);
        if ($observation === null) {
            abort(404, 'Observation not found.');
        }

        $ratings = $this->ratingsFromRequest();
        $comments = (string) Request::input('observer_comments', '');

        try {
            $result = (new ObservationService())->submit((int) $observation['observation_id'], $ratings, $comments, $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/observations/' . $observation['observation_id'] . '/edit');
        }

        if (!$result['valid']) {
            Flash::withErrors(
                ['ratings' => implode(' ', $result['errors'])],
                ['ratings' => $ratings, 'observer_comments' => $comments]
            );
            Flash::error('The observation cannot be submitted yet. ' . implode(' ', $result['errors']));
            redirect('/dean/observations/' . $observation['observation_id'] . '/edit');
        }

        Flash::success('Observation submitted. Review the record, then publish it to the faculty portal.');
        redirect('/dean/observations/' . $observation['observation_id']);
    }

    public function publish(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        try {
            (new ObservationService())->publish((int) $id, $dean);
            Flash::success('Evaluation published. The faculty member has been notified.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }

        redirect('/dean/observations/' . (int) $id);
    }

    public function returnForCorrection(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $reason = trim((string) Request::input('reason', ''));
        if (strlen($reason) < 10) {
            Flash::error('Provide a correction reason of at least 10 characters.');
            redirect('/dean/observations/' . (int) $id);
        }

        try {
            (new ObservationService())->returnForCorrection((int) $id, $reason, $dean);
            Flash::success('Observation returned for correction.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }

        redirect('/dean/observations/' . (int) $id);
    }

    public function archive(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        try {
            (new ObservationService())->archive((int) $id, $dean);
            Flash::success('Observation archived. All ratings and history are preserved.');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }

        redirect('/dean/observations/' . (int) $id);
    }

    public function discard(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        try {
            (new ObservationService())->deleteDraft((int) $id, $dean);
            Flash::success('Draft discarded.');
            redirect('/dean/observations');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/observations/' . (int) $id);
        }
    }

    public function amendForm(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $observation = Observation::findDetailed((int) $id);
        if ($observation === null) {
            abort(404, 'Observation not found.');
        }
        if ($observation['status'] !== Observation::PUBLISHED) {
            Flash::error('Only published observations can be amended.');
            redirect('/dean/observations/' . $observation['observation_id']);
        }

        $this->render('dean/observations/cof', [
            'pageTitle'    => 'Amend Published COF — ' . $observation['faculty_name'],
            'pageSubtitle' => 'Exceptional amendment: the original ratings and scores will be preserved in the audit trail.',
            'activeNav'    => 'observations',
            'observation'  => $observation,
            'scores'       => ObservationRating::scoreMap((int) $observation['observation_id']),
            'criteria'     => COFCriterion::byCategory(),
            'labels'       => rating_labels(),
            'cofVerified'  => (string) setting('cof_verified', '0') === '1',
            'amendMode'    => true,
        ]);
    }

    public function amend(string $id): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $reason = trim((string) Request::input('amendment_reason', ''));
        if (strlen($reason) < 10) {
            Flash::error('Provide an amendment reason of at least 10 characters.');
            redirect('/dean/observations/' . (int) $id . '/amend');
        }

        $ratings = $this->ratingsFromRequest();
        $comments = (string) Request::input('observer_comments', '');

        try {
            $result = (new ObservationService())->amendPublished((int) $id, $ratings, $comments, $reason, $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/observations/' . (int) $id . '/amend');
        }

        if (!$result['valid']) {
            Flash::withErrors(['ratings' => implode(' ', $result['errors'])], ['ratings' => $ratings]);
            Flash::error('The amendment could not be saved. ' . implode(' ', $result['errors']));
            redirect('/dean/observations/' . (int) $id . '/amend');
        }

        Flash::success('Published observation amended. The original values were preserved in the audit trail.');
        redirect('/dean/observations/' . (int) $id);
    }

    // ------------------------------------------------------------------
    // Observation assignments
    // ------------------------------------------------------------------

    public function assignments(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters(['status', 'faculty_id', 'search']);
        $page = $this->pageNumber();
        $total = Assignment::countFiltered($filters);
        $pagination = paginate($total, $page);

        $this->render('dean/assignments/index', [
            'pageTitle'    => 'Observation Assignments',
            'pageSubtitle' => 'Plan classroom observations and track completion.',
            'activeNav'    => 'assignments',
            'rows'         => Assignment::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'faculty'      => User::facultyOptions(false),
            'classes'      => ClassModel::options(null, true),
        ]);
    }

    public function storeAssignment(): void
    {
        $dean = (new AuthorizationService())->requireDean();

        $data = [
            'faculty_id'   => trim((string) Request::input('faculty_id', '')),
            'class_id'     => trim((string) Request::input('class_id', '')),
            'assigned_date'=> trim((string) Request::input('assigned_date', '')),
            'due_date'     => trim((string) Request::input('due_date', '')),
            'notes'        => trim((string) Request::input('notes', '')),
        ];

        $validated = $this->validate($data, [
            'faculty_id'    => 'required|integer|exists:users,user_id',
            'class_id'      => 'required|integer|exists:classes,class_id',
            'assigned_date' => 'required|date',
            'due_date'      => 'required|date|after_or_equal:assigned_date',
            'notes'         => 'nullable|max:1000',
        ], [
            'faculty_id'    => 'Faculty member',
            'class_id'      => 'Class',
            'assigned_date' => 'Assigned date',
            'due_date'      => 'Due date',
            'notes'         => 'Notes',
        ], '/dean/assignments');

        $class = ClassModel::findDetailed((int) $validated['class_id']);
        if ($class === null || (int) $class['faculty_id'] !== (int) $validated['faculty_id']) {
            Flash::error('The selected class is not assigned to that faculty member.');
            redirect('/dean/assignments');
        }

        $id = Assignment::create([
            'faculty_id'       => (int) $validated['faculty_id'],
            'observer_id'      => (int) $dean['user_id'],
            'class_id'         => (int) $validated['class_id'],
            'assigned_date'    => $validated['assigned_date'],
            'due_date'         => $validated['due_date'],
            'assignment_status'=> 'Pending',
            'notes'            => $validated['notes'] !== '' ? $validated['notes'] : null,
            'created_by'       => (int) $dean['user_id'],
        ]);

        $this->audit('assignment.created', 'Classroom Observation', 'assignment:' . $id, 'Observation assignment created.');
        Flash::success('Observation assignment created.');
        redirect('/dean/assignments');
    }

    public function cancelAssignment(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $assignment = Assignment::findDetailed((int) $id);
        if ($assignment === null) {
            abort(404, 'Assignment not found.');
        }
        if ($assignment['assignment_status'] !== 'Pending') {
            Flash::error('Only pending assignments can be cancelled.');
            redirect('/dean/assignments');
        }

        Assignment::updateById((int) $assignment['assignment_id'], ['assignment_status' => 'Cancelled']);
        $this->audit('assignment.cancelled', 'Classroom Observation', 'assignment:' . $assignment['assignment_id'], 'Assignment cancelled.');
        Flash::success('Assignment cancelled.');
        redirect('/dean/assignments');
    }

    // ------------------------------------------------------------------

    /**
     * @return array<int,array{number:int,text:string,score:?int}>
     */
    private function groupedRatings(int $observationId): array
    {
        $scores = ObservationRating::scoreMap($observationId);
        $grouped = ['Teaching Competence' => [], 'Personality' => []];
        foreach (COFCriterion::active() as $criterion) {
            $number = (int) $criterion['criterion_number'];
            $grouped[$criterion['category']][] = [
                'number' => $number,
                'text'   => (string) $criterion['criterion_text'],
                'score'  => $scores[$number] ?? null,
            ];
        }
        return $grouped;
    }

    /** @return array<int,mixed> */
    private function ratingsFromRequest(): array
    {
        $ratings = Request::input('ratings', []);
        return is_array($ratings) ? $ratings : [];
    }
}
