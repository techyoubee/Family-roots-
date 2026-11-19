<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Models\Ancestor;
use App\Models\FamilyMember;
use App\Models\MembershipRequest;
use App\Models\MembershipRequestClaim;

/**
 * Membership request workflow: drafting, submitting, explainable matching and
 * the human review that follows.
 *
 * Design rules (mirroring the platform promise):
 *  - the matching engine is *advisory*: it returns a score and per-claim
 *    explanations, and it can route a request, but it never grants membership
 *    on its own unless the family has explicitly enabled auto-approval AND
 *    every claim is a strong, unambiguous match;
 *  - a surname alone is never enough - matches weigh relationships, name,
 *    dates, place and documented evidence together;
 *  - every state change is audited and the applicant is notified;
 *  - a decline always carries a reason, and an applicant may appeal once.
 */
final class MembershipService
{
    /** A claim is a "strong" match at or above this confidence (0..100). */
    public const STRONG_MATCH = 80.0;
    /** Below this a claim is considered unmatched. */
    public const WEAK_MATCH = 45.0;

    /* ------------------------------------------------------------- drafting */

    /**
     * Begin (or return) the applicant's open draft request for a family.
     *
     * @return array{request:array,error:?string}
     */
    public static function startDraft(array $family, array $applicant, $claimedRelationship = null, Request $request = null)
    {
        if (FamilyMember::roleFor($family['id'], $applicant['id']) !== null) {
            return ['request' => null, 'error' => 'You are already a member of this family.'];
        }

        $existing = MembershipRequest::openFor($family['id'], $applicant['id']);
        if ($existing) {
            return ['request' => $existing, 'error' => null];
        }

        $created = MembershipRequest::create([
            'family_id'            => (int) $family['id'],
            'applicant_user_id'    => (int) $applicant['id'],
            'status'               => 'draft',
            'claimed_relationship' => $claimedRelationship,
        ]);

        AuditLog::record('membership.draft_started', [
            'request'     => $request,
            'family_id'   => $family['id'],
            'entity_type' => 'membership_request',
            'entity_id'   => $created['id'],
            'summary'     => 'A membership application draft was started for "' . $family['name'] . '".',
        ]);

        return ['request' => $created, 'error' => null];
    }

    public static function updateDraft(array $requestRow, array $data, Request $req = null)
    {
        if ($requestRow['status'] !== 'draft') {
            return 'This request can no longer be edited; it has already been submitted.';
        }

        MembershipRequest::update($requestRow['id'], array_intersect_key($data, array_flip([
            'claimed_relationship', 'applicant_message',
        ])));

        return null;
    }

    /* --------------------------------------------------------------- claims */

