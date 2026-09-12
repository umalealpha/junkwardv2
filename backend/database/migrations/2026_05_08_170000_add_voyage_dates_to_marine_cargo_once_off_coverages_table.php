<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->date('voyage_from')->nullable();
            $table->date('voyage_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->dropColumn(['voyage_from', 'voyage_to']);
        });
    }
};
