<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the BQ- branch in PublicPaymentService::initiateDpo.
 *
 * Before 2026-05-27 initiateDpo resolved only two reference shapes:
 *   - MQ-…  → motor_quotes lookup
 *   - else  → Policy lookup by policyNumber
 * A bundle quote (BQ-…) has no Policy yet at initiate time (the Policy rows
 * are only materialised after the DPO IPN), so it fell into the else branch
 * and dead-ended at policy_not_found (HTTP 404) — retail bundle payments
 * could never start. The fix adds a BQ- branch that resolves the
 * bundle_quotes row, mirroring the MQ- path.
 *
 * These three tests pin the branch behaviour without depending on a live
 * DPO gateway: they assert on the resolution outcome (which error we get),
 * not on a real createToken round-trip.
 *
 * Tests seed their own session token + bundle_quotes rows directly and clean
 * up in tearDown so they're repeatable against the dev DB.
 */
class BundleQuoteDpoInitiateBranchTest extends TestCase
{
    private const TEST_CELL  = '70000099'; // 8-digit BW local for the verified session
    private const OTHER_CELL = '70000200'; // a different cellphone — used for the mismatch case

    private string $sessionToken;
    private array $cleanupQuoteNumbers = [];

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
        if (!empty($this->cleanupQuoteNumbers)) {
            DB::table('bundle_quotes')->whereIn('quote_number', $this->cleanupQuoteNumbers)->delete();
        }
        parent::tearDown();
    }

    /**
     * Insert a minimal bundle_quotes row and return its BQ- quote number.
     */
    private function seedBundleQuote(string $cellphone): string
    {
        $quoteNumber = 'BQ-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        DB::table('bundle_quotes')->insert([
            'quote_number'      => $quoteNumber,
            'cellphone'         => $cellphone,
            'auth_token_hash'   => hash('sha256', $cellphone . config('app.key')),
            'customer_payload'  => json_encode([
                'firstName' => 'Bundle',
                'lastName'  => 'Smoketest',
                'email'     => 'smoke-bundle@yopmail.com',
            ]),
            'lines_payload'     => json_encode([
                ['product_id' => 3, 'plan_id' => 1, 'premium' => 49],
                ['product_id' => 6, 'plan_id' => 1, 'premium' => 50],
            ]),
            'subtotal'          => 99,
            'discount_rate_pct' => 0,
            'discount_amount'   => 0,
            'total'             => 99,
            'status'            => 'pending_pay',
            'expires_at'        => Carbon::now()->addDays(7),
            'client_ip'         => '127.0.0.1',
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);
        $this->cleanupQuoteNumbers[] = $quoteNumber;
        return $quoteNumber;
    }

    private function initiate(string $policyNumber): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->postJson('/api/v1/public/payments/dpo/initiate', [
                'policy_number' => $policyNumber,
                'amount'        => 99,
                'context'       => 'policy_create',
                'email'         => 'smoke-bundle@yopmail.com',
            ]);
    }

    /** @test */
    public function bq_quote_resolves_past_the_policy_lookup(): void
    {
        $quoteNumber = $this->seedBundleQuote(self::TEST_CELL);

        $resp  = $this->initiate($quoteNumber);
        $error = $resp->json('error');

        // The whole point of the fix: a BQ- ref on a matching session must NOT
        // dead-end at the Policy lookup. Any outcome past resolution (ok, or a
        // gateway_* error when COMPANY_TOKEN isn't configured in this env) is
        // acceptable — what must never happen again is policy_not_found / 404.
        $this->assertNotSame('policy_not_found', $error,
            'BQ- ref still dead-ends at the Policy lookup — the BQ- branch is not resolving the bundle_quotes row.');
        $this->assertNotSame('quote_not_found', $error,
            'Seeded bundle_quotes row was not found by the BQ- branch.');
        $this->assertNotSame('session_policy_mismatch', $error,
            'Matching session cellphone was wrongly rejected.');
        $this->assertNotSame(404, $resp->status());
    }

    /** @test */
    public function bq_quote_for_a_different_session_is_rejected(): void
    {
        // Quote belongs to OTHER_CELL; the verified session is TEST_CELL.
        $quoteNumber = $this->seedBundleQuote(self::OTHER_CELL);

        $resp = $this->initiate($quoteNumber);

        $resp->assertStatus(403)
             ->assertJsonPath('error', 'session_policy_mismatch');
    }

    /** @test */
    public function unknown_bq_ref_returns_quote_not_found(): void
    {
        $resp = $this->initiate('BQ-20260101-ZZZZZZ');

        $resp->assertStatus(400)
             ->assertJsonPath('error', 'quote_not_found');
    }
}
