<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plot number / Ward — a separate (FE-mandatory) address component the
 * start.alphadirect.co.bw create-policy form now collects, alongside the
 * physical address. Botswana physical addresses are keyed by plot + ward.
 *
 * Nullable at the DB layer so existing rows/callers that omit it stay valid;
 * the frontend enforces it as required. Create controllers write via
 * array_intersect_key / hasColumn guards, so the create path is safe on
 * environments where this migration hasn't run yet.
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
                if (!Schema::hasColumn($tableName, 'plot_number')) {
                    $table->string('plot_number', 120)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'plot_number')) {
                    $table->dropColumn('plot_number');
                }
            });
        }
    }
};
