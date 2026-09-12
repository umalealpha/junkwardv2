<?php

namespace Tests\Feature\Public;

use AlphaDirect\Services\Travel\TravelPolicyNumberGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Travel policy numbers — TRVL{YYYY}{NNNNNN}.
 *
 * Unlike the two travel smoke tests beside it, this one does touch the
 * database: the whole point of the generator is the counter row and the UNIQUE
 * indexes, and neither can be asserted in the abstract. It stays off the live
 * book by minting in far-future years (2095/2096) and deleting its own counter
 * and audit rows in tearDown, so a run never consumes a real 2026 number.
 *
 * What is NOT covered here: true concurrency. Two PHPUnit tests share one
 * connection, so a duplicate can only be provoked from separate processes —
 * verified out-of-band with six parallel minters (90 numbers, 90 distinct,
 * contiguous, no lock failures). What this file pins down is the format, the
 * per-year restart, the seeding, the collision probe, replay behaviour, and
 * that the database itself refuses a duplicate.
 */
class TravelPolicyNumberGeneratorTest extends TestCase
{
    /** Years far enough out that no real policy will ever be minted in them. */
    private const YEAR      = 2095;
    private const YEAR_NEXT = 2096;

    /** Every audit row this test writes carries this reference prefix. */
    private const REF_PREFIX = 'TEST-TRVLNO-';

