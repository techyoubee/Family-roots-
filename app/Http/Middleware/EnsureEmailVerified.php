<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Response;

/**
 * Blocks account actions until the email address is confirmed. Users are told
 * exactly what to do next instead of being silently redirected.
 */
class EnsureEmailVerified implements Middleware
{
    public function handle($request, callable $next)
    {
        $user = Auth::user();
        if ($user && empty($user['email_verified_at'])) {
            \App\Core\Session::flash('error', 'Please confirm your email address before continuing. We can send a new confirmation link.');
            return Response::redirect(url('/verify-email'));
        }
        return $next($request);
    }
}
