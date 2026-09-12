<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC Register — step 5 of 5. Two tables the original specification did not
 * carry, both proved necessary by the June FY26 master workbook.
 *
 * ── fac_slips ────────────────────────────────────────────────────────────
 * A FAC slip number is NOT unique per placement row. Slip 2024-149 carries
 * THREE rows on the June sheet (M P MINING, one per monthly instalment); slip
 * 2024-060 carries two. So the slip is a parent document over many placement
 * lines. Generating and emailing "the slip" therefore has to happen at slip
 * level, or the reinsurer receives three near-identical documents for one risk.
 *
 * ── fac_period_snapshots ─────────────────────────────────────────────────
 * The SUMMARY tab is a ROLL-FORWARD: "Reinsurance FAC Payable" vs "Prior FAC
 * Payable Amount" vs "Change (+/-)", then a variance to the general ledger and
 * the journal to pass. Without a frozen month-end snapshot per counterparty the
 * prior column cannot be produced, the change column cannot be produced, and
 * Finance keeps the spreadsheet — which is the Phase 3 acceptance test.
 *
 * Graphite has no general ledger, so the GL comparatives are captured, dated
 * and attributed. They are an input, never a derived figure.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_slips')) {
            Schema::create('fac_slips', function (Blueprint $t) {
                $t->id();
                $t->string('slip_no', 60)->index();
                $t->string('financial_year', 9)->nullable()->index();
                $t->enum('placement_type', ['fac', 'auto_fac'])->default('fac');

                $t->string('policy_number', 60)->nullable()->index();
                $t->unsignedBigInteger('policy_id')->nullable();
                $t->string('insured_name', 255)->nullable();

                $t->unsignedBigInteger('counterparty_id')->nullable()->index();
                $t->string('counterparty_name', 160)->nullable();
                $t->string('risk_carrier', 255)->nullable();

                $t->enum('status', ['draft', 'generated', 'sent', 'accepted', 'superseded'])
                    ->default('draft')->index();

                $t->string('document_path', 500)->nullable();  // S3 key of the rendered slip
                $t->timestamp('generated_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->text('sent_to')->nullable();
                $t->text('send_error')->nullable();
                $t->unsignedSmallInteger('version')->default(1);

                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->unique(['slip_no', 'version'], 'fac_slip_no_version_uq');
            });
        }

        if (!Schema::hasTable('fac_period_snapshots')) {
            Schema::create('fac_period_snapshots', function (Blueprint $t) {
                $t->id();
                $t->date('period_end')->index();                 // 2026-06-30
                $t->string('financial_year', 9)->nullable();
                $t->enum('placement_type', ['fac', 'auto_fac'])->default('fac');
                $t->string('currency', 3)->default('BWP');
                $t->unsignedBigInteger('counterparty_id')->nullable();
                $t->string('counterparty_name', 160);

                // Register side — computed, frozen at close.
                $t->decimal('premium_excl_vat', 18, 2)->default(0);
                $t->decimal('commission_excl_vat', 18, 2)->default(0);
                $t->decimal('payable', 18, 2)->default(0);       // Σ gross ceded premium as captured
                $t->decimal('prior_payable', 18, 2)->default(0);
                $t->decimal('change', 18, 2)->default(0);
                $t->unsignedInteger('line_count')->default(0);

                $t->timestamp('closed_at')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->timestamps();

                $t->unique(
                    ['period_end', 'placement_type', 'currency', 'counterparty_name'],
                    'fac_snapshot_uq'
                );
            });
        }

        if (!Schema::hasTable('fac_period_gl')) {
            Schema::create('fac_period_gl', function (Blueprint $t) {
                $t->id();
                $t->date('period_end')->unique();

                // Captured from omni / the trial balance. Graphite holds no ledger,
                // so these are an INPUT with a source and a date — never derived.
                $t->decimal('gl_premium', 18, 2)->nullable();
                $t->decimal('gl_commission', 18, 2)->nullable();
                $t->decimal('gl_vat_premium', 18, 2)->nullable();
                $t->decimal('gl_vat_commission', 18, 2)->nullable();
                $t->string('gl_source', 200)->nullable();
                $t->date('gl_as_at')->nullable();

                $t->unsignedBigInteger('captured_by')->nullable();
                $t->string('captured_by_name', 160)->nullable();
                $t->timestamps();
            });
        }

        // FX rates, so a foreign-currency placement can never again be valued
        // from a hand-typed number with no source and no date. The June
        // workbook's Rates tab is completely empty.
        if (!Schema::hasTable('fac_fx_rates')) {
            Schema::create('fac_fx_rates', function (Blueprint $t) {
                $t->id();
                $t->string('currency', 3);
                $t->date('rate_date');
                $t->decimal('rate', 18, 8);          // 1 unit of `currency` = `rate` BWP
                $t->string('source', 160);           // e.g. "Bank of Botswana middle rate"
                $t->unsignedBigInteger('captured_by')->nullable();
                $t->timestamps();
                $t->unique(['currency', 'rate_date'], 'fac_fx_currency_date_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_fx_rates');
        Schema::dropIfExists('fac_period_gl');
        Schema::dropIfExists('fac_period_snapshots');
        Schema::dropIfExists('fac_slips');
    }
};
