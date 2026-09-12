<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementScheduleItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The Fire & Allied Perils and Business Interruption schedule — defect 8.
 *
 * A signed slip does not state one figure; it itemises what is insured and then
 * totals it. Auto FAC slip 2026-002 (Strides of Success) carries nine fire lines
 * and six business interruption lines against a stated TOTAL LIMITS OF INDEMNITY
 * of P300,580,000, and that total is exactly the sum of the lines. The real figures
 * are used throughout here, so the arithmetic is tested against the document rather
 * than against numbers invented for a test.
 *
 * Sqlite forced and verified, as in the other FAC feature tests.
 */
class FacScheduleTest extends TestCase
{
    /** Slip 2026-002, Fire and Allied Perils. */
    private const FIRE = [
        ['Plant and machinery including generators', 170000000.00],
        ['Stock of cables and spares',                  200000.00],
        ['Claims preparation costs',                    100000.00],
        ['Removal of debris',                           300000.00],
        ['Stock',                                      2900000.00],
        ['Building',                                  65000000.00],
        ['New warehouse',                              7300000.00],
        ['Electronics',                                4000000.00],
        ['Boiler & Treatment Plant',                  24000000.00],
    ];

    /** Slip 2026-002, Business Interruption. The first line states no money. */
    private const BI = [
        ['Indemnity period – 15 months',                      null],
        ['Annual gross profit',                        18700000.00],
        ['Claims preparation costs',                     100000.00],
        ['Increase cost of working',                     500000.00],
        ['Utilities extension – extended cover',        3740000.00],
        ['Prevention of access – extended cover',       3740000.00],
    ];

    private const FIRE_TOTAL  = 273800000.00;
    private const BI_TOTAL    =  26780000.00;
    private const GRAND_TOTAL = 300580000.00;

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
        Storage::fake('s3');

        $this->buildSchema();
        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    // ───────────────────────────── the arithmetic, against the real slip

    /** The schedule totals exactly what slip 2026-002 states. */
    public function test_the_schedule_totals_what_the_signed_slip_states(): void
    {
        $id = $this->seedWithSchedule();

        $s = FacPlacementScheduleItem::scheduleFor($id);

        $this->assertSame(15, $s['lineCount']);
        $this->assertEqualsWithDelta(self::GRAND_TOTAL, $s['totalLimitsOfIndemnity'], 0.005);
        $this->assertEqualsWithDelta(self::FIRE_TOTAL, $s['sections'][0]['subtotal'], 0.005);
        $this->assertEqualsWithDelta(self::BI_TOTAL, $s['sections'][1]['subtotal'], 0.005);
    }

    /** The subtotals must add to the grand total, or the slip contradicts itself. */
    public function test_the_subtotals_add_to_the_grand_total(): void
    {
        $s = FacPlacementScheduleItem::scheduleFor($this->seedWithSchedule());

        $this->assertEqualsWithDelta(
            $s['totalLimitsOfIndemnity'],
            array_sum(array_column($s['sections'], 'subtotal')),
            0.005
        );
    }

    /**
     * A line with no amount is EXCLUDED from the total, not counted as nil.
     * "Indemnity period – 15 months" states a term, not a sum insured.
     */
    public function test_a_line_with_no_amount_is_not_counted_as_nil(): void
    {
        $s = FacPlacementScheduleItem::scheduleFor($this->seedWithSchedule());

        $bi = $s['sections'][1];
        $this->assertSame('Indemnity period – 15 months', $bi['lines'][0]['label']);
        $this->assertNull($bi['lines'][0]['amount'], 'the indemnity period must not carry a figure');
        $this->assertEqualsWithDelta(self::BI_TOTAL, $bi['subtotal'], 0.005);
    }

    /** Fire always precedes business interruption, whatever order rows arrive in. */
    public function test_the_sections_print_in_the_order_the_signed_slips_use(): void
    {
        $id = $this->seedPlacement();
        // Deliberately inserted business interruption FIRST.
        $this->seedItems($id, 'business_interruption', self::BI);
        $this->seedItems($id, 'fire', self::FIRE);

        $s = FacPlacementScheduleItem::scheduleFor($id);

        $this->assertSame('fire', $s['sections'][0]['section']);
        $this->assertSame('business_interruption', $s['sections'][1]['section']);
    }

