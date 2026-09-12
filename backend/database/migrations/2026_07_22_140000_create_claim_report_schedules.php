<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 Claims-Tracker migration — scheduled executive KPI email reports.
 *
 * One row per schedule (mirrors the tracker's `email_schedules`): a named
 * report, a report_type, a frequency (daily/weekly/monthly), the day/hour it
 * fires, its recipient list, and an enabled flag. The claims:run-report-schedules
 * command scans this table on a tick and fires the ones that are due.
 *
 * SENDS ARE GATED at the command layer: with the `claims_scheduled_reports`
 * feature flag OFF, or the schedule disabled, or no recipients, the report is
 * assembled + rendered + logged but NOTHING is sent. This table only DESCRIBES
 * schedules; it never itself causes a send.
 *
 * Additive + idempotent (Schema::hasTable guard) — the repo standard, because
 * some V2 environments were hand-built. No change to any existing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_report_schedules')) {
            Schema::create('claim_report_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                // 'executive_kpi' | 'pending_digest' (see ClaimReportAssembler::REPORT_TYPES).
                $table->string('report_type', 40)->default('executive_kpi');
                // 'daily' | 'weekly' | 'monthly'.
                $table->string('frequency', 20)->default('weekly');
                // 0 (Sun) .. 6 (Sat) — only meaningful for weekly.
                $table->unsignedTinyInteger('day_of_week')->nullable();
                // 1 .. 31 — only meaningful for monthly (clamped to month length at run time).
                $table->unsignedTinyInteger('day_of_month')->nullable();
                // 0 .. 23 — the hour of day the report fires (server tz).
                $table->unsignedTinyInteger('hour')->default(7);
                // JSON array of recipient email addresses. Empty => never sends.
                $table->text('recipients')->nullable();
                // Master enable for THIS schedule. Also requires the global
                // claims_scheduled_reports flag before anything is dispatched.
                $table->boolean('enabled')->default(false);
                // Run bookkeeping — set every tick a due schedule is processed.
                $table->timestamp('last_run_at')->nullable();
                // 'sent' | 'rendered_only' | 'skipped_flag_off' | 'skipped_no_recipients' | 'error'.
                $table->string('last_status', 40)->nullable();
                $table->text('last_note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['enabled', 'frequency'], 'claim_report_schedules_enabled_freq_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_report_schedules');
    }
};
