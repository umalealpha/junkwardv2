<?php

namespace AlphaDirect\Services;

/**
 * ClaimMentionParser — pure @mention parsing + resolution for claim review
 * notes (ported from the Claims Tracker's comment @mentions).
 *
 * Two pure steps, both DB-free so they are unit-testable:
 *   1. parse()   — pull the @handles out of a note body.
 *   2. resolve() — map those handles to Graphite user ids given a candidate
 *                  user list (the controller supplies the users from the DB).
 *
 * A "handle" is a single token after '@' — letters, digits, dot, underscore or
 * hyphen (no spaces). Emails inside the note (e.g. jane@alphadirect.co.bw) are
 * NOT treated as mentions: the '@' there is preceded by a word character, which
 * the parser rejects.
 */
class ClaimMentionParser
{
    /**
     * Extract unique, lowercased @handles from free text (leading @ stripped).
     * Order-preserving and de-duplicated.
     *
     * @return array<int,string>
     */
    public static function parse(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        // '@' must NOT be preceded by a word char or another '@' (so email
        // local@domain and @@ are ignored). Handle = 1..64 of [A-Za-z0-9._-],
        // must start with an alphanumeric.
        preg_match_all(
            '/(?<![\w@])@([A-Za-z0-9][A-Za-z0-9._-]{0,63})/',
            $text,
            $matches
        );

        $handles = [];
        foreach ($matches[1] as $raw) {
            // Trim trailing punctuation-ish separators that commonly abut a
            // handle in prose (e.g. "@jdoe." / "@jdoe-").
            $h = strtolower(rtrim($raw, '.-_'));
            if ($h === '') {
                continue;
            }
            $handles[$h] = true;
        }

        return array_keys($handles);
    }

    /**
     * Resolve handles against candidate users.
     *
     * Each candidate user is an array with at least:
     *   ['id' => int, 'firstName' => ?string, 'lastName' => ?string, 'email' => ?string]
     *
     * A handle matches a user when it equals (case-insensitively, spaces
     * stripped) any of the user's identity keys:
     *   - email local-part (before '@')
     *   - firstName
     *   - lastName
     *   - firstName + lastName  (e.g. "@JohnDoe")
     *   - firstName . lastName  (e.g. "@john.doe")
     * First matching user wins (candidate order).
     *
     * @param  array<int,string> $handles
     * @param  array<int,array>  $users
     * @return array{matches:array<int,array{handle:string,userId:int}>,unknown:array<int,string>}
     */
    public static function resolve(array $handles, array $users): array
    {
        // Build handle -> userId index from the candidate users.
        $index = [];
        foreach ($users as $u) {
            $id = isset($u['id']) ? (int) $u['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            foreach (self::identityKeys($u) as $key) {
                // Don't let a later user clobber an earlier match on the same key.
                if (!isset($index[$key])) {
                    $index[$key] = $id;
                }
            }
        }

        $matches = [];
        $unknown = [];
        foreach ($handles as $handle) {
            $key = self::normalize($handle);
            if ($key !== '' && isset($index[$key])) {
                $matches[] = ['handle' => $handle, 'userId' => $index[$key]];
            } else {
                $unknown[] = $handle;
            }
        }

        return ['matches' => $matches, 'unknown' => $unknown];
    }

    /**
     * Unique matched user ids from a resolve() result, preserving first-seen order.
     *
     * @param  array{matches:array<int,array{handle:string,userId:int}>} $resolved
     * @return array<int,int>
     */
    public static function matchedUserIds(array $resolved): array
    {
        $ids = [];
        foreach ($resolved['matches'] ?? [] as $m) {
            $ids[(int) $m['userId']] = true;
        }
        return array_keys($ids);
    }

    /** Identity keys a handle may match, normalized. */
    private static function identityKeys(array $u): array
    {
        $first = self::normalize($u['firstName'] ?? '');
        $last  = self::normalize($u['lastName'] ?? '');
        $email = (string) ($u['email'] ?? '');
        $local = self::normalize(strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email);

        $keys = [];
        foreach ([$local, $first, $last, $first . $last, $first . '.' . $last] as $k) {
            if ($k !== '' && $k !== '.') {
                $keys[$k] = true;
            }
        }
        return array_keys($keys);
    }

    /** Lowercase + strip whitespace. */
    private static function normalize(?string $s): string
    {
        return strtolower(preg_replace('/\s+/', '', (string) $s));
    }
}
