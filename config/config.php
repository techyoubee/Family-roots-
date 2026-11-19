<?php

/**
 * Family Roots - application configuration.
 *
 * Every value can be overridden from the environment (`.env` in the project
 * root or real OS environment variables). Nothing in this file is secret:
 * real credentials live only in `.env`, which is git-ignored and never placed
 * inside the public web directory.
 */

$root = dirname(__DIR__);
$env  = static function ($key, $default = null) {
    $value = getenv((string) $key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
};
$bool = static function ($key, $default = false) use ($env) {
    $value = $env($key, null);
    if ($value === null) {
        return (bool) $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
};
$int = static function ($key, $default) use ($env) {
    $value = $env($key, null);
    return $value === null ? (int) $default : (int) $value;
};

return [
    'root' => $root,

    'app' => [
        'name'        => $env('APP_NAME', 'Family Roots'),
        'env'         => $env('APP_ENV', 'local'),
        'debug'       => $bool('APP_DEBUG', true),
        'url'         => rtrim((string) $env('APP_URL', ''), '/'),
        'timezone'    => $env('APP_TIMEZONE', 'UTC'),
        'key'         => $env('APP_KEY', ''),
        // Pretty URLs require Apache mod_rewrite. When the server cannot
        // rewrite (default XAMPP/AppServ configuration) the router falls back
        // to `index.php?r=/path` links, which keeps every route reachable.
        'pretty_urls' => $bool('APP_PRETTY_URLS', true),
        'views'       => $root . '/app/Views',
    ],

    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => $int('DB_PORT', 3306),
        'name'    => $env('DB_NAME', 'family_roots'),
        'user'    => $env('DB_USER', 'family_roots_app'),
        'pass'    => (string) $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
        // Seconds to wait for the initial TCP/handshake. Queries are not
        // limited by this; lock waits are capped below.
        'timeout'           => $int('DB_TIMEOUT', 5),
        // Statements slower than this are written to storage/logs. 500 ms is
        // far above normal on localhost, so a line here means real trouble.
        'slow_query_ms'     => $int('DB_SLOW_QUERY_MS', 500),
        // InnoDB row-lock waits become exceptions well before PHP's own
        // 30 s execution limit, so a stuck request fails with a log entry
        // instead of a blank fatal error page.
        'lock_wait_timeout' => $int('DB_LOCK_WAIT_TIMEOUT', 20),
    ],

    'session' => [
        'name'     => $env('SESSION_NAME', 'family_roots_session'),
        'lifetime' => $int('SESSION_LIFETIME', 7200),
        'secure'   => $bool('SESSION_SECURE', false),
        'samesite' => $env('SESSION_SAMESITE', 'Lax'),
    ],

    'mail' => [
        'transport' => $env('MAIL_TRANSPORT', 'log'),
        'from'      => $env('MAIL_FROM', 'Family Roots <no-reply@family-roots.local>'),
    ],

    'uploads' => [
        'dir'       => $env('UPLOAD_DIR', $root . '/storage/private/evidence'),
        'max_bytes' => $int('UPLOAD_MAX_BYTES', 5 * 1024 * 1024),
        'allowed'   => [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/plain', 'text/csv'],
            'doc'  => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
        ],
    ],

    'security' => [
        'password_algo' => PASSWORD_DEFAULT,
        'password_options' => [],
        // Session id is regenerated after login and after privilege changes.
        'regenerate_on_login'     => true,
        'token_ttl_minutes'       => 120,
        'email_verify_ttl_minutes' => 1440,
        'auth_max_attempts'       => $int('AUTH_MAX_ATTEMPTS', 5),
        'auth_window_seconds'     => $int('AUTH_WINDOW_SECONDS', 900),
        'generic_auth_errors'     => true,
    ],

    /**
     * Ancestry matching engine.
     *
     * Scores are workflow indicators used to route an application, never proof
     * of biological kinship. Automatic approval only happens when every
     * condition in `auto_approve` is satisfied *and* a verified family
     * administrator can still override the outcome.
     */
    'matching' => [
        'weights' => [
            'name_exact'        => 40,
            'name_variant'      => 25,
            'birth_year'        => 15,
            'death_year'        => 5,
            'place'             => 10,
            'relationship_path' => 20,
            'evidence'          => 10,
        ],
        'thresholds' => [
            'match'      => 45,   // below this a claim is reported as "no match"
            'strong'     => 70,   // used in explanations, not as proof
            'duplicate'  => 85,   // candidate duplicate-person detection
        ],
        'auto_approve' => [
            'enabled'                    => true,
            'min_score'                  => 80,
            'min_confirmed_claims'       => 2,
            'require_parent_and_grandparent' => true,
            'max_conflicting_claims'     => 0,
            'require_verified_family'    => true,
            'family_verification_exempt' => true, // founder's own family is exempt
        ],
        // A single matching ancestor or surname is never enough on its own.
        'min_independent_signals' => 2,
    ],

    'privacy' => [
        'default_visibility'    => 'members', // public | members | admins
        'living_person_masking' => true,
        'directory_requires_login' => false,
    ],

    'audit' => [
        // Every membership, verification, correction and account event is
        // written to audit_logs. Turn off only for throwaway local installs.
        'enabled' => $bool('AUDIT_ENABLED', true),
    ],
];
