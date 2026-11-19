<?php

namespace App\Core;

/**
 * String helpers used across the app - notably name normalisation, which the
 * matching engine relies on to compare spelling variants without ever treating
 * a name alone as proof of kinship.
 */
final class Str
{
    public static function randomToken($bytes = 32)
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function uuid4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function slug($value, $fallbackPrefix = 'family')
    {
        $value = self::ascii($value);
        $value = preg_replace('/[^a-zA-Z0-9]+/', '-', $value);
        $value = trim(strtolower((string) $value), '-');
        $value = preg_replace('/-+/', '-', (string) $value);
        if ($value === '') {
            $value = $fallbackPrefix . '-' . substr(self::randomToken(3), 0, 6);
        }
        return $value;
    }

    /** Transliterate accented and non-latin characters to ASCII where possible. */
    public static function ascii($value)
    {
        $value = (string) $value;
        if (function_exists('transliterator_create')) {
            $translit = \transliterator_create('Any-Latin; Latin-ASCII');
            if ($translit !== null) {
                $converted = \transliterator_transliterate($translit, $value);
                if ($converted !== false) {
                    $value = $converted;
                }
            }
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        return (string) $value;
    }

    /**
     * Normalise a personal name for comparison:
     *  - transliterate, lowercase, remove punctuation and titles
     *  - collapse whitespace
     */
    public static function normalizeName($value)
    {
        $value = self::ascii($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);
        return trim((string) $value);
    }

    /**
     * Return the sorted set of name tokens, ignoring very short particles so
     * that "de la Cruz" and "Cruz" still share the meaningful token.
     */
    public static function nameTokens($value)
    {
        $normalized = self::normalizeName($value);
        if ($normalized === '') {
            return [];
        }
        $tokens = array_filter(explode(' ', $normalized), static function ($token) {
            return $token !== '' && mb_strlen($token) > 1;
        });
        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        return $tokens;
    }

    /**
     * Similarity of two names in the range 0..1 using token overlap blended
     * with PHP's metaphone key. Deliberately *not* a kinship claim.
     */
    public static function nameSimilarity($a, $b)
    {
        $ta = self::nameTokens($a);
        $tb = self::nameTokens($b);
        if (!$ta || !$tb) {
            return 0.0;
        }
        $common = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        $jaccard = $union > 0 ? $common / $union : 0.0;

        $ma = metaphone(implode('', $ta));
        $mb = metaphone(implode('', $tb));
        $phonetic = ($ma !== '' && $ma === $mb) ? 1.0 : 0.0;

        return min(1.0, ($jaccard * 0.7) + ($phonetic * 0.3));
    }

    public static function limit($value, $length)
    {
        $value = (string) $value;
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length);
    }

    /** Mask a person's name for living-relative privacy: "A*** K***". */
    public static function maskName($value)
    {
        $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];
        $masked = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $masked[] = mb_substr($token, 0, 1) . str_repeat('*', max(2, min(5, mb_strlen($token) - 1)));
        }
        return implode(' ', $masked);
    }
}
