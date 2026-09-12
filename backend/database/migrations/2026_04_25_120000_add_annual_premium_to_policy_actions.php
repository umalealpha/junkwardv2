<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_actions', 'annual_premium')) {
                $table->decimal('annual_premium', 12, 2)->nullable()->after('premium');
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            if (Schema::hasColumn('policy_actions', 'annual_premium')) {
                $table->dropColumn('annual_premium');
            }
        });
    }
};
