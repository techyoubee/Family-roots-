<?php

namespace App\Core;

use App\Models\FamilyMember;
use App\Models\User;

/**
 * Session identity + role checks. Roles are always re-read from the database on
 * demand so a revoked administrator loses access immediately.
 */
final class Auth
{
    /** @var array|null */
    private static $user;
    /** @var bool */
    private static $resolved = false;
    /** @var array<int,string> */
    private static $familyRoles = [];

    public static function check()
    {
        return self::user() !== null;
    }

    public static function id()
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /** @return array|null */
    public static function user()
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = Session::get('user_id');
        if (!$id) {
            self::$user = null;
            return null;
        }
        $user = User::find((int) $id);
        // Accounts awaiting email confirmation keep the `pending` status but must
        // still be able to sign in - otherwise they can never reach the
        // "confirm your email" screen and login appears to loop. Restricted
        // features stay gated by the EnsureEmailVerified middleware.
        $blocked = ['suspended', 'deletion_requested', 'disabled', 'banned'];
        if (!$user || in_array((string) $user['status'], $blocked, true)) {
            if ($user && $user['status'] === 'suspended') {
                Session::flash('error', 'Your account is suspended. Please contact the platform administrators.');
            }
            self::$user = null;
            Session::remove('user_id');
            return null;
        }
        self::$user = $user;
        return self::$user;
    }

    public static function login(array $user, Request $request = null)
    {
        if (Config::get('security.regenerate_on_login', true)) {
            Session::regenerate(true);
        }
        Session::set('user_id', (int) $user['id']);
        Session::set('login_at', time());
        Csrf::rotate();
        self::$user = $user;
        self::$resolved = true;
    }

    public static function logout()
    {
        self::$user = null;
        self::$resolved = false;
        self::$familyRoles = [];
        Session::destroy();
    }

    public static function isPlatformAdmin()
    {
        $user = self::user();
        return $user !== null && (int) ($user['is_platform_admin'] ?? 0) === 1;
    }

    /** Role of the current user inside one family, or null. */
    public static function roleIn($familyId)
    {
        $userId = self::id();
        if (!$userId || !$familyId) {
            return null;
        }
        $familyId = (int) $familyId;
        if (array_key_exists($familyId, self::$familyRoles)) {
            return self::$familyRoles[$familyId];
        }
        $role = FamilyMember::roleFor($familyId, $userId);
        self::$familyRoles[$familyId] = $role;
        return $role;
    }

    public static function isFamilyAdmin($familyId)
    {
        return in_array(self::roleIn($familyId), ['founder', 'admin'], true);
    }

    public static function isFamilyMember($familyId)
    {
        return in_array(self::roleIn($familyId), ['founder', 'admin', 'member', 'viewer'], true);
    }

    public static function clearCache()
    {
        self::$user = null;
        self::$resolved = false;
        self::$familyRoles = [];
    }
}
