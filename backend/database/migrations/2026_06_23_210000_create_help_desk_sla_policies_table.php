<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable SLA policy matrix (one row per priority). Targets are stored in
 * BUSINESS minutes — the SLA engine only counts time during business hours.
 *
 * Seeded matrix (business_day_hours = 9):
 *   Critical : response 1h=60   | resolution 6h=360
 *   High     : response 2h=120  | resolution 8 business h=480
 *   Medium   : response 8 business h=480 | resolution 3 business days=1620
 *   Low      : response 1 business day=540 | resolution 5 business days=2700
 *
 * Editable at runtime (admin API in a later phase) — nothing is hardcoded in
 * the engine; it reads these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_sla_policies')) {
            Schema::create('help_desk_sla_policies', function (Blueprint $table) {
                $table->id();
                $table->string('priority', 20)->unique();              // low | medium | high | critical
                $table->unsignedInteger('response_target_minutes');    // business minutes
                $table->unsignedInteger('resolution_target_minutes');  // business minutes
                $table->string('clock_type', 12)->default('business'); // business | calendar (future)
                $table->boolean('active')->default(true);
                $table->string('external_ref', 60)->nullable();        // Bridge-ready
                $table->timestamp('bridge_synced_at')->nullable();     // Bridge-ready
                $table->timestamps();
            });
        }

        // Idempotent seed of the agreed matrix.
        $now = now();
        $rows = [
            ['priority' => 'critical', 'response_target_minutes' => 60,  'resolution_target_minutes' => 360],
            ['priority' => 'high',     'response_target_minutes' => 120, 'resolution_target_minutes' => 480],
            ['priority' => 'medium',   'response_target_minutes' => 480, 'resolution_target_minutes' => 1620],
            ['priority' => 'low',      'response_target_minutes' => 540, 'resolution_target_minutes' => 2700],
        ];
        foreach ($rows as $r) {
            DB::table('help_desk_sla_policies')->updateOrInsert(
                ['priority' => $r['priority']],
                array_merge($r, [
                    'clock_type' => 'business',
                    'active'     => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_sla_policies');
    }
};
