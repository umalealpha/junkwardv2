<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-policy name override to policy_extention_detail.
 *
 * The extension's display name normally comes from the `extentions` master
 * (extentions.s_ScreenName) — operators don't rename extensions per policy
 * in legacy graphiteBWV8. V2 requested the ability to override that name on
 * a specific policy's coverage row without touching the shared master, so
 * this column carries the override. Read path: COALESCE(custom_name, master).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_extention_detail')) return;
        if (Schema::hasColumn('policy_extention_detail', 'custom_name')) return;
        Schema::table('policy_extention_detail', function (Blueprint $t) {
            $t->string('custom_name', 255)->nullable()->after('extentions_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('policy_extention_detail')) return;
        if (!Schema::hasColumn('policy_extention_detail', 'custom_name')) return;
        Schema::table('policy_extention_detail', function (Blueprint $t) {
            $t->dropColumn('custom_name');
        });
    }
};
