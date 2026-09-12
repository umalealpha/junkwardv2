<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke test for the customer "Me" portal documents surface:
 *   GET  /api/v1/public/me/policies/{policyNumber}/documents
 *   POST /api/v1/public/me/policies/{policyNumber}/resend-documents
 *
 * The happy path of resend-documents calls through to
 * DocumentController::sendPolicyDocument(), which fires a real WhatsApp
 * send (plain `new WhatsAppController()`, not container-resolved, so it
 * can't be swapped for a test double) and a real SendMail event. This
 * suite intentionally only exercises the auth/ownership/validation guard
 * rails that return *before* that call — every case here is a fast-fail
 * branch, so no external notification is ever triggered. Covering the
 * actual send is left to manual/staging verification.
 */
class MeResendPolicyDocumentsSmokeTest extends TestCase
{
    private const TEST_CELL  = '70000033';
    private const OTHER_CELL = '70000034';
    private const PRODUCT_ID = 3; // Motor Comprehensive ("MIS")

    private string $sessionToken;
    private array $cleanupCustomerIds = [];
    private array $cleanupPolicyIds = [];
    private array $cleanupDocumentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionToken = Str::random(64);
        DB::table('public_session_tokens')->insert([
            'token_hash' => hash('sha256', $this->sessionToken . config('app.key')),
            'cellphone'  => self::TEST_CELL,
            'purpose'    => 'customer_auth',
            'expires_at' => Carbon::now()->addMinutes(10),
            'ip'         => '127.0.0.1',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('public_session_tokens')->where('cellphone', self::TEST_CELL)->delete();

        if (!empty($this->cleanupDocumentIds)) {
            DB::table('documents')->whereIn('id', $this->cleanupDocumentIds)->delete();
        }
        if (!empty($this->cleanupPolicyIds)) {
            DB::table('policies')->whereIn('id', $this->cleanupPolicyIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::table('customer')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        parent::tearDown();
    }

    private function makeCustomer(string $cellphone, ?string $email): int
    {
        $id = DB::table('customer')->insertGetId([
            'firstName'  => 'Doc',
            'lastName'   => 'Smoke',
            'email'      => $email,
            'cellphone'  => $cellphone,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function makePolicy(int $customerId, ?string $policyDocument = null): array
    {
        $policyNumber = 'MIS' . Carbon::now()->format('Y') . random_int(100000, 999999);
        $id = DB::table('policies')->insertGetId([
            'customer_id'    => $customerId,
            'product_id'     => self::PRODUCT_ID,
            'policyNumber'   => $policyNumber,
            'status'         => 1,
            'premium'        => 250.00,
            'policyDocument' => $policyDocument,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);
        $this->cleanupPolicyIds[] = $id;
        return [$id, $policyNumber];
    }

    /** @test */
    public function documents_requires_auth(): void
    {
        $this->getJson('/api/v1/public/me/policies/MIS2026000001/documents')
            ->assertStatus(401);
    }

    /** @test */
    public function resend_documents_requires_auth(): void
    {
        $this->postJson('/api/v1/public/me/policies/MIS2026000001/resend-documents')
            ->assertStatus(401);
    }

    /** @test */
    public function documents_404s_for_a_policy_the_session_customer_does_not_own(): void
    {
        // Our session customer must exist too, so the 404 below is proven to
        // come from the ownership check, not from customer_not_found.
        $this->makeCustomer(self::TEST_CELL, 'doc-smoke@yopmail.com');
        $otherCustomerId = $this->makeCustomer(self::OTHER_CELL, 'someone-else@yopmail.com');
        [, $policyNumber] = $this->makePolicy($otherCustomerId);

        $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->getJson("/api/v1/public/me/policies/{$policyNumber}/documents")
            ->assertStatus(404)
            ->assertJsonPath('error', 'policy_not_found');
    }

    /** @test */
    public function resend_documents_404s_for_a_policy_the_session_customer_does_not_own(): void
    {
        // Our session customer *does* exist and has an email — proves the
        // 404 comes from the ownership check, not the email guard.
        $this->makeCustomer(self::TEST_CELL, 'doc-smoke@yopmail.com');
        $otherCustomerId = $this->makeCustomer(self::OTHER_CELL, 'someone-else@yopmail.com');
        [, $policyNumber] = $this->makePolicy($otherCustomerId);

        $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson("/api/v1/public/me/policies/{$policyNumber}/resend-documents")
            ->assertStatus(404)
            ->assertJsonPath('error', 'policy_not_found');
    }

    /** @test */
    public function resend_documents_returns_422_when_customer_has_no_email_on_file(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL, null);
        [, $policyNumber] = $this->makePolicy($customerId);

        $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson("/api/v1/public/me/policies/{$policyNumber}/resend-documents")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function documents_lists_the_policy_schedule_and_matching_wording_doc(): void
    {
        $customerId = $this->makeCustomer(self::TEST_CELL, 'doc-smoke@yopmail.com');
        [, $policyNumber] = $this->makePolicy($customerId, 'PolicyDocument/smoke-schedule.pdf');

        $this->cleanupDocumentIds[] = DB::table('documents')->insertGetId([
            'name'       => 'Motor Comprehensive Wording',
            'link'       => 'Document/Wording/motor-comp/smoke-wording.pdf',
            'product_id' => self::PRODUCT_ID,
            'status'     => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->getJson("/api/v1/public/me/policies/{$policyNumber}/documents");

        $resp->assertStatus(200)
             ->assertJsonStructure(['documents' => [['name', 'url']], 'last_sent']);

        $names = collect($resp->json('documents'))->pluck('name');
        $this->assertTrue($names->contains('Policy Schedule'));
        $this->assertTrue($names->contains('Motor Comprehensive Wording'));
    }
}
