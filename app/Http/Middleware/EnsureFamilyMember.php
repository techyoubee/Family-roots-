<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Exceptions\ForbiddenException;

/**
 * The current user must hold an active role (any role) in the family named by
 * the route parameter. This is the isolation gate: unrelated families never see
 * each other's trees, records or evidence.
 *
 * Works with ResolveFamily (resolved family row preferred) and falls back to
 * the raw route parameter so route tables stay readable.
 */
class EnsureFamilyMember implements Middleware
{
    public function handle($request, callable $next)
    {
        $family = $request->attribute('family');
        $familyId = $family ? (int) $family['id'] : $request->routeParam('family', $request->routeParam('id'));
        if (!Auth::isFamilyMember($familyId) && !Auth::isPlatformAdmin()) {
            throw new ForbiddenException('This family keeps its records private. Ask a family administrator for access.');
        }
        return $next($request);
    }
}
