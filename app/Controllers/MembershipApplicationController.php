<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MembershipRequest;
use App\Models\MembershipRequestClaim;
use App\Services\MembershipService;

/**
 * Membership applications (Module F).
 *
 * Two audiences share this controller:
 *  - applicants: choose a family, declare ancestry claims, submit, appeal;
 *  - family administrators: the review queue, matching explanation, decision.
 *
 * The applicant's own requests are shown on /applications; a family's review
 * queue lives at /families/{family}/applications.
 */
final class MembershipApplicationController extends Controller
{
    /* --------------------------------------------------------- applicant side */

    /** The families the signed-in user has applied to (or is a member of). */
    public function index(Request $request)
    {
        $user = Auth::user();
        $requests = $this->databaseAvailable() ? MembershipRequest::forApplicant((int) $user['id']) : [];

        return $this->view('applications/index', [
            'title'    => 'Your membership applications',
            'requests' => $requests,
        ]);
    }

    /** Start (or resume) a draft application for one family. */
    public function create(Request $request)
    {
        $user = Auth::user();
        $familyKey = (string) $request->query('family', '');
        $family = null;
        if ($familyKey !== '' && $this->databaseAvailable()) {
            $family = strpos($familyKey, 'FAM-') === 0 ? Family::findByPublicId($familyKey) : Family::findBySlug($familyKey);
        }

        if (!$family) {
            return $this->view('applications/choose', [
                'title'    => 'Start a membership application',
                'families' => $this->databaseAvailable() ? Family::forDirectory(null, 60) : [],
            ]);
        }

        $result = MembershipService::startDraft($family, $user, null, $request);
        if ($result['error'] !== null) {
            $this->flash('error', $result['error']);
            return $this->redirect('/families/' . rawurlencode($family['public_id']));
        }

        return $this->redirect('/applications/' . rawurlencode($result['request']['public_id']));
    }

    /** The draft workspace: message, ancestry claims, submit. */
    public function show(Request $request)
    {
        $row = $this->applicantRequest($request);
        $family = Family::find($row['family_id']);
        $claims = MembershipRequestClaim::forRequest($row['id']);
        $assessment = $this->decodeSummary($row);

        return $this->view('applications/show', [
            'title'      => 'Application ' . $row['public_id'],
            'request'    => $row,
            'family'     => $family,
            'claims'     => $claims,
            'assessment' => $assessment,
            'isOwner'    => true,
            'editable'   => in_array($row['status'], ['draft', 'more_info_required'], true),
        ]);
    }

