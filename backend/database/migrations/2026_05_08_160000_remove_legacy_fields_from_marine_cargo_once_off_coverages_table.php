<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'voyage_from',
                'voyage_to',
                'departure_date',
                'arrival_date',
                'inception_date',
                'expiry_date',
                'new_altered',
                'period_of_insurance',
                'is_renewable',
                'is_project_specific',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('marine_cargo_once_off_coverages', function (Blueprint $table) {
            $table->date('voyage_from')->nullable();
            $table->string('voyage_to')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('is_project_specific')->nullable();
        });
    }
};
