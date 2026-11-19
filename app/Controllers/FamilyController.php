<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Family;
use App\Services\FamilyService;

/**
 * Register a family and maintain its profile.
 *
 * Anyone signed in with a confirmed email address may register a family and
 * becomes its founder. Profile editing is limited to family administrators
 * (EnsureFamilyAdmin on the routes).
 */
final class FamilyController extends Controller
{
    /** Registration form. */
    public function create(Request $request)
    {
        return $this->view('families/register', [
            'title' => 'Register a family',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:150',
            'description' => 'nullable|string|max:2000',
            'origins'     => 'nullable|string|max:255',
            'region'      => 'nullable|string|max:120',
            'country'     => 'nullable|string|max:120',
            'visibility'  => 'required|in:private,members,directory',
        ], [
            'name'        => 'Family name',
            'description' => 'Description',
            'origins'     => 'Origins',
            'region'      => 'Region',
            'country'     => 'Country',
            'visibility'  => 'Visibility',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/families/create');
        }

        $limit = (int) config('security.family_create_per_day', 5);
        if (!RateLimiter::attempt('family_create', (string) $this->userId(), $limit, 86400)) {
            Session::flash('error', 'You have registered several families recently. Please try again tomorrow.');
            return $this->redirect('/dashboard');
        }

        $user = Auth::user();
        $family = FamilyService::createFamily($user, [
            'name'        => $validator->value('name'),
            'description' => $validator->value('description'),
            'origins'     => $validator->value('origins'),
            'region'      => $validator->value('region'),
            'country'     => $validator->value('country'),
            'visibility'  => $validator->value('visibility'),
        ], $request);

        Session::flash('success', '"' . $family['name'] . '" is registered. You are its founder - add ancestor records when you are ready.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']));
    }

    /** Profile settings form (administrators only). */
    public function edit(Request $request)
    {
        $family = $request->attribute('family');
        return $this->view('families/settings', [
            'title'  => $family['name'] . ' - settings',
            'family' => $family,
            'myRole' => Auth::roleIn($family['id']),
        ]);
    }

    public function update(Request $request)
    {
        $family = $request->attribute('family');

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|min:2|max:150',
            'description'   => 'nullable|string|max:2000',
            'history'       => 'nullable|string|max:10000',
            'origins'       => 'nullable|string|max:255',
            'founding_note' => 'nullable|string|max:2000',
            'region'        => 'nullable|string|max:120',
            'country'       => 'nullable|string|max:120',
            'visibility'    => 'required|in:private,members,directory',
        ], [
            'name'          => 'Family name',
            'description'   => 'Description',
            'history'       => 'Family history',
            'origins'       => 'Origins',
            'founding_note' => 'Founding ancestors',
            'region'        => 'Region',
            'country'       => 'Country',
            'visibility'    => 'Visibility',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/families/' . rawurlencode($family['public_id']) . '/settings');
        }

        $updated = FamilyService::updateProfile($family, Auth::user(), [
            'name'          => $validator->value('name'),
            'description'   => $validator->value('description'),
            'history'       => $validator->value('history'),
            'origins'       => $validator->value('origins'),
            'founding_note' => $validator->value('founding_note'),
            'region'        => $validator->value('region'),
            'country'       => $validator->value('country'),
            'visibility'    => $validator->value('visibility'),
        ], $request);

        Session::flash('success', 'The family profile has been saved.');
        return $this->redirect('/families/' . rawurlencode($updated['public_id']));
    }

    private function backWithErrors(Validator $validator, $fallback)
    {
        Session::flashErrors($validator->errors(), request()->all());
        return $this->redirect($fallback);
    }
}
