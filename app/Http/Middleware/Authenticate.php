<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Session;

class Authenticate implements Middleware
{
    public function handle($request, callable $next)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in to continue.');
            Session::set('_intended', $request->path());
            $returnTo = url($request->path());
            return \App\Core\Response::redirect(\query_url('/login', ['next' => $returnTo]));
        }
        return $next($request);
    }
}
