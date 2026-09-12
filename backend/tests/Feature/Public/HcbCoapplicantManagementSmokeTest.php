<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke test for customer self-service ("START") Hospital Cashback
 * Insurance (product_id=9) co-applicant management:
 *   POST/PUT/DELETE /api/v1/public/policies/{policyNumber}/coapplicants[/{id}]
 *
 * Covers the HcbCoapplicantService contract end-to-end against the real
 * hospital_Cashback_coapplicants table: ownership guard, non-HCB-policy
 * guard, max-1-spouse / max-6-children limits, and premium recalculation
 * on add/update/delete. The schedule-PDF regeneration and RealPay sync
 * inside the service are exercised for real too (no external send — PDF
 * generation only writes to S3/`policies`, and RealPay sync only fires
 * when policy.billing === 'RealPay', which these test policies never set).
 */
class HcbCoapplicantManagementSmokeTest extends TestCase
{
    private const TEST_CELL  = '70000044';
    private const OTHER_CELL = '70000045';
    private const HCB_PRODUCT_ID = 9;

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
            'purpose'    => 'policy_edit',
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
            DB::table('hospital_Cashback_coapplicants')->whereIn('policy_id', $this->cleanupPolicyIds)->delete();
            DB::table('audits')->where('auditable_type', 'like', '%Policy')->whereIn('auditable_id', $this->cleanupPolicyIds)->delete();
            DB::table('activity_log')->where('subject_type', 'like', '%Policy')->whereIn('subject_id', $this->cleanupPolicyIds)->delete();
            DB::table('policies')->whereIn('id', $this->cleanupPolicyIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::table('customer')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        parent::tearDown();
    }

