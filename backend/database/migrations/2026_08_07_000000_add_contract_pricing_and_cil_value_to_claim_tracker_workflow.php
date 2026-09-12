<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the two stage-5 monetary fields the Claims Tracker records but Graphite's
 * claim_tracker_workflow was missing:
 *   - contract_pricing_value — the contract-priced repair value
 *   - cil_value              — the CIL (cash-in-lieu) settlement value
 *
 * Both live on the tracker's stage-5 (purchase order) form. Bringing them into
 * claim_tracker_workflow lets the create-time stage save + the PATCH
 * sla-timeline path persist them alongside the existing stage columns.
 *
 * Additive, nullable, guarded and reversible. No data migrated here. The columns
 * simply sit empty on existing rows until a claim records them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_tracker_workflow')) {
            return;
        }

        Schema::table('claim_tracker_workflow', function (Blueprint $table) {
            if (!Schema::hasColumn('claim_tracker_workflow', 'contract_pricing_value')) {
                $table->decimal('contract_pricing_value', 15, 2)->nullable()->after('po_issue_date');
            }
            if (!Schema::hasColumn('claim_tracker_workflow', 'cil_value')) {
                $table->decimal('cil_value', 15, 2)->nullable()->after('contract_pricing_value');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('claim_tracker_workflow')) {
            return;
        }

        Schema::table('claim_tracker_workflow', function (Blueprint $table) {
            foreach (['contract_pricing_value', 'cil_value'] as $column) {
                if (Schema::hasColumn('claim_tracker_workflow', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
