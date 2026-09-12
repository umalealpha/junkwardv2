<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Hospital Cashback Insurance (product 9) create-policy smoke test.
 *
 * Product 9 is NOT in the bundle's DEDICATED_ENDPOINT_PRODUCTS map, so there
 * is no bundle-refusal step (cf. ThirdPartyCar / Legal). Mirrors the
 * MobileElectronic smoke shape:
 *   1. Dedicated endpoint creates a real policy — create-hospital-cashback
 *      returns 201 with a MIS-prefixed policyNumber and a `policies` row
 *      (product 9, plan 19, status 0 = pending payment). Premium is computed
 *      server-side (P99 policy holder) — no product_plans rate dependency.
 *   2. An unlisted plan id is rejected (validation pins planId to 19).
 *
 * Seeds its own session token directly; cleans up in tearDown.
 */
class HospitalCashbackCreateSmokeTest extends TestCase
{
    private const TEST_CELL = '70000022';
    private const PRODUCT_ID = 9;
    private const PLAN = 19; // Hospital Cash Assurance (Adults Plan) — the only accepted plan

    private string $sessionToken;
    private array $cleanupCustomerIds = [];
    private array $cleanupPolicyIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionToken = Str::random(64);
        DB::table('public_session_tokens')->insert([
            'token_hash' => hash('sha256', $this->sessionToken . config('app.key')),
            'cellphone'  => self::TEST_CELL,
            'purpose'    => 'payment_authorize',
            'expires_at' => Carbon::now()->addMinutes(10),
            'ip'         => '127.0.0.1',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('public_session_tokens')->where('cellphone', self::TEST_CELL)->delete();

        if (!empty($this->cleanupPolicyIds)) {
            if (\Schema::hasTable('policy_members')) {
                DB::table('policy_members')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            }
            DB::table('policies')->whereIn('id', $this->cleanupPolicyIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            if (\Schema::hasTable('customer_profile')) {
                DB::table('customer_profile')->whereIn('customer_id', $this->cleanupCustomerIds)->delete();
            }
            DB::table('customer')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        parent::tearDown();
    }

    /** @test */
    public function hospital_cashback_create_endpoint_persists_policy(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-hospital-cashback', [
                'firstName'     => 'Tebogo',
                'middleName'    => 'Kgomotso',
                'lastName'      => 'Hcb',
                'passport'      => 'HCB0022',
                'idType'        => 'Passport',
                'dob'           => '1988-11-05',
                'gender'        => 'Male',
                'maritalStatus' => 'Single',
                'phone'         => self::TEST_CELL,
                'email'         => 'hcb-smoke@yopmail.com',
                'address'       => 'Gaborone',
                'planId'        => self::PLAN,
                'paymentMethod' => 'DPO',
            ]);

        $resp->assertStatus(201)
             ->assertJsonPath('ok', true)
             ->assertJsonStructure([
                 'policy_number',
                 'amount_to_pay',
                 'policy' => ['id', 'policyNumber', 'customer_id', 'product_id', 'plan_id'],
             ]);

        $policyId     = (int) $resp->json('policy.id');
        $customerId   = (int) $resp->json('policy.customer_id');
        $policyNumber = $resp->json('policy.policyNumber');

        $this->cleanupPolicyIds[]   = $policyId;
        $this->cleanupCustomerIds[] = $customerId;

        $this->assertStringStartsWith('MIS', (string) $policyNumber,
            'Hospital Cashback policy_number must use the MIS-prefix legacy retail pattern.');

        // Policy holder only → premium is the P99 base rate.
        $this->assertSame(99, (int) $resp->json('amount_to_pay'),
            'Policy-holder-only premium should be the P99 base rate.');

        // Real policies row, correct product + plan + pending-payment status.
        $row = DB::table('policies')->where('id', $policyId)->first(['product_id', 'plan_id', 'status']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(self::PLAN, (int) $row->plan_id);
        $this->assertSame(0, (int) $row->status, 'Newly created policy must be in pending-payment status (0).');

        // Middle name persisted to customer; marital status + ID type to profile.
        $cust = DB::table('customer')->where('id', $customerId)->first(['middleName']);
        $this->assertSame('Kgomotso', (string) $cust->middleName, 'Middle name should persist to customer.middleName.');

        if (\Schema::hasTable('customer_profile')) {
            $profile = DB::table('customer_profile')->where('customer_id', $customerId)->first();
            $this->assertNotNull($profile, 'customer_profile row should be persisted.');
            $this->assertSame('Single', (string) $profile->maritalstatus, 'Marital status should persist.');
            if (\Schema::hasColumn('customer_profile', 'id_type')) {
                $this->assertSame('Passport', (string) $profile->id_type, 'ID type should persist (Omang/Passport).');
            }
        }
    }

    /** @test */
    public function hospital_cashback_rejects_unlisted_plan(): void
    {
        // planId is pinned to 19 by the validator — anything else must 422.
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-hospital-cashback', [
                'firstName'     => 'Tebogo',
                'lastName'      => 'Hcb',
                'passport'      => 'HCB0022',
                'dob'           => '1988-11-05',
                'gender'        => 'Male',
                'phone'         => self::TEST_CELL,
                'planId'        => 18,
                'paymentMethod' => 'DPO',
            ]);

        $resp->assertStatus(422);
    }
}
