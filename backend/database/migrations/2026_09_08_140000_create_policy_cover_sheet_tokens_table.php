<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two tables behind the policy cover sheet: the QR access tokens, and the
 * append-only trail of every document access they lead to. Kept in one
 * migration because they ship, and roll back, as one feature.
 *
 * ── policy_cover_sheet_tokens ────────────────────────────────────────────
 * Per-policy access tokens printed as the QR code on the policy cover sheet.
 *
 * The printed token has the shape "<lookup>.<secret>". Only `lookup` is
 * stored in the clear — it is the indexed handle used to find the row; the
 * secret half is stored as a salted SHA-256 hash and compared in constant
 * time, so a database read alone never yields a working QR.
 *
 * A token does NOT expire, because the sheet it is printed on has to keep
 * working for the whole policy term. It grants nothing on its own: it only
 * names the policy, and the holder must still pass the identity check
 * (portal login, or an OTP to the registered number) before any document is
 * served. Revocation is by `revoked_at` — set it when a sheet is reprinted
 * or the policy holder changes, and issue a fresh token.
 *
 * Rows are append-only evidence and are never deleted; the access trail in
 * policy_document_access_logs references them by id.
 *
 * ── policy_document_access_logs ──────────────────────────────────────────
 * One row per event: the QR resolved, an OTP asked for / sent / failed /
 * verified, a portal login accepted, a grant issued or refused, and the
 * document served. Nothing in the flow may happen without a row here —
 * CoverSheetAccessLog is the only writer. This is the DPA record of who opened
 * a policy document and when.
 *
 * No secrets: no OTP codes, no raw QR tokens, no signed URLs. The cellphone is
 * stored masked. Rows are never updated or deleted; retention is a policy
 * decision, not a code one, so no pruning command ships with it.
 *
 * Both live on the V2 ops DB (mysql_system) beside public_otps — the default
 * connection is V1's read replica and rejects writes with 1290.
 *
 * Guarded per the self-healing migration standard — safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('mysql_system')->hasTable('policy_cover_sheet_tokens')) {
            Schema::connection('mysql_system')->create('policy_cover_sheet_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id');
                // Set when the sheet was printed for one specific transaction,
                // so a reprint can be traced to the action it summarised.
                $table->unsignedBigInteger('action_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();

                // Public handle from the printed token — indexed, unique.
                $table->string('lookup', 24)->unique('pcst_lookup_uq');
                // sha256(secret . APP_KEY). Never the secret itself.
                $table->char('secret_hash', 64);

                // Kept so a reissue can be explained without reading the logs.
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->string('issue_reason', 60)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->string('revoke_reason', 120)->nullable();

                // Cheap counters for the policy screen; the log table is the
                // authority on who did what and when.
                $table->unsignedInteger('scan_count')->default(0);
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamp('last_scan_at')->nullable();
                $table->timestamp('last_download_at')->nullable();

                $table->timestamps();

                $table->index(['policy_id', 'id'], 'pcst_policy_idx');
                $table->index(['policy_id', 'revoked_at'], 'pcst_live_idx');
            });
        }

        if (!Schema::connection('mysql_system')->hasTable('policy_document_access_logs')) {
            Schema::connection('mysql_system')->create('policy_document_access_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->unsignedBigInteger('token_id')->nullable();
                $table->unsignedBigInteger('action_id')->nullable();

                // CoverSheetAccessLog::EVENT_* — see that class for the list.
                $table->string('event', 40);
                // 'ok' | 'denied' | 'error' — lets a reviewer filter to
                // failures without knowing the event vocabulary.
                $table->string('outcome', 12)->default('ok');
                // Machine-readable cause on a denial or error, e.g.
                // 'token_revoked', 'otp_expired', 'wrong_customer'.
                $table->string('reason', 60)->nullable();

                // Who, as far as we can tell. A scan before verification has
                // neither; after an OTP we know the customer, after a portal
                // login we know the user.
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                // Masked, e.g. 2677***4567. Never the full number.
                $table->string('cellphone_masked', 24)->nullable();
                // Non-reversible handle for the access session, so several
                // events can be tied to one visit without storing the token.
                $table->string('session_ref', 32)->nullable();

                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                // Small structured context. Never secrets, never full PII.
                $table->text('meta')->nullable();

                $table->timestamp('created_at')->nullable();

                $table->index(['policy_id', 'id'], 'pdal_policy_idx');
                $table->index(['token_id', 'id'], 'pdal_token_idx');
                $table->index(['event', 'id'], 'pdal_event_idx');
                $table->index(['outcome', 'id'], 'pdal_outcome_idx');
                $table->index('session_ref', 'pdal_session_idx');
                $table->index('created_at', 'pdal_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_system')->dropIfExists('policy_document_access_logs');
        Schema::connection('mysql_system')->dropIfExists('policy_cover_sheet_tokens');
    }
};
