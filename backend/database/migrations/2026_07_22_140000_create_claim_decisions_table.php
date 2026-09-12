<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims-Tracker -> Graphite migration — the claim DECISION workflow
 * (approve / repudiate / reverse), ported as an ADDITIVE layer.
 *
 * Graphite already owns a claim STATUS / sub-status workflow (claims.status +
 * ClaimsV2Controller::updateStatus / allowedTransitions). This table is a
 * SEPARATE, parallel record of the formal underwriting decision the tracker
 * modelled — it does NOT touch, mirror, or drive claims.status. A decision is
 * a data write by an authenticated user; recording one has no side effects on
 * the claim's status, reserves, or any financial table.
 *
 * Model (mirrors the tracker, D:\ADRisk\claims server.js /decide + /reverse):
 *   - one row per decision EVENT (append-only history — the tracker overwrote
 *     a single row, we keep every event so the audit chain is complete);
 *   - the CURRENT decision for a claim is the latest row with reversed_at NULL;
 *   - reversing sets reversed_at/by on that active row but preserves the
 *     original decision fields for audit;
 *   - a reversed claim can be re-decided (a new row is inserted).
 *
 * Additive + idempotent (Schema::hasTable guard) + PR-guard wrapped. No change
 * to any existing flow. Ships behind the `claims_decision_workflow` runtime
 * flag (default OFF).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_decisions')) {
            Schema::create('claim_decisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();

                // 'approved' | 'repudiated' (validated at the app layer).
                $table->string('decision_status', 20);

                // The attributed decider (the authenticated user recording it).
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->string('decided_by_name', 191)->nullable();
                $table->string('decided_by_role', 100)->nullable();

                // Server-stamped decision time — never client-supplied / backdated.
                $table->timestamp('decision_date')->nullable();

                // Mandatory for repudiate (enforced in the controller).
                $table->text('decision_note')->nullable();

                // Reversal (Admin / Claims Manager). Original decision fields
                // above are preserved; only these are set on reverse.
                $table->timestamp('reversed_at')->nullable();
                $table->unsignedBigInteger('reversed_by')->nullable();
                $table->string('reversed_by_name', 191)->nullable();
                $table->text('reversal_note')->nullable();

                $table->timestamps();

                // Fast lookup of the current (un-reversed) decision per claim.
                $table->index(['claim_id', 'reversed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_decisions');
    }
};
