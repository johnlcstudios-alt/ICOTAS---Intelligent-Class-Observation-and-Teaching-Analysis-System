<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Acknowledgement;
use App\Models\AuditLog;
use App\Models\COFCriterion;
use App\Models\ClassModel;
use App\Models\Notification;
use App\Models\Observation;
use App\Models\ObservationRating;
use App\Models\RateeComment;
use App\Services\AnalyticsService;
use App\Services\AuthorizationService;
use App\Services\ReportingService;

final class FacultyController extends Controller
{
    public function evaluations(): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $facultyId = (int) $faculty['user_id'];
        $page = $this->pageNumber();

        $total = Observation::countPublishedForFaculty($facultyId);
        $pagination = paginate($total, $page);
        $rows = Observation::publishedForFaculty($facultyId, (int) $pagination['per_page'], (int) $pagination['offset']);

        $this->render('faculty/evaluations/index', [
            'pageTitle'   => 'My Evaluations',
            'pageSubtitle'=> 'Only evaluations published by the Dean are shown here.',
            'activeNav'   => 'evaluations',
            'rows'        => $rows,
            'pagination'  => $pagination,
            'qrVerified'  => (int) \App\Core\Session::get('qr_verified_faculty', 0) === $facultyId,
            'stats'       => Observation::statsForFaculty($facultyId),
        ]);
    }

    public function show(string $id): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $observation = (new AuthorizationService())->requirePublishedObservation((int) $id, (int) $faculty['user_id']);

        $observationId = (int) $observation['observation_id'];

        $this->render('faculty/evaluations/show', [
            'pageTitle'    => 'Evaluation — ' . fmt_date($observation['observation_date']),
            'pageSubtitle' => $observation['subject_code'] . ' · ' . $observation['section_name'],
            'activeNav'    => 'evaluations',
            'observation'  => $observation,
            'grouped'      => $this->groupedRatings($observationId),
            'comments'     => RateeComment::forObservation($observationId),
            'acknowledgement' => Acknowledgement::forObservation($observationId, (int) $faculty['user_id']),
            'labels'       => rating_labels(),
            'qrVerified'   => (int) \App\Core\Session::get('qr_verified_faculty', 0) === (int) $faculty['user_id'],
        ]);
    }

    public function addComment(string $id): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $observation = (new AuthorizationService())->requirePublishedObservation((int) $id, (int) $faculty['user_id']);

        $text = trim((string) Request::input('comment_text', ''));
        if (strlen($text) < 5) {
            Flash::error('Your comment must be at least 5 characters.');
            redirect('/faculty/evaluations/' . $observation['observation_id'] . '#comments');
        }
        if (strlen($text) > 2000) {
            Flash::error('Your comment must not exceed 2000 characters.');
            redirect('/faculty/evaluations/' . $observation['observation_id'] . '#comments');
        }

        $commentId = RateeComment::add((int) $observation['observation_id'], (int) $faculty['user_id'], $text);

        AuditLog::record((int) $faculty['user_id'], 'ratee_comment.created', 'Faculty Portal', 'observation:' . $observation['observation_id'], 'Ratee comment #' . $commentId . ' added.');
        try {
            Notification::pushToDeans(
                'New ratee comment',
                $faculty['full_name'] . ' submitted a comment on an evaluation dated ' . $observation['observation_date'] . '.',
                '/dean/observations/' . $observation['observation_id'],
                'info'
            );
        } catch (\Throwable $e) {
            app_log('warning', 'Ratee comment notification failed: ' . $e->getMessage());
        }

        Flash::success('Your comment has been saved. Observer ratings are unchanged.');
        redirect('/faculty/evaluations/' . $observation['observation_id'] . '#comments');
    }

    public function acknowledge(string $id): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $observation = (new AuthorizationService())->requirePublishedObservation((int) $id, (int) $faculty['user_id']);

        $remarks = trim((string) Request::input('remarks', ''));
        if (strlen($remarks) > 1000) {
            Flash::error('Acknowledgement remarks must not exceed 1000 characters.');
            redirect('/faculty/evaluations/' . $observation['observation_id']);
        }

        Acknowledgement::acknowledge(
            (int) $observation['observation_id'],
            (int) $faculty['user_id'],
            $remarks !== '' ? $remarks : null
        );

        AuditLog::record((int) $faculty['user_id'], 'acknowledgement.submitted', 'Faculty Portal', 'observation:' . $observation['observation_id'], 'Faculty acknowledged receipt of the evaluation.');
        try {
            Notification::pushToDeans(
                'Evaluation acknowledged',
                $faculty['full_name'] . ' acknowledged the evaluation dated ' . $observation['observation_date'] . '. Receipt does not imply agreement.',
                '/dean/observations/' . $observation['observation_id'],
                'success'
            );
        } catch (\Throwable $e) {
            app_log('warning', 'Acknowledgement notification failed: ' . $e->getMessage());
        }

        Flash::success('Acknowledgement recorded. This confirms receipt, not necessarily agreement.');
        redirect('/faculty/evaluations/' . $observation['observation_id']);
    }

    public function trends(): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $facultyId = (int) $faculty['user_id'];

        $analytics = new AnalyticsService();
        $data = $analytics->facultyDashboard($facultyId);

        $this->render('faculty/trends', [
            'pageTitle'    => 'My Performance Trends',
            'pageSubtitle' => 'Chronological view of your published observation results.',
            'activeNav'    => 'trends',
            'stats'        => $data['stats'],
            'trend'        => $data['trend'],
            'distribution' => $data['distribution'],
            'byPeriod'     => $this->periodAverages($facultyId),
        ]);
    }

    public function profile(): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $facultyId = (int) $faculty['user_id'];

        $this->render('faculty/profile', [
            'pageTitle'    => 'My Faculty Profile',
            'pageSubtitle' => 'Account details, department and assigned classes.',
            'activeNav'    => 'profile',
            'faculty'      => $faculty,
            'classes'      => ClassModel::forFaculty($facultyId),
            'classesByPeriod' => $this->classesByPeriod($facultyId),
        ]);
    }

    public function report(string $id): void
    {
        $faculty = (new AuthorizationService())->requireFaculty();
        $observation = (new AuthorizationService())->requirePublishedObservation((int) $id, (int) $faculty['user_id']);

        $report = (new ReportingService())->facultyObservation((int) $observation['observation_id']);
        if ($report === null) {
            abort(404, 'Report not available.');
        }

        $this->render('reports/faculty-observation', [
            'pageTitle'   => 'Observation Report',
            'institution' => (new ReportingService())->institution(),
            'report'      => $report,
            'labels'      => rating_labels(),
            'printMode'   => true,
        ], 'layouts/print');
    }

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

    private function periodAverages(int $facultyId): array
    {
        $rows = Observation::periodAveragesForFaculty($facultyId);
        return array_map(static fn (array $r): array => [
            'label'        => $r['academic_year'] . ' · ' . semester_abbr((string) $r['semester']),
            'teaching'     => $r['teaching_avg'] !== null ? (float) $r['teaching_avg'] : null,
            'personality'  => $r['personality_avg'] !== null ? (float) $r['personality_avg'] : null,
            'overall'      => $r['overall_avg'] !== null ? (float) $r['overall_avg'] : null,
            'observations' => (int) $r['observation_count'],
        ], $rows);
    }

    private function classesByPeriod(int $facultyId): array
    {
        $classes = ClassModel::forFaculty($facultyId);
        $grouped = [];
        foreach ($classes as $class) {
            $key = $class['academic_year'] . ' · ' . semester_abbr((string) $class['semester']);
            $grouped[$key][] = $class;
        }
        return $grouped;
    }
}
