<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\RateLimiter;
use App\Core\Str;
use App\Exceptions\HttpException;
use App\Models\EmailToken;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\Mailer;

/**
 * Account lifecycle: registration, sign in/out, email confirmation and
 * password reset. Controllers stay thin - they validate input and delegate the
 * security-sensitive work here so the same rules apply from every entry point
 * (web form, test harness, CLI).
 *
 * The service deliberately gives generic answers on failure so an attacker
 * cannot learn which email addresses exist.
 */
final class AuthService
{
    public static function hashPassword($plain)
    {
        return password_hash(
            (string) $plain,
            (int) Config::get('security.password_algo', PASSWORD_DEFAULT),
            (array) Config::get('security.password_options', [])
        );
    }

    /** Create an account in `pending` state and send the confirmation email. */
    public static function register(array $data, Request $request)
    {
        $user = User::create([
            'email'         => $data['email'],
            'password_hash' => self::hashPassword($data['password']),
            'full_name'     => $data['full_name'],
            'display_name'  => isset($data['display_name']) ? $data['display_name'] : null,
            'timezone'      => isset($data['timezone']) ? $data['timezone'] : 'UTC',
            'status'        => 'pending',
        ]);

        self::sendEmailVerification($user, $request);

        AuditLog::record('auth.registered', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Account registered and awaiting email confirmation',
        ]);

