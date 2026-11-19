<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Exceptions\ForbiddenException;

class EnsurePlatformAdmin implements Middleware
{
    public function handle($request, callable $next)
    {
        if (!Auth::isPlatformAdmin()) {
            throw new ForbiddenException('Platform administrator access is required for that page.');
        }
        return $next($request);
    }
}
