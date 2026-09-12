<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile and Electronic Device Insurance create-policy smoke test.
 *
 * Asserts the fix for "product 5 policy cannot be created":
 *   1. Dedicated endpoint creates a real policy — create-mobile-electronic
 *      returns 201 with a MIS-prefixed policyNumber, a `policies` row lands
 *      (product 5, status 0 = pending payment), and a `policy_cellphone`
 *      row is linked to it with the captured IMEI.
 *   2. An unlisted plan id is rejected (only {9,17} are accepted).
 *
 * Seeds its own session token directly; cleans up in tearDown.
 */
class MobileElectronicCreateSmokeTest extends TestCase
{
    private const TEST_CELL = '70000099';
    private const PRODUCT_ID = 5;
    private const PLAN = 9; // P49_cellphone_insurance_bronze — in the accepted set {9,17}

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
            if (\Schema::hasTable('policy_cellphone')) {
                DB::table('policy_cellphone')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
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
    public function mobile_electronic_create_endpoint_persists_policy_and_device(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-mobile-electronic', [
                'firstName'     => 'Lesedi',
                'lastName'      => 'Mobelec',
                'passport'      => 'MOBELEC99',
                'dob'           => '1994-01-15',
                'gender'        => 'Female',
                'phone'         => self::TEST_CELL,
                'email'         => 'mobelec-smoke@yopmail.com',
                'address'       => 'Gaborone',
                'planId'        => self::PLAN,
                'paymentMethod' => 'DPO',
                'device' => [
                    'deviceType' => 'Cellphone',
                    'imei'       => '356938035643809',
                    'make'       => 'Samsung',
                    'model'      => 'Galaxy S21',
                    'value'      => 8000,
                ],
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
            'Mobile/Electronic policy_number must use the MIS-prefix legacy retail pattern.');

        // Real policies row, correct product + pending-payment status.
        $row = DB::table('policies')->where('id', $policyId)->first(['product_id', 'plan_id', 'status']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(self::PLAN, (int) $row->plan_id);
        $this->assertSame(0, (int) $row->status);

        // Device row linked to the policy with the IMEI captured.
        $dev = DB::table('policy_cellphone')->where('policy_id', $policyId)->first();
        $this->assertNotNull($dev, 'policy_cellphone row should be persisted for device cover.');
        $this->assertSame('356938035643809', (string) $dev->imei);
        $this->assertSame('Samsung', $dev->cell_phone_make);
    }

    /** @test */
    public function mobile_electronic_rejects_unlisted_plan(): void
    {
        // Plan 99 is not in the accepted set {9,17} — must be rejected.
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-mobile-electronic', [
                'firstName'     => 'Lesedi',
                'lastName'      => 'Mobelec',
                'passport'      => 'MOBELEC99',
                'dob'           => '1994-01-15',
                'gender'        => 'Female',
                'phone'         => self::TEST_CELL,
                'planId'        => 99,
                'paymentMethod' => 'DPO',
                'device' => [
                    'deviceType' => 'Cellphone',
                    'imei'       => '356938035643809',
                    'make'       => 'Samsung',
                    'model'      => 'Galaxy S21',
                    'value'      => 8000,
                ],
            ]);

        $resp->assertStatus(422);
    }
}
