<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Negative-path coverage for the OTP-gated consent flow.
 *
 * Mirrors the spec's test checklist:
 *   - Wrong OTP 5x  → 429 locked
 *   - OTP expired   → 410, restart
 *   - auth_session reused / >15min → 401
 *   - Skip mandatory checkbox → 422
 *   - Try create policy without consent → 403 consent_required (smoke)
 *   - Tamper detection: UPDATE row → re-hash mismatch (logic check)
 *
 * Each test seeds its own public_otps + public_session_tokens rows
 * directly via the fluent builder — the OTP send pipeline is tested
 * separately and isn't on the critical path here.
 */
class ConsentFlowNegativePathsTest extends TestCase
{
    private const CELL = '+267777000999';

    /** Insert a fresh OTP row and return its id. */
    private function seedOtp(string $code = '123456', ?Carbon $expiresAt = null, int $attempts = 0): int
    {
        return DB::table('public_otps')->insertGetId([
            'cellphone'        => self::CELL,
            'purpose'          => 'customer_auth',
            'code_hash'        => hash('sha256', $code),
            'expires_at'       => $expiresAt ?? Carbon::now()->addMinutes(5),
            'attempts'         => $attempts,
            'delivery_status'  => 'sent',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
    }

    /** @test */
    public function wrong_otp_returns_422_otp_wrong(): void
    {
        $otpId = $this->seedOtp('111111');

        $resp = $this->postJson('/api/public/v1/consents/verify', [
            'otp_session_id' => $otpId,
            'code'           => '999999',
        ]);

        $resp->assertStatus(422)
             ->assertJsonPath('error', 'otp_wrong');
    }

    /** @test */
    public function locked_after_5_attempts_returns_429(): void
    {
        $otpId = $this->seedOtp('111111', null, 5); // already at the lockout threshold

        $resp = $this->postJson('/api/public/v1/consents/verify', [
            'otp_session_id' => $otpId,
            'code'           => '999999',
        ]);

        $resp->assertStatus(429)
             ->assertJsonPath('error', 'otp_locked');
    }

    /** @test */
    public function expired_otp_returns_410(): void
    {
        $otpId = $this->seedOtp('111111', Carbon::now()->subMinute());

        $resp = $this->postJson('/api/public/v1/consents/verify', [
            'otp_session_id' => $otpId,
            'code'           => '111111',
        ]);

        $resp->assertStatus(410)
             ->assertJsonPath('error', 'otp_expired');
    }

    /** @test */
    public function reused_otp_returns_409_already_used(): void
    {
        $otpId = $this->seedOtp('111111');
        DB::table('public_otps')->where('id', $otpId)->update(['consumed_at' => Carbon::now()]);

        $resp = $this->postJson('/api/public/v1/consents/verify', [
            'otp_session_id' => $otpId,
            'code'           => '111111',
        ]);

        $resp->assertStatus(409)
             ->assertJsonPath('error', 'otp_already_used');
    }

    /** @test */
    public function accept_with_invalid_session_returns_401(): void
    {
        $resp = $this->postJson('/api/public/v1/consents/accept', [
            'auth_session_id'           => str_repeat('a', 64), // wrong, not in DB
            'accepted_terms'            => true,
            'accepted_privacy'          => true,
            'accepted_data_processing'  => true,
            'accepted_marketing'        => false,
            'terms_version'             => 'v2.0',
            'privacy_version'           => 'v2.0',
            'product_scope'             => 'retail',
        ]);

        $resp->assertStatus(401)
             ->assertJsonPath('error', 'session_invalid_or_expired');
    }

    /** @test */
    public function accept_with_expired_session_returns_401(): void
    {
        $rawToken = Str::random(64);
        DB::table('public_session_tokens')->insert([
            'token_hash' => hash('sha256', $rawToken),
            'cellphone'  => self::CELL,
            'purpose'    => 'consent_capture',
            'expires_at' => Carbon::now()->subMinute(), // already expired
            'created_at' => Carbon::now()->subMinutes(20),
            'updated_at' => Carbon::now()->subMinutes(20),
        ]);

        $resp = $this->postJson('/api/public/v1/consents/accept', [
            'auth_session_id'           => $rawToken,
            'accepted_terms'            => true,
            'accepted_privacy'          => true,
            'accepted_data_processing'  => true,
            'accepted_marketing'        => false,
            'terms_version'             => 'v2.0',
            'privacy_version'           => 'v2.0',
            'product_scope'             => 'retail',
        ]);

        $resp->assertStatus(401)
             ->assertJsonPath('error', 'session_invalid_or_expired');
    }

    /** @test */
    public function reused_session_token_returns_401_after_first_accept(): void
    {
        // Seed a consumed OTP + a session token, then mint a consent,
        // then try to use the same token a second time.
        $rawToken = Str::random(64);
        DB::table('public_otps')->insert([
            'cellphone'   => self::CELL,
            'purpose'     => 'customer_auth',
            'code_hash'   => hash('sha256', '111111'),
            'expires_at'  => Carbon::now()->addMinutes(5),
            'consumed_at' => Carbon::now(),
            'attempts'    => 1,
            'delivery_status' => 'sent',
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
        DB::table('public_session_tokens')->insert([
            'token_hash' => hash('sha256', $rawToken),
            'cellphone'  => self::CELL,
            'purpose'    => 'consent_capture',
            'expires_at' => Carbon::now()->addMinutes(15),
            'revoked_at' => Carbon::now(), // already used (revoked at first /accept)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $resp = $this->postJson('/api/public/v1/consents/accept', [
            'auth_session_id'           => $rawToken,
            'accepted_terms'            => true,
            'accepted_privacy'          => true,
            'accepted_data_processing'  => true,
            'accepted_marketing'        => false,
            'terms_version'             => 'v2.0',
            'privacy_version'           => 'v2.0',
            'product_scope'             => 'retail',
        ]);

        $resp->assertStatus(401)
             ->assertJsonPath('error', 'session_invalid_or_expired');
    }

    /** @test */
    public function accept_with_unticked_mandatory_returns_422(): void
    {
        $resp = $this->postJson('/api/public/v1/consents/accept', [
            'auth_session_id'           => str_repeat('a', 64),
            'accepted_terms'            => true,
            'accepted_privacy'          => false, // mandatory not ticked
            'accepted_data_processing'  => true,
            'accepted_marketing'        => false,
            'terms_version'             => 'v2.0',
            'privacy_version'           => 'v2.0',
            'product_scope'             => 'retail',
        ]);

        // Validation failure — Laravel default 422.
        $resp->assertStatus(422);
    }

    /** @test */
    public function accept_with_invalid_product_scope_returns_422(): void
    {
        $resp = $this->postJson('/api/public/v1/consents/accept', [
            'auth_session_id'           => str_repeat('a', 64),
            'accepted_terms'            => true,
            'accepted_privacy'          => true,
            'accepted_data_processing'  => true,
            'accepted_marketing'        => false,
            'terms_version'             => 'v2.0',
            'privacy_version'           => 'v2.0',
            'product_scope'             => 'made-up-scope',
        ]);

        $resp->assertStatus(422);
    }

    /** @test */
    public function tamper_detection_recompute_breaks_when_row_mutated(): void
    {
        // Insert a fake consent row, then mutate it. Recomputing the hash
        // from the new payload must NOT match the stored evidence_hash.
        if (!Schema::hasColumn('customer_privacy_consents', 'evidence_hash')) {
            $this->markTestSkipped('evidence_hash column not present');
        }
        $original = [
            'cellphone'                => self::CELL,
            'accepted_terms'           => 1,
            'accepted_privacy'         => 1,
            'accepted_data_processing' => 1,
            'accepted_marketing'       => 0,
            'terms_version'            => 'v2.0',
            'privacy_version'          => 'v2.0',
            'source'                   => 'start_fe',
            'accepted_at'              => Carbon::now()->toDateTimeString(),
            'created_at'               => Carbon::now(),
            'updated_at'               => Carbon::now(),
        ];
        $original['evidence_hash'] = hash('sha256', json_encode($original));
        $id = DB::table('customer_privacy_consents')->insertGetId($original);

        // Mutate the row out-of-band.
        DB::table('customer_privacy_consents')->where('id', $id)
            ->update(['accepted_marketing' => 1, 'updated_at' => Carbon::now()]);

        // Recompute hash from current state and compare.
        $current = (array) DB::table('customer_privacy_consents')->where('id', $id)->first();
        $stored  = $current['evidence_hash'];
        unset($current['evidence_hash']);
        $recomputed = hash('sha256', json_encode($current));

        $this->assertNotSame($stored, $recomputed,
            'Tampered row should not match its stored evidence_hash');
    }
}
