<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Family;
use App\Services\NotificationService;

/**
 * Shared controller behaviour: rendering views, JSON helpers and safe access to
 * the current user.
 */
abstract class Controller
{
    /**
     * @return Response
     */
    protected function view($template, array $data = [], $layout = 'layouts/app')
    {
        $data['currentUser'] = \App\Core\Auth::user();
        $data['unreadNotifications'] = $data['currentUser'] ? NotificationService::unreadCount((int) $data['currentUser']['id']) : 0;
        $data['flashSuccess'] = \App\Core\Session::getFlash('success');
        $data['flashError'] = \App\Core\Session::getFlash('error');
        $data['flashInfo'] = \App\Core\Session::getFlash('info');
        $data['title'] = $data['title'] ?? \App\Core\Config::get('app.name', 'Family Roots');
        return Response::html(\App\Core\View::render($template, $data, $layout));
    }

    protected function json($data, $status = 200)
    {
        return Response::json($data, $status);
    }

    protected function redirect($path, $status = 302)
    {
        return Response::redirect(url($path), $status);
    }

    protected function flash($type, $message)
    {
        \App\Core\Session::flash($type, $message);
    }

    protected function requireLogin()
    {
        if (!\App\Core\Auth::check()) {
            abort(401, 'Please sign in to continue.');
        }
    }

    protected function userId()
    {
        return \App\Core\Auth::id();
    }

    /** Log an unexpected but non-fatal problem without exposing it to the user. */
    protected function logWarning($message, array $context = [])
    {
        Logger::warning($message, $context);
    }

    protected function databaseAvailable()
    {
        try {
            Database::value('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
