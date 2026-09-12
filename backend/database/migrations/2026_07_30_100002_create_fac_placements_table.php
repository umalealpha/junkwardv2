<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC Register — step 2 of 5. The register itself.
 *
 * One row per PLACEMENT. A policy split across several reinsurers or brokers,
 * with different cession percentages, gets a separate row for each so that each
 * settlement is tracked on its own.
 *
 * Two facts proved against the June FY26 master workbook on 30 July 2026, both
 * of which the schema has to carry or the June SUMMARY cannot be reproduced:
 *
 *  1. `placement_type` — the workbook keeps "Auto FAC" and "Normal FAC" as
 *     SEPARATE tabs and SEPARATE SUMMARY blocks. Grand Re appears twice:
 *     Auto FAC P706,829.51 and Normal FAC P2,761,161.49. Collapse them into one
 *     counterparty total and the register can never tie back.
 *
 *  2. `gross_ceded_premium` as captured is VAT-INCLUSIVE where VAT applies, and
 *     the SUMMARY's "Reinsurance FAC Payable" column is the SUM OF THAT GROSS —
 *     not gross less commission. Commission is a separate receivable in the
 *     monthly journal. Both figures are therefore stored; the payable is gross.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_placements')) {
            Schema::create('fac_placements', function (Blueprint $t) {
                $t->id();

                // ── Identification ────────────────────────────────────────────
                $t->string('fac_reference', 30)->unique();          // FAC-2026-000001
                $t->string('fac_slip_no', 60)->nullable()->index(); // NOT unique — one slip carries many lines
                $t->unsignedBigInteger('fac_slip_id')->nullable()->index();
                $t->string('financial_year', 9)->nullable()->index(); // FY2025-26

                // Normal facultative vs automatic facultative. Drives the SUMMARY split.
                $t->enum('placement_type', ['fac', 'auto_fac'])->default('fac')->index();

                // ── Policy snapshot, pulled from Graphite and refreshable ─────
                // policy_id is NULLABLE on purpose: 27 of the 138 sheet policies do
                // not exist in Graphite at all (legacy COM…, plus MEDMAL/PI/MAR/ENVI
                // specialty numbers). They must still be registered and visibly
                // flagged, not silently dropped.
                $t->unsignedBigInteger('policy_id')->nullable()->index();
                $t->string('policy_number', 60)->index();
                $t->unsignedBigInteger('policy_action_id')->nullable();
                $t->string('insured_name', 255)->nullable();
                $t->string('policy_type', 40)->nullable();          // Annual / Quarterly / Monthly
                $t->date('period_from')->nullable();
                $t->date('period_to')->nullable();
                $t->string('policy_status', 40)->nullable();
                $t->timestamp('policy_synced_at')->nullable();
                $t->boolean('policy_in_graphite')->default(false)->index();
                $t->boolean('policy_active_in_graphite')->default(false);

                // ── Cover ────────────────────────────────────────────────────
                $t->unsignedBigInteger('reinsurance_group_id')->nullable();
                $t->string('ri_group_label', 160)->nullable();
                $t->decimal('cession_sum_insured', 20, 2)->nullable();
                $t->decimal('risk_pct', 12, 6)->nullable();          // "Total Risk %"

                // ── Counterparties ───────────────────────────────────────────
                // counterparty = WHO WE PAY (broker if one fronts it, else reinsurer).
                // risk_carrier  = WHO CARRIES THE RISK — can be a panel, so free text.
                $t->unsignedBigInteger('counterparty_id')->nullable()->index();
                $t->string('counterparty_name', 160)->nullable();
                $t->string('risk_carrier', 255)->nullable();

                // ── Money, in placement currency ─────────────────────────────
                $t->string('currency', 3)->default('BWP')->index();
                $t->decimal('gross_ceded_premium', 18, 2)->default(0);       // as captured (VAT-inc where VAT applies)
                $t->decimal('commission_pct', 7, 4)->nullable();
                $t->decimal('commission_amount', 18, 2)->default(0);
                $t->decimal('net_ceded_premium', 18, 2)->default(0);         // gross − commission (memo)
                $t->boolean('vat_applicable')->default(true);
                $t->decimal('vat_rate', 6, 4)->default(0.1400);              // Botswana VAT 14%
                $t->decimal('gross_ceded_premium_excl_vat', 18, 2)->default(0);
                $t->decimal('commission_excl_vat', 18, 2)->default(0);

                // ── Foreign currency ─────────────────────────────────────────
                // A placement in a foreign currency with no rate captured leaves the
                // BWP columns NULL and raises a "rate missing" flag. It is NEVER
                // silently treated as Pula.
                $t->decimal('fx_rate', 18, 8)->nullable();
                $t->date('fx_rate_date')->nullable();
                $t->string('fx_rate_source', 120)->nullable();
                $t->decimal('gross_ceded_premium_bwp', 18, 2)->nullable();
                $t->decimal('commission_amount_bwp', 18, 2)->nullable();
                $t->decimal('net_ceded_premium_bwp', 18, 2)->nullable();

                // ── PPW — Premium Payment Warranty ───────────────────────────
                // The deadline by which the client's premium must reach us or cover
                // can be voided. It exists nowhere in the workbook today.
                $t->date('ppw_due_date')->nullable()->index();
                $t->timestamp('ppw_warned_at')->nullable();
                $t->timestamp('ppw_breached_at')->nullable();

                // ── Ownership ────────────────────────────────────────────────
                $t->unsignedBigInteger('underwriter_id')->nullable()->index();
                $t->string('underwriter_name', 160)->nullable();

                // ── Status ───────────────────────────────────────────────────
                $t->enum('status', [
                    'draft', 'placed', 'awaiting_premium', 'client_paid',
                    'ready_to_settle', 'settled', 'cancelled',
                ])->default('draft')->index();

                // ── Leg A: client → us ───────────────────────────────────────
                $t->timestamp('client_paid_at')->nullable();
                $t->enum('client_paid_source', ['graphite', 'upload', 'manual'])->nullable();
                $t->decimal('client_paid_amount', 18, 2)->nullable();
                $t->unsignedBigInteger('client_paid_marked_by')->nullable();

                // ── Leg B: us → reinsurer/broker (settled + paid in omni) ─────
                $t->date('settlement_due_date')->nullable()->index();
                $t->timestamp('settled_at')->nullable();
                $t->string('settlement_reference', 120)->nullable();
                $t->decimal('settled_amount', 18, 2)->nullable();
                $t->unsignedBigInteger('settled_by')->nullable();

                // ── Cancellation ─────────────────────────────────────────────
                // A cancellation is a STATUS CHANGE. The reversing amounts are held
                // on their own row (is_reversal = true) exactly as the workbook does
                // it — that is how the payable reconciles.
                $t->timestamp('cancelled_at')->nullable();
                $t->unsignedBigInteger('cancelled_by')->nullable();
                $t->string('cancellation_reason', 500)->nullable();
                $t->boolean('is_reversal')->default(false);
                $t->unsignedBigInteger('reverses_placement_id')->nullable();

                // ── Slip issue tracking ──────────────────────────────────────
                $t->timestamp('slip_generated_at')->nullable();
                $t->timestamp('slip_sent_at')->nullable();

                // ── Provenance ───────────────────────────────────────────────
                $t->enum('source', ['manual', 'import', 'auto'])->default('manual')->index();
                $t->string('source_ref', 160)->nullable();   // e.g. "FAC master June FY26 · FAC Analysis row 42"

                $t->text('notes')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['financial_year', 'status'], 'fac_fy_status_idx');
                $t->index(['placement_type', 'currency', 'counterparty_id'], 'fac_summary_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_placements');
    }
};