    public function update(Request $request)
    {
        $row = $this->applicantRequest($request);
        if (!$this->isEditable($row)) {
            $this->flash('error', 'This request can no longer be edited.');
            return $this->redirect('/applications/' . rawurlencode($row['public_id']));
        }

        $error = MembershipService::updateDraft($row, [
            'claimed_relationship' => $request->input('claimed_relationship'),
            'applicant_message'    => $request->input('applicant_message'),
        ], $request);
        if ($error !== null) {
            $this->flash('error', $error);
        } else {
            $this->flash('success', 'Your application details were saved.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    public function addClaim(Request $request)
    {
        $row = $this->applicantRequest($request);
        $validator = Validator::make($request->all(), [
            'relationship' => 'required|string',
            'given_names'  => 'required|string|max:150',
        ], [
            'relationship' => 'Relationship',
            'given_names'  => 'Given names',
        ]);
        if ($validator->fails()) {
            return $this->backWithErrors($validator, '/applications/' . rawurlencode($row['public_id']));
        }

        $result = MembershipService::addClaim($row, [
            'relationship'    => $validator->value('relationship'),
            'given_names'     => $validator->value('given_names'),
            'surname'         => $request->input('surname'),
            'alt_names'       => $request->input('alt_names'),
            'sex'             => $request->input('sex', 'unknown'),
            'is_living'       => $request->input('is_living'),
            'birth_date_text' => $request->input('birth_date_text'),
            'death_date_text' => $request->input('death_date_text'),
            'birth_place'     => $request->input('birth_place'),
            'notes'           => $request->input('notes'),
        ], $request);

        if ($result['error'] !== null) {
            $this->flash('error', $result['error']);
        } else {
            $this->flash('success', 'Ancestor added to your declaration.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    public function removeClaim(Request $request)
    {
        $row = $this->applicantRequest($request);
        $error = MembershipService::removeClaim($row, (int) $request->routeParam('claim'), $request);
        if ($error !== null) {
            $this->flash('error', $error);
        } else {
            $this->flash('success', 'Ancestor removed.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    public function submit(Request $request)
    {
        $row = $this->applicantRequest($request);
        $family = Family::find($row['family_id']);
        $result = MembershipService::submit($family, $row, $request);
        if ($result['error'] !== null) {
            $this->flash('error', $result['error']);
        } else {
            $this->flash('success', 'Your application was submitted for review.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    public function appeal(Request $request)
    {
        $row = $this->applicantRequest($request);
        $error = MembershipService::appeal($row, $request->input('appeal_reason'), $request);
        if ($error !== null) {
            $this->flash('error', $error);
        } else {
            $this->flash('success', 'Your appeal was submitted for review.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    public function cancel(Request $request)
    {
        $row = $this->applicantRequest($request);
        $error = MembershipService::cancel($row, $request);
        if ($error !== null) {
            $this->flash('error', $error);
        } else {
            $this->flash('success', 'Your application was cancelled.');
        }
        return $this->redirect('/applications/' . rawurlencode($row['public_id']));
    }

    /* ---------------------------------------------------------- reviewer side */

    /** A family's membership review queue (administrators only). */
    public function queue(Request $request)
    {
        $family = $request->attribute('family');
        $status = (string) $request->query('status', '');
        $rows = MembershipRequest::forFamily($family['id'], [
            'decidable' => true,
            'status'    => $status,
        ]);

        return $this->view('applications/queue', [
            'title'   => $family['name'] . ' - membership applications',
            'family'  => $family,
            'rows'    => $rows,
            'status'  => $status,
            'pending' => MembershipRequest::pendingReviewCount($family['id']),
        ]);
    }

    /** Full review detail for one request, with the matching explanation. */
    public function review(Request $request)
    {
        $family = $request->attribute('family');
        $row = MembershipRequest::findForFamily($family['id'], (string) $request->routeParam('application'));
        if (!$row) {
            abort(404, 'That application could not be found in this family.');
        }
        $claims = MembershipRequestClaim::forRequest($row['id']);

        return $this->view('applications/review', [
            'title'      => 'Review application ' . $row['public_id'],
            'family'     => $family,
            'request'    => $row,
            'claims'     => $claims,
            'assessment' => $this->decodeSummary($row),
        ]);
    }

    public function decide(Request $request)
    {
        $family = $request->attribute('family');
        $row = MembershipRequest::findForFamily($family['id'], (string) $request->routeParam('application'));
        if (!$row) {
            abort(404, 'That application could not be found in this family.');
        }
        $actor = Auth::user();
        $action = (string) $request->input('action', '');
        $redirect = '/families/' . rawurlencode($family['public_id']) . '/applications/' . rawurlencode($row['public_id']);

        switch ($action) {
            case 'approve':
                $error = MembershipService::approve($family, $row, $actor, $request->input('link_ancestor_id'), $request);
                break;
            case 'decline':
                $error = MembershipService::decline($family, $row, $actor, $request->input('decline_reason'), $request);
                break;
            case 'more_info':
                $error = MembershipService::requestMoreInfo($family, $row, $actor, $request->input('more_info_message'), $request);
                break;
            default:
                $error = 'Choose a valid decision.';
        }

        if ($error !== null) {
            $this->flash('error', $error);
        } else {
            $this->flash('success', 'The applicant has been notified of your decision.');
        }
        return $this->redirect($redirect);
    }

    /* -------------------------------------------------------------- helpers */

    private function applicantRequest(Request $request)
    {
        $row = MembershipRequest::findByPublicId((string) $request->routeParam('application', ''));
        if (!$row) {
            abort(404, 'That application could not be found.');
        }
        if ((int) $row['applicant_user_id'] !== (int) $this->userId()) {
            abort(403, 'You can only view your own applications.');
        }
        return $row;
    }

    private function isEditable(array $row)
    {
        return in_array($row['status'], ['draft', 'more_info_required'], true);
    }

    private function decodeSummary(array $row)
    {
        if (empty($row['match_summary'])) {
            return null;
        }
        $decoded = is_string($row['match_summary']) ? json_decode($row['match_summary'], true) : $row['match_summary'];
        return is_array($decoded) ? $decoded : null;
    }

    private function backWithErrors(Validator $validator, $fallback)
    {
        Session::flashErrors($validator->errors(), \request()->all());
        return $this->redirect($fallback);
    }
}
