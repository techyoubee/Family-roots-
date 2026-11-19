<?php

namespace App\Core;

/**
 * Secure session wrapper: hardened cookie flags, id regeneration after
 * privilege changes, flash messages and one-time old-input storage.
 */
final class Session
{
    /** @var bool */
    private static $started = false;

    public static function start()
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        if (headers_sent($file, $line)) {
            Logger::warning('Session could not start: headers already sent at ' . $file . ':' . $line);
            return;
        }

        $lifetime = (int) Config::get('session.lifetime', 7200);
        $secure = (bool) Config::get('session.secure', false);
        $samesite = (string) Config::get('session.samesite', 'Lax');

        session_name((string) Config::get('session.name', 'family_roots_session'));

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $samesite,
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=' . $samesite, '', $secure, true);
        }

        $savePath = defined('FAMILY_ROOTS_ROOT') ? FAMILY_ROOTS_ROOT . '/storage/sessions' : dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0777, true);
        }
        @ini_set('session.save_handler', 'files');
        @ini_set('session.save_path', $savePath);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        @session_start();
        self::$started = true;

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
    }

    public static function get($key, $default = null)
    {
        self::start();
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has($key)
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function remove($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function pull($key, $default = null)
    {
        $value = self::get($key, $default);
        self::remove($key);
        return $value;
    }

    public static function forget()
    {
        self::start();
        $_SESSION = [];
    }

    public static function flash($key, $value)
    {
        self::start();
        // Flash values live in a nested array; getFlash() reads and clears
        // them on the next request. Do not use dotted key syntax here - it
        // stores a literal `_flash.success` key that getFlash() cannot see.
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash($key, $default = null)
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Queue validation errors + previously submitted input for the next request. */
    public static function flashErrors(array $errors, array $old = [])
    {
        self::set('_errors', $errors);
        self::set('_old', self::exceptSensitive($old));
    }

    public static function errors()
    {
        self::start();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }

    public static function old()
    {
        self::start();
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $old;
    }

    private static function exceptSensitive(array $input)
    {
        $clean = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['password', 'password_confirmation', 'current_password', '_token', 'new_password'], true)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::exceptSensitive($value) : $value;
        }
        return $clean;
    }

    public static function regenerate($destroyOld = true)
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOld);
        }
        self::set('_regenerated_at', time());
    }

    public static function id()
    {
        self::start();
        return session_id();
    }

    public static function destroy()
    {
        self::start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && ini_get('session.use_cookies') && session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    public static function age()
    {
        self::start();
        return isset($_SESSION['_created']) ? time() - (int) $_SESSION['_created'] : 0;
    }
}
