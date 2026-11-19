<?php

/**
 * Global view helpers.
 *
 * Kept deliberately small: output escaping (e), URLs (url/asset), CSRF fields,
 * flash/old input and a handful of formatting helpers. Anything more complex
 * belongs in a service class.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Str;
use App\Core\View;
use App\Exceptions\HttpException;

if (!function_exists('e')) {
    /** Escape for HTML context. Use everywhere user data is printed. */
    function e($value)
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        $root = rtrim((string) Config::get('root', dirname(__DIR__)), '/');
        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '')
    {
        return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('request')) {
    function request()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = isset($GLOBALS['__request']) ? $GLOBALS['__request'] : Request::capture();
        }
        return $instance;
    }
}

if (!function_exists('url')) {
    /**
     * Build an application URL. When the server cannot rewrite (no mod_rewrite)
     * the router falls back to index.php query routing, so every link keeps
     * working on a default XAMPP install.
     */
    function url($path = '/')
    {
        $base = Request::basePath();
        $path = '/' . ltrim((string) $path, '/');
        $pretty = (bool) Config::get('app.pretty_urls', true);
        if (!$pretty) {
            $raw = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
            // Keep slashes readable inside r= (index.php?r=/families/x) so
            // links stay human-readable; every other character stays encoded.
            return $base . '/index.php?r=' . str_replace('%2F', '/', rawurlencode($raw));
        }
        return $base . ($path === '/' ? '/' : $path);
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Absolute URL (scheme + host) for links that leave the browser - the
     * link inside a password-reset or confirmation email. Recipients cannot
     * resolve a relative path, and the app may sit under a sub-directory.
     */
    function absolute_url($path = '/')
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $relative = url($path);
        if (PHP_SAPI === 'cli') {
            // CLI (tests): basePath() is the test runner's folder ("tests").
            // Emails must not carry that bogus prefix.
            $b = Request::basePath();
            if ($b !== '' && $b !== '.' && strpos($relative, $b) === 0) {
                $relative = substr($relative, strlen($b));
            }
            if ($relative === '' || $relative[0] !== '/') {
                $relative = '/' . ltrim($relative, '/');
            }
        }
        return ($https ? 'https' : 'http') . '://' . $host . $relative;
    }
}

if (!function_exists('query_url')) {
    /**
     * Build an application URL with a query string. Unlike concatenating
     * `url($path) . '?x='` this works in both URL modes: in fallback mode the
     * link is `index.php?r=/path&x=1` (params joined with &), never a second ?.
     */
    function query_url($path, array $params = [])
    {
        if (empty($params)) {
            return url($path);
        }
        $qs = http_build_query($params);
        $base = Request::basePath();
        $path = '/' . ltrim((string) $path, '/');
        $pretty = (bool) Config::get('app.pretty_urls', true);
        if (!$pretty) {
            $raw = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
            return $base . '/index.php?r=' . str_replace('%2F', '/', rawurlencode($raw)) . '&' . $qs;
        }
        return $base . ($path === '/' ? '/' : $path) . '?' . $qs;
    }
}

if (!function_exists('route_exists')) {
    /** True when a named route has been registered in routes/web.php. */
    function route_exists($name)
    {
        $router = isset($GLOBALS['__router']) ? $GLOBALS['__router'] : null;
        return $router !== null && array_key_exists($name, $router->names());
    }
}

if (!function_exists('route')) {
    /** URL for a named route. Falls back to '/' when unknown. */
    function route($name, array $params = [])
    {
        $router = isset($GLOBALS['__router']) ? $GLOBALS['__router'] : null;
        if ($router === null || !array_key_exists($name, $router->names())) {
            return url('/');
        }
        return url($router->url($name, $params));
    }
}

if (!function_exists('asset')) {
    function asset($path)
    {
        $path = '/' . ltrim((string) $path, '/');
        $file = base_path('public' . $path);
        $version = is_file($file) ? (string) filemtime($file) : '1';
        // Assets are real files under public/ - always link them directly.
        // Routing through index.php?r= would 404 (there is no /assets route).
        return Request::basePath() . $path . '?v=' . $version;
    }
}

