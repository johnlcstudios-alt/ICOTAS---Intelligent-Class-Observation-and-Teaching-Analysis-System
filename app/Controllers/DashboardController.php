<?php

namespace App\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\ClassRecord;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Observation;
use App\Models\QRToken;
use App\Models\User;
use App\Services\AnalyticsService;

final class DashboardController extends Controller
{
    public function root(): void
    {
        if (!\App\Core\Auth::check()) {
            redirect('/login');
        }
        redirect(\App\Core\Auth::dashboardPath());
    }

    public function dean(): void
    {
        $dean = (new \App\Services\AuthorizationService())->requireDean();
        $analytics = new AnalyticsService();

        $summary = $analytics->summary();
        $statusCounts = $analytics->statusCounts();
        $departmentRows = $analytics->byDepartment();
        $periodTrend = $analytics->periodTrend();
        $recentObservations = Observation::recentActivity(7);

        $classStats = ClassRecord::statistics();
        $recordByPeriod = ClassRecord::byPeriod();

        $this->render('dean/dashboard', [
            'pageTitle'          => 'Administrative Overview',
            'pageSubtitle'       => 'Institutional teaching-quality monitoring at a glance.',
            'activeNav'          => 'dashboard',
            'dean'               => $dean,
            'summary'            => $summary,
            'statusCounts'       => $statusCounts,
            'departments'        => $departmentRows,
            'periodTrend'        => $periodTrend,
            'recentObservations' => $recentObservations,
            'pendingReview'      => Observation::pendingReviewCount(),
            'pendingAssignments' => Assignment::pending(),
            'pendingAssignmentCount' => Assignment::countPending(),
            'facultyTotal'       => User::countFacultyTotal(),
            'activeClasses'      => ClassModel::countActive(),
            'activeQr'           => QRToken::countActive(),
            'classStats'         => $classStats,
            'recordsByPeriod'    => array_slice(array_reverse($recordByPeriod), 0, 5),
            'recentAudit'        => AuditLog::recent(8),
            'currentPeriod'      => AcademicPeriod::current(),
            'cofVerified'        => (string) setting('cof_verified', '0') === '1',
            'cofStructure'       => \App\Models\COFCriterion::structureReport(),
            'unreadCount'        => Notification::unreadCount((int) $dean['user_id']),
        ]);
    }

    public function faculty(): void
    {
        $faculty = (new \App\Services\AuthorizationService())->requireFaculty();
        $facultyId = (int) $faculty['user_id'];

        $analytics = new AnalyticsService();
        $data = $analytics->facultyDashboard($facultyId);

        $this->render('faculty/dashboard', [
            'pageTitle'      => 'Faculty Overview',
            'pageSubtitle'   => 'Your published classroom observation results and teaching trends.',
            'activeNav'      => 'dashboard',
            'faculty'        => $faculty,
            'stats'          => $data['stats'],
            'trend'          => $data['trend'],
            'distribution'   => $data['distribution'],
            'latest'         => Observation::latestPublishedForFaculty($facultyId),
            'recent'         => array_slice(Observation::publishedForFaculty($facultyId, 5), 0, 5),
            'classes'        => ClassModel::forFaculty($facultyId),
            'acknowledged'   => \App\Models\Acknowledgement::countForFaculty($facultyId),
            'commentCount'   => \App\Models\RateeComment::countForFaculty($facultyId),
            'unreadCount'    => Notification::unreadCount($facultyId),
        ]);
    }
}
