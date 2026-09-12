<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Services\Reinsurance\FacBordereauService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The cession bordereau — the statement a broker or reinsurer agrees line by line.
 *
 * The rules being proved are the ones that make a bordereau safe to send:
 *  · a DRAFT never appears. It is a placement no reinsurer has signed, so putting
 *    it on a bordereau claims a cession against somebody who has not agreed to it.
 *  · a reversal is listed apart and is NOT netted into a subtotal, because netting
 *    hides the movement the recipient is trying to agree.
 *  · one reinsurer written in two currencies gets two sections. A total across
 *    currencies is a total in no currency.
 *  · subtotals are rounded once at the end, so the bordereau and the register do
 *    not disagree by a few thebe.
 *
 * Same safety harness as the other FAC feature tests: in-memory sqlite forced and
 * verified, auditing off.
 */
class FacBordereauTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected in-memory sqlite, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        FacPlacement::disableAuditing();

        $this->buildSchema();
        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    private function svc(): FacBordereauService
    {
        return app(FacBordereauService::class);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** The ordinary case: signed lines, grouped by reinsurer, subtotalled. */
    public function test_signed_lines_are_grouped_by_reinsurer_and_subtotalled(): void
    {
        $this->seedLine(['counterparty_name' => 'Grand Re', 'gross_ceded_premium' => 8500.00, 'cession_sum_insured' => 1000000.00]);
        $this->seedLine(['counterparty_name' => 'Grand Re', 'gross_ceded_premium' => 1500.00, 'cession_sum_insured' => 250000.00]);
        $this->seedLine(['counterparty_id' => 8, 'counterparty_name' => 'FBC Re', 'gross_ceded_premium' => 3000.00, 'cession_sum_insured' => 500000.00]);

        $b = $this->svc()->build(['period_from' => '2026-07-01', 'period_to' => '2026-07-31']);

        $this->assertCount(2, $b['groups']);
        // Alphabetical, so a recipient can find their own section.
        $this->assertSame('FBC Re', $b['groups'][0]['counterpartyName']);
        $this->assertSame('Grand Re', $b['groups'][1]['counterpartyName']);

        $grand = $b['groups'][1]['subtotals'];
        $this->assertSame(2, $grand['lineCount']);
        $this->assertEqualsWithDelta(10000.00, $grand['grossCededPremium'], 0.005);
        $this->assertEqualsWithDelta(1250000.00, $grand['cessionSumInsured'], 0.005);

        $this->assertEqualsWithDelta(13000.00, $b['grandTotal']['grossCededPremium'], 0.005);
        $this->assertSame(3, $b['grandTotal']['lineCount']);
    }

    /**
     * A DRAFT must never reach a bordereau. This is the rule that matters most —
     * a draft is a placement nobody has signed.
     */
    public function test_a_draft_is_never_on_the_bordereau(): void
    {
        $this->seedLine(['status' => 'placed', 'gross_ceded_premium' => 8500.00]);
        $this->seedLine(['status' => 'draft', 'gross_ceded_premium' => 99999.00, 'fac_reference' => 'FAC-2026-DRAFT']);

        $b = $this->svc()->build([]);

        $this->assertSame(1, $b['grandTotal']['lineCount']);
        $this->assertEqualsWithDelta(8500.00, $b['grandTotal']['grossCededPremium'], 0.005);

        $refs = collect($b['groups'])->flatMap(fn ($g) => collect($g['lines'])->pluck('facReference'));
        $this->assertNotContains('FAC-2026-DRAFT', $refs->all());
    }

    /** A cancelled placement is not a cession either. */
    public function test_a_cancelled_placement_is_excluded(): void
    {
        $this->seedLine(['status' => 'placed', 'gross_ceded_premium' => 8500.00]);
        $this->seedLine(['status' => 'cancelled', 'gross_ceded_premium' => 4000.00]);

        $b = $this->svc()->build([]);

        $this->assertSame(1, $b['grandTotal']['lineCount']);
        $this->assertEqualsWithDelta(8500.00, $b['grandTotal']['grossCededPremium'], 0.005);
    }

    /** A reversal is listed, but apart, and never netted into a subtotal. */
    public function test_a_reversal_is_listed_separately_and_not_netted(): void
    {
        $this->seedLine(['gross_ceded_premium' => 8500.00]);
        $this->seedLine([
            'gross_ceded_premium' => -8500.00,
            'is_reversal'         => true,
            'fac_reference'       => 'FAC-2026-000001-R',
        ]);

        $b = $this->svc()->build([]);

        $this->assertCount(1, $b['reversals']);
        $this->assertSame('FAC-2026-000001-R', $b['reversals'][0]['facReference']);
        $this->assertTrue($b['reversals'][0]['isReversal']);

        // The subtotal still reads 8,500 — NOT nil.
        $this->assertSame(1, $b['grandTotal']['lineCount']);
        $this->assertEqualsWithDelta(8500.00, $b['grandTotal']['grossCededPremium'], 0.005);
    }

    /** One reinsurer, two currencies, two sections. */
    public function test_one_reinsurer_in_two_currencies_gets_two_sections(): void
    {
        $this->seedLine(['currency' => 'BWP', 'gross_ceded_premium' => 8500.00]);
        $this->seedLine(['currency' => 'USD', 'gross_ceded_premium' => 600.00]);

        $b = $this->svc()->build([]);

        $this->assertCount(2, $b['groups']);
        $this->assertSame(['BWP', 'USD'], collect($b['groups'])->pluck('currency')->sort()->values()->all());
        foreach ($b['groups'] as $g) {
            $this->assertSame(1, $g['subtotals']['lineCount']);
        }
    }

    /**
     * The period is tested on the SLIP SIGNING date — when the cession came on
     * risk — not on capture and not on the policy period.
     */
    public function test_the_period_is_tested_on_the_slip_signing_date(): void
    {
        $this->seedLine(['slip_signed_date' => '2026-07-15', 'fac_reference' => 'FAC-IN']);
        $this->seedLine(['slip_signed_date' => '2026-08-15', 'fac_reference' => 'FAC-OUT']);

        $b = $this->svc()->build(['period_from' => '2026-07-01', 'period_to' => '2026-07-31']);

        $refs = collect($b['groups'])->flatMap(fn ($g) => collect($g['lines'])->pluck('facReference'));
        $this->assertContains('FAC-IN', $refs->all());
        $this->assertNotContains('FAC-OUT', $refs->all());
    }

    /** Filtering to one counterparty gives that reinsurer their own statement. */
    public function test_it_can_be_cut_to_one_reinsurer(): void
    {
        $this->seedLine(['counterparty_id' => 7, 'counterparty_name' => 'Grand Re']);
        $this->seedLine(['counterparty_id' => 8, 'counterparty_name' => 'FBC Re']);

        $b = $this->svc()->build(['counterparty_id' => 7]);

        $this->assertCount(1, $b['groups']);
        $this->assertSame('Grand Re', $b['groups'][0]['counterpartyName']);
    }

    /** Auto FAC and FAC can be reported apart. */
    public function test_it_can_be_cut_to_one_basis(): void
    {
        $this->seedLine(['placement_type' => 'fac']);
        $this->seedLine(['placement_type' => 'auto_fac']);

        $this->assertSame(1, $this->svc()->build(['placement_type' => 'auto_fac'])['grandTotal']['lineCount']);
        $this->assertSame('Auto FAC', $this->svc()->build(['placement_type' => 'auto_fac'])['groups'][0]['lines'][0]['placementType']);
    }

    /**
     * Rounded once at the end. Three lines of 0.005 must not each round up and
     * leave the bordereau a cent away from the register.
     */
    public function test_totals_are_rounded_once_at_the_end(): void
    {
        foreach ([10.005, 10.005, 10.005] as $i => $amt) {
            $this->seedLine(['gross_ceded_premium' => $amt, 'fac_reference' => 'FAC-R' . $i]);
        }

        $b = $this->svc()->build([]);

        $this->assertEqualsWithDelta(30.02, $b['grandTotal']['grossCededPremium'], 0.005);
    }

    /** The header states the basis, so nobody has to ask what is in it. */
    public function test_the_header_states_what_is_included(): void
    {
        $this->seedLine([]);

        $h = $this->svc()->build(['period_from' => '2026-07-01', 'period_to' => '2026-07-31'])['header'];

        $this->assertSame('Alpha Direct Insurance Company (Pty) Ltd', $h['cedant']);
        $this->assertSame('Facultative Cession Bordereau', $h['statement']);
        $this->assertSame('2026-07-01', $h['periodFrom']);
        $this->assertStringContainsString('Drafts', $h['basis']);
        $this->assertStringContainsString('Reversals', $h['basis']);
    }

    /** An empty period is an empty bordereau, not an error. */
    public function test_an_empty_period_produces_an_empty_bordereau(): void
    {
        $b = $this->svc()->build(['period_from' => '2030-01-01', 'period_to' => '2030-01-31']);

        $this->assertSame([], $b['groups']);
        $this->assertSame(0, $b['grandTotal']['lineCount']);
        $this->assertEqualsWithDelta(0.0, $b['grandTotal']['grossCededPremium'], 0.005);
    }

    /** Every line carries what a recipient needs to agree it. */
    public function test_a_line_carries_the_fields_a_recipient_agrees_against(): void
    {
        $this->seedLine([]);

        $l = $this->svc()->build([])['groups'][0]['lines'][0];

        foreach ([
            'facReference', 'placementType', 'slipNo', 'policyNumber', 'insuredName',
            'periodFrom', 'periodTo', 'slipSignedDate', 'riskPct', 'cessionSumInsured',
            'currency', 'grossCededPremium', 'premiumExclVat', 'commissionPct',
            'commission', 'netCededPremium',
        ] as $k) {
            $this->assertArrayHasKey($k, $l, "the bordereau line is missing $k");
        }
    }

    // ────────────────────────── why an empty bordereau is empty

    /**
     * A NIL BORDEREAU AND A BROKEN ONE LOOKED IDENTICAL, and Reinsurance reported
     * the module as "not recording the placements" on the strength of one. It was
     * recording them: the page defaults the period to the current month, the
     * period is tested on the SIGNING date, and every real slip had been signed
     * earlier. The figures were right and the screen gave no way to know it.
     */
    public function test_an_empty_bordereau_says_the_cessions_were_signed_in_another_period(): void
    {
        $this->seedLine(['slip_signed_date' => '2026-07-15']);

        $b = $this->svc()->build(['period_from' => '2026-09-01', 'period_to' => '2026-09-30']);

        $this->assertSame([], $b['groups']);
        $this->assertNotNull($b['emptyReason'], 'an empty bordereau explained nothing');
        $this->assertSame(1, $b['emptyReason']['signedOutsidePeriod']);
        $this->assertSame('2026-07-15', $b['emptyReason']['signedRange']['from']);
        $this->assertStringContainsString('outside this period', $b['emptyReason']['summary']);
    }

    /**
     * THE UNDATED CASE IS THE ONE A WIDER WINDOW WILL NEVER FIX. whereDate on a
     * null column matches nothing, so a placement with no signed slip is absent
     * from EVERY dated bordereau — the fix is filing the slip, not changing the
     * dates, and the message has to say so.
     */
    public function test_an_empty_bordereau_reports_placements_with_no_signed_date(): void
    {
        $this->seedLine(['slip_signed_date' => null]);

        $b = $this->svc()->build(['period_from' => '2026-09-01', 'period_to' => '2026-09-30']);

        $this->assertSame(1, $b['emptyReason']['withoutSignedDate']);
        $this->assertSame(0, $b['emptyReason']['signedOutsidePeriod']);
        $this->assertStringContainsString('no signed date', $b['emptyReason']['summary']);
    }

    /** Excluded and absent are different answers to "where is my line". */
    public function test_an_empty_bordereau_reports_what_was_excluded(): void
    {
        $this->seedLine(['status' => 'draft']);
        $this->seedLine(['status' => 'cancelled']);

        $b = $this->svc()->build(['period_from' => '2026-09-01', 'period_to' => '2026-09-30']);

        $this->assertSame(1, $b['emptyReason']['drafts']);
        $this->assertSame(1, $b['emptyReason']['cancelled']);
        $this->assertStringContainsString('no reinsurer has signed', $b['emptyReason']['summary']);
    }

    /** And it costs nothing on a document that has lines. */
    public function test_a_bordereau_with_lines_carries_no_empty_reason(): void
    {
        $this->seedLine([]);

        $this->assertNull($this->svc()->build([])['emptyReason']);
    }

    // ────────────────────────── the columns the master spreadsheet keeps

    /**
     * TWO COLUMNS REINSURANCE READ FROM AND THIS DOCUMENT NEVER CARRIED. Policy
     * type is on their sheet; the underwriter is the person a broker's query has
     * to reach, and the bordereau is the document that prompts the query.
     */
    public function test_a_line_carries_the_policy_type_and_the_underwriter(): void
    {
        $this->seedLine([
            'policy_type'      => 'PROFESSIONAL INDEMNITY',
            'underwriter_name' => 'Elaine Mokone',
        ]);

        $l = $this->svc()->build([])['groups'][0]['lines'][0];

        $this->assertSame('PROFESSIONAL INDEMNITY', $l['policyType']);
        $this->assertSame('Elaine Mokone', $l['underwriter']);
    }

    /**
     * ON A PULA LINE THE PULA IS THE AMOUNT. No conversion applies and the column
     * must not be blank — the ledger is kept in Pula, so a Pula bordereau that
     * leaves its Pula column empty is the one document nobody can agree.
     */
    public function test_a_pula_line_carries_its_own_amount_as_the_pula_figure(): void
    {
        $this->seedLine(['currency' => 'BWP', 'gross_ceded_premium' => 8500.00]);

        $l = $this->svc()->build([])['groups'][0]['lines'][0];

        $this->assertEqualsWithDelta(8500.00, $l['grossCededPremiumBwp'], 0.005);
    }

    /** A foreign line uses the rate captured with it, never one derived today. */
    public function test_a_foreign_line_uses_the_stored_conversion(): void
    {
        $this->seedLine([
            'currency'                => 'USD',
            'gross_ceded_premium'     => 1000.00,
            'gross_ceded_premium_bwp' => 13750.00,
        ]);

        $l = $this->svc()->build([])['groups'][0]['lines'][0];

        $this->assertEqualsWithDelta(13750.00, $l['grossCededPremiumBwp'], 0.005);
    }

    /**
     * NO RATE, NO PULA FIGURE — and no Pula TOTAL either. A subtotal that quietly
     * drops the lines it could not convert reads as the section's Pula value, and
     * that is the figure somebody agrees to the ledger.
     */
    public function test_a_line_with_no_rate_has_no_pula_figure_and_voids_the_pula_total(): void
    {
        $this->seedLine([
            'currency'                => 'USD',
            'gross_ceded_premium'     => 1000.00,
            'gross_ceded_premium_bwp' => null,
        ]);

        $b = $this->svc()->build([]);

        $this->assertNull($b['groups'][0]['lines'][0]['grossCededPremiumBwp']);
        $this->assertSame(1, $b['groups'][0]['subtotals']['bwpMissing']);
        $this->assertSame(1, $b['grandTotal']['bwpMissing'], 'the grand total is unconvertible too');
    }

    /** With every rate on file the Pula total stands. */
    public function test_a_fully_rated_section_totals_in_pula(): void
    {
        $this->seedLine(['currency' => 'USD', 'gross_ceded_premium' => 1000.00, 'gross_ceded_premium_bwp' => 13750.00]);
        $this->seedLine(['currency' => 'USD', 'gross_ceded_premium' => 500.00,  'gross_ceded_premium_bwp' => 6875.00]);

        $s = $this->svc()->build([])['groups'][0]['subtotals'];

        $this->assertSame(0, $s['bwpMissing']);
        $this->assertEqualsWithDelta(20625.00, $s['grossCededPremiumBwp'], 0.005);
    }

    /** Counts are counts — rounding them to two decimals prints them as money. */
    public function test_the_missing_rate_count_stays_an_integer(): void
    {
        $this->seedLine(['currency' => 'USD', 'gross_ceded_premium_bwp' => null]);

        $this->assertIsInt($this->svc()->build([])['grandTotal']['bwpMissing']);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $overrides */
    private function seedLine(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('fac_placements')->insertGetId(array_merge([
            'fac_reference'                 => 'FAC-2026-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'fac_slip_no'                   => '2026-' . (112 + $n),
            'financial_year'                => 'FY2026-27',
            'placement_type'                => 'fac',
            'policy_number'                 => 'COMG2026999888',
            'insured_name'                  => 'Kalahari Mining (Pty) Ltd',
            'ri_group_label'                => 'Property',
            'counterparty_id'               => 7,
            'counterparty_name'             => 'Grand Re',
            'risk_carrier'                  => 'Grand Re',
            'risk_pct'                      => 0.70,
            'cession_sum_insured'           => 1000000.00,
            'currency'                      => 'BWP',
            'gross_ceded_premium'           => 8500.00,
            'gross_ceded_premium_excl_vat'  => 7456.14,
            'commission_pct'                => 0.2750,
            'commission_amount'             => 2337.50,
            'commission_excl_vat'           => 2050.44,
            'net_ceded_premium'             => 6162.50,
            'gross_ceded_premium_bwp'        => 8500.00,
            'period_from'                   => '2026-07-01',
            'period_to'                     => '2027-06-30',
            'slip_signed_date'              => '2026-07-15',
            'status'                        => 'placed',
            'is_reversal'                   => false,
            'source'                        => 'manual',
            'created_by'                    => 41,
            'updated_by'                    => 41,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ], $overrides));
    }

    private function actor(int $id, string $name): Authenticatable
    {
        return new class($id, $name) implements Authenticatable {
            public function __construct(public int $id, public string $name)
            {
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value)
            {
            }

            public function getRememberTokenName()
            {
                return '';
            }
        };
    }

    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100003_create_fac_placement_attachments_table.php',
            '2026_07_30_100004_create_fac_placement_events_table.php',
            '2026_07_30_100005_create_fac_slips_and_period_snapshots.php',
            '2026_07_30_100006_add_slip_terms_to_fac.php',
            '2026_08_11_100007_add_ppw_terms_and_source_premium_to_fac.php',
            // show() reports the schedule, so the table has to exist.
            '2026_08_24_100008_create_fac_placement_schedule_items_table.php',
            '2026_08_25_100009_add_premium_frequency_and_widen_risk_pct.php',
            '2026_09_07_100009_add_slip_notes_and_basis_source_to_fac.php',
        ] as $migration) {
            (include database_path('migrations/' . $migration))->up();
        }

        Schema::create('reinsurer', function ($t) {
            $t->id();
            $t->string('company_name')->nullable();
        });
    }
}