    /** Lines keep the order the underwriter entered them. */
    public function test_lines_keep_the_order_they_were_entered_in(): void
    {
        $s = FacPlacementScheduleItem::scheduleFor($this->seedWithSchedule());

        $this->assertSame(
            array_column(self::FIRE, 0),
            array_column($s['sections'][0]['lines'], 'label')
        );
    }

    /** No schedule is an empty schedule, not an error. */
    public function test_a_placement_with_no_schedule_returns_an_empty_one(): void
    {
        $s = FacPlacementScheduleItem::scheduleFor($this->seedPlacement());

        $this->assertSame([], $s['sections']);
        $this->assertSame(0, $s['lineCount']);
        $this->assertEqualsWithDelta(0.0, $s['totalLimitsOfIndemnity'], 0.005);
        $this->assertSame([], FacPlacementScheduleItem::scheduleFor(null)['sections']);
    }

    // ───────────────────────────── capture

    /** The underwriter's schedule saves through the placement edit. */
    public function test_a_schedule_can_be_captured_on_an_edit(): void
    {
        $id = $this->seedPlacement();

        $this->controller()->update($this->request([
            'schedule' => [
                ['section' => 'fire', 'label' => 'Building', 'amount' => 65000000],
                ['section' => 'fire', 'label' => 'Stock',    'amount' => 2900000],
                ['section' => 'business_interruption', 'label' => 'Indemnity period – 15 months'],
            ],
        ]), $id);

        $s = FacPlacementScheduleItem::scheduleFor($id);
        $this->assertSame(3, $s['lineCount']);
        $this->assertEqualsWithDelta(67900000.00, $s['totalLimitsOfIndemnity'], 0.005);
        // Sent with no amount key at all — must be null, not zero.
        $this->assertNull($s['sections'][1]['lines'][0]['amount']);
    }

    /**
     * A save REPLACES the schedule. Reconciling line by line would leave orphans
     * behind whenever the underwriter removed a line.
     */
    public function test_saving_a_schedule_replaces_the_previous_one(): void
    {
        $id = $this->seedWithSchedule();
        $this->assertSame(15, FacPlacementScheduleItem::scheduleFor($id)['lineCount']);

        $this->controller()->update($this->request([
            'schedule' => [['section' => 'fire', 'label' => 'Building', 'amount' => 65000000]],
        ]), $id);

        $s = FacPlacementScheduleItem::scheduleFor($id);
        $this->assertSame(1, $s['lineCount']);
        $this->assertEqualsWithDelta(65000000.00, $s['totalLimitsOfIndemnity'], 0.005);
    }

    /**
     * THE IMPORTANT ONE. An edit that does not carry a schedule must leave it
     * alone. A form that does not render the schedule would otherwise wipe it on
     * every unrelated correction.
     */
    public function test_an_edit_without_a_schedule_key_leaves_the_schedule_untouched(): void
    {
        $id = $this->seedWithSchedule();

        $this->controller()->update($this->request(['risk_carrier' => 'Grand Re Botswana']), $id);

        $this->assertSame(15, FacPlacementScheduleItem::scheduleFor($id)['lineCount']);
    }

    /** An empty array is a deliberate clear, and is not the same as omitting it. */
    public function test_an_empty_schedule_array_clears_it(): void
    {
        $id = $this->seedWithSchedule();

        $this->controller()->update($this->request(['schedule' => []]), $id);

        $this->assertSame(0, FacPlacementScheduleItem::scheduleFor($id)['lineCount']);
    }

    /** A replaced schedule is on the trail — it changes what the slip states. */
    public function test_replacing_the_schedule_is_recorded_on_the_trail(): void
    {
        $id = $this->seedPlacement();

        $this->controller()->update($this->request([
            'schedule' => [['section' => 'fire', 'label' => 'Building', 'amount' => 65000000]],
        ]), $id);

        $e = DB::table('fac_placement_events')
            ->where('fac_placement_id', $id)->where('event', 'schedule_updated')->first();

        $this->assertNotNull($e, 'no schedule_updated event was written');
        $this->assertStringContainsString('65,000,000.00', $e->summary);
    }

    /** An unknown section is refused rather than printed under a made-up heading. */
    public function test_an_unknown_section_is_refused(): void
    {
        $id = $this->seedPlacement();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller()->update($this->request([
            'schedule' => [['section' => 'marine', 'label' => 'Hull', 'amount' => 1000]],
        ]), $id);
    }

