<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public-holiday calendar honoured by BusinessHoursCalculator (a holiday is
 * treated as fully closed). Created now and seeded empty — the engine already
 * excludes these dates, so adding rows later needs no code change.
 *
 * `recurring` is reserved for fixed-date annual holidays (future enhancement);
 * the v1 engine only matches exact `holiday_date` values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_holidays')) {
            Schema::create('help_desk_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('name', 100)->nullable();
                $table->boolean('recurring')->default(false); // future: annual recurrence
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_holidays');
    }
};
