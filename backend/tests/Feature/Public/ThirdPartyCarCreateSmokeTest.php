<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Third Party Car Insurance create-policy smoke test.
 *
 * Two assertions:
 *   1. Bundle gate refuses product 2 — create-bundle returns 422
 *      product_has_dedicated_endpoint pointing at the dedicated endpoint.
 *   2. Dedicated endpoint creates a real policy — create-third-party-car
 *      returns 201 with a MIS-prefixed policyNumber, a `policies` row
 *      lands, and a `vehicle` row is linked to it.
 *
 * Routes the customer-facing flow off /create-bundle (which staged a
 * BQ-* quote the payment service couldn't resolve) onto a dedicated
 * MIS-minting endpoint that DPO initiate handles natively.
 *
 * Seeds its own session token directly; cleans up in tearDown.
 */
class ThirdPartyCarCreateSmokeTest extends TestCase
{
    private const TEST_CELL = '70000088';
    private const TPCAR_PRODUCT_ID = 2;
    private const TPCAR_PLAN = 16; // P79_P3000000_Cover — in the accepted set {4,16,29}

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
            if (\Schema::hasTable('vehicle')) {
                DB::table('vehicle')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
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
    public function bundle_endpoint_refuses_third_party_car(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-bundle', [
                'firstName'          => 'Sita',
                'lastName'           => 'Tpcar',
                'passport'           => 'TPCAR088',
                'dob'                => '1992-09-23',
                'gender'             => 'Female',
                'phone'              => self::TEST_CELL,
                'email'              => 'tpcar-smoke@yopmail.com',
                'residentialAddress' => 'Gaborone',
                'lines' => [[
                    'product_id'   => self::TPCAR_PRODUCT_ID,
                    'plan_id'      => self::TPCAR_PLAN,
                    'premium'      => 79,
                    'product_name' => 'Third Party Car Insurance',
                ]],
                'premiumFrequency' => 'annual',
            ]);

        $resp->assertStatus(422)
             ->assertJsonPath('error', 'product_has_dedicated_endpoint')
             ->assertJsonPath('product_id', self::TPCAR_PRODUCT_ID)
             ->assertJsonPath('use_endpoint', '/api/v1/public/policies/create-third-party-car');
    }

    /** @test */
    public function third_party_car_create_endpoint_persists_policy_and_vehicle(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-third-party-car', [
                'firstName'     => 'Sita',
                'lastName'      => 'Tpcar',
                'passport'      => 'TPCAR088',
                'dob'           => '1992-09-23',
                'gender'        => 'Female',
                'phone'         => self::TEST_CELL,
                'email'         => 'tpcar-smoke@yopmail.com',
                'address'       => 'Gaborone',
                'planId'        => self::TPCAR_PLAN,
                'paymentMethod' => 'DPO',
                'vehicle' => [
                    'plate' => 'B123ABC',
                    'make'  => 'Toyota',
                    'model' => 'Corolla',
                    'year'  => 2018,
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
            'TP Car policy_number must use the MIS-prefix legacy retail pattern.');

        // Real policies row, correct product + pending-payment status.
        $row = DB::table('policies')->where('id', $policyId)->first(['product_id', 'plan_id', 'status', 'has_vehicle']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::TPCAR_PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(self::TPCAR_PLAN, (int) $row->plan_id);
        $this->assertSame(0, (int) $row->status);
        $this->assertSame(1, (int) $row->has_vehicle, 'has_vehicle flag should be set for TP Car.');

        // Vehicle row linked to the policy with the plate captured.
        if (\Schema::hasTable('vehicle')) {
            $veh = DB::table('vehicle')->where('policy_id', $policyId)->first();
            $this->assertNotNull($veh, 'vehicle row should be persisted for TP Car.');
            $this->assertSame('B123ABC', strtoupper((string) $veh->vehiclePlate));
            $this->assertSame('Toyota', $veh->make);
        }
    }

    /** @test */
    public function third_party_car_rejects_unlisted_plan(): void
    {
        // Plan 2 (P29) exists for product 2 but is NOT in the accepted set
        // {4,16,29} — must be rejected by the validator.
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-third-party-car', [
                'firstName'     => 'Sita',
                'lastName'      => 'Tpcar',
                'passport'      => 'TPCAR088',
                'dob'           => '1992-09-23',
                'gender'        => 'Female',
                'phone'         => self::TEST_CELL,
                'planId'        => 2,
                'paymentMethod' => 'DPO',
                'vehicle' => [
                    'plate' => 'B123ABC',
                    'make'  => 'Toyota',
                    'model' => 'Corolla',
                    'year'  => 2018,
                ],
            ]);

        $resp->assertStatus(422);
    }
}
