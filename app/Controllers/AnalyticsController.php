<?php

namespace App\Controllers;

use App\Models\AcademicPeriod;
use App\Models\ClassModel;
use App\Models\COFCriterion;
use App\Models\Department;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuthorizationService;
use App\Services\ReportingService;

/**
 * Dean analytics workspace. Every figure is produced from database rows by
 * AnalyticsService; there are no hardcoded dashboard values.
 */
final class AnalyticsController extends Controller
{
    public function index(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters([
            'faculty_id', 'department_id', 'class_id', 'period_id',
            'academic_year', 'semester', 'date_from', 'date_to',
        ]);

        $analytics = new AnalyticsService();
        $byFaculty = $analytics->byFaculty($filters);

        $this->render('dean/analytics/index', [
            'pageTitle'      => 'Teaching Analytics',
            'pageSubtitle'   => 'Published COF results analysed by faculty, department, period and academic year.',
            'activeNav'      => 'analytics',
            'filters'        => $filters,
            'summary'        => $analytics->summary($filters),
            'statusCounts'   => $analytics->statusCounts($filters),
            'byFaculty'      => $byFaculty,
            'byDepartment'   => $analytics->byDepartment($filters),
            'byPeriod'       => $analytics->byPeriod($filters),
            'byYear'         => $analytics->byAcademicYear($filters),
            'monthly'        => $analytics->monthlyActivity($filters),
            'periodTrend'    => $analytics->periodTrend($filters),
            'distribution'   => $analytics->distribution($filters),
            'facultyOptions' => User::facultyOptions(false),
            'departments'    => Department::options(),
            'periods'        => AcademicPeriod::options(),
            'classes'        => ClassModel::options(null, false),
            'ratings'        => COFCriterion::countsForValidation(),
            'labels'         => rating_labels(),
        ]);
    }

    /**
     * CSV export of the per-faculty comparison table, honouring the filters.
     */
    public function export(): void
    {
        (new AuthorizationService())->requireDean();

        $filters = $this->filters([
            'faculty_id', 'department_id', 'class_id', 'period_id',
            'academic_year', 'semester', 'date_from', 'date_to',
        ]);

        $rows = (new AnalyticsService())->byFaculty($filters);
        $reporting = new ReportingService();
        $csv = $reporting->toCsv(
            ['Faculty', 'Department', 'Observations', 'Teaching Competence Average', 'Personality Average', 'Overall Average'],
            array_map(static fn (array $r): array => [
                $r['full_name'],
                $r['department_code'],
                (int) $r['observation_count'],
                $r['avg_teaching'],
                $r['avg_personality'],
                $r['avg_overall'],
            ], $rows)
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $reporting->safeFilename('icotas-analytics') . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }
}