if (!function_exists('view')) {
    function view($template, array $data = [], $layout = 'layouts/app')
    {
        return View::render($template, $data, $layout);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    function method_field($method)
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    function old($key, $default = '')
    {
        $old = session_old();
        if (strpos($key, '.') === false) {
            return isset($old[$key]) ? $old[$key] : $default;
        }
        $segments = explode('.', $key);
        $value = $old;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('session_old')) {
    function session_old()
    {
        static $old = null;
        if ($old === null) {
            $old = Session::old();
        }
        return $old;
    }
}

if (!function_exists('errors_for')) {
    function errors_for()
    {
        static $errors = null;
        if ($errors === null) {
            $errors = Session::errors();
        }
        return $errors;
    }
}

if (!function_exists('error')) {
    function error($field)
    {
        $errors = errors_for();
        if (!isset($errors[$field]) || empty($errors[$field])) {
            return '';
        }
        $messages = is_array($errors[$field]) ? $errors[$field] : [$errors[$field]];
        return '<p class="field-error" id="' . e($field) . '-error" role="alert">' . e(implode(' ', $messages)) . '</p>';
    }
}

if (!function_exists('has_error')) {
    function has_error($field)
    {
        $errors = errors_for();
        return isset($errors[$field]) && !empty($errors[$field]);
    }
}

if (!function_exists('flash')) {
    function flash($key, $default = null)
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth()
    {
        return Auth::user();
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $status = 302)
    {
        return \App\Core\Response::redirect(preg_match('#^https?://#i', $url) ? $url : url($url), $status);
    }
}

if (!function_exists('back')) {
    function back($fallback = '/')
    {
        $referer = request()->header('Referer', '');
        return redirect($referer !== '' ? $referer : $fallback);
    }
}

if (!function_exists('abort')) {
    function abort($status, $message = '')
    {
        throw new HttpException((int) $status, $message);
    }
}

if (!function_exists('short_name')) {
    function short_name($value, $length = 28)
    {
        $value = trim((string) $value);
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '...' : $value;
    }
}

if (!function_exists('format_date')) {
    function format_date($value, $fallback = 'Unknown')
    {
        if (!$value) {
            return $fallback;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $ts ? date('d M Y', $ts) : $fallback;
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($value, $fallback = '')
    {
        if (!$value) {
            return $fallback;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $ts ? date('d M Y H:i', $ts) : $fallback;
    }
}

if (!function_exists('time_ago')) {
    function time_ago($value)
    {
        if (!$value) {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        $units = ['year' => 31536000, 'month' => 2592000, 'week' => 604800, 'day' => 86400, 'hour' => 3600, 'minute' => 60];
        foreach ($units as $label => $seconds) {
            if ($diff >= $seconds) {
                $count = (int) floor($diff / $seconds);
                return $count . ' ' . $label . ($count === 1 ? '' : 's') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('status_label')) {
    function status_label($status)
    {
        return ucwords(str_replace('_', ' ', (string) $status));
    }
}

if (!function_exists('initials')) {
    function initials($name)
    {
        $tokens = preg_split('/\s+/', trim((string) $name)) ?: [];
        $out = '';
        foreach (array_slice($tokens, 0, 2) as $token) {
            $out .= mb_strtoupper(mb_substr($token, 0, 1));
        }
        return $out === '' ? '?' : $out;
    }
}

if (!function_exists('mask_name')) {
    function mask_name($name)
    {
        return Str::maskName($name);
    }
}

if (!function_exists('status_badge')) {
    /** Consistent status pill for verification, record and request statuses. */
    function status_badge($status, $text = null)
    {
        $status = (string) $status;
        $map = [
            'verified'            => 'badge--verified',
            'approved'            => 'badge--verified',
            'active'              => 'badge--verified',
            'accepted'            => 'badge--verified',
            'confirmed'           => 'badge--confirmed',
            'unverified'          => 'badge--unverified',
            'more_info_required'  => 'badge--unverified',
            'pending'             => 'badge--pending',
            'pending_review'      => 'badge--pending',
            'submitted'           => 'badge--pending',
            'auto_matched'        => 'badge--pending',
            'appeal_requested'    => 'badge--pending',
            'draft'               => 'badge--neutral',
            'cancelled'           => 'badge--neutral',
            'withdrawn'           => 'badge--neutral',
            'closed'              => 'badge--neutral',
            'rejected'            => 'badge--rejected',
            'declined'            => 'badge--rejected',
            'suspended'           => 'badge--rejected',
            'disputed'            => 'badge--disputed',
            'proposed'            => 'badge--proposed',
            'unknown'             => 'badge--unknown',
        ];
        $class = isset($map[$status]) ? $map[$status] : 'badge--neutral';
        $label = $text !== null ? $text : status_label($status);
        return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('is_active_route')) {
    function is_active_route($path, $class = 'is-active')
    {
        $current = request()->path();
        $path = '/' . trim((string) $path, '/');
        if ($path === '/') {
            return $current === '/' ? $class : '';
        }
        return strpos($current, $path) === 0 ? $class : '';
    }
}
