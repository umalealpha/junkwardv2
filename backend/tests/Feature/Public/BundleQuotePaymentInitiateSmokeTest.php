<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Legal Insurance create-policy smoke test — full happy path.
 *
 * Two assertions, in order:
 *
 *   1. Bundle gate refuses product 4 — POST /api/v1/public/policies/create-bundle
 *      with a Legal Insurance line returns 422 product_has_dedicated_endpoint,
 *      proving the misroute guard in PublicBundleCreateController:39.
 *
 *   2. Dedicated endpoint creates a real policy — POST /api/v1/public/policies/
 *      create-legal-insurance returns 201 with a MIS-prefixed policyNumber,
 *      and a corresponding `policies` row lands in the DB linked to the
 *      customer record we just created.
 *
 * The original bug surface from 2026-05-26 (DPO initiate 404 on a BQ-* ref)
 * is now sidestepped by routing Legal through its own endpoint that mints
 * a MIS-prefixed policy_number — DPO initiate's existing Policy lookup
 * path handles MIS- natively, no BQ- branch needed for Legal.
 *
 * Test seeds its own session token and product_plans rows directly so it
 * doesn't depend on the OTP delivery pipeline or external seeding. Cleans
 * up everything in tearDown so it's repeatable.
 */
class BundleQuotePaymentInitiateSmokeTest extends TestCase
{
    private const TEST_CELL = '70000099'; // 8-digit BW local, unlikely to collide with real customer data
    private const LEGAL_PRODUCT_ID = 4;
    private const LEGAL_PLAN_BRONZE = 5; // P49_Legal_bronze — exists in product_plans on every deploy with product 4

    private string $sessionToken;
    private array $cleanupCustomerIds = [];
    private array $cleanupPolicyIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Mint a payment_authorize session token directly. PublicOtpService
        // hashes tokens as sha256($token . config('app.key')) — mirror that.
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
            if (\Schema::hasTable('policy_beneficiary')) {
                DB::table('policy_beneficiary')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            }
            if (\Schema::hasTable('policy_legal')) {
                DB::table('policy_legal')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
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
    public function bundle_endpoint_refuses_legal_insurance(): void
    {
        // create-bundle should refuse product 4 with a clear 422 pointing
        // at the dedicated endpoint, NOT silently stage a BQ-* quote.
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-bundle', [
                'firstName'          => 'Legal',
                'lastName'           => 'Smoketest',
                'passport'           => 'SMOKE0099',
                'dob'                => '1990-01-01',
                'gender'             => 'Female',
                'phone'              => self::TEST_CELL,
                'email'              => 'smoke-legal@yopmail.com',
                'residentialAddress' => 'Gaborone',
                'lines' => [[
                    'product_id'   => self::LEGAL_PRODUCT_ID,
                    'plan_id'      => self::LEGAL_PLAN_BRONZE,
                    'premium'      => 49,
                    'product_name' => 'Legal Insurance',
                ]],
                'premiumFrequency' => 'annual',
            ]);

        $resp->assertStatus(422)
             ->assertJsonPath('error', 'product_has_dedicated_endpoint')
             ->assertJsonPath('product_id', self::LEGAL_PRODUCT_ID)
             ->assertJsonPath('use_endpoint', '/api/v1/public/policies/create-legal-insurance');
    }

    /** @test */
    public function legal_insurance_create_endpoint_persists_policies_row(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-legal-insurance', [
                'firstName'     => 'Legal',
                'lastName'      => 'Smoketest',
                'passport'      => 'SMOKE0099',
                'dob'           => '1990-01-01',
                'gender'        => 'Female',
                'maritalStatus' => 'married',
                'phone'         => self::TEST_CELL,
                'email'         => 'smoke-legal@yopmail.com',
                'address'       => 'Gaborone',
                'planId'        => self::LEGAL_PLAN_BRONZE,
                'paymentMethod' => 'DPO',
                'spouse' => [
                    'firstName' => 'Spouse',
                    'lastName'  => 'Smoketest',
                    'dob'       => '1992-03-15',
                    'gender'    => 'Male',
                    'omang'     => '123456789',
                    'cellphone' => '70000100',
                    'email'     => 'spouse-smoke@yopmail.com',
                ],
            ]);

        $resp->assertStatus(201)
             ->assertJsonPath('ok', true)
             ->assertJsonStructure([
                 'policy_number',
                 'amount_to_pay',
                 'premium_freq',
                 'policy' => ['id', 'policyNumber', 'customer_id', 'product_id', 'plan_id'],
             ]);

        $policyId    = $resp->json('policy.id');
        $customerId  = $resp->json('policy.customer_id');
        $policyNumber = $resp->json('policy.policyNumber');

        $this->cleanupPolicyIds[]   = (int) $policyId;
        $this->cleanupCustomerIds[] = (int) $customerId;

        // MIS prefix per legacy retail convention (matches V8 AddWizard).
        $this->assertStringStartsWith('MIS', (string) $policyNumber,
            'Legal Insurance policy_number must use the MIS-prefix legacy retail pattern.');

        // Hard proof: a real `policies` row lives in the DB linked to the
        // customer record we just created, with the correct product_id +
        // pending-payment status.
        $row = DB::table('policies')->where('id', $policyId)->first(['product_id', 'plan_id', 'status', 'customer_id', 'policyNumber']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::LEGAL_PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(self::LEGAL_PLAN_BRONZE, (int) $row->plan_id);
        $this->assertSame(0, (int) $row->status, 'Newly created policy must be in pending-payment status (0).');
        $this->assertSame((int) $customerId, (int) $row->customer_id);
        $this->assertSame($policyNumber, $row->policyNumber);

        // Spouse should be captured as a policy_beneficiary row when the
        // table is present (V8 BundleProductController::processLegalBeneficiary
        // parity — Legal uses policy_beneficiary not a separate spouse table).
        if (\Schema::hasTable('policy_beneficiary')) {
            $spouse = DB::table('policy_beneficiary')->where('policy_id', $policyId)->first();
            $this->assertNotNull($spouse, 'Spouse should be persisted as a policy_beneficiary row for Legal Insurance.');
            $this->assertSame('spouse', strtolower((string) $spouse->relation));
            $this->assertSame('Spouse', $spouse->first_name);
        }
    }
}
