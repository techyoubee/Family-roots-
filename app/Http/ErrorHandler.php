<?php

namespace App\Http;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;

/**
 * Converts exceptions into user friendly responses and logs the internals.
 * Internal details are only exposed when APP_DEBUG=true.
 */
final class ErrorHandler
{
    public static function render(Request $request, \Throwable $e)
    {
        $status = 500;
        $message = 'Something went wrong on our side. The problem has been recorded.';
        $detail = null;

        if ($e instanceof ValidationException) {
            $status = 422;
            $message = 'Please correct the highlighted fields and try again.';
            $detail = null;
        } elseif ($e instanceof HttpException) {
            $status = $e->status();
            $message = $e->getMessage() !== '' ? $e->getMessage() : self::defaultMessage($status);
        }

        if ($status >= 500) {
            Logger::exception($e);
        } else {
            Logger::info('HTTP ' . $status . ' ' . $request->method() . ' ' . $request->path() . ': ' . $e->getMessage());
        }

        if ((bool) Config::get('app.debug') && !($e instanceof HttpException)) {
            $detail = get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
        }

        if ($request->wantsJson()) {
            $payload = ['error' => $message, 'status' => $status];
            if ($detail !== null) {
                $payload['detail'] = $detail;
            }
            return Response::json($payload, $status);
        }

        $html = View::render('errors/error', [
            'status'  => $status,
            'message' => $message,
            'detail'  => $detail,
            'title'   => 'Error ' . $status,
        ], 'layouts/app');

        return Response::html($html, $status);
    }

    private static function defaultMessage($status)
    {
        switch ($status) {
            case 403:
                return 'You do not have permission to open that page.';
            case 404:
                return 'We could not find the page you were looking for.';
            case 405:
                return 'That action is not allowed on this address.';
            case 419:
                return 'Your session expired. Please try again.';
            case 429:
                return 'Too many attempts. Please wait before trying again.';
            default:
                return 'Something went wrong.';
        }
    }

    /**
     * PHP's "Maximum execution time" fatal is not catchable, so a shutdown
     * handler is the only way to turn it into a readable response. The log
     * entry records the request URI, which the plain PHP error log does not,
     * so a slow page can always be traced back to a route.
     */
    public static function registerTimeoutHandler()
    {
        register_shutdown_function(static function () {
            $error = error_get_last();
            if (!is_array($error) || (int) ($error['type'] ?? 0) !== E_ERROR) {
                return;
            }
            if (stripos((string) ($error['message'] ?? ''), 'Maximum execution time') === false) {
                return;
            }

            try {
                Logger::error('Request exceeded PHP max_execution_time', [
                    'method'        => $_SERVER['REQUEST_METHOD'] ?? '',
                    'uri'           => $_SERVER['REQUEST_URI'] ?? '',
                    'limit_seconds' => ini_get('max_execution_time'),
                    'file'          => $error['file'] ?? '',
                    'line'          => $error['line'] ?? 0,
                ]);
            } catch (\Throwable $ignored) {
                // The process is already ending; nothing else can be done.
            }

            if (headers_sent()) {
                return;
            }

            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 10');
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                . '<title>Request timed out</title></head><body>'
                . '<main><h1>The request took too long</h1>'
                . '<p>The server is responding slowly and this page was stopped before it finished.</p>'
                . '<p>Your last action may not have been saved. Please check before trying again.</p>'
                . '</main></body></html>';
        });
    }
}
