<?php

namespace Tests\Feature\Api;

use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\Services\ClaimSla\ClaimStageTimelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Claims SLA API — auth + feature-flag gating (deterministic, DB-free) plus a
 * DB-guarded stage-timeline audit test that skips when no test DB is reachable.
 */
class ClaimSlaTest extends TestCase
{
    /**
     * Force in-memory sqlite, and build the two tables from their OWN migrations.
     *
     * Two problems this fixes. First, `dbAvailable()` called
     * DB::connection()->getPdo() against whatever .env points at — the production
     * RDS — so on any machine that cannot reach it the file spent a full TCP
     * timeout per test and then skipped the only test that exercises anything.
     * Second, and worse: on a machine that CAN reach it, the audit test deletes
     * and inserts claim_id 999999001 in PRODUCTION.
     *
     * The schema comes from the migrations rather than being hand-built here. A
     * hand-built copy is what silently broke ClaimTrackingTest — it predated the
     * `purpose` column and every OTP test failed for a reason that had nothing to
     * do with the service.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            // The backdate-governance hook reads its runtime flag from
            // mysql_system. Pointed at a throwaway in-memory DB with no settings
            // table so it resolves to OFF without a network call.
            'database.connections.mysql_system' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'cache.default' => 'array',
        ]);

        DB::purge();

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected in-memory sqlite, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        // Two migration styles live side by side in this repo, and they have to be
        // loaded differently.
        //
        // A named-class migration must be require_ONCE'd: setUp runs per test, and
        // a plain `include` re-declares the class on the second one, which is a
        // fatal that kills the whole PHP process rather than failing a test — the
        // run just stops after the first dot with no error.
        //
        // An anonymous `return new class` migration is the opposite: it has to be
        // re-included each time to get a fresh object back.
        foreach ([
            '2026_04_14_000004_create_claim_edit_log.php'                                        => 'CreateClaimEditLog',
            '2026_07_14_120000_create_claim_tracker_workflow_table.php'                          => 'CreateClaimTrackerWorkflowTable',
            '2026_08_07_000000_add_contract_pricing_and_cil_value_to_claim_tracker_workflow.php' => null,
        ] as $migration => $class) {
            $path = database_path('migrations/' . $migration);

            if ($class !== null) {
                if (!class_exists($class, false)) {
                    require_once $path;
                }
                (new $class())->up();
                continue;
            }

            (include $path)->up();
        }
    }

    private function actAsAnyUser(): void
    {
        // A transient user is enough for the acting guard; role checks below
        // resolve to "no roles" which is exactly what the gate tests need.
        Sanctum::actingAs(new \AlphaDirect\User(), ['*']);
    }

    private function dbAvailable(): bool
    {
        try {
            DB::connection()->getPdo();
            return Schema::hasTable('claim_edit_log') && Schema::hasTable('claim_tracker_workflow');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function test_sla_timeline_requires_authentication(): void
    {
        $this->getJson('/api/v1/claims-v2/1/sla-timeline')->assertStatus(401);
    }

    public function test_sla_read_requires_authentication(): void
    {
        $this->getJson('/api/v1/claims-v2/1/sla')->assertStatus(401);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/claims/sla/dashboard')->assertStatus(401);
    }

    public function test_disabled_feature_returns_404_even_when_authenticated(): void
    {
        // Default config flag is OFF and there is no enabling integration row,
        // so the whole module is dark: authenticated reads 404.
        config()->set('claims_sla.enabled', false);
        $this->actAsAnyUser();

        $this->getJson('/api/v1/claims-v2/1/sla-timeline')->assertStatus(404);
        $this->getJson('/api/v1/claims-v2/1/sla')->assertStatus(404);
        $this->getJson('/api/v1/claims/sla/dashboard')->assertStatus(404);
        $this->getJson('/api/v1/claims/sla/leaderboard')->assertStatus(404);
    }

    public function test_enabled_but_no_role_is_forbidden(): void
    {
        // Force the flag on via config default (isEnabled falls back to it when
        // no DB row exists). A roleless user must be refused with 403 — unless a
        // real DB carries a claims_sla row that overrides the default, in which
        // case the module is legitimately dark (404) and we skip.
        config()->set('claims_sla.enabled', true);
        $this->actAsAnyUser();

        $res = $this->getJson('/api/v1/claims/sla/dashboard');
        if ($res->getStatusCode() === 404) {
            $this->markTestSkipped('claims_sla resolved disabled via DB row — gate is dark, RBAC not reachable here.');
        }
        $res->assertStatus(403);
    }

    public function test_stage_update_writes_prefixed_audit_rows(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('No test DB with claim_edit_log / claim_tracker_workflow — audit write not exercised.');
        }

        $service = app(ClaimStageTimelineService::class);
        $claimId = 999999001; // synthetic id; workflow row is independent of a real claim row

        // Clean any prior run.
        ClaimTrackerWorkflow::where('claim_id', $claimId)->delete();
        DB::table('claim_edit_log')->where('claim_id', $claimId)->where('field', 'like', 'workflow.%')->delete();

        $result = $service->update($claimId, [
            'assessor_name'           => 'A. Ndlovu',
            'assessor_allotment_date' => '2026-07-20',
        ], null);

        // The service returns `changes` — the audit rows it wrote — not a
        // `changed` count. This assertion had never run: dbAvailable() returned
        // false on any machine without a reachable DB carrying these two tables,
        // so the test skipped every time and the wrong key was never noticed.
        $this->assertCount(2, $result['changes']);
        $this->assertSame(
            ['workflow.assessor_name', 'workflow.assessor_allotment_date'],
            array_column($result['changes'], 'field'),
            'both edited fields are audited, and prefixed'
        );

        $logs = DB::table('claim_edit_log')
            ->where('claim_id', $claimId)
            ->where('field', 'like', 'workflow.%')
            ->pluck('new_value', 'field');

        $this->assertSame('A. Ndlovu', $logs['workflow.assessor_name'] ?? null);
        $this->assertArrayHasKey('workflow.assessor_allotment_date', $logs->toArray());

        // Cleanup.
        ClaimTrackerWorkflow::where('claim_id', $claimId)->delete();
        DB::table('claim_edit_log')->where('claim_id', $claimId)->where('field', 'like', 'workflow.%')->delete();
    }
}
