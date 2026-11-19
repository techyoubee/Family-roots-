<?php

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Models\Family;

/**
 * Resolve the `family` route parameter (public identifier FAM-XXXXXX or slug)
 * into a full family row and attach it to the request. Downstream middleware
 * and controllers then work with the resolved id, never a string slug.
 *
 * Privacy rules mirror the public directory: an unknown family is 404, and a
 * private family is 404 for anyone outside it - the response never reveals
 * that the family exists.
 */
class ResolveFamily implements Middleware
{
    public function handle($request, callable $next)
    {
        $key = (string) $request->routeParam('family', '');
        if ($key === '') {
            abort(404, 'That family could not be found.');
        }

        $family = strpos($key, 'FAM-') === 0 ? Family::findByPublicId($key) : Family::findBySlug($key);
        if (!$family) {
            abort(404, 'That family could not be found.');
        }

        if ($family['visibility'] === 'private' && !Auth::isFamilyMember($family['id']) && !Auth::isPlatformAdmin()) {
            abort(404, 'That family could not be found.');
        }

        $request->setAttribute('family', $family);
        return $next($request);
    }
}