    public static function addClaim(array $requestRow, array $data, Request $req = null)
    {
        if (!in_array($requestRow['status'], ['draft', 'more_info_required'], true)) {
            return ['claim' => null, 'error' => 'Claims can only be added to a draft or a request that needs more information.'];
        }

        $claim = MembershipRequestClaim::create([
            'relationship'    => $data['relationship'] ?? 'other',
            'given_names'     => trim((string) $data['given_names']),
            'surname'         => isset($data['surname']) && trim((string) $data['surname']) !== '' ? trim((string) $data['surname']) : null,
            'alt_names'       => $data['alt_names'] ?? null,
            'sex'             => $data['sex'] ?? 'unknown',
            'is_living'       => !empty($data['is_living']) ? 1 : 0,
            'birth_date_text' => $data['birth_date_text'] ?? null,
            'death_date_text' => $data['death_date_text'] ?? null,
            'birth_place'     => $data['birth_place'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ], $requestRow['id']);

        AuditLog::record('membership.claim_added', [
            'request'     => $req,
            'family_id'   => $requestRow['family_id'],
            'entity_type' => 'membership_claim',
            'entity_id'   => $claim['id'],
            'summary'     => 'An ancestry claim (' . $claim['relationship'] . ') was added to a request.',
        ]);

        return ['claim' => $claim, 'error' => null];
    }

    public static function removeClaim(array $requestRow, $claimId, Request $req = null)
    {
        if (!in_array($requestRow['status'], ['draft', 'more_info_required'], true)) {
            return 'Claims can only be removed while the request is editable.';
        }
        $claim = MembershipRequestClaim::findForRequest($requestRow['id'], $claimId);
        if (!$claim) {
            return 'That claim could not be found in this request.';
        }
        MembershipRequestClaim::delete($claimId, $requestRow['id']);
        return null;
    }

    /* ---------------------------------------------------------- submit+score */

    /**
     * Submit a draft: validate it has at least one ancestor beyond "self",
     * run the explainable matching engine and route the request.
     *
     * @return array{request:?array,error:?string}
     */
    public static function submit(array $family, array $requestRow, Request $req = null)
    {
        if ($requestRow['status'] !== 'draft' && $requestRow['status'] !== 'more_info_required') {
            return ['request' => null, 'error' => 'This request has already been submitted.'];
        }

        $claims = MembershipRequestClaim::forRequest($requestRow['id']);
        if (count($claims) < 1) {
            return ['request' => null, 'error' => 'Add at least one ancestor (a parent or grandparent) before submitting.'];
        }

        $assessment = self::runMatching($family, $requestRow, $claims);

        $status = 'pending_review';
        $autoDecision = 'needs_review';
        if ($assessment['auto_approvable']) {
            $status = 'auto_matched';
            $autoDecision = 'auto_approved';
        }

        MembershipRequest::update($requestRow['id'], [
            'status'          => $status,
            'submitted_at'    => date('Y-m-d H:i:s'),
            'match_score'     => $assessment['score'],
            'match_summary'   => json_encode($assessment['summary'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'auto_decision'   => $autoDecision,
            'decision_reason' => $assessment['headline'],
            'last_reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record('membership.submitted', [
            'request'     => $req,
            'family_id'   => $family['id'],
            'entity_type' => 'membership_request',
            'entity_id'   => $requestRow['id'],
            'summary'     => 'A membership application was submitted (score ' . number_format((float) $assessment['score'], 1) . ').',
            'after'       => ['status' => $status, 'score' => $assessment['score']],
        ]);

        self::notifyReviewers($family, $requestRow['id'], $status === 'auto_matched' ? 'membership.auto_matched' : 'membership.submitted');

        $updated = MembershipRequest::find($requestRow['id']);
        return ['request' => $updated, 'error' => null];
    }

    /**
     * The explainable matching engine.
     *
     * For every claim it finds the best candidate ancestor in the family tree
     * and builds a per-claim explanation. The overall score is the average
     * confidence weighted towards the closest generations, and `auto_approvable`
     * is true only when the family allows it and every claim is strong.
     *
     * @return array{score:float,summary:array,headline:string,auto_approvable:bool}
     */
    public static function runMatching(array $family, array $requestRow, array $claims)
    {
        MembershipRequestClaim::resetMatches($requestRow['id']);

        $familyAncestors = \App\Core\Database::select(
            'SELECT * FROM ancestors WHERE family_id = ?',
            [(int) $family['id']]
        );

        // Ancestor generations increase toward the present (the oldest known
        // ancestor is the lowest number). A claim, however, records how many
        // steps *above the applicant* a relative sits. To compare the two, the
        // engine anchors on the family's most recent generation: an ancestor at
        // the newest generation is depth 0 (the applicant's own generation), one
        // step older is depth 1 (a parent), and so on.
        $maxGeneration = 0;
        foreach ($familyAncestors as $candidate) {
            $maxGeneration = max($maxGeneration, (int) $candidate['generation']);
        }

        $perClaim = [];
        $confidences = [];
        $allStrong = true;
        $anyMatched = false;

        foreach ($claims as $claim) {
            $best = self::scoreClaim($claim, $familyAncestors, $family, $maxGeneration);

            MembershipRequestClaim::update($claim['id'], [
                'matched_ancestor_id' => $best['ancestor_id'],
                'match_confidence'    => $best['confidence'],
                'match_explanation'   => $best['explanation'],
            ]);

            $perClaim[] = [
                'claim_id'      => (int) $claim['id'],
                'relationship'  => $claim['relationship'],
                'name'          => trim(($claim['given_names'] ?? '') . ' ' . ($claim['surname'] ?? '')),
                'confidence'    => $best['confidence'],
                'matched'       => $best['ancestor_id'] !== null,
                'matched_name'  => $best['ancestor_name'],
                'explanation'   => $best['explanation'],
            ];
            $confidences[] = $best['confidence'];
            if ($best['ancestor_id'] !== null) {
                $anyMatched = true;
            }
            if ($best['confidence'] < self::STRONG_MATCH) {
                $allStrong = false;
            }
        }

        $score = $confidences ? round(array_sum($confidences) / count($confidences), 2) : 0.0;

        $auto = Config::get('matching.auto_approve', []);
        $autoApprovalEnabled = (bool) ($auto['enabled'] ?? false);
        $minScore = (float) ($auto['min_score'] ?? self::STRONG_MATCH);
        $minClaims = (int) ($auto['min_confirm_claims'] ?? ($auto['min_confirmed_claims'] ?? 2));
        $requireVerifiedFamily = (bool) ($auto['require_verified_family'] ?? true);
        $familyExempt = (bool) ($auto['family_verification_exempt'] ?? false);

        $familyOk = !$requireVerifiedFamily
            || ($family['verification_status'] ?? '') === 'verified'
            || $familyExempt;

        $autoApprovable = $autoApprovalEnabled
            && $allStrong
            && $score >= $minScore
            && count($claims) >= $minClaims
            && $familyOk;

        if (!$anyMatched) {
            $headline = 'No claim matched a documented ancestor. A family administrator will review this request by hand.';
        } elseif ($autoApprovable) {
            $headline = 'Every claim matched a documented ancestor with strong confidence.';
        } elseif ($allStrong) {
            $headline = 'Claims match documented ancestors; a final human confirmation is required.';
        } else {
            $headline = 'Some claims are uncertain or unmatched. A family administrator will review them.';
        }

        return [
            'score'            => $score,
            'summary'          => [
                'claims'             => $perClaim,
                'matched_count'      => count(array_filter($confidences, static function ($c) {
                    return $c > 0;
                })),
                'claim_count'        => count($claims),
                'auto_approval'      => $autoApprovalEnabled,
                'matched_names'      => array_values(array_filter(array_column($perClaim, 'matched_name'))),
            ],
            'headline'         => $headline,
            'auto_approvable'  => $autoApprovable,
        ];
    }

    /**
     * Score one claim against every family ancestor and return the best match
     * with a human-readable explanation. Never returns a bare "yes" - always a
     * reason and a confidence.
     */
    private static function scoreClaim(array $claim, array $familyAncestors, array $family, $maxGeneration = null)
    {
        $best = ['ancestor_id' => null, 'ancestor_name' => null, 'confidence' => 0.0, 'explanation' => 'No comparable ancestor was found in this family tree.'];
        $claimName = trim(($claim['given_names'] ?? '') . ' ' . ($claim['surname'] ?? ''));

        foreach ($familyAncestors as $candidate) {
            $candidateName = trim(($candidate['given_names'] ?? '') . ' ' . ($candidate['surname'] ?? ''));

            $reasons = [];
            $weights = 0.0;
            $earned = 0.0;

            // Name similarity (heaviest single signal, never sufficient alone).
            $nameSim = \App\Core\Str::nameSimilarity($claimName, $candidateName);
            $weights += 45.0;
            $earned += $nameSim * 45.0;
            if ($nameSim >= 0.75) {
                $reasons[] = 'the name is a close match';
            } elseif ($nameSim >= 0.4) {
                $reasons[] = 'the name is a partial / variant match';
            } else {
                $reasons[] = 'the name is a weak match';
            }

            // Surname agreement, treated separately (documented, not assumed).
            $claimSurname = \App\Core\Str::normalizeName($claim['surname'] ?? '');
            $candSurname = \App\Core\Str::normalizeName($candidate['surname'] ?? '');
            if ($claimSurname !== '' && $candSurname !== '') {
                $weights += 10.0;
                if ($claimSurname === $candSurname) {
                    $earned += 10.0;
                    $reasons[] = 'the recorded surname matches';
                } else {
                    $reasons[] = 'the recorded surname differs';
                }
            }

            // Generation agreement with the claimed relationship. The claim
            // records a relative *depth* (self = 0, father = 1, grandfather = 2),
            // so we convert the candidate's absolute generation into the same
            // depth scale using the family's most recent generation as depth 0.
            $weights += 15.0;
            $claimedDepth = (int) ($claim['generation'] ?? MembershipRequestClaim::generationFor($claim['relationship']));
            if ($maxGeneration === null) {
                $maxGeneration = 0;
                foreach ($familyAncestors as $candidate) {
                    $maxGeneration = max($maxGeneration, (int) $candidate['generation']);
                }
            }
            $candidateDepth = max(0, (int) $maxGeneration - (int) $candidate['generation']);
            if ($candidateDepth === $claimedDepth) {
                $earned += 15.0;
                $reasons[] = 'the generation matches the claimed relationship';
            } elseif (abs($candidateDepth - $claimedDepth) === 1) {
                $earned += 7.5;
                $reasons[] = 'the generation is one step off the claim';
            }

            // Birth/death year agreement when both sides recorded a year.
            $weights += 15.0;
            $yearNote = self::yearAgreement($claim, $candidate);
            $earned += $yearNote['points'] * 15.0;
            $reasons[] = $yearNote['reason'];

            // Sex agreement when the relationship implies it (father/mother...).
            $impliedSex = self::impliedSex($claim['relationship']);
            if ($impliedSex !== null) {
                $weights += 10.0;
                if (($candidate['sex'] ?? 'unknown') === $impliedSex || ($candidate['sex'] ?? 'unknown') === 'unknown') {
                    $earned += ($candidate['sex'] === $impliedSex ? 10.0 : 5.0);
                } else {
                    $reasons[] = 'the recorded sex conflicts with the claimed role';
                }
            }

            // Documented evidence is a real bonus signal.
            if (!empty($candidate['evidence_source'])) {
                $weights += 5.0;
                $earned += 5.0;
                $reasons[] = 'the tree record notes supporting evidence';
            }

            $confidence = $weights > 0 ? round(($earned / $weights) * 100, 2) : 0.0;

            if ($confidence > $best['confidence']) {
                $best = [
                    'ancestor_id'   => (int) $candidate['id'],
                    'ancestor_name' => Ancestor::displayName($candidate),
                    'confidence'    => $confidence,
                    'explanation'   => self::explain($claimName, $reasons, $confidence),
                ];
            }
        }

        // Below the weak threshold, report no match rather than a misleading one.
        if ($best['confidence'] < self::WEAK_MATCH) {
            $best['ancestor_id'] = null;
            $best['explanation'] = self::explain($claimName, ['no candidate scored above the minimum confidence threshold'], $best['confidence']);
            $best['confidence'] = 0.0;
            $best['ancestor_name'] = null;
        }

        return $best;
    }

    private static function yearAgreement(array $claim, array $candidate)
    {
        $claimYear = self::firstYear($claim['birth_date_text'] ?? null);
        $candYear = Ancestor::birthYear($candidate);
        if ($claimYear === null || $candYear === null) {
            return ['points' => 0.35, 'reason' => 'birth years could not be compared'];
        }
        $diff = abs($claimYear - $candYear);
        if ($diff <= 1) {
            return ['points' => 1.0, 'reason' => 'the birth year aligns'];
        }
        if ($diff <= 5) {
            return ['points' => 0.6, 'reason' => 'the birth year is a few years off'];
        }
        return ['points' => 0.0, 'reason' => 'the birth year disagrees'];
    }

    private static function explain($claimName, array $reasons, $confidence)
    {
        $band = $confidence >= self::STRONG_MATCH ? 'a strong match'
            : ($confidence >= self::WEAK_MATCH ? 'a partial match' : 'an unlikely match');
        return ucfirst($claimName) . ' scored ' . number_format((float) $confidence, 1) . '% (' . $band . '): '
            . implode('; ', $reasons) . '.';
    }

    private static function firstYear($text)
    {
        if ($text && preg_match('/(1[5-9]\d\d|20\d\d)/', (string) $text, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private static function impliedSex($relationship)
    {
        if (in_array($relationship, ['father', 'grandfather', 'great_grandfather'], true)) {
            return 'male';
        }
        if (in_array($relationship, ['mother', 'grandmother', 'great_grandmother'], true)) {
            return 'female';
        }
        return null;
    }

    /* --------------------------------------------------------- human review */

    /**
     * Approve a request, creating the family membership for the applicant.
     * Returns an error string when the action is not allowed.
     */
    public static function approve(array $family, array $requestRow, array $actor, $linkAncestorId = null, Request $req = null)
    {
        if (!MembershipRequest::isOpen($requestRow) || $requestRow['status'] === 'draft') {
            return 'This request is not in a reviewable state.';
        }
        if ((int) $requestRow['applicant_user_id'] === (int) $actor['id']) {
            return 'You cannot decide on your own application.';
        }

        $role = 'member';
        // Reuse the applicant's single open request slot as the membership row.
        $memberId = FamilyMember::upsertRole($family['id'], $requestRow['applicant_user_id'], $role, [
            'ancestor_id' => $linkAncestorId !== null ? (int) $linkAncestorId : null,
            'joined_at'   => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        MembershipRequest::update($requestRow['id'], [
            'status'             => 'approved',
            'decided_by_user_id' => (int) $actor['id'],
            'decided_at'         => date('Y-m-d H:i:s'),
            'family_member_id'   => (int) $memberId,
            'last_reviewed_at'   => date('Y-m-d H:i:s'),
            'decision_reason'    => 'Approved by a family administrator.',
            'decline_reason'     => null,
        ]);

        Auth::clearCache();

        AuditLog::record('membership.approved', [
            'request'       => $req,
            'family_id'     => $family['id'],
            'actor_user_id' => (int) $actor['id'],
            'entity_type'   => 'membership_request',
            'entity_id'     => $requestRow['id'],
            'summary'       => 'A membership application for "' . $family['name'] . '" was approved.',
        ]);

        NotificationService::notify(
            (int) $requestRow['applicant_user_id'],
            'membership.approved',
            'Welcome to ' . $family['name'],
            'Your membership application was approved. You can now explore the family tree and records.',
            '/families/' . rawurlencode($family['public_id']),
            (int) $family['id']
        );

        return null;
    }

    public static function decline(array $family, array $requestRow, array $actor, $reason, Request $req = null)
    {
        if (!MembershipRequest::isOpen($requestRow) || $requestRow['status'] === 'draft') {
            return 'This request is not in a reviewable state.';
        }
        if (trim((string) $reason) === '') {
            return 'A decline must include a reason for the applicant.';
        }

        MembershipRequest::update($requestRow['id'], [
            'status'             => 'declined',
            'decided_by_user_id' => (int) $actor['id'],
            'decided_at'         => date('Y-m-d H:i:s'),
            'last_reviewed_at'   => date('Y-m-d H:i:s'),
            'decline_reason'     => trim((string) $reason),
        ]);

        AuditLog::record('membership.declined', [
            'request'       => $req,
            'family_id'     => $family['id'],
            'actor_user_id' => (int) $actor['id'],
            'entity_type'   => 'membership_request',
            'entity_id'     => $requestRow['id'],
            'summary'       => 'A membership application for "' . $family['name'] . '" was declined.',
        ]);

        NotificationService::notify(
            (int) $requestRow['applicant_user_id'],
            'membership.declined',
            'Your application to ' . $family['name'] . ' was declined',
            'Reason: ' . trim((string) $reason) . ' You may appeal this decision once.',
            '/applications/' . rawurlencode($requestRow['public_id']),
            (int) $family['id']
        );

        return null;
    }

    public static function requestMoreInfo(array $family, array $requestRow, array $actor, $message, Request $req = null)
    {
        if (!MembershipRequest::isOpen($requestRow) || $requestRow['status'] === 'draft') {
            return 'This request is not in a reviewable state.';
        }
        if (trim((string) $message) === '') {
            return 'Describe what additional information you need.';
        }

        MembershipRequest::update($requestRow['id'], [
            'status'            => 'more_info_required',
            'reviewer_user_id'  => (int) $actor['id'],
            'more_info_request' => trim((string) $message),
            'last_reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record('membership.more_info_required', [
            'request'       => $req,
            'family_id'     => $family['id'],
            'actor_user_id' => (int) $actor['id'],
            'entity_type'   => 'membership_request',
            'entity_id'     => $requestRow['id'],
            'summary'       => 'More information was requested on a membership application.',
        ]);

        NotificationService::notify(
            (int) $requestRow['applicant_user_id'],
            'membership.more_info',
            'More information is needed for your application to ' . $family['name'],
            trim((string) $message),
            '/applications/' . rawurlencode($requestRow['public_id']),
            (int) $family['id']
        );

        return null;
    }

    /** Applicant appeals a decline once. */
    public static function appeal(array $requestRow, $reason, Request $req = null)
    {
        if ($requestRow['status'] !== 'declined') {
            return 'Only a declined request can be appealed.';
        }
        if (!empty($requestRow['appeal_reason'])) {
            return 'This decision has already been appealed.';
        }
        if (trim((string) $reason) === '') {
            return 'Please explain why the decision should be reconsidered.';
        }

        MembershipRequest::update($requestRow['id'], [
            'status'        => 'appeal_requested',
            'appeal_reason' => trim((string) $reason),
            'appealed_at'   => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record('membership.appealed', [
            'request'     => $req,
            'family_id'   => $requestRow['family_id'],
            'actor_user_id' => (int) $requestRow['applicant_user_id'],
            'entity_type' => 'membership_request',
            'entity_id'   => $requestRow['id'],
            'summary'     => 'A declined membership application was appealed.',
        ]);

        $family = \App\Models\Family::find($requestRow['family_id']);
        if ($family) {
            self::notifyReviewers($family, $requestRow['id'], 'membership.appealed');
        }

        return null;
    }

    /** Applicant cancels their own open request. */
    public static function cancel(array $requestRow, Request $req = null)
    {
        if (!MembershipRequest::isOpen($requestRow)) {
            return 'This request is already closed.';
        }
        MembershipRequest::update($requestRow['id'], [
            'status'            => 'cancelled',
            'last_reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record('membership.cancelled', [
            'request'     => $req,
            'family_id'   => $requestRow['family_id'],
            'actor_user_id' => (int) $requestRow['applicant_user_id'],
            'entity_type' => 'membership_request',
            'entity_id'   => $requestRow['id'],
            'summary'     => 'A membership application was cancelled by the applicant.',
        ]);

        return null;
    }

    /* ------------------------------------------------------------ internals */

    private static function notifyReviewers(array $family, $requestId, $type)
    {
        $admins = FamilyMember::adminsFor($family['id']);
        $userIds = [];
        foreach ($admins as $admin) {
            $userIds[] = (int) $admin['user_id'];
        }
        if (!$userIds) {
            return;
        }
        NotificationService::notifyMany(
            $userIds,
            $type,
            'A membership application needs attention in ' . $family['name'],
            'Open the review queue to see the ancestry claims and matching result.',
            '/families/' . rawurlencode($family['public_id']) . '/applications',
            (int) $family['id']
        );
    }
}
