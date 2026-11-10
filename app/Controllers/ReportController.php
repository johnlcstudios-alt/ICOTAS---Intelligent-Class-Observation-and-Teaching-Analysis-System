<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Academic;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\ReportService;

final class ReportController extends BaseController
{
    public function index(): void
    {
        $base = $this->adminBase();
        $service = new AnalyticsService();
        $options = $service->filterOptions($this->demo());
        $instructors = ($base === 'instructor')
            ? [(new User())->findWithRelations((int) Auth::id())]
            : $options['instructors'];
        $types = ReportService::typesFor($base === 'instructor');

        $this->view($base . '/reports/index', [
            'pageTitle' => 'Reports',
            'types' => $types,
            'options' => $options,
            'instructors' => array_values(array_filter($instructors)),
            'uatSurveys' => \App\Core\Database::fetchAll(
                'SELECT id, title, status FROM uat_surveys WHERE is_demo = ? ORDER BY created_at DESC',
                [$this->demo() ? 1 : 0]
            ),
            'base' => $base,
            'demo' => $this->demo(),
        ]);
    }

    public function show(): void
    {
        $base = $this->adminBase();
        $type = (string) $this->input('type', 'faculty_summary');
        if (!ReportService::allowedFor($type, Auth::role() === 'instructor')) {
            abort(403, 'This report type is not available for your role.');
        }
        $filters = $this->filters();
        if (Auth::role() === 'instructor') {
            $filters['instructor_id'] = (int) Auth::id();
        }
        $service = new ReportService();
        $viewer = Auth::user();
        $report = $service->build($type, $filters, $this->demo(), $viewer);
        if (isset($report['error'])) {
            $this->backWithError($report['error'], $base . '/reports');
        }
        $service->recordHistory($report, (int) Auth::id(), 'html');
        $this->printView($base . '/reports/view', [
            'pageTitle' => $report['title'],
            'report' => $report,
        ]);
    }

    public function export(): void
    {
        $this->requirePost();
        $base = $this->adminBase();
        $type = $this->postString('type', 'faculty_summary');
        $format = strtolower($this->postString('format', 'csv'));
        if (!in_array($format, ['csv', 'pdf', 'html'], true)) {
            $format = 'csv';
        }
        if (!ReportService::allowedFor($type, Auth::role() === 'instructor')) {
            abort(403, 'This report type is not available for your role.');
        }
        $filters = $this->filters();
        if (Auth::role() === 'instructor') {
            $filters['instructor_id'] = (int) Auth::id();
        }
        $service = new ReportService();
        $report = $service->build($type, $filters, $this->demo(), Auth::user());
        if (isset($report['error'])) {
            $this->backWithError($report['error'], $base . '/reports');
        }
        if ($format === 'pdf') {
            $service->recordHistory($report, (int) Auth::id(), 'pdf');
            $service->toPdf($report);
            exit;
        }
        if ($format === 'csv') {
            $service->recordHistory($report, (int) Auth::id(), 'csv');
            $service->toCsv($report);
            exit;
        }
        $service->recordHistory($report, (int) Auth::id(), 'html');
        $query = $this->filterQuery($type, $filters);
        redirect($base . '/reports/view?' . $query);
    }

    private function filters(): array
    {
        return [
            'academic_year_id' => $this->intInput('academic_year_id') ?: null,
            'semester_id' => $this->intInput('semester_id') ?: null,
            'department_id' => $this->intInput('department_id') ?: null,
            'program_id' => $this->intInput('program_id') ?: null,
            'instructor_id' => $this->intInput('instructor_id') ?: null,
            'course_id' => $this->intInput('course_id') ?: null,
            'section_id' => $this->intInput('section_id') ?: null,
            'session_id' => $this->intInput('session_id') ?: null,
            'uat_survey_id' => $this->intInput('uat_survey_id') ?: null,
            'date_from' => $this->postString('date_from') ?: null,
            'date_to' => $this->postString('date_to') ?: null,
        ];
    }

    private function filterQuery(string $type, array $filters): string
    {
        $query = ['type' => $type];
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }
        return http_build_query($query);
    }
}
