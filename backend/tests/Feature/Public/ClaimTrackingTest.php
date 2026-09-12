<?php

namespace Tests\Feature\Public;

use AlphaDirect\Models\ClaimAccessLink;
use AlphaDirect\Models\ClaimTrackingOtp;
use AlphaDirect\Models\ClaimTrackingSession;
use AlphaDirect\Services\ClaimTrackingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Claimant self-service tracking (Claims Tracker -> Graphite Phase 2).
 *
 * Runs hermetically on in-memory sqlite (the default + mysql_system connections
 * are pointed at :memory: in setUp) so it does NOT need a live MariaDB. We build
 * the minimal legacy tables the service reads (claims / customer /
 * claim_tracker_workflow) plus the four Phase-2 tables, then drive the service
 * and the public HTTP surface.
 *
 * Covers the spec's checklist:
 *   - OTP issue (creates a hashed, single-use code; delivery reused)
 *   - OTP verify happy path (mints a session scoped to ONE claim)
 *   - Wrong OTP (generic error, attempt counter increments)
 *   - Expired link + expired OTP (fail-closed)
 *   - Non-enumeration (unknown reference -> generic success, no OTP row)
 *   - Rate-limit on the public request-otp route
 *   - Feature flag OFF -> whole surface 404s
 *   - Status payload contains NO financial / internal / PII fields
 */
