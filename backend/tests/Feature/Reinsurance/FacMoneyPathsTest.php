<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Services\Reinsurance\FacPeriodLock;
use AlphaDirect\Services\Reinsurance\FacRegisterService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The four FAC actions that carried NO test coverage: markClientPaid, closePeriod,
 * storeGl and syncPolicy.
 *
 * These are the money-moving and period-locking paths. markClientPaid decides when
 * a reinsurer becomes payable; closePeriod freezes the figure Finance posts. Both
 * were reachable with nothing asserting what they do.
 *
 * Coverage was confirmed absent before writing this, not assumed:
 *   grep -rl "markClientPaid\|closePeriod\|storeGl\|syncPolicy" tests/  →  no files
 *
 * Two real control gaps were found while reading these paths, and pinned here as
 * documented failing reproductions — marked incomplete rather than left as hard
 * failures, so the suite stays green while a genuine regression stays visible.
 *
 *   · the unsigned-payment gap is now CLOSED. markClientPaid refuses a placement
 *     with no signed slip, and the reproduction has become the guard's own test
 *     (see test_an_unsigned_draft_cannot_be_marked_client_paid and the three
 *     tests beside it).
 *   · test_a_closed_period_does_not_lock_the_placements_behind_it is still OPEN.
 *     A premium can be changed after its period is closed, the frozen snapshot
 *     and the live register then disagree, and no control reports it.
 *
 * Same safety harness as FacCaptureSplitTest: in-memory sqlite forced and verified,
 * auditing off (the audit connection otherwise falls back to production), S3 faked.
 */
