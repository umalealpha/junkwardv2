<?php

namespace Tests\Feature\SpecifiedItems;

use AlphaDirect\Http\Controllers\Api\V1\PolicyCreateController;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature-level proof for the coverage-level specified-items CRUD flow
 * (add / list / edit(=soft-delete only) / delete) exposed by
 * PolicyCreateController::{listSpecifiedItems,addSpecifiedItem,
 * updateSpecifiedItem,deleteSpecifiedItem}.
 *
 * SAFETY (copied verbatim, same two hazards as
 * tests/Feature/RiskAddress/RiskAddressImportExportTest.php):
 *
 *  1. backend/.env's default connection points at the PRODUCTION RDS
 *     (DB_HOST=graphite-v2-prod-ro.../Graphite_live) and phpunit.xml's
 *     sqlite lines are commented out. setUp() forcibly rebinds the
 *     "sqlite" connection to :memory:, makes it the default connection,
 *     and FAILS LOUDLY if the resulting connection isn't actually
 *     sqlite :memory:.
 *
 *  2. Every model this test touches (PolicySpecifiedItem, Policy,
 *     PolicyCoverage, PolicyAction, PolicyTerm, SpecifiedCoveragesItems)
 *     implements OwenIt\Auditing\Contracts\Auditable, whose observer
 *     writes to the SEPARATE 'mysql_system' connection — which falls
 *     back to the SAME production host when DB_HOST_SYSTEM is unset.
 *     Neutralised three ways (belt-and-braces): audit.enabled=false,
 *     ::disableAuditing() on every touched class, and
 *     audit.drivers.database.connection forced to sqlite (with no
 *     `audits` table created, so any stray write fails loudly instead
 *     of silently hitting prod).
 *
 * Isolation strategy: every coverage created here has action_id = NULL
 * (except the single 409-guard scenario, which intentionally attaches an
 * ISSUED action). With action_id NULL, ensureCoverageEditable() treats the
 * coverage as editable AND recomputeActionTotals() is never invoked (the
 * controller gates it behind `if ($actionId)` / `if ($covActionId)`), so
 * the heavy premium/pro-rata engine never runs.
 *
 * Schema is hand-built with Schema::create() — no RefreshDatabase, no real
 * migrations. Run ONLY this file:
 *   php artisan test --filter=SpecifiedItemsCrudTest
 *   vendor/bin/phpunit tests/Feature/SpecifiedItems/SpecifiedItemsCrudTest.php
 */
class SpecifiedItemsCrudTest extends TestCase
{
    private int $policyId;
    private int $coverageId;
    private int $masterItemId;

    /** Master coverage id stamped on the seeded policy_coverages row — the list endpoint must echo this back as the top-level coverage_id. */
    private const MASTER_COVERAGE_ID = 20;

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
        PolicySpecifiedItem::disableAuditing();
        Policy::disableAuditing();
        PolicyCoverage::disableAuditing();
        PolicyAction::disableAuditing();
        PolicyTerm::disableAuditing();
        SpecifiedCoveragesItems::disableAuditing();

        $this->buildSchema();