    private TravelPolicyNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(TravelPolicyNumberGenerator::class);
        $this->purgeFixtures();
    }

    protected function tearDown(): void
    {
        $this->purgeFixtures();

        parent::tearDown();
    }

    // ─── Format ─────────────────────────────────────────────────────────────

    /** TRVL + year + six zero-padded digits, for every magnitude of sequence. */
    public function test_format_pads_the_sequence_to_six_digits(): void
    {
        $this->assertSame('TRVL2026000001', TravelPolicyNumberGenerator::format(2026, 1));
        $this->assertSame('TRVL2026000042', TravelPolicyNumberGenerator::format(2026, 42));
        $this->assertSame('TRVL2026001000', TravelPolicyNumberGenerator::format(2026, 1000));
        $this->assertSame('TRVL2026999999', TravelPolicyNumberGenerator::format(2026, 999999));
        $this->assertSame('TRVL2027000001', TravelPolicyNumberGenerator::format(2027, 1));
    }

    /** The prefix identifies the Travel book and nothing else. */
    public function test_matches_accepts_travel_numbers_and_rejects_everything_else(): void
    {
        $this->assertTrue(TravelPolicyNumberGenerator::matches('TRVL2026000001'));

        $this->assertFalse(TravelPolicyNumberGenerator::matches('MIS2026000001'), 'Instant book');
        $this->assertFalse(TravelPolicyNumberGenerator::matches('TRVL202600001'), 'five digits');
        $this->assertFalse(TravelPolicyNumberGenerator::matches('TRVL20260000011'), 'seven digits');
        $this->assertFalse(TravelPolicyNumberGenerator::matches('trvl2026000001'), 'lower case');
        $this->assertFalse(TravelPolicyNumberGenerator::matches('TRVL2026ABCDEF'), 'not numeric');
    }

    // ─── Sequence ───────────────────────────────────────────────────────────

    /** A fresh year starts at 000001 and climbs by one, leading zeros intact. */
    public function test_sequence_starts_at_one_and_increments(): void
    {
        $this->assertSame('TRVL2095000001', $this->generator->next(self::YEAR));
        $this->assertSame('TRVL2095000002', $this->generator->next(self::YEAR));
        $this->assertSame('TRVL2095000003', $this->generator->next(self::YEAR));
    }

    /** Each year keeps its own counter, so a new year restarts at 000001. */
    public function test_sequence_restarts_for_a_new_year(): void
    {
        $this->generator->next(self::YEAR);
        $this->generator->next(self::YEAR);

        $this->assertSame('TRVL2096000001', $this->generator->next(self::YEAR_NEXT));

        // ...and the old year carries on from where it left off.
        $this->assertSame('TRVL2095000003', $this->generator->next(self::YEAR));
    }

    /** A year already holding TRVL numbers resumes above the highest one. */
    public function test_counter_seeds_from_the_highest_number_already_issued(): void
    {
        $this->recordSubmission('seed', TravelPolicyNumberGenerator::format(self::YEAR, 42));

        $this->assertSame('TRVL2095000043', $this->generator->next(self::YEAR));
    }

    /** A number issued outside the generator is stepped over, never reused. */
    public function test_a_number_already_taken_is_skipped(): void
    {
        $this->assertSame('TRVL2095000001', $this->generator->next(self::YEAR));

        // 000002 arrives from elsewhere (import / restore / manual fix).
        $this->recordSubmission('squat', TravelPolicyNumberGenerator::format(self::YEAR, 2));

        $this->assertSame('TRVL2095000003', $this->generator->next(self::YEAR));
    }

    /** 999999 is the last number of a year; asking past it fails loudly. */
    public function test_exhausting_a_year_throws(): void
    {
        $this->generator->next(self::YEAR);

        DB::table('policy_number_sequences')
            ->where('prefix', TravelPolicyNumberGenerator::PREFIX)
            ->where('period_year', self::YEAR)
            ->update(['last_sequence' => TravelPolicyNumberGenerator::MAX_SEQUENCE - 1]);

        $this->assertSame('TRVL2095999999', $this->generator->next(self::YEAR));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exhausted');

        $this->generator->next(self::YEAR);
    }

    // ─── No duplicates ──────────────────────────────────────────────────────

    /**
     * Sequential mints never repeat. Kept at 40 rather than a few thousand
     * because every mint is a round trip to a remote RDS instance — the volume
     * case is the out-of-band parallel run, not this suite.
     */
    public function test_repeated_minting_never_repeats_a_number(): void
    {
        $issued = [];

        for ($i = 0; $i < 40; $i++) {
            $issued[] = $this->generator->next(self::YEAR);
        }

        $this->assertCount(40, array_unique($issued), 'a number was issued twice');
        $this->assertSame('TRVL2095000001', $issued[0]);
        $this->assertSame('TRVL2095000040', $issued[39]);
    }

    /** A replayed bind reuses its number instead of burning a second one. */
    public function test_assign_to_is_idempotent_per_reference(): void
    {
        $reference = $this->recordSubmission('assign', null);

        $first  = $this->generator->assignTo($reference, self::YEAR);
        $second = $this->generator->assignTo($reference, self::YEAR);

        $this->assertSame('TRVL2095000001', $first);
        $this->assertSame($first, $second, 'a replay minted a second number');

        // Persisted on the audit row, so the portal and ops see the same number.
        $this->assertSame($first, $this->submissions()->where('reference', $reference)->value('policy_number'));
    }

    /**
     * MapfreTravelClient writes its audit row best-effort, so the row can be
     * missing when we come to mint. The number must still end up recorded —
     * otherwise a replay of the same bind mints a second one for one sale.
     */
    public function test_assign_to_records_the_number_when_the_audit_row_is_missing(): void
    {
        $reference = self::REF_PREFIX . 'orphan';

        $this->assertFalse(
            $this->submissions()->where('reference', $reference)->exists(),
            'fixture precondition: no audit row yet'
        );

        $first = $this->generator->assignTo($reference, self::YEAR);

        $row = $this->submissions()->where('reference', $reference)->first();

        $this->assertNotNull($row, 'assignTo did not reconstruct the audit row');
        $this->assertSame($first, $row->policy_number, 'the number was not persisted');
        $this->assertSame('submitted', $row->status, 'a bound contract must not be recorded as pending');

        // submitted_at must be stamped, not left null. MapfreSubmissionsController
        // counts bound_today / bound_month with where('submitted_at','>=',...),
        // so a null here makes a real sale invisible in the ops console — the
        // failure this whole reconstruction path exists to prevent.
        $this->assertNotNull(
            $row->submitted_at,
            'submitted_at is null, so this bind is counted in neither KPI tile'
        );

        // And the replay reuses it rather than burning a second number.
        $this->assertSame($first, $this->generator->assignTo($reference, self::YEAR));
    }

    /** The last line of defence: the database itself refuses a duplicate. */
    public function test_database_rejects_a_duplicate_policy_number(): void
    {
        $policyNumber = $this->generator->next(self::YEAR);

        $this->recordSubmission('unique-a', $policyNumber);

        try {
            $this->recordSubmission('unique-b', $policyNumber);
            $this->fail('the UNIQUE index on mapfre_quote_submissions.policy_number is missing');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0), 'expected a MySQL duplicate-key error');
        }
    }

    /** policies.policyNumber — where travel policies land — is UNIQUE too. */
    public function test_policies_policy_number_column_is_unique(): void
    {
        $unique = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "policies"
                AND COLUMN_NAME = "policyNumber"
                AND NON_UNIQUE = 0
                AND INDEX_NAME <> "PRIMARY"'
        );

        $this->assertGreaterThan(0, (int) ($unique->c ?? 0), 'policies.policyNumber has no UNIQUE index');
    }

    // ─── fixtures ───────────────────────────────────────────────────────────

    /** Insert an audit row, optionally already carrying a policy number. */
    private function recordSubmission(string $suffix, ?string $policyNumber): string
    {
        $reference = self::REF_PREFIX . $suffix;

        $this->submissions()->insert([
            'reference'     => $reference,
            'product_id'    => 'TEST',
            'status'        => 'submitted',
            'policy_number' => $policyNumber,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $reference;
    }

    private function purgeFixtures(): void
    {
        $this->submissions()->where('reference', 'like', self::REF_PREFIX . '%')->delete();

        DB::table('policy_number_sequences')
            ->where('prefix', TravelPolicyNumberGenerator::PREFIX)
            ->whereIn('period_year', [self::YEAR, self::YEAR_NEXT])
            ->delete();
    }

    private function submissions()
    {
        return DB::connection('mysql_system')->table('mapfre_quote_submissions');
    }
}
