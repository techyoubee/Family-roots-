<?php

/**
 * Family Roots - application bootstrap.
 *
 * Boots the autoloader, environment and configuration. This is the single
 * entry point used by the web front controller, the CLI installer and the test
 * harness. The application code lives outside the public document root.
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;

if (defined('FAMILY_ROOTS_BOOTED')) {
    return;
}
define('FAMILY_ROOTS_BOOTED', true);
define('FAMILY_ROOTS_ROOT', dirname(__DIR__));

require FAMILY_ROOTS_ROOT . '/app/Support/helpers.php';

spl_autoload_register(static function ($class) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = FAMILY_ROOTS_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(FAMILY_ROOTS_ROOT . '/.env');

$configFile = FAMILY_ROOTS_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo 'Configuration file missing: config/config.php';
    exit(1);
}
Config::load(require $configFile);

$timezone = (string) Config::get('app.timezone', 'UTC');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
}

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', Logger::directory() . '/php-error.log');
error_reporting(E_ALL);

return [
    'root'   => FAMILY_ROOTS_ROOT,
    'debug'  => $debug,
];