        // ── Baseline fixtures reused by most scenarios ──
        $this->policyId = 500;
        DB::table('policies')->insert([
            'id'           => $this->policyId,
            'policyNumber' => 'SPECIT500',
            'customer_id'  => 900,
            'product_id'   => self::MASTER_COVERAGE_ID,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->masterItemId = DB::table('specified_coverage_items')->insertGetId([
            'coverage_id'    => self::MASTER_COVERAGE_ID,
            'specified_code' => 'LAPTOP',
            'specified_name' => 'Laptop',
            'rate'           => 5,
            'effective_from' => '2020-01-01',
            'effective_to'   => '2099-12-31',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // action_id NULL — editable, and skips recomputeActionTotals entirely.
        $this->coverageId = DB::table('policy_coverages')->insertGetId([
            'policy_id'  => $this->policyId,
            'coverage_id' => self::MASTER_COVERAGE_ID,
            'action_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  ADD (scenarios 1, 2, 3★)
    // ────────────────────────────────────────────────────────────────────

    /** Scenario 1 (must-have): master-item add — 201, motor_id NULL, name resolved from the master catalog, calculated_value = sum*rate/100. */
    public function test_add_master_item_returns_201_with_master_name_and_computed_value(): void
    {
        $response = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', ['specified_coverage_id' => $this->masterItemId, 'sum_insured' => 10000, 'rate' => 1.5]),
            $this->policyId,
            $this->coverageId
        );

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertSame($this->masterItemId, (int) $data['specified_coverage_id']);
        $this->assertFalse($data['isCustom']);
        $this->assertSame('Laptop', $data['name']);
        $this->assertEqualsWithDelta(150.0, (float) $data['calculated_value'], 0.0001, '10000 * 1.5 / 100 = 150');

        $row = DB::table('policy_specified_items')->where('id', $data['id'])->first();
        $this->assertNull($row->motor_id, 'coverage-level rows must have motor_id NULL');
        $this->assertSame($this->masterItemId, (int) $row->specified_coverage_id);
    }

    /** Scenario 2 (must-have): custom item (name only, no master pick) — 201, custom_name stored, isCustom=true. */
    public function test_add_custom_item_returns_201_with_custom_name(): void
    {
        $response = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', ['name' => 'My Custom Ring', 'sum_insured' => 500]),
            $this->policyId,
            $this->coverageId
        );

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertTrue($data['isCustom']);
        $this->assertSame('My Custom Ring', $data['name']);
        $this->assertNull($data['specified_coverage_id']);

        $row = DB::table('policy_specified_items')->where('id', $data['id'])->first();
        $this->assertNull($row->specified_coverage_id);
        $this->assertSame('My Custom Ring', $row->custom_name);
    }

    /**
     * ★ Scenario 3 (must-have, surprising): calculated_value is unconditionally
     * recomputed by PolicySpecifiedItem::boot()'s `creating` hook as
     * (sum_insured * rate) / 100 — the client-supplied calculated_value (and
     * the controller's own `round($sum * $rate / 100, 2)` fallback) are
     * silently overwritten. Sending calculated_value=999 alongside
     * sum=1000/rate=2.5 must NOT persist 999 — it must persist 25 (unrounded
     * model math, not even the controller's rounded 25.0).
     */
    public function test_calculated_value_is_model_computed_not_client_supplied(): void
    {
        $response = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', [
                'specified_coverage_id' => $this->masterItemId,
                'sum_insured'           => 1000,
                'rate'                  => 2.5,
                'calculated_value'      => 999,
            ]),
            $this->policyId,
            $this->coverageId
        );

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true)['data'];
        $id = (int) $data['id'];

        $this->assertEqualsWithDelta(25.0, (float) $data['calculated_value'], 0.0001,
            'API response must reflect the model-computed value (sum*rate/100), not the client-supplied 999');

        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertEqualsWithDelta(25.0, (float) $row->calculated_value, 0.0001,
            'stored calculated_value must be sum_insured*rate/100, overriding both the client value and the controller round()');
        $this->assertNotEquals(999.0, (float) $row->calculated_value);
    }

    // ────────────────────────────────────────────────────────────────────
    //  VALIDATION (scenario 4, must-have)
    // ────────────────────────────────────────────────────────────────────

