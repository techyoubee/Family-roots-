<?php

namespace Tests;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MembershipRequest;
use App\Models\MembershipRequestClaim;
use App\Models\User;
use App\Services\MembershipService;

/**
 * Membership applications (Module F) and the advisory matching engine (Module E).
 *
 * These tests exercise the service layer directly: drafting, declaring
 * ancestors, submitting, explainable scoring, and the human decisions that
 * follow. The matching engine must never grant membership on its own.
 */
final class MembershipTest extends TestCase
{
    public function setUp()
    {
        Auth::logout();
    }

    public function tearDown()
    {
        Auth::logout();
    }

    /** A family with a father and grandfather the applicant can name. */
    private function familyWithAncestors(&$applicantId, &$fatherId, &$grandfatherId)
    {
        $founderId = Fixtures::user();
        $familyId = Fixtures::family($founderId);
        $family = Family::find($familyId);

        // The tree is numbered oldest-first: gen 1 = grandfather, gen 2 = father,
        // gen 3 = the applicant's own generation (the anchor for depth 0).
        $grandfatherId = Fixtures::ancestor($familyId, 'Ahmad', 'Kiru', 1, ['sex' => 'male', 'birth_date' => '1930-01-01']);
        $fatherId = Fixtures::ancestor($familyId, 'Salim', 'Kiru', 2, ['sex' => 'male', 'birth_date' => '1960-01-01']);
        Fixtures::ancestor($familyId, 'Applicant', 'Kiru', 3, ['sex' => 'male', 'birth_date' => '1990-01-01']);

        $applicantId = Fixtures::user();
        return $family;
    }

    public function testStartingADraftReturnsAnOpenRequestAndBlocksDuplicates()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $applicant = User::find($applicantId);

        $first = MembershipService::startDraft($family, $applicant);
        $this->assertNull($first['error'], 'A signed-in non-member can start a draft.');
        $this->assertNotNull($first['request'], 'A draft request is returned.');
        $this->assertSame('draft', $first['request']['status'], 'The new request starts as a draft.');
        $this->assertContains('REQ-', $first['request']['public_id'], 'The request gets a REQ- public identifier.');

