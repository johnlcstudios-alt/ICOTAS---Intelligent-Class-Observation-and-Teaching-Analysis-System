<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AnalyticsService;
use App\Services\NotificationService;
use App\Services\PredictionService;

final class DashboardController extends BaseController
{
    public function dean(): void
    {
        $this->requireAdmin();
        $demo = $this->demo();
        $service = new AnalyticsService();
        $data = $service->deanDashboard($demo);

        $recentSessions = Database::fetchAll(
            'SELECT es.*, a.title, c.code AS course_code, sec.code AS section_code,
                    i.first_name, i.last_name,
                    (SELECT COUNT(*) FROM evaluation_responses r WHERE r.evaluation_session_id = es.id) AS response_count
             FROM evaluation_sessions es
             JOIN evaluation_assignments a ON a.id = es.assignment_id
             JOIN courses c ON c.id = a.course_id
             JOIN sections sec ON sec.id = a.section_id
             JOIN users i ON i.id = a.instructor_id
             WHERE es.is_demo = ? ORDER BY es.opens_at DESC LIMIT 6',
            [$demo ? 1 : 0]
        );

        $unread = (new NotificationService())->unreadCount((int) Auth::id());

        $this->view('dean/dashboard', [
            'pageTitle' => 'Institutional Dashboard',
            'data' => $data,
            'demo' => $demo,
            'recentSessions' => $recentSessions,
            'unread' => $unread,
        ]);
    }

    public function instructor(): void
    {
        \App\Core\Auth::requireRole('instructor');
        $demo = $this->demo();
        $instructorId = (int) Auth::id();
        $service = new AnalyticsService();
        $data = $service->instructorDashboard($instructorId, $demo);
        $prediction = (new PredictionService())->latest($instructorId);
        $unread = (new NotificationService())->unreadCount($instructorId);

        $this->view('instructor/dashboard', [
            'pageTitle' => 'My Teaching Dashboard',
            'data' => $data,
            'prediction' => $prediction,
            'demo' => $demo,
            'unread' => $unread,
        ]);
    }
}
