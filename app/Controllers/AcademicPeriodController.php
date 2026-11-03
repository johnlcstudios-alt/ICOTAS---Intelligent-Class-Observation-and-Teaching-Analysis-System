<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;

final class AcademicPeriodController extends Controller
{
    public function index(): void
    {
        (new \App\Services\AuthorizationService())->requireDean();

        $this->render('dean/periods/index', [
            'pageTitle'    => 'Academic Periods',
            'pageSubtitle' => 'Academic years and semesters used across observations and class records.',
            'activeNav'    => 'periods',
            'periods'      => AcademicPeriod::withCounts(),
            'current'      => AcademicPeriod::current(),
        ]);
    }

    public function store(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $data = [
            'academic_year' => trim((string) Request::input('academic_year', '')),
            'semester'      => (string) Request::input('semester', ''),
            'period_start'  => trim((string) Request::input('period_start', '')),
            'period_end'    => trim((string) Request::input('period_end', '')),
            'notes'         => trim((string) Request::input('notes', '')),
        ];

        if (!preg_match('/^\d{4}-\d{4}$/', $data['academic_year'])) {
            Flash::withErrors(['academic_year' => 'Use the format YYYY-YYYY (for example 2026-2027).'], $data);
            Flash::error('The academic year format is invalid.');
            redirect('/dean/periods');
        }

        $validated = $this->validate($data, [
            'academic_year' => 'required|max:20',
            'semester'      => 'required|in:First Semester,Second Semester,Summer',
            'period_start'  => 'required|date',
            'period_end'    => 'required|date|after_or_equal:period_start',
            'notes'         => 'nullable|max:255',
        ], [
            'academic_year' => 'Academic year',
            'semester'      => 'Semester',
            'period_start'  => 'Start date',
            'period_end'    => 'End date',
            'notes'         => 'Notes',
        ], '/dean/periods');

        $exists = AcademicPeriod::whereYearSemester($validated['academic_year'], $validated['semester']);
        if ($exists !== null) {
            Flash::error('That academic year and semester already exists.');
            redirect('/dean/periods');
        }

        $id = AcademicPeriod::create([
            'academic_year' => $validated['academic_year'],
            'semester'      => $validated['semester'],
            'period_start'  => $validated['period_start'],
            'period_end'    => $validated['period_end'],
            'notes'         => $validated['notes'] !== '' ? $validated['notes'] : null,
            'status'        => 'Upcoming',
        ]);

        AuditLog::record((int) $dean['user_id'], 'period.created', 'Academic Periods', 'period:' . $id, 'Academic period created.');
        Flash::success('Academic period created.');
        redirect('/dean/periods');
    }

    public function update(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $period = AcademicPeriod::find((int) $id);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }

        $hasHistory = AcademicPeriod::hasObservations((int) $period['period_id'])
            || AcademicPeriod::hasClassRecords((int) $period['period_id']);

        $data = [
            'academic_year' => trim((string) Request::input('academic_year', '')),
            'semester'      => (string) Request::input('semester', ''),
            'period_start'  => trim((string) Request::input('period_start', '')),
            'period_end'    => trim((string) Request::input('period_end', '')),
            'notes'         => trim((string) Request::input('notes', '')),
        ];

        if ($hasHistory) {
            // Only notes may change once evaluation or class-record history exists.
            $notesOnly = $this->validate(['notes' => $data['notes']], [
                'notes' => 'nullable|max:255',
            ], ['notes' => 'Notes'], '/dean/periods');

            AcademicPeriod::updateById((int) $period['period_id'], [
                'notes' => $notesOnly['notes'] !== '' ? $notesOnly['notes'] : null,
            ]);

            AuditLog::record((int) $dean['user_id'], 'period.notes_updated', 'Academic Periods', 'period:' . $period['period_id'], 'Only notes updated because historical records exist.');
            Flash::warning('This period has historical records, so only notes were updated. Dates and labels are protected.');
            redirect('/dean/periods');
        }

        if (!preg_match('/^\d{4}-\d{4}$/', $data['academic_year'])) {
            Flash::error('The academic year format is invalid.');
            redirect('/dean/periods');
        }

        $validated = $this->validate($data, [
            'academic_year' => 'required|max:20',
            'semester'      => 'required|in:First Semester,Second Semester,Summer',
            'period_start'  => 'required|date',
            'period_end'    => 'required|date|after_or_equal:period_start',
            'notes'         => 'nullable|max:255',
        ], [
            'academic_year' => 'Academic year',
            'semester'      => 'Semester',
            'period_start'  => 'Start date',
            'period_end'    => 'End date',
            'notes'         => 'Notes',
        ], '/dean/periods');

        $conflict = AcademicPeriod::whereYearSemester($validated['academic_year'], $validated['semester']);
        if ($conflict !== null && (int) $conflict['period_id'] !== (int) $period['period_id']) {
            Flash::error('Another period already uses that academic year and semester.');
            redirect('/dean/periods');
        }

        AcademicPeriod::updateById((int) $period['period_id'], [
            'academic_year' => $validated['academic_year'],
            'semester'      => $validated['semester'],
            'period_start'  => $validated['period_start'],
            'period_end'    => $validated['period_end'],
            'notes'         => $validated['notes'] !== '' ? $validated['notes'] : null,
        ]);

        AuditLog::record((int) $dean['user_id'], 'period.updated', 'Academic Periods', 'period:' . $period['period_id'], 'Academic period updated.');
        Flash::success('Academic period updated.');
        redirect('/dean/periods');
    }

    public function setCurrent(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $period = AcademicPeriod::find((int) $id);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }

        AcademicPeriod::setCurrent((int) $period['period_id']);
        AuditLog::record((int) $dean['user_id'], 'period.set_current', 'Academic Periods', 'period:' . $period['period_id'], 'Marked as the current academic period; previous current period closed.');
        Flash::success('Current academic period updated.');
        redirect('/dean/periods');
    }

    public function close(string $id): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();

        $period = AcademicPeriod::find((int) $id);
        if ($period === null) {
            abort(404, 'Academic period not found.');
        }

        AcademicPeriod::updateById((int) $period['period_id'], ['status' => 'Closed']);
        AuditLog::record((int) $dean['user_id'], 'period.closed', 'Academic Periods', 'period:' . $period['period_id'], 'Academic period closed.');
        Flash::success('Academic period closed. Historical records remain intact.');
        redirect('/dean/periods');
    }
}
