<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->dropColumn('open_policy_no');
        });
    }

    public function down(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->string('open_policy_no')->nullable();
        });
    }
};
