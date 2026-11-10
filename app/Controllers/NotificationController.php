<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Notification;

final class NotificationController extends BaseController
{
    public function index(): void
    {
        $pagination = (new Notification())->paginateForUser((int) Auth::id(), $this->page(), 20);
        $this->view('notifications/index', [
            'pageTitle' => 'Notifications',
            'pagination' => $pagination,
        ]);
    }

    public function read(string $id): void
    {
        $this->requirePost();
        $ok = (new Notification())->markRead((int) $id, (int) Auth::id());
        if (!$ok) {
            $this->backWithError('Notification not found.', 'notifications');
        }
        $this->back('notifications');
    }

    public function readAll(): void
    {
        $this->requirePost();
        $count = (new Notification())->markAllRead((int) Auth::id());
        $this->success($count . ' notification(s) marked as read.', 'notifications');
    }
}
