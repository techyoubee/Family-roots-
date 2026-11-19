<?php

namespace App\Http\Middleware;

use App\Core\Auth;

class RedirectIfAuthenticated implements Middleware
{
    public function handle($request, callable $next)
    {
        if (Auth::check()) {
            return \App\Core\Response::redirect(url('/dashboard'));
        }
        return $next($request);
    }
}
