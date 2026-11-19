<?php

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

interface Middleware
{
    /**
     * @param  callable(Request):Response $next
     * @return Response
     */
    public function handle(Request $request, callable $next);
}