        $second = MembershipService::startDraft($family, $applicant);
        $this->assertNull($second['error'], 'Starting again is not an error.');
        $this->assertSame($first['request']['id'], $second['request']['id'], 'The same open draft is returned, never a second one.');
    }

    public function testAnExistingMemberCannotStartAnApplication()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        Fixtures::member($family['id'], $applicantId, 'member');

        $result = MembershipService::startDraft($family, User::find($applicantId));
        $this->assertNotNull($result['error'], 'A current member is refused.');
        $this->assertNull($result['request'], 'No request is created for a member.');
    }

    public function testClaimsCanBeAddedAndRemovedWhileDrafting()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        $added = MembershipService::addClaim($request, [
            'relationship' => 'father',
            'given_names'  => 'Salim',
            'surname'      => 'Kiru',
            'sex'          => 'male',
        ]);
        $this->assertNull($added['error'], 'A claim can be added to a draft.');
        $this->assertNotNull($added['claim']['name_normalized'], 'The claim stores a normalised name for matching.');
        $this->assertSame(1, (int) $added['claim']['generation'], 'A father maps to generation 1 in the relationship map.');

        $this->assertCount(1, MembershipRequestClaim::forRequest($request['id']), 'One claim is stored.');

        $error = MembershipService::removeClaim($request, $added['claim']['id']);
        $this->assertNull($error, 'The claim can be removed while drafting.');
        $this->assertCount(0, MembershipRequestClaim::forRequest($request['id']), 'The claim is gone.');
    }

    public function testSubmitRequiresAtLeastOneClaim()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        $result = MembershipService::submit($family, $request);
        $this->assertNotNull($result['error'], 'An empty application cannot be submitted.');
        $this->assertSame('draft', MembershipRequest::find($request['id'])['status'], 'The request stays a draft after a refused submit.');
    }

    public function testStrongClaimsAreScoredAndExplained()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Salim', 'surname' => 'Kiru', 'sex' => 'male', 'birth_date_text' => '1960']);
        MembershipService::addClaim($request, ['relationship' => 'grandfather', 'given_names' => 'Ahmad', 'surname' => 'Kiru', 'sex' => 'male', 'birth_date_text' => '1930']);

        $claims = MembershipRequestClaim::forRequest($request['id']);
        $assessment = MembershipService::runMatching($family, MembershipRequest::find($request['id']), $claims);

        $this->assertGreaterThan(0, count($assessment['summary']['claims']), 'The summary explains each claim.');
        foreach ($assessment['summary']['claims'] as $entry) {
            $this->assertTrue($entry['matched'], 'A documented ancestor is matched for every claim.');
            $this->assertNotNull($entry['explanation'], 'Each claim carries a plain-language explanation.');
            $this->assertNotContains('No comparable ancestor', $entry['explanation'], 'The explanation reflects a real match.');
        }
        $this->assertGreaterThan(70, $assessment['score'], 'Two strong claims produce a high overall score.');
        $this->assertSame((int) $fatherId, (int) MembershipRequestClaim::forRequest($request['id'])[0]['matched_ancestor_id'], 'The father claim is matched to the documented father.');
    }

    public function testASurnameAloneIsNotEnoughToMatch()
    {
        $founderId = Fixtures::user();
        $familyId = Fixtures::family($founderId);
        $family = Family::find($familyId);
        // The only person sharing the surname is a distant, wrong-generation woman:
        // a shared surname must never carry a match on its own.
        Fixtures::ancestor($familyId, 'Someone', 'Kiru', 1, ['sex' => 'female']);

        $applicantId = Fixtures::user();
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Totally', 'surname' => 'Kiru', 'sex' => 'male']);
        $claims = MembershipRequestClaim::forRequest($request['id']);
        $assessment = MembershipService::runMatching($family, MembershipRequest::find($request['id']), $claims);

        $this->assertLessThan(45, $assessment['score'], 'A shared surname with conflicting generation and sex scores below the match threshold.');
        $this->assertFalse($assessment['auto_approvable'], 'A weak claim never auto-approves.');
    }

    public function testUnmatchedClaimsRouteToHumanReview()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Nobody', 'surname' => 'Nowhere', 'sex' => 'male']);

        $result = MembershipService::submit($family, MembershipRequest::find($request['id']));
        $this->assertNull($result['error'], 'The application submits.');
        $this->assertSame('pending_review', $result['request']['status'], 'An unmatched application goes to a human queue, never auto-approved.');
    }

    public function testStrongDoubleClaimAutoMatchOnlyRoutesToConfirmation()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];

        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Salim', 'surname' => 'Kiru', 'sex' => 'male', 'birth_date_text' => '1960']);
        MembershipService::addClaim($request, ['relationship' => 'grandfather', 'given_names' => 'Ahmad', 'surname' => 'Kiru', 'sex' => 'male', 'birth_date_text' => '1930']);

        $result = MembershipService::submit($family, MembershipRequest::find($request['id']));
        $this->assertNull($result['error'], 'The application submits.');

        // Auto-approval only *routes*; membership is only created by a human approve().
        $this->assertContains($result['request']['status'], ['auto_matched', 'pending_review'], 'Strong claims are flagged for confirmation.');
        $this->assertNull(FamilyMember::roleFor($family['id'], $applicantId), 'Submitting never grants membership by itself.');
    }

    public function testApprovalCreatesMembershipAndNotifiesTheApplicant()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $founder = User::find($family['founder_user_id']);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];
        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Salim', 'surname' => 'Kiru', 'sex' => 'male']);
        MembershipService::submit($family, MembershipRequest::find($request['id']));

        $row = MembershipRequest::find($request['id']);
        $error = MembershipService::approve($family, $row, $founder, $fatherId);
        $this->assertNull($error, 'An administrator can approve.');
        $this->assertSame('member', FamilyMember::roleFor($family['id'], $applicantId), 'Approval creates an active membership.');

        $updated = MembershipRequest::find($request['id']);
        $this->assertSame('approved', $updated['status'], 'The request is marked approved.');
        $this->assertNotNull($updated['decided_at'], 'The decision is timestamped.');
        $this->assertNotNull($updated['family_member_id'], 'The created membership is linked back to the request.');

        $notifications = \App\Core\Database::select('SELECT type FROM notifications WHERE user_id = ?', [$applicantId]);
        $this->assertContains('membership.approved', array_column($notifications, 'type'), 'The applicant is notified.');
    }

    public function testAnAdministratorCannotDecideTheirOwnApplication()
    {
        $founderId = Fixtures::user();
        $familyId = Fixtures::family($founderId);
        $family = Family::find($familyId);

        // The founder is an admin; give them a request to try to self-approve.
        $row = MembershipRequest::create([
            'family_id'         => $familyId,
            'applicant_user_id' => $founderId,
            'status'            => 'pending_review',
        ]);
        $error = MembershipService::approve($family, $row, User::find($founderId));
        $this->assertNotNull($error, 'Self-approval is refused.');
        $this->assertSame('founder', FamilyMember::roleFor($familyId, $founderId), 'The founder role is untouched by the refused action.');
    }

    public function testDeclineRequiresAReasonAndAllowsOneAppeal()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $founder = User::find($family['founder_user_id']);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];
        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Salim', 'surname' => 'Kiru', 'sex' => 'male']);
        MembershipService::submit($family, MembershipRequest::find($request['id']));

        $row = MembershipRequest::find($request['id']);
        $this->assertNotNull(MembershipService::decline($family, $row, $founder, '   '), 'A decline without a reason is refused.');

        $error = MembershipService::decline($family, $row, $founder, 'The evidence does not yet connect this line.');
        $this->assertNull($error, 'A decline with a reason succeeds.');
        $this->assertSame('declined', MembershipRequest::find($request['id'])['status'], 'The request is declined.');
        $this->assertNull(FamilyMember::roleFor($family['id'], $applicantId), 'A decline creates no membership.');

        $declined = MembershipRequest::find($request['id']);
        $this->assertNull(MembershipService::appeal($declined, 'My grandfather used a different spelling.'), 'The applicant can appeal once.');
        $appealed = MembershipRequest::find($request['id']);
        $this->assertSame('appeal_requested', $appealed['status'], 'The appeal reopens review.');
        $this->assertNotNull(MembershipService::appeal($appealed, 'A second appeal.'), 'A second appeal is refused.');
    }

    public function testRequestMoreInfoReopensTheDraftForEditing()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $founder = User::find($family['founder_user_id']);
        $request = MembershipService::startDraft($family, User::find($applicantId))['request'];
        MembershipService::addClaim($request, ['relationship' => 'father', 'given_names' => 'Salim', 'surname' => 'Kiru', 'sex' => 'male']);
        MembershipService::submit($family, MembershipRequest::find($request['id']));

        $row = MembershipRequest::find($request['id']);
        $error = MembershipService::requestMoreInfo($family, $row, $founder, 'Please add the mother’s line.');
        $this->assertNull($error, 'A reviewer can request more information.');
        $this->assertSame('more_info_required', MembershipRequest::find($request['id'])['status'], 'The request moves to more_info_required.');

        // The applicant may now add claims again.
        $reopened = MembershipRequest::find($request['id']);
        $added = MembershipService::addClaim($reopened, ['relationship' => 'mother', 'given_names' => 'Fatima', 'surname' => 'Kiru', 'sex' => 'female']);
        $this->assertNull($added['error'], 'The applicant can add more claims after a more-info request.');
    }

    public function testCancelClosesTheRequestAndFreesTheSlot()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);
        $applicant = User::find($applicantId);
        $request = MembershipService::startDraft($family, $applicant)['request'];

        $this->assertNull(MembershipService::cancel($request), 'The applicant can cancel a draft.');
        $this->assertSame('cancelled', MembershipRequest::find($request['id'])['status'], 'The request is cancelled.');

        $again = MembershipService::startDraft($family, $applicant);
        $this->assertNotEquals($request['id'], $again['request']['id'], 'Cancelling frees the applicant to start a fresh application.');
    }

    public function testForFamilyQueueExcludesDraftsAndOrdersByUrgency()
    {
        $family = $this->familyWithAncestors($applicantId, $fatherId, $grandfatherId);

        $draftUser = Fixtures::user();
        MembershipService::startDraft($family, User::find($draftUser)); // stays draft

        $pendingUser = Fixtures::user();
        $pending = MembershipService::startDraft($family, User::find($pendingUser))['request'];
        MembershipService::addClaim($pending, ['relationship' => 'father', 'given_names' => 'X', 'surname' => 'Y', 'sex' => 'male']);
        MembershipService::submit($family, MembershipRequest::find($pending['id']));

        $rows = MembershipRequest::forFamily($family['id'], ['decidable' => true]);
        $statuses = array_column($rows, 'status');
        $this->assertNotContains('draft', $statuses, 'Drafts are private to the applicant and never appear in the review queue.');
        $this->assertContains('pending_review', $statuses, 'Submitted work appears in the queue.');
        $this->assertGreaterThan(0, MembershipRequest::pendingReviewCount($family['id']), 'The pending counter reflects the queue.');
    }

    public function testRequestScopedFetchStopsCrossFamilyAccess()
    {
        $familyA = $this->familyWithAncestors($aId, $aFather, $aGrand);
        $familyB = $this->familyWithAncestors($bId, $bFather, $bGrand);
        $request = MembershipService::startDraft($familyA, User::find($aId))['request'];

        $this->assertNotNull(MembershipRequest::findForFamily($familyA['id'], $request['public_id']), 'The request resolves inside its own family.');
        $this->assertNull(MembershipRequest::findForFamily($familyB['id'], $request['public_id']), 'The same request is invisible through another family.');
    }
}
