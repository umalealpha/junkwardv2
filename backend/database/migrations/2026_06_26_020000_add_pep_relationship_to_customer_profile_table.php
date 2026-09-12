<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Related to a Prominent/Influential Person (PEP)" declaration: the
 * start.alphadirect.co.bw create / edit policy forms now capture whether the
 * policyholder is related to a PEP and, if so, the relationship.
 *
 *   is_pep_related          : tinyint flag (0/1), nullable.
 *   pep_relationship        : Spouse / Child / Sibling / Close associate / Other, nullable.
 *   pep_relationship_specify: free text for "Close associate" / "Other", nullable.
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
                if (!Schema::hasColumn($tableName, 'is_pep_related')) {
                    $table->tinyInteger('is_pep_related')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'pep_relationship')) {
                    $table->string('pep_relationship', 50)->nullable()->after('is_pep_related');
                }
                if (!Schema::hasColumn($tableName, 'pep_relationship_specify')) {
                    $table->string('pep_relationship_specify', 255)->nullable()->after('pep_relationship');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['pep_relationship_specify', 'pep_relationship', 'is_pep_related'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
