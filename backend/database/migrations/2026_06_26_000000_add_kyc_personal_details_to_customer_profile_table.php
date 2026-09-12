<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KYC personal-detail gap: the start.alphadirect.co.bw create / edit policy
 * forms now collect nationality, occupation, occupation level, name of
 * employer / business, and the country of the physical address across all
 * products (Legal, Accidental Death, Hospital Cashback, Third Party Car,
 * Mobile/Electronic, Motor and Bundle).
 *
 * customer_profile already holds the policyholder PII. We add the four new
 * columns here; the employer / business name reuses the existing `e_name`
 * column, so no column is added for it.
 *
 * All nullable so existing rows/callers that omit them stay valid. The create
 * controllers write via array_intersect_key / hasColumn guards, so the create
 * path is safe on environments where this migration hasn't run yet.
 *
 * Applied to both `customer_profile` (singular — canonical) and
 * `customer_profiles` (plural — schema-drift fallback used by the motor
 * materialisation job) when those tables are present.
 */
return new class extends Migration {
    /** @var string[] */
    private array $tables = ['customer_profile', 'customer_profiles'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'nationality')) {
                    $table->string('nationality', 100)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'occupation')) {
                    $table->string('occupation', 100)->nullable()->after('nationality');
                }
                if (!Schema::hasColumn($tableName, 'occupation_level')) {
                    $table->string('occupation_level', 20)->nullable()->after('occupation');
                }
                if (!Schema::hasColumn($tableName, 'country')) {
                    $table->string('country', 100)->nullable()->after('occupation_level');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['country', 'occupation_level', 'occupation', 'nationality'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