    private function makeCustomer(string $cellphone): int
    {
        $id = DB::table('customer')->insertGetId([
            'firstName'  => 'Hcb',
            'lastName'   => 'Smoke',
            'email'      => 'hcb-smoke@yopmail.com',
            'cellphone'  => $cellphone,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function makePolicy(int $customerId, int $productId = self::HCB_PRODUCT_ID): array
    {
        $policyNumber = 'MIS' . Carbon::now()->format('Y') . random_int(100000, 999999);
        $id = DB::table('policies')->insertGetId([
            'customer_id'    => $customerId,
            'product_id'     => $productId,
            'plan_id'        => 19,
            'policyNumber'   => $policyNumber,
            'status'         => 1,
            'premium'        => 99.00,
            'annual_premium' => 99.00 * 12,
            'first_premium'  => 99.00,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);
        $this->cleanupPolicyIds[] = $id;
        return [$id, $policyNumber];
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->sessionToken];
    }

    /** @test */
    public function add_requires_auth(): void
    {
        $this->postJson('/api/v1/public/policies/MIS2026000001/coapplicants', [])
            ->assertStatus(401);
    }

    /** @test */
    public function add_404s_for_a_policy_the_session_customer_does_not_own(): void
    {
        $this->makeCustomer(self::TEST_CELL);
        $otherCustomerId = $this->makeCustomer(self::OTHER_CELL);
        [, $policyNumber] = $this->makePolicy($otherCustomerId);

        $this->withHeaders($this->authHeader())
            ->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
                'relation' => 'spouse', 'firstName' => 'A', 'lastName' => 'B', 'gender' => 'Male', 'dob' => '1990-01-01',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function add_rejects_a_non_hcb_policy(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL);
        [, $policyNumber] = $this->makePolicy($customerId, productId: 3); // Motor Comprehensive

        $this->withHeaders($this->authHeader())
            ->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
                'relation' => 'spouse', 'firstName' => 'A', 'lastName' => 'B', 'gender' => 'Male', 'dob' => '1990-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'not_hospital_cashback_policy');
    }

    /** @test */
    public function add_spouse_and_children_recalculates_premium_on_the_canonical_table(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL);
        [$policyId, $policyNumber] = $this->makePolicy($customerId);

        $resp = $this->withHeaders($this->authHeader())
            ->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
                'relation' => 'spouse', 'firstName' => 'Partner', 'lastName' => 'Smoke', 'gender' => 'Female', 'dob' => '1990-01-01',
            ]);
        $resp->assertStatus(200)->assertJsonPath('ok', true);
        $this->assertEquals(99 + 89, (float) $resp->json('premium'));

        $resp = $this->withHeaders($this->authHeader())
            ->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
                'relation' => 'child', 'firstName' => 'Kid', 'lastName' => 'Smoke', 'gender' => 'Male', 'dob' => '2015-01-01',
            ]);
        $resp->assertStatus(200);
        $this->assertEquals(99 + 89 + 49, (float) $resp->json('premium'));

        // Persisted on the canonical table, not policy_members.
        $this->assertSame(2, DB::table('hospital_Cashback_coapplicants')->where('policy_id', $policyId)->count());
        $this->assertSame(0, DB::table('policy_members')->where('policy_id', $policyId)->count());

        // policies.premium actually updated (what InvoiceGenerator reads live).
        $this->assertEquals(99 + 89 + 49, (float) DB::table('policies')->where('id', $policyId)->value('premium'));
    }

    /** @test */
    public function second_spouse_is_rejected(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL);
        [, $policyNumber] = $this->makePolicy($customerId);

        $this->withHeaders($this->authHeader())->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
            'relation' => 'spouse', 'firstName' => 'One', 'lastName' => 'Smoke', 'gender' => 'Female', 'dob' => '1990-01-01',
        ])->assertStatus(200);

        $this->withHeaders($this->authHeader())->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
            'relation' => 'spouse', 'firstName' => 'Two', 'lastName' => 'Smoke', 'gender' => 'Female', 'dob' => '1991-01-01',
        ])->assertStatus(422)->assertJsonPath('error', 'max_spouse_exceeded');
    }

    /** @test */
    public function seventh_child_is_rejected(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL);
        [, $policyNumber] = $this->makePolicy($customerId);

        for ($i = 1; $i <= 6; $i++) {
            $this->withHeaders($this->authHeader())->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
                'relation' => 'child', 'firstName' => "Kid{$i}", 'lastName' => 'Smoke', 'gender' => 'Male', 'dob' => '2015-01-01',
            ])->assertStatus(200);
        }

        $this->withHeaders($this->authHeader())->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
            'relation' => 'child', 'firstName' => 'Kid7', 'lastName' => 'Smoke', 'gender' => 'Male', 'dob' => '2015-01-01',
        ])->assertStatus(422)->assertJsonPath('error', 'max_children_exceeded');
    }

    /** @test */
    public function update_and_delete_recalculate_premium(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL);
        [$policyId, $policyNumber] = $this->makePolicy($customerId);

        $addResp = $this->withHeaders($this->authHeader())->postJson("/api/v1/public/policies/{$policyNumber}/coapplicants", [
            'relation' => 'child', 'firstName' => 'Kid', 'lastName' => 'Smoke', 'gender' => 'Male', 'dob' => '2015-01-01',
        ]);
        $addResp->assertStatus(200);
        $coappId = (int) collect($addResp->json('coapplicants'))->last()['id'];

        // Update: child -> spouse. Premium should reflect spouse rate.
        $updateResp = $this->withHeaders($this->authHeader())
            ->putJson("/api/v1/public/policies/{$policyNumber}/coapplicants/{$coappId}", ['relation' => 'spouse']);
        $updateResp->assertStatus(200);
        $this->assertEquals(99 + 89, (float) $updateResp->json('premium'));

        // Delete: back to policy-holder-only premium.
        $deleteResp = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/v1/public/policies/{$policyNumber}/coapplicants/{$coappId}");
        $deleteResp->assertStatus(200);
        $this->assertEquals(99, (float) $deleteResp->json('premium'));
        $this->assertSame(0, DB::table('hospital_Cashback_coapplicants')->where('policy_id', $policyId)->count());
    }
}
