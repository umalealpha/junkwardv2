<?php

namespace Tests\Feature\Api;

use AlphaDirect\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Graphite admin Hospital Cashback Insurance co-applicant management —
 * POST/PUT/DELETE /api/v1/policies/{id}/coapplicants[/{id}] are gated by
 * the hcb-coapplicants-manage permission (mutates billing, unlike the
 * generic Members tab). GET (list) is intentionally ungated, same as
 * other read-only policy sub-resources.
 */
class HcbCoapplicantAdminTest extends TestCase
{
    private ?User $user = null;
    private bool $grantedPermission = false;
    private array $cleanupCustomerIds = [];
    private array $cleanupPolicyIds = [];

    protected function tearDown(): void
    {
        if ($this->grantedPermission && $this->user) {
            $this->user->revokePermissionTo('hcb-coapplicants-manage');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if (!empty($this->cleanupPolicyIds)) {
            DB::table('hospital_Cashback_coapplicants')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            DB::table('scheduled_transactions')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            DB::table('customer_banking')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            DB::table('audits')->where('auditable_type', 'like', '%Policy')->whereIn('auditable_id', $this->cleanupPolicyIds)->delete();
            DB::table('activity_log')->where('subject_type', 'like', '%Policy')->whereIn('subject_id', $this->cleanupPolicyIds)->delete();
            DB::table('policies')->whereIn('id', $this->cleanupPolicyIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::table('customer')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }
        parent::tearDown();
    }

    private function makeHcbPolicy(): array
    {
        $customerId = DB::table('customer')->insertGetId([
            'firstName' => 'Hcb', 'lastName' => 'AdminSmoke', 'email' => 'hcb-admin-smoke@yopmail.com',
            'cellphone' => '70000046', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $customerId;

        $policyNumber = 'MIS' . Carbon::now()->format('Y') . random_int(100000, 999999);
        $policyId = DB::table('policies')->insertGetId([
            'customer_id' => $customerId, 'product_id' => 9, 'plan_id' => 19,
            'policyNumber' => $policyNumber, 'status' => 1, 'premium' => 99.00,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);
        $this->cleanupPolicyIds[] = $policyId;
        return [$policyId, $policyNumber];
    }

    public function test_add_route_is_gated_by_hcb_coapplicants_manage_permission(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'api.v1.policies.addCoapplicant');
        $this->assertNotNull($route, 'policies.addCoapplicant route not registered');
        $this->assertContains('permission:hcb-coapplicants-manage', $route->middleware());
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->user = User::first();
        if (!$this->user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($this->user, ['*']);
        // Ensure the permission row exists (without granting it) so
        // hasPermissionTo() returns false instead of throwing
        // PermissionDoesNotExist when this test runs before the other one.
        Permission::firstOrCreate(['name' => 'hcb-coapplicants-manage', 'guard_name' => 'web']);
        if ($this->user->hasPermissionTo('hcb-coapplicants-manage')) {
            $this->markTestSkipped('Test user already has hcb-coapplicants-manage — cannot exercise the denial path.');
        }

        [$policyId] = $this->makeHcbPolicy();

        $this->postJson("/api/v1/policies/{$policyId}/coapplicants", [
            'relation' => 'spouse', 'first_name' => 'A', 'last_name' => 'B', 'gender' => 'Male',
        ])->assertStatus(403);
    }

    public function test_authorised_user_can_manage_coapplicants_and_premium_recalculates(): void
    {
        $this->user = User::first();
        if (!$this->user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($this->user, ['*']);

        Permission::firstOrCreate(['name' => 'hcb-coapplicants-manage', 'guard_name' => 'web']);
        if (!$this->user->hasPermissionTo('hcb-coapplicants-manage')) {
            $this->user->givePermissionTo('hcb-coapplicants-manage');
            $this->grantedPermission = true;
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        [$policyId, ] = $this->makeHcbPolicy();

        $addResp = $this->postJson("/api/v1/policies/{$policyId}/coapplicants", [
            'relation' => 'spouse', 'first_name' => 'Partner', 'last_name' => 'AdminSmoke', 'gender' => 'Female', 'dob' => '1990-01-01',
        ]);
        $addResp->assertStatus(201);
        $this->assertEquals(99 + 89, (float) $addResp->json('premium'));

        $coappId = (int) $addResp->json('id');

        $deleteResp = $this->deleteJson("/api/v1/policies/{$policyId}/coapplicants/{$coappId}");
        $deleteResp->assertStatus(200);
        $this->assertEquals(99, (float) $deleteResp->json('premium'));
    }

    public function test_updating_coapplicant_details_persists_and_recalculates_premium_on_relation_change(): void
    {
        $this->user = User::first();
        if (!$this->user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($this->user, ['*']);

        Permission::firstOrCreate(['name' => 'hcb-coapplicants-manage', 'guard_name' => 'web']);
        if (!$this->user->hasPermissionTo('hcb-coapplicants-manage')) {
            $this->user->givePermissionTo('hcb-coapplicants-manage');
            $this->grantedPermission = true;
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        [$policyId, ] = $this->makeHcbPolicy();

        $addResp = $this->postJson("/api/v1/policies/{$policyId}/coapplicants", [
            'relation' => 'spouse', 'first_name' => 'Original', 'last_name' => 'Name', 'gender' => 'Female', 'dob' => '1990-01-01',
        ]);
        $addResp->assertStatus(201);
        $this->assertEquals(99 + 89, (float) $addResp->json('premium'));
        $coappId = (int) $addResp->json('id');

        // Editing details (not relation) must persist the new details and leave premium untouched.
        $renameResp = $this->putJson("/api/v1/policies/{$policyId}/coapplicants/{$coappId}", [
            'first_name' => 'Corrected', 'last_name' => 'Surname',
        ]);
        $renameResp->assertStatus(200);
        $this->assertEquals(99 + 89, (float) $renameResp->json('premium'));
        $this->assertDatabaseHas('hospital_Cashback_coapplicants', [
            'id' => $coappId, 'first_name' => 'Corrected', 'last_name' => 'Surname', 'relation' => 'spouse',
        ]);

        // Changing relation must recompute premium off the new co-applicant mix.
        $relationChangeResp = $this->putJson("/api/v1/policies/{$policyId}/coapplicants/{$coappId}", [
            'relation' => 'child',
        ]);
        $relationChangeResp->assertStatus(200);
        $this->assertEquals(99 + 49, (float) $relationChangeResp->json('premium'));
        $this->assertDatabaseHas('policies', ['id' => $policyId, 'premium' => 99 + 49]);
    }

    private function authoriseManage(): void
    {
        $this->user = User::first();
        if (!$this->user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($this->user, ['*']);
        Permission::firstOrCreate(['name' => 'hcb-coapplicants-manage', 'guard_name' => 'web']);
        if (!$this->user->hasPermissionTo('hcb-coapplicants-manage')) {
            $this->user->givePermissionTo('hcb-coapplicants-manage');
            $this->grantedPermission = true;
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function seedScheduleRow(int $policyId, string $policyNumber, int $status, float $premium): int
    {
        return DB::table('scheduled_transactions')->insertGetId([
            'policy_id' => $policyId, 'policy_number' => $policyNumber,
            'premium' => $premium, 'status' => $status,
            'payment_method' => 'DPO', 'email' => 'hcb-admin-smoke@yopmail.com',
            'billing_date' => Carbon::now()->addMonth()->toDateString(),
            'installment' => 1, 'retry_count' => 0,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);
    }

    public function test_dpo_future_schedule_rows_are_repriced_and_paid_rows_are_untouched(): void
    {
        $this->authoriseManage();
        [$policyId, $policyNumber] = $this->makeHcbPolicy();

        // Authoritative billing source the service reads: latest customer_banking row.
        DB::table('customer_banking')->insert([
            'policy_id' => $policyId, 'billing' => 'DPO',
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);

        // Two future/uncollected debits (status 0) + one already-collected (status 2),
        // all currently priced at the base premium of 99.
        $futureA = $this->seedScheduleRow($policyId, $policyNumber, 0, 99);
        $futureB = $this->seedScheduleRow($policyId, $policyNumber, 0, 99);
        $paid    = $this->seedScheduleRow($policyId, $policyNumber, 2, 99);

        // Add a spouse → premium becomes 99 + 89 = 188.
        $addResp = $this->postJson("/api/v1/policies/{$policyId}/coapplicants", [
            'relation' => 'spouse', 'first_name' => 'Partner', 'last_name' => 'AdminSmoke', 'gender' => 'Female', 'dob' => '1990-01-01',
        ]);
        $addResp->assertStatus(201);
        $this->assertEquals(188, (float) $addResp->json('premium'));

        // Future rows re-priced to the new premium; the paid row is left alone.
        $this->assertEquals(188.0, (float) DB::table('scheduled_transactions')->where('id', $futureA)->value('premium'));
        $this->assertEquals(188.0, (float) DB::table('scheduled_transactions')->where('id', $futureB)->value('premium'));
        $this->assertEquals(99.0,  (float) DB::table('scheduled_transactions')->where('id', $paid)->value('premium'));

        // Removing the spouse drops premium back to 99 and re-prices the future rows again.
        $coappId = (int) $addResp->json('id');
        $this->deleteJson("/api/v1/policies/{$policyId}/coapplicants/{$coappId}")->assertStatus(200);
        $this->assertEquals(99.0, (float) DB::table('scheduled_transactions')->where('id', $futureA)->value('premium'));
        $this->assertEquals(99.0, (float) DB::table('scheduled_transactions')->where('id', $paid)->value('premium'));
    }

    public function test_non_dpo_policy_schedule_rows_are_not_touched(): void
    {
        $this->authoriseManage();
        [$policyId, $policyNumber] = $this->makeHcbPolicy();

        // No customer_banking row and no policies.billing → billing is not 'dpo',
        // so the DPO sync must be a no-op (RealPay policies go via their own path).
        $future = $this->seedScheduleRow($policyId, $policyNumber, 0, 99);

        $addResp = $this->postJson("/api/v1/policies/{$policyId}/coapplicants", [
            'relation' => 'spouse', 'first_name' => 'Partner', 'last_name' => 'AdminSmoke', 'gender' => 'Female', 'dob' => '1990-01-01',
        ]);
        $addResp->assertStatus(201);
        $this->assertEquals(188, (float) $addResp->json('premium'));

        // Schedule row left at its original amount because billing is not DPO.
        $this->assertEquals(99.0, (float) DB::table('scheduled_transactions')->where('id', $future)->value('premium'));
    }
}