        return $user;
    }

    /**
     * Attempt a sign in.
     *
     * @return array|null the authenticated user row, or null when the
     *                    credentials are wrong or the account is unusable.
     * @throws HttpException 429 when the attempt rate is exceeded.
     */
    public static function attempt($email, $password, Request $request)
    {
        $email = mb_strtolower(trim((string) $email));
        $max = (int) Config::get('security.auth_max_attempts', 5);
        $window = (int) Config::get('security.auth_window_seconds', 900);
        $bucket = $email . '|' . $request->ip();

        if (!RateLimiter::attempt('login', $bucket, $max, $window)) {
            AuditLog::record('auth.login_throttled', [
                'request'  => $request,
                'summary'  => 'Sign-in rate limit reached',
            ]);
            throw new HttpException(429, 'Too many sign-in attempts. Please wait a few minutes and try again.');
        }

        $user = User::findByEmail($email);
        if (!$user) {
            AuditLog::record('auth.login_failed', ['request' => $request, 'summary' => 'Unknown email address']);
            return null;
        }

        if (User::isLocked($user)) {
            AuditLog::record('auth.login_failed', [
                'request'   => $request,
                'entity_type' => 'user',
                'entity_id' => (int) $user['id'],
                'summary'   => 'Account temporarily locked',
            ]);
            return null;
        }

        if (!password_verify((string) $password, (string) $user['password_hash'])) {
            $attempts = (int) $user['failed_login_count'] + 1;
            $lockUntil = $attempts >= $max
                ? date('Y-m-d H:i:s', time() + $window)
                : null;
            User::recordFailedLogin($user['id'], $lockUntil);
            AuditLog::record('auth.login_failed', [
                'request'     => $request,
                'entity_type' => 'user',
                'entity_id'   => (int) $user['id'],
                'summary'     => 'Incorrect password (attempt ' . $attempts . ')',
            ]);
            return null;
        }

        if ($user['status'] === 'suspended') {
            AuditLog::record('auth.login_blocked', [
                'request'   => $request,
                'entity_type' => 'user',
                'entity_id' => (int) $user['id'],
                'summary'   => 'Suspended account attempted sign in',
            ]);
            throw new HttpException(403, 'This account is suspended. Please contact the platform administrators.');
        }

        if ($user['status'] === 'deletion_requested') {
            throw new HttpException(403, 'This account is scheduled for deletion. Please contact the platform administrators.');
        }

        if (password_needs_rehash((string) $user['password_hash'], (int) Config::get('security.password_algo', PASSWORD_DEFAULT))) {
            User::setPassword($user['id'], self::hashPassword($password));
        }

        User::recordSuccessfulLogin($user['id']);
        RateLimiter::clear('login', $bucket);

        $fresh = User::find($user['id']);
        Auth::login($fresh, $request);

        AuditLog::record('auth.login', [
            'request'     => $request,
            'entity_type' => 'user',
            'entity_id'   => (int) $user['id'],
            'summary'     => 'Signed in',
        ]);

        return $fresh;
    }

    public static function logout(Request $request = null)
    {
        $id = Auth::id();
        if ($id) {
            AuditLog::record('auth.logout', [
                'request'       => $request,
                'actor_user_id' => $id,
                'entity_type'   => 'user',
                'entity_id'     => $id,
                'summary'       => 'Signed out',
            ]);
        }
        Auth::logout();
    }

    /** Issue (and email) a fresh confirmation link. */
    public static function sendEmailVerification($user, Request $request = null)
    {
        $ttl = (int) Config::get('security.email_verify_ttl_minutes', 1440);
        EmailToken::invalidate($user['id'], 'verify_email');
        $token = EmailToken::issue($user['id'], 'verify_email', $ttl);
        $link = absolute_url('/verify-email/' . $token);

        Mailer::send(
            $user['email'],
            'Confirm your ' . Config::get('app.name', 'Family Roots') . ' email address',
            "Hello " . ($user['full_name'] !== '' ? $user['full_name'] : 'there') . ",\n\n"
            . "Please confirm your email address by opening this link:\n" . $link . "\n\n"
            . "The link expires in " . (int) round($ttl / 60) . " hours. If you did not create an account, you can ignore this message.\n"
        );

        AuditLog::record('auth.verification_sent', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Email confirmation link issued',
        ]);

        return $token;
    }

    /** @return array|null the verified user row, or null when the token is invalid. */
    public static function verifyEmail($token, Request $request = null)
    {
        $row = EmailToken::findValid($token, 'verify_email');
        if (!$row) {
            return null;
        }
        EmailToken::consume($row['id']);
        User::markEmailVerified($row['user_id']);

        AuditLog::record('auth.email_verified', [
            'request'       => $request,
            'actor_user_id' => (int) $row['user_id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $row['user_id'],
            'summary'       => 'Email address confirmed',
        ]);

        return User::find($row['user_id']);
    }

    /**
     * Start a password reset. Always behaves the same whether or not the email
     * exists, so the endpoint cannot be used to enumerate accounts.
     */
    public static function requestPasswordReset($email, Request $request)
    {
        $email = mb_strtolower(trim((string) $email));
        $user = User::findByEmail($email);
        if (!$user) {
            return false;
        }
        if ($user['status'] === 'suspended') {
            return false;
        }

        $ttl = (int) Config::get('security.token_ttl_minutes', 120);
        EmailToken::invalidate($user['id'], 'reset_password');
        $token = EmailToken::issue($user['id'], 'reset_password', $ttl);
        $link = absolute_url('/password/reset/' . $token);

        Mailer::send(
            $user['email'],
            'Reset your ' . Config::get('app.name', 'Family Roots') . ' password',
            "Hello " . ($user['full_name'] !== '' ? $user['full_name'] : 'there') . ",\n\n"
            . "We received a request to reset your password. Open this link to choose a new one:\n" . $link . "\n\n"
            . "The link expires in " . (int) round($ttl / 60) . " hours. If you did not request this, no action is needed.\n"
        );

        AuditLog::record('auth.password_reset_requested', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Password reset requested',
        ]);

        return true;
    }

    /** @return array|null the affected user row, or null when the token is invalid. */
    public static function resetPassword($token, $newPassword, Request $request = null)
    {
        $row = EmailToken::findValid($token, 'reset_password');
        if (!$row) {
            return null;
        }
        EmailToken::consume($row['id']);
        User::setPassword($row['user_id'], self::hashPassword($newPassword));
        EmailToken::invalidate($row['user_id'], 'reset_password');

        AuditLog::record('auth.password_reset', [
            'request'       => $request,
            'actor_user_id' => (int) $row['user_id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $row['user_id'],
            'summary'       => 'Password changed via reset link',
        ]);

        return User::find($row['user_id']);
    }

    /** Change the password of a signed-in user. */
    public static function changePassword(array $user, $currentPassword, $newPassword, Request $request = null)
    {
        if (!password_verify((string) $currentPassword, (string) $user['password_hash'])) {
            return false;
        }
        User::setPassword($user['id'], self::hashPassword($newPassword));
        EmailToken::invalidate($user['id'], 'reset_password');

        AuditLog::record('auth.password_changed', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Password changed from account settings',
        ]);

        return true;
    }

    /** Request account deletion (soft state change, reviewed by an admin). */
    public static function requestDeletion(array $user, Request $request = null)
    {
        \App\Core\Database::update('users', [
            'status'                => 'deletion_requested',
            'deletion_requested_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $user['id']]);

        Mailer::send(
            $user['email'],
            'Your ' . Config::get('app.name', 'Family Roots') . ' deletion request',
            "Hello,\n\nWe have received your request to delete your account. A platform administrator will review it and confirm by email.\n"
        );

        AuditLog::record('account.deletion_requested', [
            'request'       => $request,
            'actor_user_id' => (int) $user['id'],
            'entity_type'   => 'user',
            'entity_id'     => (int) $user['id'],
            'summary'       => 'Account deletion requested',
        ]);

        return true;
    }

    public static function passwordRules($field = 'password')
    {
        return [$field => 'required|string|min:10|max:72|confirmed'];
    }

    public static function displayName(array $user)
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        return $name !== '' ? $name : Str::limit((string) $user['full_name'], 60);
    }
}
