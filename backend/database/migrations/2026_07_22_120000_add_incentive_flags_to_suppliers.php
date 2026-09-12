<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-3 Claims-Tracker migration — Incentive report support.
 *
 * The tracker reports the % of MOTOR claims routed to APPROVED panel-beaters
 * and GLASS claims routed to APPROVED glass suppliers (an incentive / compliance
 * KPI). Graphite already owns the suppliers table; this migration only ADDS two
 * nullable boolean flags so an existing supplier can be marked as an approved
 * panel-beater and/or an approved glass supplier. Nothing existing is rebuilt.
 *
 * Idempotent: guarded with Schema::hasColumn so a re-run (or an env where the
 * columns already exist) is a no-op. Additive — no default behaviour changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            // Nothing to extend on an env without the suppliers table.
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'is_approved_panel_beater')) {
                // NULL = never assessed; 0 = not approved; 1 = approved.
                $table->boolean('is_approved_panel_beater')->nullable()->after('supplierLocation');
            }
            if (!Schema::hasColumn('suppliers', 'is_approved_glass_supplier')) {
                $table->boolean('is_approved_glass_supplier')->nullable()->after('is_approved_panel_beater');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('suppliers')) {
            return;
        }
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'is_approved_glass_supplier')) {
                $table->dropColumn('is_approved_glass_supplier');
            }
            if (Schema::hasColumn('suppliers', 'is_approved_panel_beater')) {
                $table->dropColumn('is_approved_panel_beater');
            }
        });
    }
};
