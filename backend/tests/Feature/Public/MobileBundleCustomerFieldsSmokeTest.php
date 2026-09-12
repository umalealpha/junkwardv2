<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile & Electronic Device Insurance (product 5) — customer-identity smoke
 * test for the /create-bundle path the start.alphadirect.co.bw form uses.
 *
 * QA flagged Middle Name + Marital Status as "column is missing" for the
 * Mobile/Electronic product. The columns exist (customer.middleName,
 * customer_profile.maritalstatus) and PublicBundleCreateController already
 * persists them — the gap was the FE never sending them. This locks in that
 * a /create-bundle for product 5 carries both fields through to the DB.
 *
 * Seeds its own session token; cleans up in tearDown.
 */
class MobileBundleCustomerFieldsSmokeTest extends TestCase
{
    private const TEST_CELL  = '70000055';
    private const PRODUCT_ID = 5;
    private const PLAN       = 9; // P49_cellphone_insurance_bronze

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
    public function bundle_create_persists_middle_name_and_marital_status_for_mobile(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-bundle', [
                'firstName'          => 'Lesedi',
                'middleName'         => 'Boipelo',
                'lastName'           => 'Mobelec',
                'passport'           => 'MOBELEC55',
                'dob'                => '1994-01-15',
                'gender'             => 'Female',
                'maritalStatus'      => 'Single',
                'phone'              => self::TEST_CELL,
                'email'              => 'mob-bundle-smoke@yopmail.com',
                'residentialAddress' => 'Gaborone',
                'premiumFrequency'   => 'annual',
                'lines' => [[
                    'product_id'   => self::PRODUCT_ID,
                    'plan_id'      => self::PLAN,
                    'premium'      => 49,
                    'product_name' => 'Mobile & Electronic Device Insurance',
                ]],
                'devices' => [[
                    'deviceType' => 'Cellphone',
                    'imei'       => '356938035643809',
                    'make'       => 'Samsung',
                    'model'      => 'Galaxy S21',
                    'value'      => 8000,
                ]],
            ]);

        $resp->assertStatus(201)
             ->assertJsonPath('ok', true)
             ->assertJsonStructure(['policy_number', 'amount_to_pay']);

        $policyNumber = (string) $resp->json('policy_number');
        $this->assertNotSame('', $policyNumber, 'A policy_number must be returned.');

        // The bundle response only carries policy_number — resolve the row.
        $policy = DB::table('policies')->where('policyNumber', $policyNumber)
            ->first(['id', 'customer_id', 'product_id']);
        $this->assertNotNull($policy, 'policies row should exist for the returned policy_number.');
        $this->assertSame(self::PRODUCT_ID, (int) $policy->product_id);

        $this->cleanupPolicyIds[]   = (int) $policy->id;
        $this->cleanupCustomerIds[] = (int) $policy->customer_id;

        // BUG: Middle name → customer.middleName.
        $cust = DB::table('customer')->where('id', $policy->customer_id)->first(['middleName']);
        $this->assertSame('Boipelo', (string) $cust->middleName, 'Middle name should persist to customer.middleName.');

        // BUG: Marital status → customer_profile.maritalstatus.
        if (\Schema::hasTable('customer_profile')) {
            $profile = DB::table('customer_profile')->where('customer_id', $policy->customer_id)->first(['maritalstatus']);
            $this->assertNotNull($profile, 'customer_profile row should be persisted.');
            $this->assertSame('Single', (string) $profile->maritalstatus, 'Marital status should persist.');
        }
    }
}
