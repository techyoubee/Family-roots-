<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

/**
 * One declared ancestor inside a membership request: the applicant's parent,
 * grandparent or older relative, exactly as the applicant knows them.
 *
 * Matching against the family tree is advisory. A claim keeps its own
 * normalised name and, once the engine has run, the ancestor it matched and a
 * plain-language explanation. `is_confirmed_match` is only set by a human.
 */
final class MembershipRequestClaim
{
    /** The relationship values the database accepts, in generation order. */
    public const RELATIONSHIPS = [
        'self'                => 0,
        'father'              => 1,
        'mother'              => 1,
        'parent'              => 1,
        'grandfather'         => 2,
        'grandmother'         => 2,
        'great_grandfather'   => 3,
        'great_grandmother'   => 3,
        'other'               => 1,
    ];

    public static function find($id)
    {
        return Database::first('SELECT * FROM membership_request_claims WHERE id = ?', [(int) $id]);
    }

    public static function findForRequest($requestId, $claimId)
    {
        return Database::first(
            'SELECT * FROM membership_request_claims WHERE id = ? AND request_id = ?',
            [(int) $claimId, (int) $requestId]
        );
    }

    public static function create(array $data, $requestId)
    {
        $data['request_id'] = (int) $requestId;
        $data['name_normalized'] = Str::normalizeName(
            ($data['given_names'] ?? '') . ' ' . ($data['surname'] ?? '')
        );
        if (!isset($data['generation']) || !$data['generation']) {
            $data['generation'] = self::generationFor($data['relationship'] ?? 'other');
        }
        unset($data['id']);
        $id = Database::insert('membership_request_claims', $data);
        return self::find($id);
    }

    public static function update($id, array $data)
    {
        if (array_key_exists('given_names', $data) || array_key_exists('surname', $data)) {
            $current = self::find($id);
            if ($current) {
                $given = array_key_exists('given_names', $data) ? $data['given_names'] : $current['given_names'];
                $surname = array_key_exists('surname', $data) ? $data['surname'] : $current['surname'];
                $data['name_normalized'] = Str::normalizeName($given . ' ' . $surname);
            }
        }
        return Database::update('membership_request_claims', $data, 'id = :id', ['id' => (int) $id]);
    }

    public static function delete($id, $requestId)
    {
        return Database::execute(
            'DELETE FROM membership_request_claims WHERE id = ? AND request_id = ?',
            [(int) $id, (int) $requestId]
        );
    }

    public static function forRequest($requestId)
    {
        return Database::select(
            'SELECT * FROM membership_request_claims WHERE request_id = ? ORDER BY generation ASC, id ASC',
            [(int) $requestId]
        );
    }

    public static function countForRequest($requestId)
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM membership_request_claims WHERE request_id = ?',
            [(int) $requestId]
        );
    }

    /** Clear previous engine output before recomputing a score. */
    public static function resetMatches($requestId)
    {
        return Database::execute(
            'UPDATE membership_request_claims
                SET matched_ancestor_id = NULL, match_confidence = NULL, match_explanation = NULL, is_confirmed_match = 0
              WHERE request_id = ?',
            [(int) $requestId]
        );
    }

    public static function generationFor($relationship)
    {
        return self::RELATIONSHIPS[$relationship] ?? 1;
    }
}
