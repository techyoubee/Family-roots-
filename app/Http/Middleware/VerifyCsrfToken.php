<?php

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Exceptions\HttpException;

/**
 * Rejects state-changing requests with a missing or stale CSRF token.
 * Applies to form posts (including JSON posts sending X-CSRF-Token).
 */
class VerifyCsrfToken implements Middleware
{
    public function handle($request, callable $next)
    {
        $method = $request->method();
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if (!Csrf::check($request->csrfToken())) {
            throw new HttpException(419, 'Your session expired or the form was submitted from an untrusted page. Please try again.');
        }

        return $next($request);
    }
}
