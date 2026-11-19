<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuthService;

/**
 * Sign in, registration, email confirmation and password reset.
 *
 * Validation happens here; the security decisions live in AuthService. Every
 * failure message is generic so the endpoints cannot be used to discover which
 * addresses have accounts.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return $this->view('auth/login', [
            'title' => 'Sign in',
            'next'  => (string) $request->query('next', ''),
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email'    => 'Email address',
            'password' => 'Password',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/login');
        }

        $user = AuthService::attempt($validator->value('email'), $request->input('password', ''), $request);

        if (!$user) {
            Session::flashErrors([
                'email' => ['Those details did not match an account. Please check your email and password.'],
            ], ['email' => $validator->value('email')]);
            $next = (string) $request->input('next', '');
            return $next !== ''
                ? \App\Core\Response::redirect(\query_url('/login', ['next' => $next]))
                : $this->redirect('/login');
        }

        Session::flash('success', 'Welcome back, ' . AuthService::displayName($user) . '.');

        if (empty($user['email_verified_at'])) {
            Session::flash('info', 'Please confirm your email address to unlock every feature.');
            return $this->redirect('/verify-email');
        }

        return $this->redirect($this->intendedPath($request));
    }

    public function showRegister(Request $request)
    {
        return $this->view('auth/register', [
            'title' => 'Create an account',
        ]);
    }

    public function register(Request $request)
    {
        $rules = array_merge([
            'full_name' => 'required|string|min:2|max:150',
            'email'     => 'required|string|email|max:190|unique:users,email',
            'terms'     => 'accepted',
        ], AuthService::passwordRules('password'));

        $validator = Validator::make($request->all(), $rules, [
            'full_name' => 'Full name',
            'email'     => 'Email address',
            'password'  => 'Password',
            'terms'     => 'Terms agreement',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/register');
        }

        $user = AuthService::register([
            'full_name'    => $validator->value('full_name'),
            'email'        => $validator->value('email'),
            'password'     => $request->input('password', ''),
            'display_name' => $request->input('display_name'),
            'timezone'     => (string) Config::get('app.timezone', 'UTC'),
        ], $request);

        Auth::login($user, $request);

        Session::flash('success', 'Your account is ready. We have emailed a confirmation link to ' . $user['email'] . '.');
        if (route_exists('journey.intro')) {
            return $this->redirect('/journey');
        }
        return $this->redirect('/verify-email');
    }

    public function logout(Request $request)
    {
        AuthService::logout($request);
        Session::flash('info', 'You have been signed out.');
        return $this->redirect('/');
    }

    /** Landing page for users who still need to confirm their email. */
    public function verifyNotice(Request $request)
    {
        $user = Auth::user();
        return $this->view('auth/verify-email', [
            'title'      => 'Confirm your email address',
            'user'       => $user,
            'verified'   => !empty($user['email_verified_at']),
            'mailLogged' => (string) Config::get('mail.transport', 'log') !== 'mail',
            // Present only right after a resend on a local server that logs mail.
            'localLink'  => Session::getFlash('verification_link'),
        ]);
    }

    public function resendVerification(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }
        if (!empty($user['email_verified_at'])) {
            return $this->redirect('/dashboard');
        }

        $limit = (int) Config::get('security.auth_max_attempts', 5);
        if (!\App\Core\RateLimiter::attempt('verify_resend', (string) $user['id'], $limit, 3600)) {
            Session::flash('error', 'You have requested too many confirmation emails. Please try again later.');
            return $this->redirect('/verify-email');
        }

        $token = AuthService::sendEmailVerification($user, $request);
        if ((string) Config::get('mail.transport', 'log') !== 'mail') {
            // Nothing is delivered on a local install; show the link instead.
            Session::flash('verification_link', absolute_url('/verify-email/' . $token));
            Session::flash('success', 'A new confirmation link has been generated below.');
        } else {
            Session::flash('success', 'A new confirmation link is on its way to ' . $user['email'] . '.');
        }
        return $this->redirect('/verify-email');
    }

    /** Confirm an email address from the emailed link. */
    public function verify(Request $request)
    {
        $token = (string) $request->routeParam('token', '');
        $user = AuthService::verifyEmail($token, $request);

        if (!$user) {
            Session::flash('error', 'That confirmation link is invalid or has expired. Sign in to request a new one.');
            return $this->redirect('/login');
        }

        Auth::clearCache();
        Session::flash('success', 'Thank you - ' . $user['email'] . ' is now confirmed.');
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function showForgot(Request $request)
    {
        return $this->view('auth/forgot-password', [
            'title' => 'Reset your password',
        ]);
    }

    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ], ['email' => 'Email address']);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/password/forgot');
        }

        $bucket = mb_strtolower((string) $validator->value('email')) . '|' . $request->ip();
        if (!\App\Core\RateLimiter::attempt('password_email', $bucket, 3, 900)) {
            Session::flash('error', 'Too many reset requests. Please wait a few minutes and try again.');
            return $this->redirect('/password/forgot');
        }

        AuthService::requestPasswordReset($validator->value('email'), $request);

        // Same message whether or not the address exists.
        Session::flash('success', 'If that email address has an account, a reset link is on its way.');
        return $this->redirect('/password/forgot');
    }

    public function showReset(Request $request)
    {
        $token = (string) $request->routeParam('token', '');
        $row = \App\Models\EmailToken::findValid($token, 'reset_password');

        return $this->view('auth/reset-password', [
            'title' => 'Choose a new password',
            'token' => $token,
            'valid' => $row !== null,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $rules = ['token' => 'required|string'];
        $rules = array_merge($rules, AuthService::passwordRules('password'));

        $validator = Validator::make($request->all(), $rules, [
            'password' => 'Password',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/password/forgot');
        }

        $user = AuthService::resetPassword($validator->value('token'), $request->input('password', ''), $request);

        if (!$user) {
            Session::flash('error', 'That reset link is invalid or has expired. Please request a new one.');
            return $this->redirect('/password/forgot');
        }

        Session::flash('success', 'Your password has been changed. You can sign in with it now.');
        return $this->redirect('/login');
    }

    private function backWithErrors(Validator $validator, $fallback)
    {
        Session::flashErrors($validator->errors(), request()->all());
        return $this->redirect($fallback);
    }

    /** Resolve a safe, same-site redirect path after sign in. */
    private function intendedPath(Request $request)
    {
        $intended = Session::pull('_intended');
        if (is_string($intended) && $intended !== '') {
            $dest = '/' . ltrim($intended, '/');
            if (!$this->isSafeInternalPath($dest)) {
                return '/dashboard';
            }
            return $dest;
        }

        $next = (string) $request->input('next', '');
        if ($next !== '') {
            // Fallback-mode URLs carry the destination inside r=
            // (…/index.php?r=/families). Prefer it over the file path.
            $query = parse_url($next, PHP_URL_QUERY);
            if (is_string($query)) {
                $params = [];
                parse_str($query, $params);
                if (isset($params['r']) && is_string($params['r']) && $params['r'] !== '') {
                    $dest = '/' . ltrim($params['r'], '/');
                    if ($this->isSafeInternalPath($dest)) {
                        return $dest;
                    }
                }
            }
            $path = parse_url($next, PHP_URL_PATH);
            $base = \App\Core\Request::basePath();
            if (is_string($path)) {
                if ($base !== '' && strpos($path, $base) === 0) {
                    $path = substr($path, strlen($base));
                }
                if ($path !== '' && $path[0] === '/' && $this->isSafeInternalPath($path)) {
                    return $path;
                }
            }
        }

        return '/dashboard';
    }

    /** Same-site path only: no //host, no backslashes, no embedded scheme. */
    private function isSafeInternalPath($path)
    {
        return strpos($path, '//') !== 0
            && strpos($path, '\\') === false
            && strpos($path, '://') === false;
    }
}
