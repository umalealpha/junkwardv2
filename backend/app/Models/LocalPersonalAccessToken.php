<?php

namespace AlphaDirect\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Routes Sanctum personal access tokens to the V2 master writable host
 * via the 'mysql_write' connection alias.
 *
 * Why 'mysql_write' and not 'mysql_system' anymore: 'mysql_system' has
 * read/write split enabled (reads load-balanced across V2 read replicas,
 * writes to master). Sanctum's auth flow does a write-then-read pattern
 * within a few hundred milliseconds:
 *
 *   1. SSO callback issues a Sanctum token → INSERT into
 *      personal_access_tokens → goes to master (write).
 *   2. Frontend immediately uses the token on the next API call.
 *   3. Sanctum middleware calls PersonalAccessToken::findToken() to look
 *      up the token → SELECT goes through the read array → V2 read replica.
 *   4. Replica hasn't received the new row yet (replication lag, sub-second
 *      but non-zero) → token not found → 401 → frontend redirects to login.
 *
 * This is a classic read-after-write replication-lag bug. Laravel's
 * sticky=true on read/write split only sticks within a single HTTP request;
 * the auth flow spans separate requests, so sticky doesn't help.
 *
 * Pinning the model to 'mysql_write' (single-host, no read/write split,
 * always points at DB_HOST_SYSTEM = V2 master) eliminates the race entirely.
 * Token reads are tiny and frequent — they don't meaningfully load the
 * master and don't benefit from replica distribution.
 */
class LocalPersonalAccessToken extends PersonalAccessToken
{
    protected $connection = 'mysql_write';
    protected $table = 'personal_access_tokens';

    /**
     * Don't let our explicit connection override propagate to the related
     * User (tokenable morphTo) — that must resolve on the default 'mysql'
     * connection so user reads stay distributed across replicas.
     */
    protected function newRelatedInstance($class)
    {
        return new $class;
    }
}
