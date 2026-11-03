<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Models\AcademicPeriod;
use App\Models\ClassModel;
use App\Models\ClassRecord;
use App\Models\Department;
use App\Models\User;
use App\Services\ClassRecordService;
use App\Services\ReportingService;

final class ClassRecordController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'faculty_id', 'department_id', 'class_id', 'period_id',
            'academic_year', 'semester', 'record_status',
        ]);
        $page = $this->pageNumber();
        $total = ClassRecord::countFiltered($filters);
        $pagination = paginate($total, $page);

        $statistics = ClassRecord::statistics($filters);
        $byPeriod = ClassRecord::byPeriod();
        $byDepartment = ClassRecord::byDepartment();

        $this->render('class-records/index', [
            'pageTitle'    => 'Class Records',
            'pageSubtitle' => 'Academic tallies kept separate from COF observation scores.',
            'activeNav'    => 'class-records',
            'rows'         => ClassRecord::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'statistics'   => $statistics,
            'byPeriod'     => $byPeriod,
            'byDepartment' => $byDepartment,
            'faculty'      => User::facultyOptions(false),
            'departments'  => Department::options(),
            'periods'      => AcademicPeriod::options(),
            'classes'      => ClassModel::options(null, false),
        ]);
    }

    public function create(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $this->render('class-records/form', [
            'pageTitle'    => 'Add Class Record',
            'pageSubtitle' => 'Record student counts and the class average for one offering and period.',
            'activeNav'    => 'class-records',
            'record'       => null,
            'classes'      => ClassModel::options(null, false),
            'periods'      => AcademicPeriod::options(),
            'grading'      => $this->gradingRules(),
        ]);
    }

    public function store(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $input = $this->input();
        $validation = (new ClassRecordService())->validate($input);

        if (!$validation['valid']) {
            Flash::withErrors($validation['errors'], $input);
            Flash::error('Please correct the class record values.');
            redirect('/dean/class-records/create');
        }

        try {
            $id = (new ClassRecordService())->create($validation['data'], $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/class-records/create');
        }

        Flash::success('Class record created.');
        redirect('/dean/class-records/' . $id);
    }

    public function show(string $id): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $record = ClassRecord::findDetailed((int) $id);
        if ($record === null) {
            abort(404, 'Class record not found.');
        }

        $this->render('class-records/show', [
            'pageTitle'    => 'Class Record — ' . $record['subject_code'],
            'pageSubtitle' => $record['section_name'] . ' · ' . $record['academic_year'] . ' · ' . semester_abbr((string) $record['semester']),
            'activeNav'    => 'class-records',
            'record'       => $record,
            'revisions'    => ClassRecord::revisions((int) $record['record_id']),
            'statistics'   => $this->recordStatistics($record),
        ]);
    }

    public function edit(string $id): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $record = ClassRecord::findDetailed((int) $id);
        if ($record === null) {
            abort(404, 'Class record not found.');
        }

        $this->render('class-records/form', [
            'pageTitle'    => 'Edit Class Record',
            'pageSubtitle' => 'Changes are snapshotted to the class record revision history.',
            'activeNav'    => 'class-records',
            'record'       => $record,
            'classes'      => ClassModel::options(null, false),
            'periods'      => AcademicPeriod::options(),
            'grading'      => $this->gradingRules(),
        ]);
    }

    public function update(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $record = ClassRecord::findDetailed((int) $id);
        if ($record === null) {
            abort(404, 'Class record not found.');
        }

        $input = $this->input();
        // Class and period are fixed once a record exists.
        $input['class_id'] = (string) $record['class_id'];
        $input['period_id'] = (string) $record['period_id'];

        $validation = (new ClassRecordService())->validate($input);
        if (!$validation['valid']) {
            Flash::withErrors($validation['errors'], $input);
            Flash::error('Please correct the class record values.');
            redirect('/dean/class-records/' . $record['record_id'] . '/edit');
        }

        try {
            (new ClassRecordService())->update((int) $record['record_id'], $validation['data'], $dean);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/class-records/' . $record['record_id'] . '/edit');
        }

        Flash::success('Class record updated.');
        redirect('/dean/class-records/' . $record['record_id']);
    }

    public function destroy(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        try {
            (new ClassRecordService())->delete((int) $id, $dean);
            Flash::success('Draft class record deleted.');
            redirect('/dean/class-records');
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            redirect('/dean/class-records/' . (int) $id);
        }
    }

    public function export(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'faculty_id', 'department_id', 'class_id', 'period_id',
            'academic_year', 'semester', 'record_status',
        ]);
        $rows = ClassRecord::allForExport($filters);

        $reporting = new ReportingService();
        $csv = $reporting->toCsv(
            ['Subject Code', 'Subject Name', 'Section', 'Academic Year', 'Semester', 'Faculty', 'Department', 'Total Students', 'Passed', 'Failed', 'Average Grade', 'Remarks', 'Status', 'Created', 'Updated'],
            array_map(static fn (array $r): array => [
                $r['subject_code'],
                $r['subject_name'],
                $r['section_name'],
                $r['academic_year'],
                $r['semester'],
                $r['faculty_name'],
                $r['department_name'],
                (int) $r['total_students'],
                (int) $r['passed_count'],
                (int) $r['failed_count'],
                $r['average_grade'],
                $r['remarks'],
                $r['record_status'],
                $r['created_at'],
                $r['updated_at'],
            ], $rows)
        );

        $filename = $reporting->safeFilename('icotas-class-records');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    public function printReport(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters([
            'search', 'faculty_id', 'department_id', 'class_id', 'period_id',
            'academic_year', 'semester', 'record_status',
        ]);

        $this->render('reports/class-records', [
            'pageTitle'   => 'Class Record Report',
            'institution' => (new ReportingService())->institution(),
            'rows'        => ClassRecord::allForExport($filters),
            'statistics'  => ClassRecord::statistics($filters),
            'filters'     => $filters,
            'printMode'   => true,
        ], 'layouts/print');
    }

    private function input(): array
    {
        return [
            'class_id'       => trim((string) Request::input('class_id', '')),
            'period_id'      => trim((string) Request::input('period_id', '')),
            'total_students' => trim((string) Request::input('total_students', '')),
            'passed_count'   => trim((string) Request::input('passed_count', '')),
            'failed_count'   => trim((string) Request::input('failed_count', '')),
            'average_grade'  => trim((string) Request::input('average_grade', '')),
            'remarks'        => trim((string) Request::input('remarks', '')),
            'record_status'  => (string) Request::input('record_status', 'Draft'),
        ];
    }

    private function gradingRules(): array
    {
        return [
            'min'      => (float) setting('grading_min', 0),
            'max'      => (float) setting('grading_max', 100),
            'passing'  => (float) setting('passing_grade', 75),
            'complete' => (string) setting('class_record_require_complete', '1') === '1',
        ];
    }

    private function recordStatistics(array $record): array
    {
        $total = (int) $record['total_students'];
        $passed = (int) $record['passed_count'];
        $failed = (int) $record['failed_count'];

        return [
            'passing_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0.0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0.0,
            'unaccounted'  => max(0, $total - $passed - $failed),
        ];
    }
}
