<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;

/**
 * In-app notification centre. A user can only ever see and mark their own
 * notifications - the service filters by user id on every query.
 */
final class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $unreadOnly = $request->query('filter') === 'unread';
        $notifications = NotificationService::forUser($user['id'], 100, $unreadOnly);
        $unread = NotificationService::unreadCount($user['id']);

        return $this->view('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
            'unread'        => $unread,
            'unreadOnly'    => $unreadOnly,
        ]);
    }

    public function markRead(Request $request)
    {
        $user = Auth::user();
        $id = (int) $request->routeParam('id', 0);

        if (!NotificationService::belongsTo($id, $user['id'])) {
            abort(404, 'That notification could not be found.');
        }

        NotificationService::markRead($id, $user['id']);

        $link = \App\Core\Database::value('SELECT link_path FROM notifications WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        return $this->redirect(!empty($link) ? (string) $link : '/notifications');
    }

    public function markAllRead(Request $request)
    {
        NotificationService::markAllRead((int) Auth::id());
        Session::flash('success', 'All notifications marked as read.');
        return $this->redirect('/notifications');
    }
}