    /**
     * A blank label is REFUSED, not silently dropped.
     *
     * An unlabelled figure on a slip means nothing to a reinsurer, and quietly
     * discarding the line would leave the underwriter's total short with no
     * explanation. The whole save is rejected so they can see and fix it.
     */
    public function test_a_blank_label_is_refused_rather_than_dropped(): void
    {
        $id = $this->seedPlacement();

        try {
            $this->controller()->update($this->request([
                'schedule' => [
                    ['section' => 'fire', 'label' => 'Building', 'amount' => 65000000],
                    ['section' => 'fire', 'label' => '   ',      'amount' => 999],
                ],
            ]), $id);
            $this->fail('a blank schedule label should have been refused');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('schedule.1.label', $e->errors());
        }

        // And nothing was written — a rejected save must not half-apply.
        $this->assertSame(0, FacPlacementScheduleItem::scheduleFor($id)['lineCount']);
    }

    // ───────────────────────────── the printed slip

    /** The schedule prints, with both blocks and the total. */
    public function test_the_slip_prints_the_schedule_and_the_total(): void
    {
        $html = $this->renderSlip($this->seedWithSchedule());

        $this->assertStringContainsString('FIRE AND ALLIED PERILS', $html);
        $this->assertStringContainsString('BUSINESS INTERRUPTION', $html);
        $this->assertStringContainsString('Plant and machinery including generators', $html);
        $this->assertStringContainsString('P170,000,000.00', $html);
        $this->assertStringContainsString('TOTAL LIMITS OF INDEMNITY', $html);
        $this->assertStringContainsString('P300,580,000.00', $html);
    }

    /**
     * The heading is now "Total Limits of Indemnity" carrying the whole sum
     * insured. It printed the CESSION amount, telling a reinsurer the risk was six
     * times smaller than it is.
     */
    public function test_the_total_limits_row_shows_the_sum_insured_not_the_cession(): void
    {
        $html = $this->renderSlip($this->seedWithSchedule());

        $this->assertStringContainsString('Total Limits of Indemnity', $html);
        // The cession still appears in the acceptance panel, but not as the limit.
        $this->assertDoesNotMatchRegularExpression(
            '/Limit of Indemnity<\/td>\s*<td>P50,000,000\.00/',
            $html
        );
    }

    /** A line with no amount prints its label and no figure. */
    public function test_a_line_with_no_amount_prints_no_figure(): void
    {
        $html = $this->renderSlip($this->seedWithSchedule());

        $this->assertStringContainsString('Indemnity period – 15 months', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/Indemnity period – 15 months<\/td>\s*<td class="r">P0\.00/',
            $html
        );
    }

    /** A placement with no schedule renders exactly as before — no empty headings. */
    public function test_a_slip_without_a_schedule_prints_no_schedule_blocks(): void
    {
        $html = $this->renderSlip($this->seedPlacement());

        $this->assertStringNotContainsString('FIRE AND ALLIED PERILS', $html);
        $this->assertStringNotContainsString('TOTAL LIMITS OF INDEMNITY', $html);
        // The original single-figure row is still there.
        $this->assertStringContainsString('Limit of Indemnity', $html);
    }

    // ───────────────────────────── harness

    private function controller(): FacRegisterApiController
    {
        return app(FacRegisterApiController::class);
    }

    /** @param array<string,mixed> $payload */
    private function request(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac/1', 'PUT', $payload);
        $r->headers->set('Accept', 'application/json');

        return $r;
    }

    private function renderSlip(int $placementId): string
    {
        $line = DB::table('fac_placements')->where('id', $placementId)->first();
        $slip = new \AlphaDirect\Models\FacSlip([
            'slip_no'        => '2026-002',
            'version'        => 1,
            'placement_type' => 'auto_fac',
            'insured_name'   => 'STRIDES OF SUCCESS (PTY) LTD',
            'cover_granted'  => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'broker_agent'   => 'DIRECT',
            'basis_of_cover' => 'Claims Occurring Basis',
            'status'         => 'generated',
        ]);
        $slip->setRelation('acceptances', collect());

        return View::make('Reinsurance.fac-slip', [
            'slip'        => $slip,
            'lines'       => collect([$line]),
            'generatedAt' => now(),
            'schedule'    => FacPlacementScheduleItem::scheduleFor($placementId),
        ])->render();
    }

