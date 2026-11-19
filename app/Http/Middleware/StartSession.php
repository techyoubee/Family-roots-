<?php

namespace App\Http\Middleware;

use App\Core\Session;

class StartSession implements Middleware
{
    public function handle($request, callable $next)
    {
        Session::start();
        return $next($request);
    }
}
