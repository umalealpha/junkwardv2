<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly business-hours calendar used by BusinessHoursCalculator. One row per
 * ISO day-of-week (1=Mon … 7=Sun). Default Botswana calendar:
 *   Mon–Fri : 08:00–17:00
 *   Sat     : 08:00–13:00
 *   Sun     : closed
 *
 * Single window per day for now; multi-window (split shifts) is a future
 * enhancement and would relax the unique(day_of_week) constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_business_calendar')) {
            Schema::create('help_desk_business_calendar', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('day_of_week')->unique(); // 1=Mon … 7=Sun (ISO-8601)
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->boolean('is_open')->default(true);
                $table->timestamps();
            });
        }

        $now  = now();
        $days = [
            ['day_of_week' => 1, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'is_open' => true],  // Mon
            ['day_of_week' => 2, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'is_open' => true],  // Tue
            ['day_of_week' => 3, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'is_open' => true],  // Wed
            ['day_of_week' => 4, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'is_open' => true],  // Thu
            ['day_of_week' => 5, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'is_open' => true],  // Fri
            ['day_of_week' => 6, 'open_time' => '08:00:00', 'close_time' => '13:00:00', 'is_open' => true],  // Sat
            ['day_of_week' => 7, 'open_time' => null,        'close_time' => null,       'is_open' => false], // Sun
        ];
        foreach ($days as $d) {
            DB::table('help_desk_business_calendar')->updateOrInsert(
                ['day_of_week' => $d['day_of_week']],
                array_merge($d, ['updated_at' => $now, 'created_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_business_calendar');
    }
};