    private function seedWithSchedule(): int
    {
        $id = $this->seedPlacement();
        $this->seedItems($id, 'fire', self::FIRE);
        $this->seedItems($id, 'business_interruption', self::BI);

        return $id;
    }

    /** @param array<int,array{0:string,1:float|null}> $rows */
    private function seedItems(int $placementId, string $section, array $rows): void
    {
        foreach ($rows as $i => [$label, $amount]) {
            DB::table('fac_placement_schedule_items')->insert([
                'fac_placement_id' => $placementId,
                'section'          => $section,
                'label'            => $label,
                'amount'           => $amount,
                'sort_order'       => $i,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    private function seedPlacement(): int
    {
        return DB::table('fac_placements')->insertGetId([
            'fac_reference'           => 'FAC-2026-000001',
            'fac_slip_no'             => '2026-002',
            'financial_year'          => 'FY2026-27',
            'placement_type'          => 'auto_fac',
            'policy_number'           => 'COMG2024129691',
            'insured_name'            => 'STRIDES OF SUCCESS (PTY) LTD',
            'ri_group_label'          => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'counterparty_id'         => 7,
            'counterparty_name'       => 'Grand Re',
            'risk_carrier'            => 'Grand Re',
            'risk_pct'                => 0.17,
            'cession_sum_insured'     => 50000000.00,
            'currency'                => 'BWP',
            'gross_ceded_premium'     => 38657.66,
            'commission_pct'          => 0.325,
            'commission_amount'       => 12563.74,
            'net_ceded_premium'       => 26093.92,
            'gross_ceded_premium_bwp' => 38657.66,
            'source_premium'          => 227398.00,
            'underwriter_name'        => 'Elaine Mokone',
            'period_from'             => '2026-01-01',
            'period_to'               => '2026-12-31',
            'status'                  => 'placed',
            'is_reversal'             => false,
            'source'                  => 'manual',
            'created_by'              => 41,
            'updated_by'              => 41,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
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
            '2026_08_24_100008_create_fac_placement_schedule_items_table.php',
            '2026_08_25_100009_add_premium_frequency_and_widen_risk_pct.php',
            '2026_09_07_100009_add_slip_notes_and_basis_source_to_fac.php',
        ] as $migration) {
            (include database_path('migrations/' . $migration))->up();
        }

        Schema::create('reinsurer', function ($t) {
            $t->id();
            $t->string('company_name')->nullable();
            $t->integer('settlement_terms_days')->nullable();
        });
        DB::table('reinsurer')->insert(['id' => 7, 'company_name' => 'Grand Re']);

        // The Graphite side. show() runs the coverage scan and the policy lookup on
        // every response, so these have to exist even for a schedule test.
        Schema::create('policies', function ($t) {
            $t->id();
            $t->string('policyNumber')->nullable();
            $t->integer('status')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->decimal('premium', 18, 2)->nullable();
            $t->decimal('annual_premium', 18, 2)->nullable();
            $t->string('premium_freq')->nullable();
        });
        Schema::create('customer', function ($t) {
            $t->id();
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
        });
        Schema::create('customer_profile', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('entity_type')->nullable();
        });
        Schema::create('companies', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('products', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('policy_actions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('term_id')->nullable();
            $t->string('transaction_type')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('policy_term', function ($t) {
            $t->id();
            $t->date('term_start_date')->nullable();
            $t->date('term_end_date')->nullable();
        });
        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->decimal('sum_insured', 18, 2)->nullable();
        });
        Schema::create('policy_ledger', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->decimal('credit', 18, 2)->nullable();
            $t->decimal('debit', 18, 2)->nullable();
            $t->date('accounting_date')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('policy_coverages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
        });
        Schema::create('policy_coverage_detail', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_coverage_id')->nullable();
            $t->string('limit_id')->nullable();
            $t->decimal('coverage_value', 20, 2)->nullable();
            $t->decimal('calculated_value', 20, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('tb_cvgpclimits', function ($t) {
            $t->integer('n_PCLimitId_PK');
            $t->string('s_LimitScreenName')->nullable();
            $t->string('s_LimitTypeCode')->nullable();
        });
    }
}
