<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Legal Insurance (product 4) create-policy smoke test.
 *
 *   1. Dedicated endpoint creates a real policy — create-legal-insurance
 *      returns 201 with a MIS-prefixed policyNumber and a `policies` row
 *      (product 4, plan 5 = P49_Legal, status 0 = pending payment).
 *   2. The main applicant's middle name, marital status and EMPLOYER details
 *      persist (customer.middleName + customer_profile.{maritalstatus,e_name,
 *      emp_no,emp_phone,salary_pay_date}) — the fields QA flagged missing.
 *   3. An unlisted plan id is rejected (validator pins planId to the
 *      product-4 allow-list).
 *
 * Seeds its own session token directly; cleans up in tearDown. Relies on the
 * product_plans row id=5 (product_id=4) shipping in graphite_dev.
 */
class LegalInsuranceCreateSmokeTest extends TestCase
{
    private const TEST_CELL  = '70000044';
    private const PRODUCT_ID = 4;
    private const PLAN       = 5; // P49_Legal

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
    public function legal_insurance_persists_policy_with_middle_name_marital_and_employer(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-legal-insurance', [
                'firstName'     => 'Mpho',
                'middleName'    => 'Oratile',
                'lastName'      => 'Legal',
                'passport'      => 'LGL0044',
                'dob'           => '1990-02-15',
                'gender'        => 'Female',
                'maritalStatus' => 'Married',
                'phone'         => self::TEST_CELL,
                'email'         => 'legal-smoke@yopmail.com',
                'address'       => 'Gaborone',
                'planId'        => self::PLAN,
                'paymentMethod' => 'DPO',
                'employer'      => [
                    'employerName'  => 'Alpha Direct',
                    'employeeNo'    => 'EMP12345',
                    'employerTel'   => '3901234',
                    'salaryPayDate' => '2026-06-25',
                ],
                'spouse' => [
                    'firstName' => 'Kabelo',
                    'lastName'  => 'Legal',
                    'dob'       => '1989-09-09',
                    'gender'    => 'Male',
                    'cellphone' => '70000045',
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
            'Legal Insurance policy_number must use the MIS-prefix legacy retail pattern.');
        $this->assertGreaterThan(0, (float) $resp->json('amount_to_pay'), 'Rated premium must be positive.');

        $row = DB::table('policies')->where('id', $policyId)->first(['product_id', 'plan_id', 'status']);
        $this->assertNotNull($row, 'policies row was not persisted.');
        $this->assertSame(self::PRODUCT_ID, (int) $row->product_id);
        $this->assertSame(self::PLAN, (int) $row->plan_id);
        $this->assertSame(0, (int) $row->status, 'Newly created policy must be in pending-payment status (0).');

        // BUG: Middle name → customer.middleName.
        $cust = DB::table('customer')->where('id', $customerId)->first(['middleName']);
        $this->assertSame('Oratile', (string) $cust->middleName, 'Middle name should persist.');

        // BUG: Marital status + Employer Details → customer_profile.
        if (\Schema::hasTable('customer_profile')) {
            $profile = DB::table('customer_profile')->where('customer_id', $customerId)->first();
            $this->assertNotNull($profile, 'customer_profile row should be persisted.');
            $this->assertSame('Married', (string) $profile->maritalstatus, 'Marital status should persist.');
            $this->assertSame('Alpha Direct', (string) $profile->e_name, 'Employer name should persist.');
            $this->assertSame('EMP12345', (string) $profile->emp_no, 'Employee number should persist.');
            $this->assertSame('3901234', (string) $profile->emp_phone, 'Employer tel should persist.');
            $this->assertSame('2026-06-25', (string) $profile->salary_pay_date, 'Salary pay date should persist.');
        }

        // Spouse captured as the legal beneficiary.
        if (\Schema::hasTable('policy_beneficiary')) {
            $bene = DB::table('policy_beneficiary')->where('policy_id', $policyId)->first();
            $this->assertNotNull($bene, 'Spouse should be persisted as a policy_beneficiary row.');
            $this->assertSame('spouse', strtolower((string) $bene->relation));
            $this->assertSame('Kabelo', $bene->first_name);
        }
    }

    /** @test */
    public function legal_insurance_rejects_unlisted_plan(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/policies/create-legal-insurance', [
                'firstName'     => 'Mpho',
                'lastName'      => 'Legal',
                'passport'      => 'LGL0044',
                'dob'           => '1990-02-15',
                'gender'        => 'Female',
                'phone'         => self::TEST_CELL,
                'planId'        => 999,
                'paymentMethod' => 'DPO',
            ]);

        $resp->assertStatus(422);
    }
}
