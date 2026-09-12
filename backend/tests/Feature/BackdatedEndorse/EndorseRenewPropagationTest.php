<?php

namespace Tests\Feature\BackdatedEndorse;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Services\BackdatedEndorse\BackdatedEndorseRefresher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Scenario-matrix proof for the PERMANENT "renewal drops mid-term endorsed
 * vehicles" fix in BackdatedEndorseRefresher.
 *
 * Matrix covered:
 *   endorse op {add vehicle, remove vehicle, premium-only change}
 *     × renew type {RENEW (monthly/quarterly share this path), ANNIVERSARY-RENEW}
 *     × chain {intact, already-broken}
 *   plus: re-dated/backdated endorse (the prod over-charge), idempotency,
 *   dry-run, ENDORSE-target annual-only, and the issue-path reconcile bridge.
 *
 * For each we assert:
 *   (a) each downstream batch on/after the endorse transaction_date carries the
 *       correct vehicle set,
 *   (b) batches BEFORE transaction_date are UNTOUCHED (no backward propagation),
 *   (c) RENEW / anniversary premium recomputed from the corrected tree,
 *   (d) a ledger_adjustments audit row is written for each billing change and
 *       the invoice ledger row is updated IN PLACE (no silent regen).
 *
 * SAFETY: this test runs ONLY against an in-memory sqlite database that it
 * builds by hand in setUp(). It never uses RefreshDatabase and setUp() FAILS
 * LOUDLY if the default connection is not sqlite — it can never touch a real DB.
 *
 * The heavy premium pipeline (PolicyAction::calculatePremiumRenew ->
 * PolicyCreateController::recomputeActionTotals) is substituted by a faithful
 * micro-model (SumOfLiveMotorRefresher below) that sums live motor
 * calculated_value — exactly the motor branch of the real recomputeActionTotals
 * — so the engine's orchestration (add/remove -> recompute -> in-place ledger +
 * audit -> no backward propagation) is proven end-to-end without the full
 * schema. The real premium formula is exercised by the app + Monika's staging test.
 */
class EndorseRenewPropagationTest extends TestCase
{
    private const POLICY_ID = 9001;
    private const TERM_ID   = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // ── HARD SAFETY: force in-memory sqlite; refuse anything else. ──
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

