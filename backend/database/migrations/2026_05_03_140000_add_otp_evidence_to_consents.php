<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OTP-bound evidence columns on customer_privacy_consents.
 *
 * Why this migration:
 * The 3-step OTP-gated consent flow at /api/public/v1/consents persists a
 * row that proves the consent chain end-to-end:
 *   otp_sent_at  → otp_verified_at  → accepted_at
 * Plus identity (otp_id, otp_channel, otp_attempts, auth_session_id) and
 * scope (product_scope, policy_id) so audits can answer:
 *   "show me every consent for retail product X in the last quarter"
 *   "for each consent, was it OTP-verified, what channel, how long did
 *    the customer take from OTP to acceptance?"
 *
 * evidence_hash + previous_hash already exist (added in an earlier
 * migration) — this migration leaves them alone.
 *
 * All adds are idempotent via Schema::hasColumn so reruns / partial
 * deploys don't crash. Indexes use IF NOT EXISTS via raw SQL since
 * Laravel's Blueprint doesn't expose that.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_privacy_consents')) return;

        Schema::table('customer_privacy_consents', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_privacy_consents', 'otp_id')) {
                $t->unsignedBigInteger('otp_id')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'otp_sent_at')) {
                $t->timestamp('otp_sent_at')->nullable()->after('otp_id');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'otp_verified_at')) {
                $t->timestamp('otp_verified_at')->nullable()->after('otp_sent_at');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'otp_channel')) {
                // sms / whatsapp / voice / email
                $t->string('otp_channel', 20)->nullable()->after('otp_verified_at');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'otp_attempts')) {
                $t->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_channel');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'auth_session_id')) {
                // SHA-256 hash of the short-lived session token (never the raw token)
                $t->string('auth_session_id', 64)->nullable()->after('otp_attempts');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'sec_send_to_verify')) {
                $t->unsignedInteger('sec_send_to_verify')->nullable()->after('auth_session_id');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'sec_verify_to_accept')) {
                $t->unsignedInteger('sec_verify_to_accept')->nullable()->after('sec_send_to_verify');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'product_scope')) {
                // retail / domcom / engineering / specialist
                $t->string('product_scope', 40)->nullable()->after('sec_verify_to_accept');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'policy_id')) {
                $t->unsignedBigInteger('policy_id')->nullable()->after('product_scope');
            }
        });

        // Indexes — created with raw SQL so we can use IF NOT EXISTS and
        // not blow up on reruns. MariaDB 10.5+ supports IF NOT EXISTS on
        // CREATE INDEX.
        $indexes = [
            'idx_consents_otp'         => '(otp_id)',
            'idx_consents_policy'      => '(policy_id)',
            'idx_consents_evidence'    => '(evidence_hash)',
            'idx_consents_scope_date'  => '(product_scope, accepted_at)',
        ];
        foreach ($indexes as $name => $cols) {
            try {
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON customer_privacy_consents {$cols}");
            } catch (\Throwable $e) {
                // Older MySQL/MariaDB without IF NOT EXISTS — fall back
                // to existence check before create.
                $exists = DB::selectOne(
                    "SELECT COUNT(*) c FROM information_schema.statistics
                     WHERE table_schema = DATABASE()
                       AND table_name = 'customer_privacy_consents'
                       AND index_name = ?",
                    [$name],
                );
                if ((int) ($exists->c ?? 0) === 0) {
                    DB::statement("CREATE INDEX {$name} ON customer_privacy_consents {$cols}");
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_privacy_consents')) return;

        foreach (['idx_consents_otp', 'idx_consents_policy', 'idx_consents_evidence', 'idx_consents_scope_date'] as $idx) {
            try { DB::statement("DROP INDEX {$idx} ON customer_privacy_consents"); }
            catch (\Throwable) { /* ignore */ }
        }

        Schema::table('customer_privacy_consents', function (Blueprint $t) {
            $cols = ['policy_id', 'product_scope', 'sec_verify_to_accept', 'sec_send_to_verify',
                     'auth_session_id', 'otp_attempts', 'otp_channel',
                     'otp_verified_at', 'otp_sent_at', 'otp_id'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('customer_privacy_consents', $c)) $t->dropColumn($c);
            }
        });
    }
};