class ClaimTrackingTest extends TestCase
{
    private ClaimTrackingService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            // IntegrationSettings reads mysql_system first; point it at a throwaway
            // in-memory DB (no integration_settings table) so it falls back to the
            // config flag below without any network call.
            'database.connections.mysql_system' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            // Flag ON by default for the flow tests; the flag-off test overrides it.
            'services.claimant_tracking.enabled' => true,
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            // Rate limiting is a CACHE concern, and only the DATABASE was isolated
            // here — the cache was left on whatever .env says, redis by default.
            // The request-otp route is throttled to 5/min per IP, so from the sixth
            // call in this file onward every test received 429 instead of the
            // behaviour it was asserting: the OTP row was never written (null), the
            // verify never succeeded (false), the attempt counter never moved (0),
            // and the flag-off test read 429 where it wanted 404. Six failures, one
            // cause, none of it in the code under test.
            'cache.default' => 'array',
        ]);

        // The array store lives for the whole PHP process, so it accumulates across
        // tests in this file exactly as redis did. Flush per test.
        Cache::flush();

        DB::purge();
        $this->buildSchema();
        Mail::fake(); // capture the Mailgun email branch if ever taken

        $this->svc = app(ClaimTrackingService::class);
    }

    // ─── Schema + seed helpers ────────────────────────────────────────────

    private function buildSchema(): void
    {
        // Legacy tables (created here because `claims` predates Laravel migrations).
        Schema::create('customer', function ($t) {
            $t->bigIncrements('id');
            $t->string('cellphone')->nullable();
            $t->string('email')->nullable();
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
            $t->string('omang')->nullable();
        });
        Schema::create('claims', function ($t) {
            $t->bigIncrements('id');
            $t->string('claim_number')->nullable();
            $t->string('external_ref')->nullable();
            $t->string('claim_type')->nullable();
            $t->string('status')->default('Pending');
            $t->string('claim_sub_status')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->date('reported_date')->nullable();
            $t->date('registered_claim')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('claim_reserves_coverages', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('claim_id');
            $t->decimal('reserve_amt', 15, 2)->default(0);
            $t->decimal('payment_amt', 15, 2)->default(0);
        });
        Schema::create('claim_tracker_workflow', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('claim_id');
            $t->date('assessor_allotment_date')->nullable();
            $t->date('physical_assessment')->nullable();
            $t->date('quote_request_date')->nullable();
            $t->date('quote_finalisation')->nullable();
            $t->date('assessment_report_date')->nullable();
            $t->date('po_generation_date')->nullable();
            $t->date('po_issue_date')->nullable();
            $t->date('parts_delivery_date')->nullable();
            $t->date('replacement_date')->nullable();
            $t->date('job_end_date')->nullable();
            $t->text('stage1_comment')->nullable();
        });

        // Phase-2 tables (mirror of the migration).
        Schema::create('claim_access_links', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('claim_id')->index();
            $t->string('token', 80)->unique();
            // Added by 2026_08_11_100000_add_claim_form_dispatch and never
            // mirrored here. issueLink() does not set `purpose` — it relies on
            // this column's DEFAULT — while resolveUsableLink() filters on
            // purpose = 'status', so on a schema without the column no link could
            // ever resolve and every OTP call fell through to the generic
            // "sent" response with no OTP row written. Five of the six remaining
            // failures were that, and none of them were a fault in the service.
            $t->string('purpose', 30)->default('status')->index();
            $t->boolean('requires_otp')->default(true);
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('status', 20)->default('active');
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->string('revoked_by', 120)->nullable();
            $t->string('issued_by', 120)->nullable();
            $t->string('issue_channel', 30)->nullable();
            $t->dateTime('otp_last_sent_at')->nullable();
            $t->unsignedInteger('otp_send_count')->default(0);
            $t->dateTime('last_viewed_at')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->timestamps();
        });
        Schema::create('claim_tracking_otps', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('access_link_id')->index();
            $t->string('code_hash', 128);
            $t->dateTime('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->dateTime('consumed_at')->nullable();
            $t->string('delivered_via', 20)->nullable();
            $t->string('delivery_status', 20)->nullable();
            $t->string('delivery_ref', 120)->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('claim_tracking_sessions', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('access_link_id')->index();
            $t->unsignedBigInteger('claim_id')->index();
            $t->string('token_hash', 128)->unique();
            $t->dateTime('expires_at');
            $t->dateTime('revoked_at')->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('claim_tracking_access_logs', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('access_link_id')->nullable();
            $t->unsignedBigInteger('claim_id')->nullable();
            $t->string('event', 40);
            $t->string('channel', 20)->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->text('meta')->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    private function seedClaim(array $overrides = []): int
    {
        $customerId = DB::table('customer')->insertGetId([
            'cellphone' => '71234567',
            'email'     => 'claimant@example.com',
            'firstName' => 'Kagiso',
            'lastName'  => 'Motswana',
            'omang'     => '123456789',
        ]);

        return DB::table('claims')->insertGetId(array_merge([
            'claim_number'     => 'CLM-000123',
            'external_ref'     => 'TRK-999',
            'claim_type'       => 'motor_comprehensive',
            'status'           => 'Open',
            'claim_sub_status' => 'Awaiting Invoice',
            'customer_id'      => $customerId,
            'reported_date'    => '2026-07-01',
            'note'             => 'INTERNAL: suspected exaggerated damage, refer to fraud.',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], $overrides));
    }

    /** Directly seed an OTP row with a known code (plaintext never stored). */
    private function seedOtp(int $linkId, string $code, ?Carbon $expiresAt = null, int $attempts = 0): void
    {
        ClaimTrackingOtp::create([
            'access_link_id'  => $linkId,
            'code_hash'       => hash('sha256', $code . config('app.key')),
            'expires_at'      => $expiresAt ?? Carbon::now()->addMinutes(5),
            'attempts'        => $attempts,
            'delivery_status' => 'sent',
            'delivered_via'   => 'sms',
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────

    /** @test */
    public function issue_link_creates_usable_token_scoped_to_claim(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');

        $this->assertNotEmpty($link->token);
        $this->assertTrue($link->isUsable());
        $this->assertSame($claimId, (int) $link->claim_id);
        $this->assertDatabaseHas('claim_tracking_access_logs', ['event' => 'link_issued', 'claim_id' => $claimId]);
    }

    /** @test */
    public function request_otp_creates_hashed_single_use_code_and_is_generic(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');

        $result = $this->svc->requestOtp($link->token, '10.0.0.1', 'phpunit');

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('sent_to', $result); // no destination leaked

        $otp = ClaimTrackingOtp::where('access_link_id', $link->id)->first();
        $this->assertNotNull($otp);
        $this->assertNull($otp->consumed_at);
        $this->assertSame('sms', $otp->delivered_via);        // reused SMS path
        $this->assertNotSame('', $otp->code_hash);
        $this->assertDatabaseHas('claim_tracking_access_logs', ['event' => 'otp_sent', 'claim_id' => $claimId]);
    }

    /** @test */
    public function request_otp_is_non_enumerating_for_unknown_reference(): void
    {
        $result = $this->svc->requestOtp('NOPE-DOES-NOT-EXIST', '10.0.0.1', 'phpunit');

        // Same generic success as a real hit — cannot distinguish.
        $this->assertTrue($result['ok']);
        $this->assertSame(0, ClaimTrackingOtp::count());
    }

    /** @test */
    public function verify_with_correct_code_mints_claim_scoped_session(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');
        $this->seedOtp($link->id, '123456');

        $result = $this->svc->verifyOtp($link->token, '123456', '10.0.0.1', 'phpunit');

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('token', $result);

        $session = ClaimTrackingSession::first();
        $this->assertNotNull($session);
        $this->assertSame($claimId, (int) $session->claim_id); // scoped to THIS claim
        $this->assertNotNull(ClaimTrackingOtp::first()->consumed_at); // single-use consumed
        $this->assertDatabaseHas('claim_tracking_access_logs', ['event' => 'otp_verified']);
    }

    /** @test */
    public function verify_with_wrong_code_fails_generic_and_counts_attempt(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');
        $this->seedOtp($link->id, '123456');

        $result = $this->svc->verifyOtp($link->token, '000000', '10.0.0.1', 'phpunit');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid', $result['error']);
        $this->assertSame(1, (int) ClaimTrackingOtp::first()->attempts);
        $this->assertSame(0, ClaimTrackingSession::count());
    }

    /** @test */
    public function verify_locks_out_after_max_attempts(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');
        $this->seedOtp($link->id, '123456', null, ClaimTrackingService::MAX_ATTEMPTS);

        $result = $this->svc->verifyOtp($link->token, '123456', '10.0.0.1', 'phpunit');

        $this->assertFalse($result['ok']);
        $this->assertSame('locked', $result['error']);
    }

    /** @test */
    public function expired_link_fails_closed(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');
        $link->expires_at = Carbon::now()->subDay();
        $link->save();

        $req = $this->svc->requestOtp($link->token, '10.0.0.1', 'phpunit');
        $this->assertTrue($req['ok']);                 // still generic
        $this->assertSame(0, ClaimTrackingOtp::count()); // but nothing sent

        $verify = $this->svc->verifyOtp($link->token, '123456', '10.0.0.1', 'phpunit');
        $this->assertFalse($verify['ok']);
        $this->assertSame('invalid', $verify['error']);
    }

    /** @test */
    public function expired_otp_is_rejected(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');
        $this->seedOtp($link->id, '123456', Carbon::now()->subMinute());

        $result = $this->svc->verifyOtp($link->token, '123456', '10.0.0.1', 'phpunit');

        $this->assertFalse($result['ok']);
        $this->assertSame('expired', $result['error']);
    }

    /** @test */
    public function status_payload_excludes_financial_and_internal_and_pii_fields(): void
    {
        $claimId = $this->seedClaim();
        DB::table('claim_reserves_coverages')->insert([
            'claim_id' => $claimId, 'reserve_amt' => 50000.00, 'payment_amt' => 12345.67,
        ]);
        DB::table('claim_tracker_workflow')->insert([
            'claim_id'                => $claimId,
            'assessor_allotment_date' => '2026-07-02',
            'physical_assessment'     => '2026-07-05',
            'stage1_comment'          => 'INTERNAL assessor note — do not disclose',
        ]);

        $link = $this->svc->issueLink($claimId, 'test');
        $verify = $this->svc->verifyOtp($link->token, '654321', '10.0.0.1', 'phpunit'); // no OTP -> fails
        $this->assertFalse($verify['ok']);

        // Seed a valid OTP and verify for real to get a session.
        $this->seedOtp($link->id, '654321');
        $verify = $this->svc->verifyOtp($link->token, '654321', '10.0.0.1', 'phpunit');
        $this->assertTrue($verify['ok']);

        $status = $this->svc->getStatus($verify['token'], '10.0.0.1', 'phpunit');
        $this->assertTrue($status['ok']);
        $claim = $status['claim'];

        // Allowed, claimant-safe fields present.
        $this->assertSame('CLM-000123', $claim['reference']);
        $this->assertSame('In progress', $claim['status']);
        $this->assertArrayHasKey('timeline', $claim);

        // Nothing financial / internal / PII anywhere in the serialized payload.
        $json = json_encode($claim);
        foreach ([
            '50000', '12345', 'reserve', 'payment_amt', 'payment',   // financials
            'INTERNAL', 'stage1_comment', 'suspected', 'fraud',       // internal notes
            'Kagiso', 'Motswana', '123456789', '71234567', 'claimant@example.com', // PII
        ] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $json, "payload leaked: {$forbidden}");
        }
    }

    /** @test */
    public function public_surface_404s_when_flag_off(): void
    {
        config(['services.claimant_tracking.enabled' => false]);

        $this->postJson('/api/public/v1/claim-tracking/request-otp', ['reference' => 'CLM-000123'])
            ->assertStatus(404);
    }

    /** @test */
    public function request_otp_route_is_rate_limited(): void
    {
        $claimId = $this->seedClaim();
        $link = $this->svc->issueLink($claimId, 'test');

        $last = null;
        for ($i = 0; $i < 7; $i++) {
            $last = $this->postJson('/api/public/v1/claim-tracking/request-otp', ['token' => $link->token]);
        }
        // Route throttle is 5/min/IP -> the 6th+ within the window is 429.
        $last->assertStatus(429);
    }
}
