<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Ancestor;
use App\Models\Family;
use App\Models\FamilyMember;

/**
 * Public family directory.
 *
 * Privacy rules enforced here:
 *  - only families whose profile is marked `directory` are listed;
 *  - a family marked `private` is completely invisible: the profile page
 *    returns 404 rather than revealing that the family exists;
 *  - no ancestor records are shown on this page, only aggregate counts.
 */
final class FamilyDirectoryController extends Controller
{
    public function index(Request $request)
    {
        if (config('privacy.directory_requires_login') && !Auth::check()) {
            return \App\Core\Response::redirect(\query_url('/login', ['next' => url('/families')]));
        }

        $search = trim((string) $request->query('q', ''));
        $families = [];
        if ($this->databaseAvailable()) {
            $families = Family::forDirectory($search !== '' ? $search : null, 60);
        }

        return $this->view('families/index', [
            'title'    => 'Family directory',
            'families' => $families,
            'search'   => $search,
        ]);
    }

    public function show(Request $request)
    {
        $key = (string) $request->routeParam('family', '');
        $family = null;
        if ($this->databaseAvailable()) {
            $family = strpos($key, 'FAM-') === 0 ? Family::findByPublicId($key) : Family::findBySlug($key);
        }

        if (!$family) {
            abort(404, 'That family profile could not be found.');
        }

        $isMember = Auth::isFamilyMember($family['id']);
        $isPlatformAdmin = Auth::isPlatformAdmin();

        // Private families exist only for their own members.
        if ($family['visibility'] === 'private' && !$isMember && !$isPlatformAdmin) {
            abort(404, 'That family profile could not be found.');
        }

        $stats = [
            'ancestors'     => Ancestor::countForFamily($family['id']),
            'members'       => FamilyMember::countActive($family['id']),
            'relationships' => (int) \App\Core\Database::value('SELECT COUNT(*) FROM family_relationships WHERE family_id = ?', [$family['id']]),
        ];

        $myRole = Auth::roleIn($family['id']);

        return $this->view('families/show', [
            'title'     => $family['name'] . ' - family profile',
            'family'    => $family,
            'stats'     => $stats,
            'isMember'  => $isMember,
            'myRole'    => $myRole,
        ]);
    }
}
