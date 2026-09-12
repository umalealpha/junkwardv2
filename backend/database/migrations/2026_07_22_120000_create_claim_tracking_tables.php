<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims Tracker -> Graphite V2 migration, Phase 2: claimant self-service.
 *
 * The standalone Claims Tracker exposed a public OTP-gated status page
 * (track.html) where a claimant entered a claim link / reference, received a
 * one-time code by SMS/email, and then saw the read-only status of THEIR claim.
 * These tables bring that workflow into Graphite. Everything here is ADDITIVE
 * and sits BEHIND the runtime flag `claimant_tracking` (Admin > Integrations,
 * default OFF via config('services.claimant_tracking.enabled')). No existing
 * flow reads or writes these tables.
 *
 * Four small tables (KB-MB scale — the tracker's whole dataset is ~0.5MB):
 *
 *   claim_access_links      opaque, time-limited (default 90-day), revocable
 *                           per-claim link token — the durable credential a
 *                           claimant is given. 1..* per claim (re-issue allowed).
 *   claim_tracking_otps     single-use, short-TTL, HASHED OTP rows scoped to a
 *                           link. Never stores the plaintext code.
 *   claim_tracking_sessions short-lived session token minted on a correct OTP,
 *                           scoped to EXACTLY ONE claim (fail-closed).
 *   claim_tracking_access_logs  append-only audit of link issue / OTP send /
 *                           verify / view events (tracker kept an access log;
 *                           7-yr cold-archive is the plan — at minimum we log).
 *
 * Idempotent: every create is wrapped in `if (!Schema::hasTable(...))` per the
 * PR-guard convention, so a partial/re-run migration is safe. Lives on the
 * DEFAULT connection alongside `claims` / `claim_tracker_workflow` so the
 * read-only status lookup is a plain local join (served from the read replica).
 */
class CreateClaimTrackingTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_access_links')) {
            Schema::create('claim_access_links', function (Blueprint $table) {
                $table->id();
                // -> claims.id. Not a hard FK: the legacy `claims` table
                // predates Laravel migrations and also has a `new_claims`
                // sibling, so we keep the reference soft (indexed) to avoid
                // coupling to a DDL we don't fully own.
                $table->unsignedBigInteger('claim_id')->index();
                // Opaque credential handed to the claimant (URL-safe, 64 chars).
                $table->string('token', 80)->unique();
                // Snapshot of the claimant for contact resolution at send time.
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                // active | revoked | expired (expiry is also enforced by date).
                $table->string('status', 20)->default('active')->index();
                $table->dateTime('expires_at')->nullable()->index();
                $table->dateTime('revoked_at')->nullable();
                $table->string('revoked_by', 120)->nullable();
                // Who/what issued the link (staff user id, 'system', 'command').
                $table->string('issued_by', 120)->nullable();
                $table->string('issue_channel', 30)->nullable();
                // Throttle + audit counters.
                $table->dateTime('otp_last_sent_at')->nullable();
                $table->unsignedInteger('otp_send_count')->default(0);
                $table->dateTime('last_viewed_at')->nullable();
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('claim_tracking_otps')) {
            Schema::create('claim_tracking_otps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('access_link_id')->index();
                // sha256(code + app.key) — plaintext OTP is NEVER persisted.
                $table->string('code_hash', 128);
                $table->dateTime('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('consumed_at')->nullable();
                $table->string('delivered_via', 20)->nullable();   // sms | email | none
                $table->string('delivery_status', 20)->nullable(); // queued | sent | failed
                $table->string('delivery_ref', 120)->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('claim_tracking_sessions')) {
            Schema::create('claim_tracking_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('access_link_id')->index();
                // The single claim this session may read — scoping is enforced
                // on every status read against this exact id (fail-closed).
                $table->unsignedBigInteger('claim_id')->index();
                $table->string('token_hash', 128)->unique();
                $table->dateTime('expires_at');
                $table->dateTime('revoked_at')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('claim_tracking_access_logs')) {
            Schema::create('claim_tracking_access_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('access_link_id')->nullable()->index();
                $table->unsignedBigInteger('claim_id')->nullable()->index();
                // link_issued | otp_requested | otp_sent | otp_verified |
                // otp_failed | status_viewed | link_revoked | denied
                $table->string('event', 40)->index();
                $table->string('channel', 20)->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                // Small JSON blob for non-PII context (masked destination,
                // failure reason). Never store the OTP or claimant PII here.
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Drop in FK-safe order (children first). Guarded so a partial down is safe.
        Schema::dropIfExists('claim_tracking_access_logs');
        Schema::dropIfExists('claim_tracking_sessions');
        Schema::dropIfExists('claim_tracking_otps');
        Schema::dropIfExists('claim_access_links');
    }
}
