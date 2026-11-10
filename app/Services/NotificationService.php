<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Notification;

final class NotificationService
{
    private Notification $model;

    public function __construct()
    {
        $this->model = new Notification();
    }

    public function toUser(int $userId, string $type, string $title, string $message, ?string $link = null): void
    {
        $this->model->createFor($userId, $type, $title, $message, $link);
    }

    public function toRole(string $role, string $type, string $title, string $message, ?string $link = null, bool $demo = false): void
    {
        $this->model->createForRole($role, $type, $title, $message, $link, $demo);
    }

    public function toAdmins(string $type, string $title, string $message, ?string $link = null, bool $demo = false): void
    {
        $this->model->createForRole('dean', $type, $title, $message, $link, $demo);
        $this->model->createForRole('academic_head', $type, $title, $message, $link, $demo);
    }

    public function newAssignment(int $instructorId, string $title, int $assignmentId, bool $demo = false): void
    {
        $this->toUser(
            $instructorId,
            'evaluation_assignment',
            'New teaching evaluation assigned',
            'A student evaluation activity, "' . $title . '", has been created for one of your classes.',
            'instructor/evaluations',
            $demo
        );
    }

    public function sessionOpened(array $session, array $students, bool $demo = false): void
    {
        $link = 'student/scan';
        $message = 'An evaluation for ' . $session['course_code'] . ' (' . $session['section_code'] . ') is open until '
            . format_datetime($session['expires_at']) . '. Scan the QR code or enter code ' . $session['session_code'] . '.';
        foreach ($students as $student) {
            $this->toUser((int) $student['id'], 'evaluation_open', 'Evaluation now open', $message, $link, $demo);
        }
    }

    public function submissionReceived(int $sessionId, array $session, bool $demo = false): void
    {
        $this->toAdmins(
            'evaluation_submission',
            'New anonymous evaluation response',
            'A response was submitted for ' . $session['course_code'] . ' — ' . $session['section_code'] . ' (session ' . $session['session_code'] . ').',
            'dean/sessions',
            $demo
        );
        $this->toUser(
            (int) $session['instructor_id'],
            'evaluation_summary',
            'New evaluation feedback received',
            'New anonymous feedback is available for ' . $session['course_code'] . '. Aggregate results update automatically.',
            'instructor/evaluations',
            $demo
        );
    }

    public function reportReady(int $userId, string $title, string $type): void
    {
        $this->toUser($userId, 'report', 'Report generated', $title . ' is ready to view or export.', 'reports/view?type=' . urlencode($type));
    }

    public function announcement(string $title, string $message, bool $demo = false): void
    {
        foreach (['dean', 'academic_head', 'instructor', 'student'] as $role) {
            $this->toRole($role, 'announcement', $title, $message, null, $demo);
        }
    }

    public function unreadCount(int $userId): int
    {
        return $this->model->unreadCount($userId);
    }

    public function recent(int $userId, int $limit = 8): array
    {
        return $this->model->forUser($userId, $limit);
    }

    public function markRead(int $id, int $userId): bool
    {
        return $this->model->markRead($id, $userId);
    }

    public function markAllRead(int $userId): int
    {
        return $this->model->markAllRead($userId);
    }
}
