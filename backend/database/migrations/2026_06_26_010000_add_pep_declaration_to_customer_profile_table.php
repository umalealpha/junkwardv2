<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prominent/Influential Person (PEP) declaration: the start.alphadirect.co.bw
 * create / edit policy forms now collect whether the policyholder is (or is
 * associated with) a person entrusted with public functions in Botswana or
 * abroad, and — when so — the PEP category.
 *
 *   is_pep   : tinyint flag (0/1), nullable.
 *   pep_type : selected category label (full text), nullable.
 *
 * All nullable so existing rows/callers that omit them stay valid. The create
 * controllers write via array_intersect_key / hasColumn guards, so the create
 * path is safe on environments where this migration hasn't run yet.
 *
 * Applied to both `customer_profile` (singular — canonical) and
 * `customer_profiles` (plural — schema-drift fallback) when present.
 */
return new class extends Migration {
    /** @var string[] */
    private array $tables = ['customer_profile', 'customer_profiles'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'is_pep')) {
                    $table->tinyInteger('is_pep')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'pep_type')) {
                    $table->string('pep_type', 255)->nullable()->after('is_pep');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['pep_type', 'is_pep'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
