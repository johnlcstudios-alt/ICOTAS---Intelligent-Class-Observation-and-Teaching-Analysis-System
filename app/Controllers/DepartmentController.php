<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\Department;

final class DepartmentController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $filters = $this->filters(['search', 'status']);
        $page = $this->pageNumber();
        $total = Department::countFiltered($filters);
        $pagination = paginate($total, $page);

        $this->render('dean/departments/index', [
            'pageTitle'    => 'Departments',
            'pageSubtitle' => 'Institutional units, their faculty and evaluation summaries.',
            'activeNav'    => 'departments',
            'rows'         => Department::paginate($filters, (int) $pagination['per_page'], (int) $pagination['offset']),
            'filters'      => $filters,
            'pagination'   => $pagination,
        ]);
    }

    public function store(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $data = [
            'department_name' => trim((string) Request::input('department_name', '')),
            'department_code' => strtoupper(trim((string) Request::input('department_code', ''))),
            'description'     => trim((string) Request::input('description', '')),
        ];

        $validated = $this->validate($data, [
            'department_name' => 'required|max:150',
            'department_code' => 'required|max:20',
            'description'     => 'nullable|max:2000',
        ], [
            'department_name' => 'Department name',
            'department_code' => 'Department code',
            'description'     => 'Description',
        ], '/dean/departments');

        if (Department::findByNameOrCode($validated['department_name'], $validated['department_code']) !== null) {
            Flash::withErrors(['department_name' => 'A department with that name or code already exists.'], $data);
            Flash::error('That department already exists.');
            redirect('/dean/departments');
        }

        $id = Department::create([
            'department_name' => $validated['department_name'],
            'department_code' => $validated['department_code'],
            'description'     => $validated['description'] !== '' ? $validated['description'] : null,
            'status'          => 'Active',
        ]);

        AuditLog::record((int) $dean['user_id'], 'department.created', 'Departments', 'department:' . $id, 'Department created.');
        Flash::success('Department created.');
        redirect('/dean/departments');
    }

    public function update(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $department = Department::find((int) $id);
        if ($department === null) {
            abort(404, 'Department not found.');
        }

        $data = [
            'department_name' => trim((string) Request::input('department_name', '')),
            'department_code' => strtoupper(trim((string) Request::input('department_code', ''))),
            'description'     => trim((string) Request::input('description', '')),
            'status'          => (string) Request::input('status', $department['status']),
        ];

        $validated = $this->validate($data, [
            'department_name' => 'required|max:150',
            'department_code' => 'required|max:20',
            'description'     => 'nullable|max:2000',
            'status'          => 'required|in:Active,Archived',
        ], [
            'department_name' => 'Department name',
            'department_code' => 'Department code',
            'description'     => 'Description',
            'status'          => 'Status',
        ], '/dean/departments');

        $conflict = Department::findByNameOrCode($validated['department_name'], $validated['department_code'], (int) $department['department_id']);
        if ($conflict !== null) {
            Flash::error('Another department already uses that name or code.');
            redirect('/dean/departments');
        }

        if ($validated['status'] === 'Archived' && $department['status'] !== 'Archived') {
            if (Department::hasActiveFaculty((int) $department['department_id']) || Department::hasActiveClasses((int) $department['department_id'])) {
                Flash::error('This department still has active faculty or classes. Reassign or deactivate them before archiving.');
                redirect('/dean/departments');
            }
            AuditLog::record((int) $dean['user_id'], 'department.archived', 'Departments', 'department:' . $department['department_id'], 'Department archived; historical records preserved.');
        }

        Department::updateById((int) $department['department_id'], [
            'department_name' => $validated['department_name'],
            'department_code' => $validated['department_code'],
            'description'     => $validated['description'] !== '' ? $validated['description'] : null,
            'status'          => $validated['status'],
        ]);

        AuditLog::record((int) $dean['user_id'], 'department.updated', 'Departments', 'department:' . $department['department_id'], 'Department details updated.');
        Flash::success('Department updated.');
        redirect('/dean/departments');
    }
}
