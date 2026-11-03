<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Session;
use App\Models\Notification;

/**
 * In-system notifications for both roles. Every lookup is scoped to the
 * authenticated account so one user cannot read another user's notices.
 */
final class NotificationController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $userId = (int) $user['user_id'];
        $page = $this->pageNumber();
        $total = Notification::countForUser($userId);
        $pagination = paginate($total, $page, 20);

        $this->render('notifications/index', [
            'pageTitle'    => 'Notifications',
            'pageSubtitle' => 'Evaluation, acknowledgement and security notices for your account.',
            'activeNav'    => 'notifications',
            'rows'         => Notification::paginateForUser($userId, (int) $pagination['per_page'], (int) $pagination['offset']),
            'pagination'   => $pagination,
        ]);
    }

    public function markRead(string $id): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $marked = Notification::markRead((int) $id, (int) $user['user_id']);
        if (!$marked) {
            abort(404, 'Notification not found.');
        }

        $link = trim((string) Request::input('link', ''));
        if ($link !== '' && str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            redirect($link);
        }
        redirect('/notifications');
    }

    public function markAllRead(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }

        $count = Notification::markAllRead((int) $user['user_id']);
        Flash::success($count . ' notification(s) marked as read.');
        redirect('/notifications');
    }

    /**
     * JSON unread counter used by the navigation bar poll.
     */
    public function unread(): void
    {
        $user = Auth::user();
        if ($user === null) {
            json_response(['success' => false, 'message' => 'Not signed in.'], 401);
        }

        json_response([
            'success' => true,
            'data'    => [
                'unread'    => Notification::unreadCount((int) $user['user_id']),
                'recent'    => array_map(static fn (array $n): array => [
                    'id'      => (int) $n['notification_id'],
                    'title'   => $n['title'],
                    'message' => $n['message'],
                    'link'    => $n['link'],
                    'type'    => $n['type'],
                    'is_read' => (int) $n['is_read'],
                    'created' => fmt_datetime($n['created_at']),
                ], Notification::forUser((int) $user['user_id'], 5)),
            ],
        ]);
    }
}
