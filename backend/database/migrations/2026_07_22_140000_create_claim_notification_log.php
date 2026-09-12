<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims Tracker -> Graphite V2 migration: the claim-notification LOG.
 *
 * The standalone Claims Tracker recorded every claim notification it sent
 * (SMS via Infobip, email via Mailgun) to a `notification_log` and surfaced an
 * admin dashboard over it (volume, delivered/failed/suppressed, cost, by
 * trigger, recent failures). This table brings that log into Graphite.
 *
 * IMPORTANT — this feature only ADDS logging + a read-only dashboard. Graphite
 * already sends the SMS/email (event(SendSms) -> Infobip, event(SendMail) ->
 * Mailgun); a passive recorder appended to those listeners writes a row here
 * WITHOUT changing send behaviour. Everything is gated behind the
 * `claims_notifications` runtime flag (Admin > Integrations, default OFF), so
 * nothing writes to or reads from this table until an admin arms it.
 *
 * DPA: the `recipient` column stores a MASKED destination only (e.g.
 * +2677***123 / j***@x.com) — never a full phone/email — so the operational
 * log never holds raw contact PII.
 *
 * Idempotent: the create is wrapped in `if (!Schema::hasTable(...))` per the
 * repo's PR-guard convention, so a partial/re-run migration is safe. Lives on
 * the DEFAULT connection alongside `claims` / `claim_tracker_workflow` so the
 * dashboard aggregates are plain local reads (served from the read replica).
 */
class CreateClaimNotificationLog extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_notification_log')) {
            Schema::create('claim_notification_log', function (Blueprint $table) {
                $table->id();

                // -> claims.id. Soft (indexed) reference, not a hard FK: the
                // legacy `claims` table predates Laravel migrations and has a
                // `new_claims` sibling, and some claim notifications (e.g. the
                // claimant-tracking OTP resolved by reference) carry no claim id
                // at send time — so this is nullable + indexed.
                $table->unsignedBigInteger('claim_id')->nullable()->index();
                // Human claim reference captured at send time (denormalised so
                // the log survives even if the claim row later changes).
                $table->string('claim_number', 80)->nullable()->index();

                // sms | email  (extend later: whatsapp, in_app)
                $table->string('channel', 20)->index();
                // infobip | mailgun | ...
                $table->string('provider', 40)->nullable();
                // What triggered the send — the tracker's "by trigger" axis.
                // Derived from the send's extradata hook/source/type
                // (e.g. claim_form, claim_tracking, claim_write_off).
                $table->string('trigger_key', 80)->nullable()->index();
                // MASKED destination only — never raw PII (DPA).
                $table->string('recipient', 120)->nullable();
                // Editable template/hook key when the send used one.
                $table->string('template_key', 120)->nullable();
                // Provider message id (Infobip messageId / Mailgun id) for DLR
                // correlation. Nullable — not every path returns one at send.
                $table->string('provider_msg_id', 191)->nullable()->index();

                // sent | delivered | failed | suppressed | pending | skipped
                $table->string('status', 24)->default('sent')->index();
                // Free-text reason for a failed/suppressed/skipped row.
                $table->string('reason', 255)->nullable();

                // Billable units for cost roll-up (SMS segments etc). Kept as a
                // small decimal so fractional/segment costs are exact.
                $table->decimal('cost_units', 10, 4)->nullable();

                // When the provider confirmed delivery (from a later DLR). Null
                // until/unless a delivery receipt is reconciled.
                $table->dateTime('delivered_at')->nullable();

                $table->timestamps();

                // Composite index for the window-scoped dashboard aggregates.
                $table->index(['created_at', 'status'], 'cnl_created_status_idx');
                $table->index(['trigger_key', 'created_at'], 'cnl_trigger_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_notification_log');
    }
}
