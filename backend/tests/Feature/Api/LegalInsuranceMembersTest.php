<?php

namespace Tests\Feature\Api;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Members / Beneficiaries parity for Legal Insurance (product_id=4) with
 * Accidental Death Insurance (product_id=1).
 *
 * PolicyController::members() and PolicyCreateController::add/update/delete
 * Member()/Beneficiary() were already product-agnostic — they key off
 * policy_id only. The actual gap was data: products.has_member was 0 for
 * Legal Insurance vs 1 for ADI, and the Policy Detail page only renders the
 * "Members / Beneficiaries" tab when `policy.hasMember ||
 * policy.product.hasMember` is true. Migration
 * 2026_06_18_100000_enable_has_member_for_legal_insurance flips the
 * product-level flag; this test exercises the now-reachable endpoints
 * end-to-end on a Legal Insurance policy.
 */
class LegalInsuranceMembersTest extends TestCase
{
    private const PRODUCT_ID_LEGAL = 4;

    private ?int $customerId = null;
    private ?int $policyId = null;

    protected function tearDown(): void
    {
        if ($this->policyId) {
            DB::table('policy_beneficiary')->where('policy_id', $this->policyId)->delete();
            DB::table('policy_members')->where('policy_id', $this->policyId)->delete();
            DB::table('audits')->where('auditable_id', $this->policyId)
                ->where('auditable_type', 'like', '%Policy')->delete();
            DB::table('activity_log')->where('subject_id', $this->policyId)->delete();
            DB::table('policies')->where('id', $this->policyId)->delete();
        }
        if ($this->customerId) {
            DB::table('customer')->where('id', $this->customerId)->delete();
        }
        parent::tearDown();
    }

    public function test_legal_insurance_product_has_member_flag_matches_adi(): void
    {
        $legal = DB::table('products')->where('id', self::PRODUCT_ID_LEGAL)->first(['has_member']);
        $adi   = DB::table('products')->where('id', 1)->first(['has_member']);

        $this->assertNotNull($legal, 'Legal Insurance product row missing');
        $this->assertNotNull($adi, 'ADI product row missing');
        $this->assertEquals((bool) $adi->has_member, true, 'Expected ADI to have has_member=1 (sanity check on the reference behaviour)');
        $this->assertEquals((bool) $adi->has_member, (bool) $legal->has_member, 'Legal Insurance has_member should match ADI');
    }

    public function test_beneficiary_can_be_added_and_fetched_on_a_legal_insurance_policy(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($user, ['*']);

        $customer = Customer::create([
            'firstName' => 'LegalBene',
            'lastName'  => 'Test',
            'email'     => 'legal-bene-test+'.uniqid().'@example.com',
            'cellphone' => '71000001',
        ]);
        $this->customerId = $customer->id;

        $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
        $policyRow = array_intersect_key([
            'customer_id'  => $customer->id,
            'product_id'   => self::PRODUCT_ID_LEGAL,
            'policyNumber' => 'MISTEST' . uniqid(),
            'status'       => 0,
            'premium'      => 100,
            'has_member'   => 0, // starts without a beneficiary, like an unmarried Legal applicant
            'created_at'   => now(),
            'updated_at'   => now(),
        ], array_flip($policiesCols));
        $this->policyId = DB::table('policies')->insertGetId($policyRow);

        // Tab visibility no longer depends on this policy's own has_member —
        // the product-level flag (asserted above) makes it reachable. Once
        // reachable, the existing generic endpoint is used exactly like ADI.
        $resp = $this->postJson("/api/v1/policies/{$this->policyId}/beneficiaries", [
            'first_name' => 'Spouse',
            'last_name'  => 'Tester',
            'relation'   => 'spouse',
            'gender'     => 'Female',
            'dob'        => '1990-01-01',
            'payment'    => 100,
        ]);
        $resp->assertStatus(201);

        $list = $this->getJson("/api/v1/policies/{$this->policyId}/members");
        $list->assertStatus(200);
        $beneficiaries = $list->json('data.beneficiaries');
        $this->assertCount(1, $beneficiaries);
        $this->assertSame('Spouse', $beneficiaries[0]['firstName']);
        $this->assertSame('Tester', $beneficiaries[0]['lastName']);

        // addBeneficiary() flips has_member back on, same as it would for ADI.
        $this->assertEquals(1, Policy::find($this->policyId)->has_member);
    }
}
