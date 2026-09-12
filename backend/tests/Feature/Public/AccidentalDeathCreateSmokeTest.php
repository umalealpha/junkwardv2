<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Accidental Death Insurance (product 1) create-policy smoke test.
 *
 * Mirrors ThirdPartyCarCreateSmokeTest / BundleQuotePaymentInitiateSmokeTest:
 *   1. Bundle gate refuses product 1 — create-bundle returns 422
 *      product_has_dedicated_endpoint pointing at create-accidental-death.
 *   2. Dedicated endpoint creates a real policy — create-accidental-death
 *      returns 201 with a MIS-prefixed policyNumber, a `policies` row lands
 *      (product 1, status 0 = pending payment), and the beneficiary is
 *      persisted to policy_beneficiary.
 *   3. Validation rejects a payload with no beneficiary (422).
 *
 * Self-contained: seeds its own session token AND a product-1 ad_pricings
 * band (graphite_dev ships none), then cleans both up in tearDown.
 */
class AccidentalDeathCreateSmokeTest extends TestCase
{
    private const TEST_CELL = '70000011';
    private const PRODUCT_ID = 1;

    private string $sessionToken;
    private array $cleanupCustomerIds = [];
    private array $cleanupPolicyIds = [];
    private array $cleanupPricingIds = [];

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

        // ad_pricings band covering the test applicant (33yo). Both genders,
        // full adult age range. Premium values are fixtures, not live rates —
        // the smoke test only cares that rating yields a positive premium.
        foreach (['Male', 'Female'] as $g) {
            $this->cleanupPricingIds[] = DB::table('ad_pricings')->insertGetId([
                'gender'          => $g,
                'product'         => (string) self::PRODUCT_ID,
                'age_from'        => 18,
                'age_to'          => 70,
                'main'            => 100.00,
                'adult_dependent' => 80.00,
                'child_dependent' => 40.00,
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::table('public_session_tokens')->where('cellphone', self::TEST_CELL)->delete();

        if (!empty($this->cleanupPolicyIds)) {
            if (\Schema::hasTable('policy_beneficiary')) {
                DB::table('policy_beneficiary')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            }
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
        if (!empty($this->cleanupPricingIds)) {
            DB::table('ad_pricings')->whereIn('id', $this->cleanupPricingIds)->delete();
        }

        parent::tearDown();
    }

    /** @test */
    public function bundle_endpoint_refuses_accidental_death(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-bundle', [
                'firstName'          => 'Kago',
                'lastName'           => 'Acd',
                'passport'           => 'ACD0011',
                'dob'                => '1992-04-10',
                'gender'             => 'Male',
                'phone'              => self::TEST_CELL,
                'email'              => 'acd-smoke@yopmail.com',
                'residentialAddress' => 'Gaborone',
                'lines' => [[
                    'product_id'   => self::PRODUCT_ID,
                    'plan_id'      => 1,
                    'premium'      => 100,
                    'product_name' => 'Accidental Death Insurance',
                ]],
                'premiumFrequency' => 'monthly',
            ]);

        $resp->assertStatus(422)
             ->assertJsonPath('error', 'product_has_dedicated_endpoint')
             ->assertJsonPath('product_id', self::PRODUCT_ID)
             ->assertJsonPath('use_endpoint', '/api/v1/public/policies/create-accidental-death');
    }

    /** @test */
    public function accidental_death_create_endpoint_persists_policy_and_beneficiary(): void
    {
        $startDate   = Carbon::now()->addDays(3)->format('Y-m-d');
        $billingDate = Carbon::now()->addDays(10)->format('Y-m-d');

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-accidental-death', [
                'firstName'        => 'Kago',
                'middleName'       => 'Tebogo',
                'lastName'         => 'Acd',
                'passport'         => 'ACD0011',
                'dob'              => '1992-04-10',
                'gender'           => 'Male',
                'maritalStatus'    => 'Married',
                'phone'            => self::TEST_CELL,
                'email'            => 'acd-smoke@yopmail.com',
                'address'          => 'Gaborone',
                'paymentMethod'    => 'DPO',
                // New AD form fields (BUG-007/008/009).
                'policyStartDate'  => $startDate,
                'billingDate'      => $billingDate,
                'assistedByBroker' => true,
                'brokerAgentId'    => '4271',
                'brokerAgentPin'   => '1234',
                'beneficiaries' => [[
                    'firstName'  => 'Naledi',
                    'lastName'   => 'Acd',
                    'dob'        => '1994-06-20',
                    'gender'     => 'Female',
                    'relation'   => 'spouse',
                    'payment'    => 'Cash',
                    'percentage' => 100, // BUG-006: proceeds share
                    'omang'      => '987654321',
                    'cellphone'  => '70000012',
                ]],
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
            'Accidental Death policy_number must use the MIS-prefix legacy retail pattern.');

        // Real policies row, correct product + pending-payment status, and
        // the new start/billing/broker fields persisted from the payload.
        $row = DB::table('policies')->where('id', $policyId)
            ->first(['product_id', 'status', 'premium', 'agent_id', 'term_start_date', 'billing_day']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(0, (int) $row->status, 'Newly created policy must be in pending-payment status (0).');
        $this->assertGreaterThan(0, (float) $row->premium, 'Rated premium must be positive.');

        // BUG-007: broker-assisted sale → agent id persisted to policies.agent_id.
        $this->assertSame('4271', (string) $row->agent_id, 'Broker Agent ID should land on policies.agent_id.');
        // BUG-008: requested policy start date drives term_start_date.
        $this->assertStringStartsWith($startDate, (string) $row->term_start_date,
            'term_start_date should honour the requested policyStartDate.');
        // BUG-009: billing date drives billing_day (day-of-month).
        $this->assertSame((int) Carbon::parse($billingDate)->format('d'), (int) $row->billing_day,
            'billing_day should be derived from the chosen billingDate.');

        // Beneficiary (payee on death) captured in policy_beneficiary, with
        // the BUG-006 proceeds percentage.
        if (\Schema::hasTable('policy_beneficiary')) {
            $bene = DB::table('policy_beneficiary')->where('policy_id', $policyId)->first();
            $this->assertNotNull($bene, 'Beneficiary should be persisted as a policy_beneficiary row.');
            $this->assertSame('spouse', strtolower((string) $bene->relation));
            $this->assertSame('Naledi', $bene->first_name);
            if (\Schema::hasColumn('policy_beneficiary', 'percentage')) {
                $this->assertSame(100.0, (float) $bene->percentage,
                    'Beneficiary proceeds percentage should persist (BUG-006).');
            }
        }
    }

    /** @test */
    public function accidental_death_rejects_missing_beneficiary(): void
    {
        // beneficiaries is required|min:1 — a payload without it must 422.
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-accidental-death', [
                'firstName'     => 'Kago',
                'lastName'      => 'Acd',
                'passport'      => 'ACD0011',
                'dob'           => '1992-04-10',
                'gender'        => 'Male',
                'phone'         => self::TEST_CELL,
                'paymentMethod' => 'DPO',
            ]);

        $resp->assertStatus(422);
    }
}
