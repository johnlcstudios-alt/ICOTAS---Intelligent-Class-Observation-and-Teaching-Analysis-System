<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\Department;
use App\Models\User;

final class ClassController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters(['search', 'faculty_id', 'department_id', 'period_id', 'academic_year', 'semester', 'status']);
        $page = $this->pageNumber();
        $total = ClassModel::countFiltered($filters);
        $pagination = paginate($total, $page);

        $this->render('dean/classes/index', [
            'pageTitle'    => 'Classes and Subjects',
            'pageSubtitle' => 'Subject offerings, sections and faculty assignments per academic period.',
            'activeNav'    => 'classes',
            'rows'         => ClassModel::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
            'departments'  => Department::options(),
            'faculty'      => User::facultyOptions(false),
            'periods'      => AcademicPeriod::options(),
        ]);
    }

    public function create(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $this->render('dean/classes/form', [
            'pageTitle'    => 'Add Class Offering',
            'pageSubtitle' => 'Assign a subject and section to a faculty member for an academic period.',
            'activeNav'    => 'classes',
            'class'        => null,
            'departments'  => Department::options(true),
            'faculty'      => User::facultyOptions(true),
            'periods'      => AcademicPeriod::options(),
        ]);
    }

    public function store(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $data = [
            'subject_code'  => strtoupper(trim((string) Request::input('subject_code', ''))),
            'subject_name'  => trim((string) Request::input('subject_name', '')),
            'section_name'  => strtoupper(trim((string) Request::input('section_name', ''))),
            'department_id' => trim((string) Request::input('department_id', '')),
            'faculty_id'    => trim((string) Request::input('faculty_id', '')),
            'period_id'     => trim((string) Request::input('period_id', '')),
        ];

        $validated = $this->validate($data, [
            'subject_code'  => 'required|max:30',
            'subject_name'  => 'required|max:150',
            'section_name'  => 'required|max:60',
            'department_id' => 'required|integer|exists:departments,department_id',
            'faculty_id'    => 'required|integer|exists:users,user_id',
            'period_id'     => 'required|integer|exists:academic_periods,period_id',
        ], [
            'subject_code'  => 'Subject code',
            'subject_name'  => 'Subject name',
            'section_name'  => 'Section',
            'department_id' => 'Department',
            'faculty_id'    => 'Faculty member',
            'period_id'     => 'Academic period',
        ], '/dean/classes/create');

        $faculty = User::findFaculty((int) $validated['faculty_id']);
        if ($faculty === null || $faculty['account_status'] !== 'Active') {
            Flash::withErrors(['faculty_id' => 'Select an active faculty member.'], $data);
            Flash::error('The selected faculty member is not active.');
            redirect('/dean/classes/create');
        }

        $period = AcademicPeriod::find((int) $validated['period_id']);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }

        $duplicate = ClassModel::findOffering((int) $period['period_id'], $validated['subject_code'], $validated['section_name']);
        if ($duplicate !== null) {
            Flash::error('That subject, section and period combination already exists.');
            redirect('/dean/classes/create');
        }

        $id = ClassModel::create([
            'subject_code'  => $validated['subject_code'],
            'subject_name'  => $validated['subject_name'],
            'section_name'  => $validated['section_name'],
            'department_id' => (int) $validated['department_id'],
            'faculty_id'    => (int) $validated['faculty_id'],
            'period_id'     => (int) $period['period_id'],
            'academic_year' => (string) $period['academic_year'],
            'semester'      => (string) $period['semester'],
            'status'        => 'Active',
        ]);

        AuditLog::record((int) $dean['user_id'], 'class.created', 'Classes', 'class:' . $id, 'Class offering created.');
        Flash::success('Class offering created.');
        redirect('/dean/classes');
    }

    public function edit(string $id): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $class = ClassModel::findDetailed((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }

        $this->render('dean/classes/form', [
            'pageTitle'    => 'Edit ' . $class['subject_code'] . ' — ' . $class['section_name'],
            'pageSubtitle' => 'Update the class offering. Historical observations protect key fields.',
            'activeNav'    => 'classes',
            'class'        => $class,
            'departments'  => Department::options(),
            'faculty'      => User::facultyOptions(false),
            'periods'      => AcademicPeriod::options(),
            'hasHistory'   => ClassModel::hasObservations((int) $class['class_id']),
        ]);
    }

    public function update(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $class = ClassModel::findDetailed((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }

        $hasHistory = ClassModel::hasObservations((int) $class['class_id']);

        $data = [
            'subject_code'  => strtoupper(trim((string) Request::input('subject_code', ''))),
            'subject_name'  => trim((string) Request::input('subject_name', '')),
            'section_name'  => strtoupper(trim((string) Request::input('section_name', ''))),
            'department_id' => trim((string) Request::input('department_id', '')),
            'faculty_id'    => trim((string) Request::input('faculty_id', '')),
            'period_id'     => trim((string) Request::input('period_id', '')),
            'status'        => (string) Request::input('status', $class['status']),
        ];

        $validated = $this->validate($data, [
            'subject_code'  => 'required|max:30',
            'subject_name'  => 'required|max:150',
            'section_name'  => 'required|max:60',
            'department_id' => 'required|integer|exists:departments,department_id',
            'faculty_id'    => 'required|integer|exists:users,user_id',
            'period_id'     => 'required|integer|exists:academic_periods,period_id',
            'status'        => 'required|in:Active,Archived',
        ], [
            'subject_code'  => 'Subject code',
            'subject_name'  => 'Subject name',
            'section_name'  => 'Section',
            'department_id' => 'Department',
            'faculty_id'    => 'Faculty member',
            'period_id'     => 'Academic period',
            'status'        => 'Status',
        ], '/dean/classes/' . $class['class_id'] . '/edit');

        if ($hasHistory && ((int) $validated['faculty_id'] !== (int) $class['faculty_id']
            || (int) $validated['period_id'] !== (int) $class['period_id']
            || $validated['subject_code'] !== $class['subject_code']
            || $validated['section_name'] !== $class['section_name'])) {
            Flash::error('This class already has observation history. Faculty, period, subject code and section cannot be changed; create a new class offering instead.');
            redirect('/dean/classes/' . $class['class_id'] . '/edit');
        }

        $period = AcademicPeriod::find((int) $validated['period_id']);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }

        $faculty = User::findFaculty((int) $validated['faculty_id']);
        if ($faculty === null) {
            Flash::error('The selected faculty member is invalid.');
            redirect('/dean/classes/' . $class['class_id'] . '/edit');
        }

        ClassModel::updateById((int) $class['class_id'], [
            'subject_code'  => $validated['subject_code'],
            'subject_name'  => $validated['subject_name'],
            'section_name'  => $validated['section_name'],
            'department_id' => (int) $validated['department_id'],
            'faculty_id'    => (int) $validated['faculty_id'],
            'period_id'     => (int) $period['period_id'],
            'academic_year' => (string) $period['academic_year'],
            'semester'      => (string) $period['semester'],
            'status'        => $validated['status'],
        ]);

        AuditLog::record((int) $dean['user_id'], 'class.updated', 'Classes', 'class:' . $class['class_id'], 'Class offering updated.');
        Flash::success('Class offering updated.');
        redirect('/dean/classes');
    }

    public function archive(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $class = ClassModel::findDetailed((int) $id);
        if ($class === null) {
            abort(404, 'Class not found.');
        }

        ClassModel::updateById((int) $class['class_id'], ['status' => 'Archived']);
        AuditLog::record((int) $dean['user_id'], 'class.archived', 'Classes', 'class:' . $class['class_id'], 'Class archived; history preserved.');
        Flash::success('Class archived. Existing observations and class records are preserved.');
        redirect('/dean/classes');
    }
}
