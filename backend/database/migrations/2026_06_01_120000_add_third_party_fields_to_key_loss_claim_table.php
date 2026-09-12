<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key Loss claim form alignment — adds the three new fields requested
 * for the V2 ClaimCreatePage Key Loss section:
 *
 *   - third_party_insured_elsewhere : Yes/No select
 *   - driver_as_insured             : Yes/No select
 *   - event_name                    : free-text label for the incident
 *
 * The replaced fields (chassis_num, financial_interest, estimate) are
 * left in place intentionally so existing rows retain their data and
 * the change is reversible. The FE no longer reads or writes them; the
 * sub_claim_data column-intersect filter in ClaimsController will
 * simply ignore those columns once the FE stops sending them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('key_loss_claim')) {
            return;
        }

        Schema::table('key_loss_claim', function (Blueprint $table) {
            if (! Schema::hasColumn('key_loss_claim', 'third_party_insured_elsewhere')) {
                $table->string('third_party_insured_elsewhere')->nullable()->after('financial_interest');
            }
            if (! Schema::hasColumn('key_loss_claim', 'driver_as_insured')) {
                $table->string('driver_as_insured')->nullable()->after('third_party_insured_elsewhere');
            }
            if (! Schema::hasColumn('key_loss_claim', 'event_name')) {
                $table->string('event_name')->nullable()->after('driver_as_insured');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('key_loss_claim')) {
            return;
        }

        Schema::table('key_loss_claim', function (Blueprint $table) {
            foreach (['third_party_insured_elsewhere', 'driver_as_insured', 'event_name'] as $col) {
                if (Schema::hasColumn('key_loss_claim', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
