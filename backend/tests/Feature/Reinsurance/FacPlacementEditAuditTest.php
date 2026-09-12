<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Models\FacPlacement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature-level proof for FacRegisterApiController::update() — the correction
 * path and the trail it has to leave behind.
 *
 * What is being proved, in the terms the request was made in: a capture error is
 * no longer permanent, and a correction is not silent. Every field the edit moved
 * is recorded with the user, the timestamp, and the value before and after.
 *
 * SAFETY (same two hazards as tests/Feature/SpecifiedItems/SpecifiedItemsCrudTest.php):
 *
 *  1. backend/.env's default connection points at the PRODUCTION RDS and
 *     phpunit.xml's sqlite lines are commented out. setUp() forcibly rebinds
 *     "sqlite" to :memory:, makes it the default, and FAILS LOUDLY if the
 *     resulting connection is not actually sqlite :memory:.
 *
 *  2. FacPlacement implements OwenIt\Auditing\Contracts\Auditable, and the audit
 *     driver is configured against the SEPARATE 'mysql_system' connection —
 *     which falls back to the SAME production host when DB_HOST_SYSTEM is unset.
 *     Neutralised three ways: audit.enabled=false, ::disableAuditing() on the
 *     model, and the audit connection forced to sqlite with no `audits` table,
 *     so a stray write fails loudly instead of silently reaching prod.
 *
 * The controller is called directly rather than over HTTP: the route carries
 * `permission:reinsurance-fac-edit`, and the subject here is the correction and
 * its trail, not the middleware.
 *
 * The fac_placements / fac_placement_events schema is created by INCLUDING the
 * real migrations, so the test cannot drift from the shipped columns. The
 * Graphite-side tables that update() reads through (the policy lookup, the
 * coverage check, the receipts) are hand-built — they are V1 tables with no
 * migrations in this repo.
 *
 * Run ONLY this file:
 *   php artisan test --filter=FacPlacementEditAuditTest
 *   vendor/bin/phpunit tests/Feature/Reinsurance/FacPlacementEditAuditTest.php
 */
class FacPlacementEditAuditTest extends TestCase
{
    private int $placementId;

    /** The counterparty every fixture pays. */
    private const COUNTERPARTY_ID = 7;

    protected function setUp(): void
    {
        parent::setUp();

        // ── HARD SAFETY #1: force in-memory sqlite; refuse anything else. ──
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected an in-memory sqlite connection, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        // ── HARD SAFETY #2: neutralise the mysql_system audit-write hazard. ──
        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        FacPlacement::disableAuditing();

        // The VAT rate and the reconciliation tolerance the arithmetic asserts
        // against, pinned so a config change cannot silently rewrite the expected
        // figures below.
        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);

        $this->buildSchema();
        $this->seedGraphite();

