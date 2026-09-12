<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent — guarded so this migration is safe to re-run when the
 * `migrations` tracking table has drifted from the actual DB schema
 * (post-cutover state on PROD). First successful run records itself;
 * subsequent boots skip it entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cron_runs')) {
            Schema::create('cron_runs', function (Blueprint $table) {
                $table->id();
                $table->string('job_key', 50)->index();
                $table->enum('status', ['success', 'failed', 'running'])->default('running');
                $table->string('elapsed', 20)->nullable();
                $table->text('summary')->nullable();   // JSON blob
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_runs');
    }
};
