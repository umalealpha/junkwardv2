<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * High-risk countries watch-list (AML/CFT). A customer whose
 * customer_profile.countryId is in this list is flagged "High Risk Customer"
 * on the policy view header (alongside the PEP declarations). The list is
 * managed via the Compliance > High Risk Countries CRUD screen so Compliance
 * can add/remove countries over time without a code change.
 *
 *   country_id : FK to countries.id (the same reference table
 *                customer_profile.countryId points at). Unique — a country can
 *                only appear once. No hard FK constraint: the legacy `countries`
 *                table is not guaranteed InnoDB across envs, matching how the
 *                rest of the schema references it.
 *   created_by : user who added the entry (nullable), for the audit column.
 *
 * The initial 22 countries from the Compliance watch-list are seeded here
 * (idempotent — only IDs that actually exist in `countries` are inserted, and
 * insertOrIgnore keeps re-runs safe).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('high_risk_countries')) {
            Schema::create('high_risk_countries', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('country_id')->unique();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Seed the initial watch-list. Resolved against `countries` at authoring
        // time (Angola #6 … Yemen #243). Congo → Democratic Republic (#50);
        // Virgin Islands → British/UK (#239).
        $seedIds = [6, 26, 27, 33, 37, 53, 50, 95, 104, 113, 117, 119, 121, 145, 153, 170, 204, 213, 237, 238, 239, 243];

        if (Schema::hasTable('countries')) {
            $existing = DB::table('countries')->whereIn('id', $seedIds)->pluck('id')->all();
            $now = now();
            $rows = array_map(fn ($id) => [
                'country_id' => $id,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $existing);
            if ($rows) {
                DB::table('high_risk_countries')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('high_risk_countries');
    }
};
