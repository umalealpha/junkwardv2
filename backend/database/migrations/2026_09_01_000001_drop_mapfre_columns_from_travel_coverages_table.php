<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead MAPFRE link columns from travel_coverages.
 *
 * 2026_06_27_000001 added mapfre_quote_id / mapfre_contract_number /
 * mapfre_product_code / mapfre_synced_at / mapfre_metadata in anticipation of a
 * MAPFRE sale being reconciled against a Graphite travel coverage. That link
 * was never built and is not going to be: a portal travel sale is bound in
 * MAPFRE's book and stays there, audited in mapfre_quote_submissions on the V2
 * ops DB and surfaced by the read-only console at /admin/mapfre-submissions.
 * Nothing in the codebase has ever read or written these five columns, so they
 * are schema that only invites someone to trust an always-empty value.
 *
 * The 2026_06_27 migration is left exactly as it is — it has already run on
 * every environment, and rewriting a migration in place is what broke
 * union_members. This one runs after it and undoes it.
 *
 * Reversible: down() restores the columns (empty, as they always were), so a
 * rollback past this point lands on the same schema 2026_06_27 produced.
 *
 * Guarded per the self-healing migration standard — safe to re-run.
 */
return new class extends Migration {
    /** The columns 2026_06_27_000001 added, in the order it added them. */
    private const COLUMNS = [
        'mapfre_quote_id',
        'mapfre_contract_number',
        'mapfre_product_code',
        'mapfre_synced_at',
        'mapfre_metadata',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('travel_coverages')) {
            return;
        }

        $present = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => Schema::hasColumn('travel_coverages', $column)
        ));

        if (!$present) {
            return;
        }

        Schema::table('travel_coverages', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('travel_coverages')) {
            return;
        }

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
};