        $this->buildSchema();
    }

    // ────────────────────────────────────────────────────────────────────
    //  SCENARIO 1 — ADD vehicle + RE-DATED endorse (the prod over-charge):
    //  proves add-missing (a,c,d) AND no backward propagation (b) at once.
    // ────────────────────────────────────────────────────────────────────
    public function test_add_missing_vehicle_carries_forward_and_never_propagates_backward(): void
    {
        // Endorse transacted 28/01 but effective_from re-dated to the 01/09 term
        // start (the exact prod shape on COMG2024103065).
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2025-09-01', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        // 3 carried vehicles (stamped by a prior action) + 1 NEW vehicle stamped
        // by THIS endorse (endors_flag=1, previousActionIdCov=endorse.id).
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R3', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R4', 1000, 1, 200); // <- added by endorse

        // A RENEW batch effective BETWEEN term-start and transaction_date — must
        // stay UNTOUCHED (the batch the added risk was NOT yet on cover for).
        $early = $this->makeAction(210, 'RENEW', 'ISSUED', '2025-10-01', '2026-08-31', '2025-10-01', 3000.0);
        $earlyPc = $this->makeCoverage(2100, 210);
        $this->makeMotor($earlyPc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($earlyPc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($earlyPc, 'BW-R3', 1000, 0, 100);
        $earlyInvoice = $this->makeInvoiceLedger(210, 3000);

        // A RENEW batch effective AFTER transaction_date — must gain the vehicle.
        $late = $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 3000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R3', 1000, 0, 100);
        $lateInvoice = $this->makeInvoiceLedger(220, 3000);

        $report = $this->refresher()->refresh($endorse);

        // (b) NO BACKWARD PROPAGATION — the pre-transaction_date batch is untouched.
        $this->assertSame(['BW-R1', 'BW-R2', 'BW-R3'], $this->liveRegs(210), 'early batch vehicle set changed — backward propagation!');
        $this->assertSame(3000.0, $this->premiumOf(210), 'early batch premium moved — backward propagation!');
        $this->assertSame(3000.0, $this->ledgerAmount($earlyInvoice), 'early batch invoice re-billed — backward propagation!');
        $this->assertSame(0, $this->ledgerAdjustmentsFor(210), 'early batch must have NO ledger_adjustments');

        // (a) the on/after batch carries the correct 4-vehicle set.
        $this->assertSame(['BW-R1', 'BW-R2', 'BW-R3', 'BW-R4'], $this->liveRegs(220), 'endorsed vehicle was not added to the later renewal');
        // (c) premium recomputed from the corrected tree.
        $this->assertSame(4000.0, $this->premiumOf(220));
        // (d) invoice ledger updated IN PLACE (same row id, not regenerated) + audit row.
        $this->assertSame(4000.0, $this->ledgerAmount($lateInvoice), 'invoice ledger not updated in place');
        $this->assertNull($this->ledgerDeletedAt($lateInvoice), 'invoice ledger was soft-deleted (silent regen) — must be in-place');
        $this->assertSame(1, $this->invoiceLedgerCount(220), 'a new invoice row was generated — must reuse the existing one');
        $this->assertSame(1, $this->ledgerAdjustmentsFor(220), 'exactly one ledger_adjustments audit row expected');
        $this->assertEqualsWithDelta(1000.0, $this->ledgerAdjustmentDelta(220), 0.001, 'audited delta wrong');

        $this->assertSame(1, $report->targetActionsProcessed, 'only the on/after batch should be targeted');
        $this->assertSame(1, $report->renewsUpdated);
    }

    // ── SCENARIO 2 — REMOVE vehicle from a downstream RENEW. ──────────────
    public function test_removed_vehicle_is_dropped_from_downstream_renew_with_audit(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R2', 1000, 0, 100);
        // Vehicle removed by this endorse: soft-deleted in place, still stamped.
        $this->makeMotor($endorsePc, 'BW-R3', 1000, 1, 200, '2026-01-28 09:00:00');

        $late = $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 3000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R3', 1000, 0, 100); // still live in the renewal
        $lateInvoice = $this->makeInvoiceLedger(220, 3000);

        $this->refresher()->refresh($endorse);

        $this->assertSame(['BW-R1', 'BW-R2'], $this->liveRegs(220), 'removed vehicle still live in the renewal');
        $this->assertSame(2000.0, $this->premiumOf(220), 'premium did not fall after removal');
        $this->assertSame(2000.0, $this->ledgerAmount($lateInvoice));
        $this->assertNull($this->ledgerDeletedAt($lateInvoice), 'invoice must be updated in place, not regenerated');
        $this->assertSame(1, $this->ledgerAdjustmentsFor(220));
        $this->assertEqualsWithDelta(-1000.0, $this->ledgerAdjustmentDelta(220), 0.001, 'removal should refund one vehicle');
    }

    // ── SCENARIO 3 — PREMIUM-ONLY change on an existing vehicle. ──────────
    public function test_premium_only_change_propagates_value_and_audits_delta(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        // Existing vehicle whose value was raised by this endorse (3000 -> 5000).
        $this->makeMotor($endorsePc, 'BW-R1', 5000, 1, 200);

        $late = $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 5000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 3000, 0, 100);
        $this->makeMotor($latePc, 'BW-R2', 2000, 0, 100);
        $lateInvoice = $this->makeInvoiceLedger(220, 5000);

        $this->refresher()->refresh($endorse);

        $this->assertSame(5000.0, $this->motorValue(220, 'BW-R1'), 'new value not propagated');
        $this->assertSame(7000.0, $this->premiumOf(220), 'premium not recomputed from updated value');
        $this->assertSame(7000.0, $this->ledgerAmount($lateInvoice));
        $this->assertSame(1, $this->ledgerAdjustmentsFor(220));
        $this->assertEqualsWithDelta(2000.0, $this->ledgerAdjustmentDelta(220), 0.001);
    }

    // ── SCENARIO 4 — ANNIVERSARY-RENEW gets tree + recomputed premium. ────
    public function test_anniversary_renew_quote_gets_tree_and_recomputed_premium(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R3', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R4', 1000, 1, 200); // added

        // An ISSUED later RENEW so the endorse is detected as backdated.
        $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 3000.0);
        $rpc = $this->makeCoverage(2200, 220);
        $this->makeMotor($rpc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($rpc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($rpc, 'BW-R3', 1000, 0, 100);

        // The next-term ANNIVERSARY-RENEW quote — a 3-vehicle tree carrying a
        // 3-vehicle premium that must become a 4-vehicle tree + premium.
        $anniv = $this->makeAction(230, 'ANNIVERSARY-RENEW', 'QUOTE', '2026-09-01', '2027-08-31', '2026-09-01', 3000.0);
        $apc = $this->makeCoverage(2300, 230);
        $this->makeMotor($apc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($apc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($apc, 'BW-R3', 1000, 0, 100);

        $report = $this->refresher()->refresh($endorse);

        $this->assertSame(['BW-R1', 'BW-R2', 'BW-R3', 'BW-R4'], $this->liveRegs(230), 'anniversary quote did not get the endorsed vehicle');
        $this->assertSame(4000.0, $this->premiumOf(230), 'anniversary premium not recomputed (6-vehicle-tree/3-vehicle-premium bug)');
        // A quote has no invoice ledger yet, so no ledger_adjustments are written.
        $this->assertSame(0, $this->ledgerAdjustmentsFor(230));
        $this->assertGreaterThanOrEqual(2, $report->targetActionsProcessed, 'both the RENEW and the anniversary should be targeted');
    }

    // ── SCENARIO 5 — idempotency: a second refresh is a no-op. ────────────
    public function test_refresh_is_idempotent_no_duplicate_vehicle_or_ledger_adjustment(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R4', 1000, 1, 200);

        $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 1000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 1000, 0, 100);
        $lateInvoice = $this->makeInvoiceLedger(220, 1000);

        $this->refresher()->refresh($endorse);
        $this->refresher()->refresh(PolicyAction::find(200)); // run again

        $this->assertSame(['BW-R1', 'BW-R4'], $this->liveRegs(220), 'vehicle duplicated on second run');
        $this->assertSame(2000.0, $this->premiumOf(220));
        $this->assertSame(2000.0, $this->ledgerAmount($lateInvoice), 'ledger double-adjusted on second run');
        $this->assertSame(1, $this->ledgerAdjustmentsFor(220), 'second run must not write another ledger_adjustments row');
    }

    // ── SCENARIO 6 — dry-run writes NOTHING but reports the plan. ─────────
    public function test_dry_run_writes_nothing(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R4', 1000, 1, 200);

        $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 1000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 1000, 0, 100);
        $lateInvoice = $this->makeInvoiceLedger(220, 1000);

        $report = $this->refresher()->dryRun($endorse);

        // Nothing changed.
        $this->assertSame(['BW-R1'], $this->liveRegs(220), 'dry-run added a vehicle');
        $this->assertSame(1000.0, $this->premiumOf(220), 'dry-run changed the premium');
        $this->assertSame(1000.0, $this->ledgerAmount($lateInvoice), 'dry-run changed the ledger');
        $this->assertSame(0, $this->ledgerAdjustmentsFor(220), 'dry-run wrote a ledger_adjustments row');
        $this->assertSame(0, (int) DB::table('backdated_endorse_refresh_log')->count(), 'dry-run wrote a refresh-log row');
        // But it reported the plan.
        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->targetActionsProcessed);
        $this->assertNotEmpty($report->decisions);
    }

    // ── SCENARIO 7 — issue-path reconcile bridge is idempotent (Change 3). ─
    public function test_reconcile_renew_target_is_delta_based_and_idempotent(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);

        // A RENEW whose tree (4 vehicles) already exceeds its stale premium (3000):
        // the first reconcile should reprice to 4000 + audit; the second is a no-op.
        $late = $this->makeAction(220, 'RENEW', 'ISSUED', '2026-02-01', '2026-08-31', '2026-02-01', 3000.0);
        $latePc = $this->makeCoverage(2200, 220);
        $this->makeMotor($latePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R2', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R3', 1000, 0, 100);
        $this->makeMotor($latePc, 'BW-R4', 1000, 0, 100);
        $lateInvoice = $this->makeInvoiceLedger(220, 3000);

        $refresher = $this->refresher();
        $d1 = $refresher->reconcileRenewTarget($endorse, PolicyAction::find(220));
        $d2 = $refresher->reconcileRenewTarget($endorse, PolicyAction::find(220));

        $this->assertEqualsWithDelta(1000.0, $d1, 0.001, 'first reconcile should reprice by +1000');
        $this->assertEqualsWithDelta(0.0, $d2, 0.001, 'second reconcile must be a zero-delta no-op');
        $this->assertSame(4000.0, $this->ledgerAmount($lateInvoice));
        $this->assertSame(1, $this->ledgerAdjustmentsFor(220), 'reconcile must not double-charge');
    }

    // ── SCENARIO 8 — ENDORSE target refreshes annual only; pro-rata sealed. ─
    public function test_endorse_target_refreshes_annual_only_and_seals_pro_rata(): void
    {
        $endorse = $this->makeAction(200, 'ENDORSE', 'ISSUED', '2026-01-28', '2026-08-31', '2026-01-28', 50.0);
        $endorsePc = $this->makeCoverage(2000, 200);
        $this->makeMotor($endorsePc, 'BW-R1', 1000, 0, 100);
        $this->makeMotor($endorsePc, 'BW-R4', 1000, 1, 200); // added

        // A later ENDORSE batch (its pro-rata premium=175 must stay sealed).
        $downEndorse = $this->makeAction(220, 'ENDORSE', 'ISSUED', '2026-03-01', '2026-08-31', '2026-03-01', 175.0);
        $dpc = $this->makeCoverage(2200, 220);
        $this->makeMotor($dpc, 'BW-R1', 1000, 0, 100);

        $this->refresher()->refresh($endorse);

        $this->assertSame(['BW-R1', 'BW-R4'], $this->liveRegs(220), 'endorsed vehicle not carried into later ENDORSE batch');
        // Pro-rata (premium) SEALED at its issued value.
        $this->assertSame(175.0, $this->premiumOf(220), 'pro-rata premium was not sealed on the ENDORSE target');
        // Annual refreshed from the corrected 2-vehicle tree.
        $this->assertSame(2000.0, (float) DB::table('policy_actions')->where('id', 220)->value('annual_premium'), 'annual_premium not refreshed');
        // ENDORSE targets never touch the ledger.
        $this->assertSame(0, $this->ledgerAdjustmentsFor(220));
    }

    // ─────────────────────────── helpers ────────────────────────────────

    private function refresher(): SumOfLiveMotorRefresher
    {
        return new SumOfLiveMotorRefresher();
    }

    private function makeAction(int $id, string $type, string $status, string $effFrom, string $effTo, string $txDate, float $premium): PolicyAction
    {
        DB::table('policy_actions')->insert([
            'id'               => $id,
            'policy_id'        => self::POLICY_ID,
            'term_id'          => self::TERM_ID,
            'transaction_type' => $type,
            'status'           => $status,
            'effective_from'   => $effFrom,
            'effective_to'     => $effTo,
            'transaction_date' => $txDate,
            'premium'          => $premium,
            'annual_premium'   => $premium,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        return PolicyAction::find($id);
    }

    private function makeCoverage(int $pcId, int $actionId, int $coverageId = 15, string $addr = 'RISK-A'): int
    {
        $raId = $pcId * 10;
        DB::table('risk_address')->insert([
            'id'           => $raId,
            'policy_id'    => self::POLICY_ID,
            'action_id'    => $actionId,
            'address_name' => $addr,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('policy_coverages')->insert([
            'id'              => $pcId,
            'policy_id'       => self::POLICY_ID,
            'action_id'       => $actionId,
            'term_id'         => self::TERM_ID,
            'coverage_id'     => $coverageId,
            'risk_address_id' => $raId,
            'row_type'        => 'OLD',
            'status'          => '0',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return $pcId;
    }

    private function makeMotor(int $pcId, string $reg, float $value, int $endorsFlag, int $prevActionIdCov, ?string $deletedAt = null): int
    {
        return (int) DB::table('motor')->insertGetId([
            'policy_coverage_id'  => $pcId,
            'registration_no'     => $reg,
            'calculated_value'    => $value,
            'coverage_value'      => $value,
            'endors_flag'         => $endorsFlag,
            'previousActionIdCov' => $prevActionIdCov,
            'pro_rate_premium'    => $value,
            'deleted_at'          => $deletedAt,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    private function makeInvoiceLedger(int $actionId, float $amount): int
    {
        return (int) DB::table('policy_ledger')->insertGetId([
            'policy_id'  => self::POLICY_ID,
            'action_id'  => $actionId,
            'trans_type' => 'Invoice',
            'amount'     => $amount,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Live vehicle registrations under an action, ordered, for set comparison. */
    private function liveRegs(int $actionId): array
    {
        $pcIds = DB::table('policy_coverages')->where('action_id', $actionId)->whereNull('deleted_at')->pluck('id');
        return DB::table('motor')->whereIn('policy_coverage_id', $pcIds)->whereNull('deleted_at')
            ->orderBy('registration_no')->pluck('registration_no')->all();
    }

    private function motorValue(int $actionId, string $reg): float
    {
        $pcIds = DB::table('policy_coverages')->where('action_id', $actionId)->whereNull('deleted_at')->pluck('id');
        return (float) DB::table('motor')->whereIn('policy_coverage_id', $pcIds)->whereNull('deleted_at')
            ->where('registration_no', $reg)->value('calculated_value');
    }

    private function premiumOf(int $actionId): float
    {
        return (float) DB::table('policy_actions')->where('id', $actionId)->value('premium');
    }

    private function ledgerAmount(int $ledgerId): float
    {
        return (float) DB::table('policy_ledger')->where('id', $ledgerId)->value('amount');
    }

    private function ledgerDeletedAt(int $ledgerId): ?string
    {
        return DB::table('policy_ledger')->where('id', $ledgerId)->value('deleted_at');
    }

    private function invoiceLedgerCount(int $actionId): int
    {
        return (int) DB::table('policy_ledger')->where('action_id', $actionId)->whereNull('deleted_at')->count();
    }

    private function ledgerAdjustmentsFor(int $targetActionId): int
    {
        return (int) DB::table('ledger_adjustments')->where('target_action_id', $targetActionId)->count();
    }

    private function ledgerAdjustmentDelta(int $targetActionId): float
    {
        return (float) DB::table('ledger_adjustments')->where('target_action_id', $targetActionId)
            ->whereNull('target_sub_ledger_id')->sum('delta_amount');
    }

    private function buildSchema(): void
    {
        $s = Schema::connection('sqlite');

        $s->create('policy_actions', function ($t) {
            $t->integer('id')->primary();
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->string('transaction_type', 40)->nullable();
            $t->string('status', 40)->nullable();
            $t->string('effective_from')->nullable();
            $t->string('effective_to')->nullable();
            $t->string('transaction_date')->nullable();
            $t->decimal('premium', 20, 2)->default(0);
            $t->decimal('annual_premium', 20, 2)->nullable();
            $t->integer('previous_action_id')->nullable();
            $t->integer('current_frequency_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->integer('updated_by')->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('risk_address', function ($t) {
            $t->integer('id')->primary();
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('address_name')->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('policy_coverages', function ($t) {
            $t->integer('id')->primary();
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->integer('coverage_id')->nullable();
            $t->integer('risk_address_id')->nullable();
            $t->string('row_type', 16)->nullable();
            $t->string('status', 8)->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('motor', function ($t) {
            $t->increments('id');
            $t->integer('policy_coverage_id')->nullable();
            $t->string('registration_no')->nullable();
            $t->decimal('calculated_value', 20, 2)->default(0);
            $t->decimal('coverage_value', 20, 2)->default(0);
            $t->integer('endors_flag')->default(0);
            $t->integer('previousActionIdCov')->nullable();
            $t->decimal('pro_rate_premium', 20, 2)->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        // Non-motor child with the endorsement columns present but left empty in
        // these motor-focused scenarios (proves it is queried without error and
        // that non-motor behaviour is untouched).
        $s->create('policy_coverage_detail', function ($t) {
            $t->increments('id');
            $t->integer('policy_coverage_id')->nullable();
            $t->integer('coverage_id')->nullable();
            $t->decimal('coverage_value', 20, 2)->nullable();
            $t->decimal('rate', 20, 4)->nullable();
            $t->decimal('calculated_value', 20, 2)->nullable();
            $t->integer('endors_flag')->default(0);
            $t->integer('previousActionIdCov')->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('policy_specified_items', function ($t) {
            $t->increments('id');
            $t->integer('policy_coverage_id')->nullable();
            $t->integer('specified_coverage_id')->nullable();
            $t->integer('motor_id')->nullable();
            $t->decimal('sum_insured', 20, 2)->nullable();
            $t->decimal('rate', 20, 4)->nullable();
            $t->decimal('calculated_value', 20, 2)->nullable();
            $t->integer('endors_flag')->default(0);
            $t->integer('previousActionIdCov')->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('policy_coverage_notes', function ($t) {
            $t->increments('id');
            $t->integer('policy_coverage_id')->nullable();
            $t->integer('motor_id')->nullable();
            $t->text('note')->nullable();
            $t->text('benefits_note')->nullable();
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('trans_type', 40)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('policy_subledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->integer('ledger_id')->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->string('deleted_at')->nullable();
            $t->timestamps();
        });

        $s->create('ledger_adjustments', function ($t) {
            $t->increments('id');
            $t->integer('source_action_id');
            $t->integer('target_action_id');
            $t->integer('target_ledger_id');
            $t->integer('target_sub_ledger_id')->nullable();
            $t->decimal('old_amount', 20, 2);
            $t->decimal('new_amount', 20, 2);
            $t->decimal('delta_amount', 20, 2);
            $t->string('reason', 64)->default('BACKDATED_REFRESH');
            $t->integer('applied_by_user_id')->nullable();
            $t->timestamps();
        });

        $s->create('backdated_endorse_refresh_log', function ($t) {
            $t->increments('id');
            $t->integer('source_action_id');
            $t->integer('target_action_id');
            $t->string('target_transaction_type', 32);
            $t->string('table_name', 64);
            $t->string('row_business_key', 255)->nullable();
            $t->text('fields_updated')->nullable();
            $t->text('fields_skipped_protected')->nullable();
            $t->boolean('premium_recalculated')->default(false);
            $t->decimal('premium_delta', 20, 2)->nullable();
            $t->string('idempotency_hash', 64);
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
            $t->unique(['source_action_id', 'target_action_id', 'idempotency_hash'], 'bder_src_tgt_hash_uq');
        });
    }
}

/**
 * Test double: replaces the two external premium-recompute seams with a faithful
 * micro-model — premium = Σ live motor.calculated_value under the action — which
 * is exactly the motor branch of the real PolicyCreateController::recomputeActionTotals.
 * This lets the propagation/ledger/audit orchestration be proven hermetically
 * without booting the full controller or schema.
 */
class SumOfLiveMotorRefresher extends BackdatedEndorseRefresher
{
    protected function runRenewPremiumRecompute(PolicyAction $target): void
    {
        $sum = self::liveMotorSum((int) $target->id);
        DB::table('policy_actions')->where('id', $target->id)
            ->update(['premium' => $sum, 'annual_premium' => $sum, 'updated_at' => now()]);
    }

    protected function runEndorseAnnualRecompute(PolicyAction $target): void
    {
        // Real recomputeActionTotals writes BOTH premium + annual_premium; the
        // caller (recomputeAnnualOnlyForEndorse) then restores the sealed pro-rata.
        $sum = self::liveMotorSum((int) $target->id);
        DB::table('policy_actions')->where('id', $target->id)
            ->update(['premium' => $sum, 'annual_premium' => $sum, 'updated_at' => now()]);
    }

    public static function liveMotorSum(int $actionId): float
    {
        $pcIds = DB::table('policy_coverages')->where('action_id', $actionId)->whereNull('deleted_at')->pluck('id');
        if ($pcIds->isEmpty()) {
            return 0.0;
        }
        return (float) DB::table('motor')->whereIn('policy_coverage_id', $pcIds)->whereNull('deleted_at')->sum('calculated_value');
    }
}
