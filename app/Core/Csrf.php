<?php

namespace App\Core;

/**
 * Synchroniser-token CSRF protection. A random token per session is required
 * on every state-changing request, either as `_token` field or the
 * `X-CSRF-Token` header.
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';

    public static function token()
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = Str::randomToken(32);
        }
        return $_SESSION['_csrf'];
    }

    public static function field()
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e(self::token()) . '">';
    }

    public static function check($token = null)
    {
        Session::start();
        $expected = $_SESSION['_csrf'] ?? null;
        if ($token === null) {
            $token = $_REQUEST[self::FIELD] ?? null;
            if ($token === null && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
        }
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /** Rotate the token (e.g. after login) to limit token fixation. */
    public static function rotate()
    {
        Session::start();
        $_SESSION['_csrf'] = Str::randomToken(32);
        return $_SESSION['_csrf'];
    }
}