class FacMoneyPathsTest extends TestCase
{
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

        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);
        config(['fac.recipients.ri_team' => [], 'fac.recipients.debtors' => []]);
        Storage::fake('s3');

        $this->buildSchema();
        $this->seedGraphite();

        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────
    //  markClientPaid — when the reinsurer becomes payable
    // ────────────────────────────────────────────────────────────────────

    /** The ordinary path: a signed, placed line whose client premium arrives. */
    public function test_recording_the_client_premium_makes_a_placed_line_ready_to_settle(): void
    {
        $id = $this->seedDraft([
            'status'           => 'placed',
            'slip_signed_date' => '2026-08-01',
            'ppw_due_date'     => '2026-10-30',
        ]);

        $this->controller()->markClientPaid(
            $this->request(['source' => 'manual', 'amount' => 8500.00, 'paid_at' => '2026-08-15']),
            $id
        );

        $p = FacPlacement::find($id);
        $this->assertSame('ready_to_settle', $p->status);
        $this->assertSame('manual', $p->client_paid_source);
        $this->assertEqualsWithDelta(8500.00, (float) $p->client_paid_amount, 0.005);
        $this->assertSame(41, (int) $p->client_paid_marked_by);
        $this->assertNotNull($p->client_paid_at);
    }

    /** The settlement clock starts from the client payment, not from capture. */
    public function test_the_settlement_due_date_is_derived_from_the_client_payment(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => '2026-08-01']);

        $this->controller()->markClientPaid(
            $this->request(['source' => 'manual', 'paid_at' => '2026-08-15']),
            $id
        );

        $p = FacPlacement::find($id);
        $this->assertNotNull($p->settlement_due_date, 'no settlement date was derived');
        $this->assertTrue(
            $p->settlement_due_date->greaterThan($p->client_paid_at),
            'the settlement date must fall after the client paid'
        );
    }

    /** A cancelled line cannot be paid. */
    public function test_a_cancelled_placement_cannot_be_marked_client_paid(): void
    {
        $id = $this->seedDraft(['status' => 'cancelled']);

        $r = $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsStringIgnoringCase('cancelled', $r->getData(true)['message']);
        $this->assertSame('cancelled', FacPlacement::find($id)->status);
    }

    /**
     * A settled line cannot be dragged back into the settle queue. Without this the
     * RI team is told to pay the same reinsurer twice.
     */
    public function test_a_settled_placement_cannot_be_marked_client_paid_again(): void
    {
        $id = $this->seedDraft(['status' => 'settled', 'settled_at' => now()]);

        $r = $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsStringIgnoringCase('already been settled', $r->getData(true)['message']);
        $this->assertSame('settled', FacPlacement::find($id)->status);
    }

    /** The payment is on the trail, and the trail names who recorded it. */
    public function test_the_client_payment_is_recorded_on_the_trail(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => '2026-08-01']);

        $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $e = DB::table('fac_placement_events')
            ->where('fac_placement_id', $id)->where('event', 'client_paid')->first();

        $this->assertNotNull($e, 'no client_paid event was written');
        $this->assertStringContainsStringIgnoringCase('client premium received', $e->summary);
        $this->assertSame('Kefilwe Mokwena', $e->actor_name, 'the trail must name who recorded it');
    }

    /**
     * WAS A GAP, NOW GUARDED — an unsigned placement cannot be marked
     * client-paid. markClientPaid() used to guard only `cancelled` and
     * `settled`; neither asked whether the reinsurer had ever signed, so a
     * draft — by definition a placement nobody has agreed to — was promoted to
     * `ready_to_settle` and queued a payment to a reinsurer not on risk.
     *
     * The guard is on `slip_signed_date`, not on the status, which is why the
     * sibling test below covers a `placed` row too: `placed` is an assertion
     * that the panel was placed, the signed slip is the evidence of it.
     */
    public function test_an_unsigned_draft_cannot_be_marked_client_paid(): void
    {
        $id = $this->seedDraft(['status' => 'draft', 'slip_signed_date' => null]);

        $r = $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsStringIgnoringCase('signed slip', $r->getData(true)['message']);

        $p = FacPlacement::find($id);
        $this->assertSame('draft', $p->status, 'the status must not have moved');
        $this->assertNull($p->client_paid_at, 'no client receipt may be recorded');
        $this->assertNull($p->settlement_due_date, 'nothing may become due to the counterparty');
    }

    /**
     * The same refusal for a `placed` row with no slip on file. This is the case
     * that actually exists in the register — four of the six live placements are
     * `placed` with a null signed date — so a guard written against the status
     * rather than the date would have closed the draft hole and left this one
     * open.
     */
    public function test_a_placed_placement_with_no_signed_slip_cannot_be_marked_client_paid(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => null]);

        $r = $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame('placed', FacPlacement::find($id)->status);
        $this->assertNull(FacPlacement::find($id)->client_paid_at);
    }

    /** The control must not block the legitimate path: a signed line still pays. */
    public function test_a_signed_placement_is_still_marked_client_paid(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => '2026-08-20']);

        $this->controller()->markClientPaid($this->request(['source' => 'manual']), $id);

        $p = FacPlacement::find($id);
        $this->assertSame('ready_to_settle', $p->status);
        $this->assertNotNull($p->client_paid_at);
    }

    /**
     * A client proof of payment on an UNSIGNED line files the document and holds
     * the line — it must not 500, and it must not queue the payment.
     *
     * storeAttachment() calls markClientPaid() without a try/catch, so the new
     * guard would otherwise throw out of a request that had already stored the
     * file and written the attachment row: a 500 on top of a half-done write.
     */
    public function test_a_client_pop_on_an_unsigned_line_is_filed_but_does_not_queue_payment(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => null]);

        $request = Request::create("/api/v1/reinsurance/fac/{$id}/attachments", 'POST', [
            'doc_type' => 'client_pop',
            'amount'   => 8500.00,
        ], [], [
            'file' => UploadedFile::fake()->create('client-pop.pdf', 16, 'application/pdf'),
        ]);
        $request->headers->set('Accept', 'application/json');

        $r = $this->controller()->storeAttachment($request, $id);

        $this->assertSame(200, $r->getStatusCode(), 'the upload must not error');

        // The document is on file.
        $body = json_decode($r->getContent(), true);
        $this->assertNotNull(
            collect($body['attachments'])->firstWhere('originalName', 'client-pop.pdf'),
            'the proof of payment must be kept'
        );

        // The line is not.
        $p = FacPlacement::find($id);
        $this->assertSame('placed', $p->status, 'the line must not reach the settle queue');
        $this->assertNull($p->client_paid_at);

        // And the reason is on the trail the detail page renders.
        $this->assertSame(
            1,
            DB::table('fac_placement_events')
                ->where('fac_placement_id', $id)
                ->where('event', 'client_paid_withheld')
                ->count(),
            'the refusal must be recorded, not swallowed'
        );
    }

    /** The sweep skips unsigned lines instead of dying on the guard. */
    public function test_the_sweep_skips_unsigned_placements_without_aborting(): void
    {
        $this->seedDraft([
            'status'           => 'placed',
            'slip_signed_date' => null,
            'policy_id'        => 4242,
            'source'           => 'manual',
        ]);

        // No exception, and nothing promoted.
        $out = app(FacRegisterService::class)->sweepClientPayments();

        $this->assertSame(0, $out['marked']);
        $this->assertSame(
            0,
            DB::table('fac_placements')->where('status', 'ready_to_settle')->count(),
            'an unsigned line must not reach the settle queue via the sweep'
        );
    }

    /**
     * The recorded client amount is never compared with what is owed, so a part
     * payment promotes the line exactly as a full one does. Recording this as
     * observed behaviour rather than a defect — whether a part payment should
     * promote the line is a Finance rule we do not hold.
     */
    public function test_a_part_payment_promotes_the_line_the_same_as_a_full_one(): void
    {
        $id = $this->seedDraft(['status' => 'placed', 'slip_signed_date' => '2026-08-01']);

        $this->controller()->markClientPaid(
            $this->request(['source' => 'manual', 'amount' => 1.00]),
            $id
        );

        $p = FacPlacement::find($id);
        $this->assertSame('ready_to_settle', $p->status);
        $this->assertEqualsWithDelta(1.00, (float) $p->client_paid_amount, 0.005);
        $this->assertEqualsWithDelta(8500.00, (float) $p->gross_ceded_premium, 0.005);
    }

    // ────────────────────────────────────────────────────────────────────
    //  closePeriod — freezing the figure Finance posts
    // ────────────────────────────────────────────────────────────────────

    /** Closing writes one snapshot row per counterparty, stamped with who closed it. */
    public function test_closing_a_period_writes_a_snapshot(): void
    {
        $this->seedDraft([
            'status'           => 'ready_to_settle',
            'slip_signed_date' => '2026-08-01',
            'client_paid_at'   => '2026-08-15',
        ]);

        $out = $this->controller()->closePeriod(
            $this->request(['period_end' => '2026-08-31', 'financial_year' => 'FY2026-27'])
        )->getData(true);

        $this->assertSame('2026-08-31', $out['periodEnd']);
        $this->assertGreaterThan(0, $out['rowsWritten'], 'nothing was snapshotted');

        $snap = DB::table('fac_period_snapshots')->first();
        $this->assertNotNull($snap);
        $this->assertSame(41, (int) $snap->closed_by);
        $this->assertNotNull($snap->closed_at);
    }

    /** Re-closing replaces the period rather than doubling it. */
    public function test_re_closing_a_period_replaces_it_rather_than_duplicating(): void
    {
        $this->seedDraft([
            'status'           => 'ready_to_settle',
            'slip_signed_date' => '2026-08-01',
            'client_paid_at'   => '2026-08-15',
        ]);

        $first  = $this->controller()->closePeriod($this->request(['period_end' => '2026-08-31']))->getData(true);
        $second = $this->controller()->closePeriod($this->request(['period_end' => '2026-08-31']))->getData(true);

        $this->assertSame($first['rowsWritten'], $second['rowsWritten']);
        $this->assertSame(
            $second['rowsWritten'],
            DB::table('fac_period_snapshots')->whereDate('period_end', '2026-08-31')->count(),
            're-closing duplicated the period'
        );
    }

    /** A period end is required — a snapshot with no date belongs to no month. */
    public function test_closing_without_a_period_end_is_refused(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller()->closePeriod($this->request(['financial_year' => 'FY2026-27']));
    }

    /**
     * CLOSING A PERIOD NOW LOCKS THE PLACEMENTS INSIDE IT.
     *
     * This was pinned incomplete: nothing read fac_period_snapshots or closed_at,
     * so a premium could be edited after its month closed, the register moved, the
     * snapshot did not, and no control anywhere reported the difference.
     *
     * The change is made through the MODEL, not a controller — which is how the
     * gap was found and why the guard lives on FacPlacement's saving event rather
     * than in FacRegisterApiController.
     */
    public function test_a_closed_period_locks_the_money_behind_it(): void
    {
        $id = $this->seedDraft([
            'status'           => 'ready_to_settle',
            'slip_signed_date' => '2026-08-01',
            'client_paid_at'   => '2026-08-15',
        ]);

        $this->controller()->closePeriod($this->request(['period_end' => '2026-08-31']));
        $closed = (float) DB::table('fac_period_snapshots')->whereDate('period_end', '2026-08-31')->sum('payable');
        $this->assertGreaterThan(0.0, $closed);

        try {
            FacPlacement::find($id)->update(['gross_ceded_premium' => 99999.00]);
            $this->fail('the premium moved behind a closed period');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('closed to 2026-08-31', $e->getMessage());
            $this->assertStringContainsString('gross_ceded_premium', $e->getMessage());
        }

        // BOTH SIDES STILL AGREE, which is the whole point of the control.
        $this->assertEqualsWithDelta(
            $closed,
            (float) DB::table('fac_period_snapshots')->whereDate('period_end', '2026-08-31')->sum('payable'),
            0.005
        );
        $this->assertEqualsWithDelta(
            $closed,
            (float) FacPlacement::find($id)->gross_ceded_premium,
            0.005,
            'the register moved even though the save was refused'
        );
    }

    /**
     * A CORRECTION IS STILL POSSIBLE, AND LEAVES A RECORD. A premium captured
     * wrong in a month Finance has closed has to be fixable; what must not happen
     * is fixing it invisibly.
     */
    public function test_a_reopening_is_allowed_and_recorded(): void
    {
        $id = $this->seedDraft([
            'status'           => 'ready_to_settle',
            'slip_signed_date' => '2026-08-01',
            'client_paid_at'   => '2026-08-15',
        ]);
        $this->controller()->closePeriod($this->request(['period_end' => '2026-08-31']));

        FacPeriodLock::withReopened('premium captured from the wrong slip', function () use ($id) {
            FacPlacement::find($id)->update(['gross_ceded_premium' => 99999.00]);
        });

        $this->assertEqualsWithDelta(99999.00, (float) FacPlacement::find($id)->gross_ceded_premium, 0.005);

        $event = DB::table('fac_placement_events')
            ->where('fac_placement_id', $id)
            ->where('event', 'period_reopened')
            ->first();

        $this->assertNotNull($event, 'the reopening left no trail');
        $this->assertStringContainsString('wrong slip', (string) $event->summary);
        $this->assertStringContainsString('gross_ceded_premium', (string) $event->summary);
    }

    /** A bypass with no reason is refused — one nobody can explain is worthless. */
    public function test_a_reopening_without_a_reason_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('needs a reason');

        FacPeriodLock::withReopened('   ', fn () => null);
    }

    /**
     * SETTLING A LINE AFTER ITS MONTH CLOSES IS ORDINARY WORK, not a breach.
     * Locking the whole row would have stopped it. Moving between placed,
     * client_paid and settled changes no figure a snapshot carries — only the
     * draft boundary adds a line to a period or removes one.
     */
    public function test_a_closed_period_still_allows_the_line_to_be_settled(): void
    {
        $id = $this->seedDraft([
            'status'           => 'ready_to_settle',
            'slip_signed_date' => '2026-08-01',
            'client_paid_at'   => '2026-08-15',
        ]);
        $this->controller()->closePeriod($this->request(['period_end' => '2026-08-31']));

        FacPlacement::find($id)->update(['status' => 'settled', 'settled_at' => now()]);

        $this->assertSame('settled', FacPlacement::find($id)->status);
    }

    /** With nothing closed, nothing is locked — the guard must not fire on a clean register. */
    public function test_nothing_is_locked_before_a_period_is_closed(): void
    {
        $id = $this->seedDraft(['status' => 'ready_to_settle']);

        FacPlacement::find($id)->update(['gross_ceded_premium' => 4242.00]);

        $this->assertEqualsWithDelta(4242.00, (float) FacPlacement::find($id)->gross_ceded_premium, 0.005);
    }

    // ────────────────────────────────────────────────────────────────────
    //  syncPolicy — re-reading the Graphite snapshot
    // ────────────────────────────────────────────────────────────────────

    /** Syncing re-reads Graphite and stamps when it last looked. */
    public function test_syncing_a_placement_refreshes_the_graphite_snapshot(): void
    {
        $id = $this->seedDraft(['policy_number' => 'COMG2026999888', 'insured_name' => 'WRONG NAME']);

        $this->controller()->syncPolicy($id);

        $p = FacPlacement::find($id);
        $this->assertNotNull($p->policy_synced_at, 'the sync was not stamped');
        $this->assertTrue((bool) $p->policy_in_graphite, 'a seeded policy should be found');
    }

    /** A policy number Graphite does not hold is reported, not invented. */
    public function test_syncing_an_unknown_policy_number_is_reported(): void
    {
        $id = $this->seedDraft(['policy_number' => 'COMG0000000000']);

        $this->controller()->syncPolicy($id);

        $p = FacPlacement::find($id);
        $this->assertFalse((bool) $p->policy_in_graphite, 'an unknown policy must not read as found');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Harness
    // ────────────────────────────────────────────────────────────────────

    private function controller(): FacRegisterApiController
    {
        return app(FacRegisterApiController::class);
    }

    /** @param array<string, mixed> $payload */
    private function request(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac', 'POST', $payload);
        $r->headers->set('Accept', 'application/json');

        return $r;
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

    /** @param array<string,mixed> $overrides */
    private function seedDraft(array $overrides = []): int
    {
        return DB::table('fac_placements')->insertGetId(array_merge([
            'fac_reference'           => 'FAC-2026-000001',
            'fac_slip_no'             => '2026-113',
            'financial_year'          => 'FY2026-27',
            'placement_type'          => 'fac',
            'policy_number'           => 'COMG2026999888',
            'insured_name'            => 'Kalahari Mining (Pty) Ltd',
            'counterparty_id'         => self::COUNTERPARTY_ID,
            'counterparty_name'       => 'Grand Re',
            'currency'                => 'BWP',
            'gross_ceded_premium'     => 8500.00,
            'commission_pct'          => 0.2750,
            'commission_amount'       => 2337.50,
            'net_ceded_premium'       => 6162.50,
            'vat_applicable'          => true,
            'vat_rate'                => 0.14,
            'gross_ceded_premium_bwp' => 8500.00,
            'ppw_terms'               => '90 days',
            'ppw_days'                => 90,
            'slip_signed_date'        => null,
            'ppw_due_date'            => null,
            'status'                  => 'draft',
            'source'                  => 'manual',
            'created_by'              => 41,
            'updated_by'              => 41,
            // INSIDE THE PERIOD THESE TESTS CLOSE, not today.
            //
            // payableByCounterparty filters whereDate('created_at', '<=', $asAt),
            // so a placement created "now" falls outside a period that has already
            // ended. With now() here the snapshot tests passed every day of August
            // and began failing on 1 September, having tested nothing about the
            // close and everything about the calendar.
            'created_at'              => '2026-08-15 09:00:00',
            'updated_at'              => '2026-08-15 09:00:00',
        ], $overrides));
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
            $t->integer('settlement_terms_days')->nullable();
            $t->string('email')->nullable();
        });
        Schema::create('policies', function ($t) {
            $t->id();
            $t->string('policyNumber')->nullable();
            $t->integer('status')->nullable();
            $t->integer('product_id')->nullable();
            $t->integer('customer_id')->nullable();
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
            $t->integer('customer_id')->nullable();
            $t->integer('company_id')->nullable();
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
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
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
            $t->integer('policy_id')->nullable();
            $t->decimal('sum_insured', 18, 2)->nullable();
        });
        Schema::create('policy_ledger', function ($t) {
            $t->id();
            $t->integer('policy_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->decimal('credit', 18, 2)->nullable();
            $t->decimal('debit', 18, 2)->nullable();
            $t->date('accounting_date')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    private function seedGraphite(): void
    {
        DB::table('reinsurer')->insert([
            ['id' => self::COUNTERPARTY_ID, 'company_name' => 'Grand Re'],
        ]);
        DB::table('companies')->insert(['id' => 300, 'name' => 'Kalahari Mining (Pty) Ltd']);
        DB::table('customer')->insert(['id' => 900, 'firstName' => 'Kalahari', 'lastName' => 'Mining']);
        DB::table('customer_profile')->insert([
            'id' => 1, 'customer_id' => 900, 'company_id' => 300, 'entity_type' => 'Organisation',
        ]);
        DB::table('products')->insert(['id' => 8, 'name' => 'Commercial Combined']);
        DB::table('policies')->insert([
            'id' => 902, 'policyNumber' => 'COMG2026999888', 'status' => 1,
            'product_id' => 8, 'customer_id' => 900, 'premium' => 49795.00, 'premium_freq' => '12',
        ]);
        DB::table('policy_term')->insert([
            'id' => 60, 'term_start_date' => '2026-03-01', 'term_end_date' => '2027-02-28',
        ]);
        DB::table('policy_actions')->insert([
            'id' => 70, 'policy_id' => 902, 'term_id' => 60, 'transaction_type' => 'New Business',
            'effective_from' => '2026-03-01', 'effective_to' => '2027-02-28',
        ]);
    }
}
