<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Claims API — auth and validation gating.
 *
 * WHY THIS FILE LOOKS DIFFERENT NOW
 *
 * It used to call `User::first()` with no connection guard, which means it ran
 * against whatever .env points at — the PRODUCTION RDS. On a machine that cannot
 * reach prod it errored (3 of 4 tests); on a machine that can, it read production
 * users and drove the live claims API. The run also tried to write to
 * api_error_log on the live database.
 *
 * So: the connection is forced to in-memory sqlite and the run FAILS LOUDLY if
 * anything else is resolved, and the acting user is transient rather than read
 * from a table. Neither change costs the file anything — nothing here needed a
 * real user, only an authenticated one.
 */
class ClaimsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'database.connections.mysql_system' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            // Throttled routes otherwise share a limiter across tests and runs.
            'cache.default' => 'array',
        ]);

        DB::purge();

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run against ' . $conn->getDriverName() . ' / '
                . $conn->getDatabaseName() . ' — this file must never touch a real database.');
        }
    }

    /**
     * A transient user. Sanctum only needs something authenticatable; nothing in
     * this file asserts anything about who it is.
     */
    private function actAsUser(): void
    {
        Sanctum::actingAs(new \AlphaDirect\User(), ['*']);
    }

    public function test_claims_list_requires_auth(): void
    {
        $this->getJson('/api/v1/claims')->assertStatus(401);
    }

    public function test_claims_create_requires_auth(): void
    {
        $this->postJson('/api/v1/claims', [])->assertStatus(401);
    }

    /**
     * NOT COVERED HERE, deliberately and visibly.
     *
     * Everything past the auth check needs a database, including the checks that
     * look like they should not. `POST /claims` with an empty body returns 500 on
     * a schema-less connection, and the cause is not validation: the permission
     * middleware queries the `permissions` table BEFORE the controller is reached,
     * so the request never gets as far as the validator. The same is true of
     * `GET /claims` and `GET /claims/create-data`, which read a wide slice of the
     * legacy schema.
     *
     * Those tables are legacy — there is no create_permission_tables migration to
     * run — so covering this means hand-building schema, and a hand-built copy is
     * precisely what rotted the other two claims suites: ClaimTrackingTest predated
     * the `purpose` column and failed six tests for a reason that had nothing to do
     * with the service under test.
     *
     * So this is a visible marker, not a silent skip. A silently-skipping test is
     * how ClaimSlaTest carried a broken assertion indefinitely — it checked a
     * `changed` key the service never returned, and skipped on every machine, so
     * nobody found out.
     */
    public function test_authenticated_surface_is_not_yet_covered_hermetically(): void
    {
        $this->markTestIncomplete(
            'POST /api/v1/claims (validation), GET /api/v1/claims and '
            . '/api/v1/claims/create-data all need the legacy permissions + claims '
            . 'schema before they can run without a live database. They previously '
            . '"passed" only by querying production.'
        );
    }
}
