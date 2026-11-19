<?php

/**
 * Family Roots - web front controller.
 *
 * This is the only file that must be reachable from the web server. Application
 * code, configuration, private evidence and logs all live outside public/.
 */

// Buffer the response so that PHP's own fatal-error text (only shown while
// APP_DEBUG=true) cannot flush the HTTP headers before the timeout handler
// decides how to answer. The buffer is flushed automatically at shutdown.
ob_start();

use App\Core\Request;
use App\Core\Router;
use App\Http\ErrorHandler;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StartSession;

require dirname(__DIR__) . '/app/bootstrap.php';

// Registered before routing so a slow request that hits PHP's own time limit
// produces a readable 503 page plus a log line naming the route.
ErrorHandler::registerTimeoutHandler();

$request = Request::capture();
$GLOBALS['__request'] = $request;

$router = new Router();
$router->addGlobalMiddleware(StartSession::class);
$router->addGlobalMiddleware(SecurityHeaders::class);

$routeFile = base_path('routes/web.php');
if (!is_file($routeFile)) {
    http_response_code(500);
    echo 'Route file missing.';
    exit(1);
}
require $routeFile;

// Expose the router for named-route URL generation inside views.
$GLOBALS['__router'] = $router;

$response = null;
try {
    $response = $router->dispatch($request);
} catch (\Throwable $e) {
    try {
        $response = ErrorHandler::render($request, $e);
    } catch (\Throwable $inner) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fatal error. Please check storage/logs.';
        exit(1);
    }
}

$response->send();
