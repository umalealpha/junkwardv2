<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MAPFRE/MAWDY "maip-travel" integration — link columns on travel_coverages.
 *
 * Stores the MAPFRE-side identifiers returned across the quote -> contract
 * lifecycle so a Graphite travel coverage can be reconciled against the
 * external policy. All columns are nullable (only populated once a coverage
 * has been quoted/bound through MAPFRE) and each add is guarded with
 * Schema::hasColumn so the migration is idempotent / re-runnable per the
 * self-healing migration standard.
 *
 * Runs on the default connection (where travel_coverages lives).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_coverages', 'mapfre_quote_id')) {
                $table->string('mapfre_quote_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('travel_coverages', 'mapfre_contract_number')) {
                $table->string('mapfre_contract_number')->nullable()->after('mapfre_quote_id');
            }
            if (!Schema::hasColumn('travel_coverages', 'mapfre_product_code')) {
                $table->string('mapfre_product_code')->nullable()->after('mapfre_contract_number');
            }
            if (!Schema::hasColumn('travel_coverages', 'mapfre_synced_at')) {
                $table->timestamp('mapfre_synced_at')->nullable()->after('mapfre_product_code');
            }
            if (!Schema::hasColumn('travel_coverages', 'mapfre_metadata')) {
                $table->json('mapfre_metadata')->nullable()->after('mapfre_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('travel_coverages', function (Blueprint $table) {
            foreach ([
                'mapfre_quote_id',
                'mapfre_contract_number',
                'mapfre_product_code',
                'mapfre_synced_at',
                'mapfre_metadata',
            ] as $column) {
                if (Schema::hasColumn('travel_coverages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