        // recordEvent() reads the actor off the guard. Without a user it records a
        // NULL actor, which would let the "who changed it" assertion pass for the
        // wrong reason.
        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));

        $this->placementId = $this->seedPlacement();
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────
    //  The trail
    // ────────────────────────────────────────────────────────────────────

    /**
     * The headline requirement: an edit records the user, the timestamp, the
     * field, and the values before and after.
     */
    public function test_amending_a_placement_records_field_level_before_and_after(): void
    {
        $response = $this->controller()->update($this->request([
            'fac_slip_no'         => '2026-114',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.25,       // was 0.275
        ]), $this->placementId);

        $this->assertSame(200, $response->getStatusCode());

        $event = DB::table('fac_placement_events')
            ->where('fac_placement_id', $this->placementId)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($event, 'an amendment must leave an event on the trail');
        $this->assertSame(41, (int) $event->actor_id, 'the trail must name WHO changed it');
        $this->assertSame('Kefilwe Mokwena', $event->actor_name);
        $this->assertNotNull($event->created_at, 'the trail must record WHEN');

        $changes = collect(json_decode($event->payload, true)['changes'])->keyBy('field');

        $this->assertSame(
            ['from' => '27.50%', 'to' => '25.00%'],
            ['from' => $changes['commission_pct']['from'], 'to' => $changes['commission_pct']['to']],
            'the commission rate must be recorded as a percentage, both sides'
        );
        $this->assertSame('Commission %', $changes['commission_pct']['label']);

        $this->assertSame('2026-113', $changes['fac_slip_no']['from']);
        $this->assertSame('2026-114', $changes['fac_slip_no']['to']);

        // The derived figures move with it, and the trail says so — otherwise the
        // reader has to redo the arithmetic to find out what the edit cost.
        $this->assertSame('2,337.50', $changes['commission_amount']['from']);
        $this->assertSame('2,125.00', $changes['commission_amount']['to']);
        $this->assertSame('6,162.50', $changes['net_ceded_premium']['from']);
        $this->assertSame('6,375.00', $changes['net_ceded_premium']['to']);

        // Untouched fields must NOT appear. A trail that lists every column on
        // every edit is a trail nobody reads.
        $this->assertArrayNotHasKey('gross_ceded_premium', $changes->all());
        $this->assertArrayNotHasKey('policy_number', $changes->all());
        $this->assertArrayNotHasKey('notes', $changes->all());

        $this->assertStringContainsString('Commission %', $event->summary);
        $this->assertStringContainsString('FAC-2026-000001', $event->summary);
    }

    /** The stored row actually moves — the trail is not describing a save that never happened. */
    public function test_the_amendment_is_persisted_and_the_payable_recomputed(): void
    {
        $this->controller()->update($this->request([
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.25,
        ]), $this->placementId);

        $row = DB::table('fac_placements')->where('id', $this->placementId)->first();

        $this->assertEqualsWithDelta(0.25, (float) $row->commission_pct, 0.000001);
        $this->assertEqualsWithDelta(2125.00, (float) $row->commission_amount, 0.001);
        $this->assertEqualsWithDelta(6375.00, (float) $row->net_ceded_premium, 0.001);
        $this->assertEqualsWithDelta(8500.00, (float) $row->gross_ceded_premium_bwp, 0.001,
            'a Pula line pays the gross — the payable follows the gross, not the net');
        $this->assertSame(41, (int) $row->updated_by);
    }

    /**
     * A no-op edit is not an amendment.
     *
     * Re-opening the form and saving it unchanged used to write "Placement …
     * amended." every time. A trail padded with empty entries is one that stops
     * being read, which defeats the point of keeping it.
     */
    public function test_an_edit_that_changes_nothing_leaves_no_entry(): void
    {
        $unchanged = [
            'policy_number'       => 'COMG2026213751',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'vat_applicable'      => true,
            'currency'            => 'BWP',
            'status'              => 'placed',
        ];

        $this->assertSame(200, $this->controller()->update($this->request($unchanged), $this->placementId)->getStatusCode());

        $this->assertSame(0, DB::table('fac_placement_events')
            ->where('fac_placement_id', $this->placementId)->where('event', 'updated')->count());
    }

    // ────────────────────────────────────────────────────────────────────
    //  The correction that was impossible
    // ────────────────────────────────────────────────────────────────────

    /**
     * A mistyped policy number was the one capture error nothing could fix: the
     * field was validated and then dropped, so the edit returned 200 and changed
     * nothing at all. Re-pointing must move the number AND re-read the snapshot
     * that describes it.
     */
    public function test_correcting_the_policy_number_repoints_the_line_and_records_it(): void
    {
        $response = $this->controller()->update($this->request([
            'policy_number'       => 'COMG2026999888',   // the real policy
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
        ]), $this->placementId);

        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('fac_placements')->where('id', $this->placementId)->first();
        $this->assertSame('COMG2026999888', $row->policy_number, 'the corrected number must persist');
        $this->assertSame(902, (int) $row->policy_id, 'the line must point at the policy it now names');
        $this->assertSame('Kalahari Mining (Pty) Ltd', $row->insured_name,
            'the insured must be re-read, not left describing the old number');
        $this->assertSame(1, (int) $row->policy_in_graphite);
        $this->assertSame(1, (int) $row->policy_active_in_graphite);

        $event = DB::table('fac_placement_events')
            ->where('fac_placement_id', $this->placementId)->where('event', 'updated')->first();
        $changes = collect(json_decode($event->payload, true)['changes'])->keyBy('field');

        $this->assertSame('COMG2026213751', $changes['policy_number']['from']);
        $this->assertSame('COMG2026999888', $changes['policy_number']['to']);
        $this->assertSame('Snehal Test Holdings', $changes['insured_name']['from']);
        $this->assertSame('Kalahari Mining (Pty) Ltd', $changes['insured_name']['to']);
        $this->assertSame('Found in Graphite', $changes['policy_in_graphite']['label']);
        $this->assertSame('no', $changes['policy_in_graphite']['from']);
        $this->assertSame('yes', $changes['policy_in_graphite']['to']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  What an edit may NOT do
    // ────────────────────────────────────────────────────────────────────

    /** A settled placement has been paid. Correcting the figure is a reversal, not an edit. */
    public function test_a_settled_placement_is_refused(): void
    {
        DB::table('fac_placements')->where('id', $this->placementId)->update(['status' => 'settled']);

        $response = $this->controller()->update($this->request(['commission_pct' => 0.25]), $this->placementId);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('settled placement cannot be edited',
            json_decode($response->getContent(), true)['message']);
        $this->assertSame(0, DB::table('fac_placement_events')->where('event', 'updated')->count());
    }

    /** A cancelled placement is closed, and its reversal is already on the payable. */
    public function test_a_cancelled_placement_is_refused(): void
    {
        DB::table('fac_placements')->where('id', $this->placementId)->update(['status' => 'cancelled']);

        $response = $this->controller()->update($this->request(['commission_pct' => 0.25]), $this->placementId);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('cancelled placement cannot be edited',
            json_decode($response->getContent(), true)['message']);
    }

    /**
     * A line whose client premium is confirmed received carries a liability
     * Finance has already counted. Dragging it back to `draft` would drop it out
     * of the payable, out of the frozen month-end snapshot and out of the journal
     * figure — so the status is ignored while the rest of the edit is honoured.
     */
    public function test_status_cannot_be_dragged_back_out_of_a_confirmed_liability(): void
    {
        DB::table('fac_placements')->where('id', $this->placementId)->update(['status' => 'ready_to_settle']);

        $response = $this->controller()->update($this->request([
            'status'              => 'draft',
            'risk_carrier'        => 'Grand Re, NCA Re',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
        ]), $this->placementId);

        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('fac_placements')->where('id', $this->placementId)->first();
        $this->assertSame('ready_to_settle', $row->status, 'the status must not move');
        $this->assertSame('Grand Re, NCA Re', $row->risk_carrier, 'the rest of the edit still applies');

        $event   = DB::table('fac_placement_events')->where('event', 'updated')->first();
        $changes = collect(json_decode($event->payload, true)['changes'])->keyBy('field');
        $this->assertArrayNotHasKey('status', $changes->all(),
            'a status that did not move must not be recorded as though it had');
    }

    /**
     * A line whose client premium has landed is still correctable — only settled
     * and cancelled are closed.
     *
     * The form omits the status field entirely for these, because the validator
     * accepts none of the post-capture statuses: echoing the line's own status
     * back would 422 and make the line uneditable over a field nobody touched.
     */
    public function test_a_client_paid_line_can_still_be_corrected(): void
    {
        DB::table('fac_placements')->where('id', $this->placementId)->update(['status' => 'client_paid']);

        $response = $this->controller()->update($this->request([
            'risk_carrier'        => 'Grand Re, NCA Re',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            // No status key at all — as the correction form sends it.
        ]), $this->placementId);

        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('fac_placements')->where('id', $this->placementId)->first();
        $this->assertSame('Grand Re, NCA Re', $row->risk_carrier);
        $this->assertSame('client_paid', $row->status, 'the confirmed receipt must not be disturbed');

        $event   = DB::table('fac_placement_events')->where('event', 'updated')->first();
        $changes = collect(json_decode($event->payload, true)['changes'])->keyBy('field');
        $this->assertSame('Risk carried by', $changes['risk_carrier']['label']);
        $this->assertNull($changes['risk_carrier']['from'], 'a field that was blank reads as blank, not as 0 or ""');
        $this->assertArrayNotHasKey('status', $changes->all());
    }

    /**
     * A settlement status cannot be reached through the ordinary edit validator.
     * Without this, an edit right would do a settlement right's job: status=settled
     * with no reference, no actor and no reversing row.
     */
    public function test_settled_cannot_be_set_through_the_edit_validator(): void
    {
        // Called directly, so the validator's rejection surfaces as the exception
        // the framework would otherwise render as a 422.
        try {
            $this->controller()->update($this->request([
                'status'              => 'settled',
                'gross_ceded_premium' => 8500.00,
                'commission_pct'      => 0.275,
            ]), $this->placementId);
            $this->fail('a settlement status must not be reachable through the edit validator');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('placed', DB::table('fac_placements')->where('id', $this->placementId)->value('status'));
        $this->assertSame(0, DB::table('fac_placement_events')->where('event', 'updated')->count());
    }

    /**
     * The server must name EVERY field it rejects, not just the first.
     *
     * The form's summary panel lists each rejection against its field label, so a
     * capturer with several problems is told about all of them at once. That only
     * works if the response carries them all — Reinsurance reported being shown
     * one field and having to resubmit to discover the next.
     */
    public function test_a_rejection_names_every_failing_field(): void
    {
        try {
            $this->controller()->update($this->request([
                'gross_ceded_premium' => 8500.00,
                'risk_pct'            => 1.5,          // > 1 (i.e. over 100%)
                'commission_pct'      => 2.75,         // the classic decimal slip
                'ppw_days'            => 4000,         // > 1095
                'currency'            => 'ZZZZ',       // not 3 characters
                'counterparty_id'     => 999999,       // not in reinsurer
                'period_from'         => '2026-06-01',
                'period_to'           => '2026-01-01', // before period_from
            ]), $this->placementId);
            $this->fail('a payload failing several rules must be rejected');
        } catch (ValidationException $e) {
            $failed = array_keys($e->errors());

            foreach (['risk_pct', 'commission_pct', 'ppw_days', 'currency', 'counterparty_id', 'period_to'] as $field) {
                $this->assertContains($field, $failed, "the response must name {$field}");
            }
            $this->assertGreaterThanOrEqual(6, count($failed),
                'all failing fields must arrive together, not one per round trip');
        }

        $this->assertSame(0, DB::table('fac_placement_events')->where('event', 'updated')->count());
    }

    /**
     * The arithmetic guard still applies to an edit, and a refused edit leaves
     * neither a changed row nor an entry claiming it changed one.
     *
     * A full commission leaves the reinsurer with nothing, which is not a
     * placement — it is a decimal point in the wrong place (0.275 typed as 2.75).
     */
    public function test_a_full_commission_is_refused_and_leaves_no_entry(): void
    {
        $response = $this->controller()->update($this->request([
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 1.0,
        ]), $this->placementId);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('under 100%', json_decode($response->getContent(), true)['message']);

        $row = DB::table('fac_placements')->where('id', $this->placementId)->first();
        $this->assertEqualsWithDelta(0.275, (float) $row->commission_pct, 0.000001, 'the row must not move');
        $this->assertSame(0, DB::table('fac_placement_events')->where('event', 'updated')->count());
    }

    // ────────────────────────────────────────────────────────────────────
    //  Plumbing
    // ────────────────────────────────────────────────────────────────────

    private function controller(): FacRegisterApiController
    {
        return app(FacRegisterApiController::class);
    }

    /** @param array<string, mixed> $payload */
    private function request(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac/' . $this->placementId, 'PUT', $payload);
        $r->headers->set('Accept', 'application/json');

        return $r;
    }

    /** A guard user with an id and a name, without needing the users table. */
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

    /**
     * The FAC tables come from the real migrations — including them keeps this
     * test from asserting against a schema the application does not have.
     */
    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100003_create_fac_placement_attachments_table.php',
            '2026_07_30_100004_create_fac_placement_events_table.php',
            // show() reads the slip and its acceptance panel to report who is on the
            // placement, so the slip tables have to exist even for an edit test.
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

        // ── The Graphite side of the lookup. V1 tables, no migrations here. ──
        Schema::create('policies', function ($t) {
            $t->id();
            $t->string('policyNumber')->nullable();
            $t->integer('status')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->decimal('premium', 14, 2)->nullable();
            $t->decimal('annual_premium', 14, 2)->nullable();
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
            $t->softDeletes();
        });

        Schema::create('policy_term', function ($t) {
            $t->id();
            $t->date('term_start_date')->nullable();
            $t->date('term_end_date')->nullable();
        });

        // Read by the coverage check and the receipts panel that update() returns
        // through show(). Left empty — the assertions here are about the trail.
        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->softDeletes();
        });

        // Receipts land on different SIDES depending on trans_type — payments in
        // `credit`, reversals and refunds in `debit`. Both columns are needed or
        // the read throws rather than returning zero.
        Schema::create('policy_ledger', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->decimal('credit', 14, 2)->nullable();
            $t->decimal('debit', 14, 2)->nullable();
            $t->date('accounting_date')->nullable();
            $t->softDeletes();
        });
    }

    private function seedGraphite(): void
    {
        DB::table('reinsurer')->insert([
            ['id' => self::COUNTERPARTY_ID, 'company_name' => 'Grand Re'],
            ['id' => 9, 'company_name' => 'Continental Re'],
        ]);

        // Product 8 is a COM product, so the insured resolves to the company name.
        DB::table('companies')->insert(['id' => 300, 'name' => 'Kalahari Mining (Pty) Ltd']);
        DB::table('customer')->insert(['id' => 900, 'firstName' => 'Kalahari', 'lastName' => 'Mining']);
        DB::table('customer_profile')->insert([
            'id' => 1, 'customer_id' => 900, 'company_id' => 300, 'entity_type' => 'Organisation',
        ]);
        DB::table('products')->insert(['id' => 8, 'name' => 'Commercial Combined']);
        DB::table('policies')->insert([
            'id'           => 902,
            'policyNumber' => 'COMG2026999888',
            'status'       => 1,                 // 1 = active
            'product_id'   => 8,
            'customer_id'  => 900,
            'premium'      => 49_795.00,
            'premium_freq' => '12',
        ]);
        DB::table('policy_term')->insert([
            'id' => 60, 'term_start_date' => '2026-03-01', 'term_end_date' => '2027-02-28',
        ]);
        DB::table('policy_actions')->insert([
            'id' => 70, 'policy_id' => 902, 'term_id' => 60, 'transaction_type' => 'New Business',
            'effective_from' => '2026-03-01', 'effective_to' => '2027-02-28',
        ]);
    }

    /**
     * The line as it was captured — with the policy number typo the correction
     * path exists to fix, so `policy_in_graphite` starts false.
     *
     * Gross 8,500.00 at 27.5% commission: 2,337.50 commission, 6,162.50 net, and
     * 7,456.14 excluding the 14% VAT carried inside the gross.
     */
    private function seedPlacement(): int
    {
        return DB::table('fac_placements')->insertGetId([
            'fac_reference'                => 'FAC-2026-000001',
            'fac_slip_no'                  => '2026-113',
            'financial_year'               => 'FY2026-27',
            'placement_type'               => 'fac',
            'policy_id'                    => null,
            'policy_number'                => 'COMG2026213751',
            'insured_name'                 => 'Snehal Test Holdings',
            'policy_in_graphite'           => false,
            'policy_active_in_graphite'    => false,
            'counterparty_id'              => self::COUNTERPARTY_ID,
            'counterparty_name'            => 'Grand Re',
            'currency'                     => 'BWP',
            'cession_sum_insured'          => 12_000_000.00,
            'risk_pct'                     => 0.170700,
            'gross_ceded_premium'          => 8_500.00,
            'commission_pct'               => 0.2750,
            'commission_amount'            => 2_337.50,
            'net_ceded_premium'            => 6_162.50,
            'vat_applicable'               => true,
            'vat_rate'                     => 0.14,
            'gross_ceded_premium_excl_vat' => 7_456.14,
            'commission_excl_vat'          => 2_050.44,
            'gross_ceded_premium_bwp'      => 8_500.00,
            'commission_amount_bwp'        => 2_337.50,
            'net_ceded_premium_bwp'        => 6_162.50,
            'slip_signed_date'             => '2026-03-11',
            'ppw_terms'                    => '90 days',
            'ppw_days'                     => 90,
            'ppw_due_date'                 => '2026-06-09',
            'underwriter_id'               => 41,
            'underwriter_name'             => 'Kefilwe Mokwena',
            'status'                       => 'placed',
            'source'                       => 'manual',
            'created_by'                   => 41,
            'updated_by'                   => 41,
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);
    }
}