    /** 4(a): neither specified_coverage_id nor name → 422 JsonResponse (not a ValidationException). */
    public function test_validation_neither_master_nor_name_returns_422_json_response(): void
    {
        $response = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', ['sum_insured' => 100]),
            $this->policyId,
            $this->coverageId
        );

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Provide a master item', $body['error']);
    }

    /** 4(b): missing sum_insured → ValidationException (required rule). */
    public function test_validation_missing_sum_insured_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', ['name' => 'No Sum']),
            $this->policyId,
            $this->coverageId
        );
    }

    /** 4(c): negative sum_insured → ValidationException (min:0 rule). */
    public function test_validation_negative_sum_insured_throws_validation_exception(): void
    {
        try {
            $this->controller()->addSpecifiedItem(
                $this->jsonRequest('POST', ['name' => 'Negative Sum', 'sum_insured' => -5]),
                $this->policyId,
                $this->coverageId
            );
            $this->fail('Expected a ValidationException for a negative sum_insured');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sum_insured', $e->errors());
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  LIST (scenario 5, must-have)
    // ────────────────────────────────────────────────────────────────────

    /** Master + custom + legacy motor_id=0 rows are all included; a motor_id!=0 row is excluded; values are strings; top-level coverage_id = master coverage_id. */
    public function test_list_includes_null_and_zero_motor_rows_excludes_motor_attached_and_returns_strings(): void
    {
        $masterId = $this->addItemAndGetId(['specified_coverage_id' => $this->masterItemId, 'sum_insured' => 10000, 'rate' => 1.5]);
        $customId = $this->addItemAndGetId(['name' => 'Custom Ring', 'sum_insured' => 500, 'rate' => 2]);

        $legacyId = DB::table('policy_specified_items')->insertGetId([
            'policy_coverage_id' => $this->coverageId,
            'motor_id'           => 0,
            'custom_name'        => 'Legacy Misc',
            'sum_insured'        => 50,
            'rate'               => 0,
            'calculated_value'   => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $motorAttachedId = DB::table('policy_specified_items')->insertGetId([
            'policy_coverage_id' => $this->coverageId,
            'motor_id'           => 5,
            'custom_name'        => 'Vehicle Radio',
            'sum_insured'        => 700,
            'rate'               => 0,
            'calculated_value'   => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $response = $this->controller()->listSpecifiedItems($this->policyId, $this->coverageId);
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertSame(self::MASTER_COVERAGE_ID, $body['coverage_id'], 'top-level coverage_id must be the master coverage_id');

        $rows = collect($body['data'])->keyBy('id');
        $this->assertCount(3, $rows, 'must include motor_id NULL and motor_id 0 rows, excluding the motor-attached row');
        $this->assertTrue($rows->has($masterId));
        $this->assertTrue($rows->has($customId));
        $this->assertTrue($rows->has($legacyId));
        $this->assertFalse($rows->has($motorAttachedId), 'a motor_id != 0 row must be excluded from the coverage-level listing');

        $master = $rows[$masterId];
        $this->assertFalse($master['isCustom']);
        $this->assertSame('Laptop', $master['name']);
        $this->assertIsString($master['sum_insured']);
        $this->assertIsString($master['rate']);
        $this->assertIsString($master['calculated_value']);

        $custom = $rows[$customId];
        $this->assertTrue($custom['isCustom']);
        $this->assertSame('Custom Ring', $custom['name']);

        $legacy = $rows[$legacyId];
        $this->assertTrue($legacy['isCustom']);
        $this->assertSame('Legacy Misc', $legacy['name']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  updateSpecifiedItem: soft-delete / reinstate (scenarios 6, 7)
    // ────────────────────────────────────────────────────────────────────

    /** Scenario 6: valid Y-m-d\TH:i:s.uP deleted_at soft-deletes the row and stamps endors_flag. */
    public function test_update_specified_item_soft_delete_sets_deleted_at_and_endors_flag(): void
    {
        $id = $this->addItemAndGetId(['name' => 'To Delete', 'sum_insured' => 100]);

        $response = $this->controller()->updateSpecifiedItem(
            $this->policyId, $this->coverageId, $id,
            $this->jsonRequest('PUT', ['deleted_at' => '2026-07-20T10:00:00.000000+00:00'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertNotNull($row->deleted_at);
        $this->assertSame('1', (string) $row->endors_flag);
    }

    /** Scenario 7: deleted_at = null reinstates a soft-deleted row. */
    public function test_update_specified_item_reinstate_with_null_clears_deleted_at(): void
    {
        $id = $this->addItemAndGetId(['name' => 'To Reinstate', 'sum_insured' => 100]);
        DB::table('policy_specified_items')->where('id', $id)->update(['deleted_at' => now()]);

        $response = $this->controller()->updateSpecifiedItem(
            $this->policyId, $this->coverageId, $id,
            $this->jsonRequest('PUT', ['deleted_at' => null])
        );

        $this->assertSame(200, $response->getStatusCode());
        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertNull($row->deleted_at);
    }

    /** Scenario 7 (string variant): deleted_at = the literal string 'null' also reinstates. */
    public function test_update_specified_item_reinstate_with_string_null_clears_deleted_at(): void
    {
        $id = $this->addItemAndGetId(['name' => 'To Reinstate 2', 'sum_insured' => 100]);
        DB::table('policy_specified_items')->where('id', $id)->update(['deleted_at' => now()]);

        $response = $this->controller()->updateSpecifiedItem(
            $this->policyId, $this->coverageId, $id,
            $this->jsonRequest('PUT', ['deleted_at' => 'null'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertNull($row->deleted_at);
    }

    /**
     * ★ Scenario 8 (must-have, surprising): updateSpecifiedItem does NOT
     * support editing sum_insured / rate / name. It only ever touches
     * deleted_at / updated_at / previousActionIdCov via a raw DB::table()
     * update — any sum_insured/rate/name sent in the body (without
     * deleted_at) are silently ignored. This means the "edit a specified
     * item's values" use case is NOT implemented by this endpoint.
     */
    public function test_update_specified_item_ignores_value_edits(): void
    {
        $id = $this->addItemAndGetId(['name' => 'Original Name', 'sum_insured' => 1000, 'rate' => 5]);
        $before = DB::table('policy_specified_items')->where('id', $id)->first();

        $response = $this->controller()->updateSpecifiedItem(
            $this->policyId, $this->coverageId, $id,
            $this->jsonRequest('PUT', ['sum_insured' => 99999, 'rate' => 50, 'name' => 'Changed Name'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $after = DB::table('policy_specified_items')->where('id', $id)->first();

        $this->assertEqualsWithDelta((float) $before->sum_insured, (float) $after->sum_insured, 0.0001,
            'updateSpecifiedItem must NOT change sum_insured — it only mutates deleted_at/updated_at/previousActionIdCov');
        $this->assertEqualsWithDelta((float) $before->rate, (float) $after->rate, 0.0001,
            'updateSpecifiedItem must NOT change rate');
        $this->assertSame($before->custom_name, $after->custom_name,
            'updateSpecifiedItem must NOT change the name/custom_name');
    }

    /** Scenario 9: a deleted_at that doesn't match Y-m-d\TH:i:s.uP → 422, and the row is left untouched. */
    public function test_update_specified_item_invalid_date_format_returns_422(): void
    {
        $id = $this->addItemAndGetId(['name' => 'Bad Date', 'sum_insured' => 100]);

        $response = $this->controller()->updateSpecifiedItem(
            $this->policyId, $this->coverageId, $id,
            $this->jsonRequest('PUT', ['deleted_at' => '2026-07-20'])
        );

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Invalid date format for deleted_at', $body['message']);

        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertNull($row->deleted_at, 'a rejected update must not have mutated the row');
    }

    // ────────────────────────────────────────────────────────────────────
    //  deleteSpecifiedItem (scenario 10)
    // ────────────────────────────────────────────────────────────────────

    public function test_delete_specified_item_soft_deletes_row(): void
    {
        $id = $this->addItemAndGetId(['name' => 'To Hard Delete Endpoint', 'sum_insured' => 100]);

        $response = $this->controller()->deleteSpecifiedItem($this->policyId, $this->coverageId, $id);
        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('policy_specified_items')->where('id', $id)->first();
        $this->assertNotNull($row->deleted_at);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Editable guard (scenario 11, must-have)
    // ────────────────────────────────────────────────────────────────────

    /** A coverage on an ISSUED (non-QUOTE) action blocks add/update/delete with 409 + action_status. */
    public function test_editable_guard_blocks_add_update_delete_with_409_when_action_not_quote(): void
    {
        $actionId = DB::table('policy_actions')->insertGetId([
            'policy_id'        => $this->policyId,
            'transaction_type' => 'NEWBUSINESS',
            'status'           => 'ISSUED',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $coverageId = DB::table('policy_coverages')->insertGetId([
            'policy_id'   => $this->policyId,
            'coverage_id' => self::MASTER_COVERAGE_ID,
            'action_id'   => $actionId,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $addResponse = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', ['name' => 'Blocked', 'sum_insured' => 100]),
            $this->policyId,
            $coverageId
        );
        $this->assertSame(409, $addResponse->getStatusCode());
        $addBody = json_decode($addResponse->getContent(), true);
        $this->assertSame('ISSUED', $addBody['action_status']);

        // Pre-existing row inserted directly (the endpoint itself is blocked from creating one).
        $itemId = DB::table('policy_specified_items')->insertGetId([
            'policy_coverage_id' => $coverageId,
            'custom_name'        => 'Pre-existing',
            'sum_insured'        => 100,
            'rate'               => 0,
            'calculated_value'   => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $updateResponse = $this->controller()->updateSpecifiedItem(
            $this->policyId, $coverageId, $itemId,
            $this->jsonRequest('PUT', ['deleted_at' => null])
        );
        $this->assertSame(409, $updateResponse->getStatusCode());
        $this->assertSame('ISSUED', json_decode($updateResponse->getContent(), true)['action_status']);

        $deleteResponse = $this->controller()->deleteSpecifiedItem($this->policyId, $coverageId, $itemId);
        $this->assertSame(409, $deleteResponse->getStatusCode());
        $this->assertSame('ISSUED', json_decode($deleteResponse->getContent(), true)['action_status']);

        // Confirm none of the blocked calls mutated the row.
        $row = DB::table('policy_specified_items')->where('id', $itemId)->first();
        $this->assertNull($row->deleted_at);
    }

    // ─────────────────────────── helpers ────────────────────────────────

    private function controller(): PolicyCreateController
    {
        return new PolicyCreateController();
    }

    private function jsonRequest(string $method, array $data): Request
    {
        return Request::create('/test/specified-items', $method, $data);
    }

    /** Adds a specified item to $this->coverageId via the controller and returns the new row's id. */
    private function addItemAndGetId(array $payload): int
    {
        $response = $this->controller()->addSpecifiedItem(
            $this->jsonRequest('POST', $payload),
            $this->policyId,
            $this->coverageId
        );
        $body = json_decode($response->getContent(), true);
        $this->assertSame(201, $response->getStatusCode(), 'fixture setup failed: ' . $response->getContent());

        return (int) $body['data']['id'];
    }

    private function buildSchema(): void
    {
        $s = Schema::connection('sqlite');

        $s->create('policies', function ($t) {
            $t->integer('id')->primary();
            $t->string('policyNumber')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->timestamps();
        });

        $s->create('policy_term', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->string('term_start_date')->nullable();
            $t->string('term_end_date')->nullable();
            $t->timestamps();
        });

        $s->create('policy_actions', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->string('transaction_type', 40)->nullable();
            $t->string('status', 40)->nullable();
            $t->string('effective_from')->nullable();
            $t->string('effective_to')->nullable();
            $t->timestamps();
            $t->string('deleted_at')->nullable();
        });

        $s->create('policy_coverages', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('coverage_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        $s->create('specified_coverage_items', function ($t) {
            $t->increments('id');
            $t->integer('coverage_id')->nullable();
            $t->string('specified_code')->nullable();
            $t->string('specified_name')->nullable();
            $t->decimal('rate', 12, 6)->nullable();
            $t->integer('sub_coverage_id')->nullable();
            $t->string('effective_from')->nullable();
            $t->string('effective_to')->nullable();
            $t->timestamps();
        });

        $s->create('policy_specified_items', function ($t) {
            $t->increments('id');
            $t->unsignedBigInteger('policy_coverage_id')->nullable();
            $t->integer('motor_id')->nullable();
            $t->unsignedBigInteger('specified_coverage_id')->nullable();
            $t->string('custom_name', 255)->nullable();
            $t->decimal('sum_insured', 12, 4)->nullable();
            $t->decimal('rate', 12, 4)->nullable();
            $t->decimal('calculated_value', 12, 4)->nullable();
            $t->integer('term_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->decimal('pro_rate_premium', 20, 2)->nullable()->default(0);
            $t->unsignedBigInteger('previousActionIdCov')->nullable()->default(0);
            $t->string('endors_flag')->nullable();
            $t->integer('created_by')->nullable();
            $t->integer('updated_by')->nullable();
            $t->string('description')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
