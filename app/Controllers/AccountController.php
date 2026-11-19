<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\AuthService;
use App\Services\NotificationService;

/**
 * The signed-in member's home: dashboard, profile and password settings.
 *
 * The dashboard is deliberately a summary, not a genealogy view - it shows
 * which families a person belongs to, their role, and anything waiting for
 * their attention.
 */
final class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $memberships = FamilyMember::familiesForUser($user['id']);
        $notifications = NotificationService::forUser($user['id'], 8);
        $unread = NotificationService::unreadCount($user['id']);

        return $this->view('account/dashboard', [
            'title'         => 'Dashboard',
            'user'          => $user,
            'memberships'   => $memberships,
            'notifications' => $notifications,
            'unread'        => $unread,
            'needsEmail'    => empty($user['email_verified_at']),
        ]);
    }

    public function settings(Request $request)
    {
        return $this->view('account/settings', [
            'title' => 'Account settings',
            'user'  => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'full_name'    => 'required|string|min:2|max:150',
            'display_name' => 'nullable|string|max:80',
            'phone'        => 'nullable|string|max:40',
            'timezone'     => 'nullable|string|max:64',
        ], [
            'full_name'    => 'Full name',
            'display_name' => 'Display name',
            'phone'        => 'Phone',
            'timezone'     => 'Time zone',
        ]);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors(), request()->all());
            return $this->redirect('/settings');
        }

        User::updateProfile($user['id'], [
            'full_name'    => $validator->value('full_name'),
            'display_name' => $validator->value('display_name'),
            'phone'        => $validator->value('phone'),
            'timezone'     => $validator->value('timezone') ?: 'UTC',
        ]);

        Auth::clearCache();
        \App\Services\AuditLog::record('account.profile_updated', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Profile details updated',
        ]);

        Session::flash('success', 'Your profile has been saved.');
        return $this->redirect('/settings');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $rules = array_merge([
            'current_password' => 'required|string',
        ], AuthService::passwordRules('password'));

        $validator = Validator::make($request->all(), $rules, [
            'current_password' => 'Current password',
            'password'         => 'New password',
        ]);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors(), request()->all());
            return $this->redirect('/settings');
        }

        $changed = AuthService::changePassword(
            $user,
            $request->input('current_password', ''),
            $request->input('password', ''),
            $request
        );

        if (!$changed) {
            Session::flashErrors(['current_password' => ['That is not your current password.']], []);
            return $this->redirect('/settings');
        }

        Session::flash('success', 'Your password has been changed.');
        return $this->redirect('/settings');
    }

    public function requestDeletion(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'confirm' => 'required|string|in:DELETE',
        ], ['confirm' => 'Confirmation']);

        if ($validator->fails()) {
            Session::flashErrors([
                'confirm' => ['Type DELETE exactly to confirm this request.'],
            ], []);
            return $this->redirect('/settings');
        }

        AuthService::requestDeletion($user, $request);
        Auth::logout();

        Session::flash('info', 'We have received your deletion request. A platform administrator will review it and confirm by email.');
        return $this->redirect('/');
    }
}
