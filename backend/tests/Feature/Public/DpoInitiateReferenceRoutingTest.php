<?php

namespace Tests\Feature\Public;

use AlphaDirect\Services\PublicPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for PublicPaymentService::initiateDpo reference routing.
 *
 * The BQ- branch guard previously tested 'MIS' instead of 'BQ-', which:
 *   (a) left bundle-quote (BQ-) refs falling through to the Policy lookup,
 *       dead-ending at policy_not_found; and
 *   (b) mis-routed real MIS- policies into the bundle_quotes lookup,
 *       dead-ending at quote_not_found.
 *
 * These assertions exercise the resolution branches only — they all return
 * before the external DPO cURL call, so no gateway network hop is made.
 */
class DpoInitiateReferenceRoutingTest extends TestCase
{
    private const SESSION_CELL = '70000033';
    private const OTHER_CELL   = '70000034';

    private PublicPaymentService $payments;
    private array $cleanupQuoteNumbers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PublicPaymentService::class);
    }

    protected function tearDown(): void
    {
        if (!empty($this->cleanupQuoteNumbers) && \Schema::hasTable('bundle_quotes')) {
            DB::table('bundle_quotes')->whereIn('quote_number', $this->cleanupQuoteNumbers)->delete();
        }
        parent::tearDown();
    }

    private function sessionRow(string $cell = self::SESSION_CELL): array
    {
        return ['cellphone' => $cell];
    }

    /** @test */
    public function bundle_quote_reference_routes_to_bundle_quotes_lookup(): void
    {
        // An unknown BQ- ref must resolve via bundle_quotes → quote_not_found.
        // Pre-fix it fell through to the Policy branch → policy_not_found.
        $result = $this->payments->initiateDpo(
            $this->sessionRow(),
            'BQ-20260527-NOPE01',
            99.00,
            'policy_create',
            'routing-smoke@yopmail.com',
            '127.0.0.1'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('quote_not_found', $result['error'],
            'BQ- refs must be resolved against bundle_quotes, not the Policy table.');
    }

    /** @test */
    public function mis_policy_reference_routes_to_policy_lookup(): void
    {
        // A MIS- ref is a real (dedicated-endpoint) policy and must resolve via
        // the Policy table → policy_not_found for an unknown one. Pre-fix it was
        // mis-routed into bundle_quotes → quote_not_found.
        $result = $this->payments->initiateDpo(
            $this->sessionRow(),
            'MIS2026999999',
            99.00,
            'policy_create',
            'routing-smoke@yopmail.com',
            '127.0.0.1'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('policy_not_found', $result['error'],
            'MIS- refs are real policies and must resolve against the Policy table, not bundle_quotes.');
    }

    /** @test */
    public function bundle_quote_reads_the_seeded_bundle_quotes_row(): void
    {
        // Seed a real BQ- row whose cellphone differs from the session. Reaching
        // session_policy_mismatch proves the branch found the row in
        // bundle_quotes and compared its cellphone — i.e. it is genuinely the
        // bundle-quote path, not a coincidental not-found.
        $quoteNumber = 'BQ-20260527-SEED01';
        $this->cleanupQuoteNumbers[] = $quoteNumber;

        DB::table('bundle_quotes')->insert([
            'quote_number'     => $quoteNumber,
            'cellphone'        => self::OTHER_CELL, // deliberately != session
            'customer_payload' => json_encode(['firstName' => 'Bundle', 'lastName' => 'Quote', 'email' => 'bq@yopmail.com']),
            'lines_payload'    => json_encode([['product_id' => 1, 'plan_id' => 1, 'premium' => 99]]),
            'subtotal'         => 99.00,
            'total'            => 99.00,
            'status'           => 'pending_pay',
            'expires_at'       => Carbon::now()->addHours(2),
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);

        $result = $this->payments->initiateDpo(
            $this->sessionRow(self::SESSION_CELL),
            $quoteNumber,
            99.00,
            'policy_create',
            'routing-smoke@yopmail.com',
            '127.0.0.1'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('session_policy_mismatch', $result['error'],
            'A found BQ- row with a non-matching session phone must be rejected as a mismatch.');
    }
}
