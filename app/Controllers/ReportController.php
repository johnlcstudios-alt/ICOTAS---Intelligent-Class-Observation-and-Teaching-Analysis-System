<?php

namespace App\Controllers;

use App\Models\AcademicPeriod;
use App\Models\ClassRecord;
use App\Models\Department;
use App\Models\Observation;
use App\Models\PredictionRun;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuthorizationService;
use App\Services\ReportingService;

/**
 * Dean reporting suite. Printable reports and CSV exports both read the same
 * database rows; CSV output is generated from the same queries as the page.
 */
final class ReportController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $this->render('dean/reports/index', [
            'pageTitle'    => 'Reports',
            'pageSubtitle' => 'Printable and exportable institutional reports.',
            'activeNav'    => 'reports',
            'publishedCount' => (int) (new AnalyticsService())->summary()['published'],
            'facultyCount' => User::countFacultyTotal(),
            'classRecordCount' => ClassRecord::statistics()['record_count'],
            'predictionCount' => count(PredictionRun::latestPerFaculty()),
        ]);
    }

    public function observations(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->reportFilters();
        $filters['status'] = 'Published';

        $page = $this->pageNumber();
        $total = Observation::countFiltered($filters);
        $pagination = paginate($total, $page);

        $analytics = new AnalyticsService();

        $this->render('dean/reports/observations', [
            'pageTitle'    => 'Observation Summary Report',
            'pageSubtitle' => 'Published classroom observation results with institutional roll-ups.',
            'activeNav'    => 'reports',
            'rows'         => Observation::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'summary'      => $analytics->summary($filters),
            'byDepartment' => $analytics->byDepartment($filters),
            'byPeriod'     => $analytics->byPeriod($filters),
            'facultyOptions' => User::facultyOptions(false),
            'departments'  => Department::options(),
            'periods'      => AcademicPeriod::options(),
        ]);
    }

    public function exportObservations(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->reportFilters();
        $filters['status'] = 'Published';
        $rows = Observation::allFiltered($filters);

        $reporting = new ReportingService();
        $csv = $reporting->toCsv(
            ['Observation ID', 'Observation Date', 'Faculty', 'Employee No', 'Subject Code', 'Subject', 'Section',
             'Department', 'Academic Year', 'Semester', 'Lesson Topic', 'Teaching Competence', 'Personality', 'Overall', 'Published At'],
            array_map(static fn (array $r): array => [
                (int) $r['observation_id'],
                $r['observation_date'],
                $r['faculty_name'],
                $r['employee_no'],
                $r['subject_code'],
                $r['subject_name'],
                $r['section_name'],
                $r['department_name'],
                $r['academic_year'],
                $r['semester'],
                $r['lesson_topic'],
                $r['teaching_competence_avg'],
                $r['personality_avg'],
                $r['overall_avg'],
                $r['published_at'],
            ], $rows)
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reporting->safeFilename('icotas-observation-summary') . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    public function facultyHistory(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $faculty = User::findFaculty((int) $id);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }

        $facultyId = (int) $faculty['user_id'];
        $report = (new ReportingService())->facultyHistory($facultyId);

        $this->render('dean/reports/faculty-history', [
            'pageTitle'    => 'Faculty History — ' . $faculty['full_name'],
            'pageSubtitle' => 'Chronological record of published classroom observations.',
            'activeNav'    => 'reports',
            'faculty'      => $faculty,
            'observations' => $report['observations'],
            'stats'        => Observation::statsForFaculty($facultyId),
            'trend'        => Observation::trendForFaculty($facultyId),
            'institution'  => (new ReportingService())->institution(),
        ]);
    }

    public function observation(string $id): void
    {
        (new AuthorizationService())->requireDean();

        $report = (new ReportingService())->facultyObservation((int) $id);
        if ($report === null) {
            abort(404, 'Observation not found.');
        }

        $this->render('reports/faculty-observation', [
            'pageTitle'   => 'Observation Report',
            'institution' => (new ReportingService())->institution(),
            'report'      => $report,
            'labels'      => rating_labels(),
            'printMode'   => true,
        ], 'layouts/print');
    }

    /**
     * @return array<string,string>
     */
    private function reportFilters(): array
    {
        return $this->filters([
            'faculty_id', 'department_id', 'period_id', 'academic_year',
            'semester', 'date_from', 'date_to', 'search',
        ]);
    }
}
