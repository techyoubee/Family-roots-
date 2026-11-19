<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Exceptions\ForbiddenException;

/**
 * Route parameter `family` must identify a family in which the current user
 * holds an active founder/administrator role. Works with ResolveFamily.
 */
class EnsureFamilyAdmin implements Middleware
{
    public function handle($request, callable $next)
    {
        $family = $request->attribute('family');
        $familyId = $family ? (int) $family['id'] : $request->routeParam('family', $request->routeParam('id'));
        if (!Auth::isFamilyAdmin($familyId) && !Auth::isPlatformAdmin()) {
            throw new ForbiddenException('Only family administrators can open that page.');
        }
        return $next($request);
    }
}
