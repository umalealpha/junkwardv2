<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Create summary_age_analyst_report_dom_com_fifo (2026-07-02).
     *
     * Destination for the FIFO credit-allocation build of the COM
     * age-analysis summary (procedure summary_age_analyst_com_2025).
     * Mirrors the shape of the original summary_age_analyst_report_dom_com_2025
     * but is a separate table, so the original report is left untouched.
     *
     * Forward-only; no data copy.
     */
    public function up(): void
    {
        if (!Schema::hasTable('summary_age_analyst_report_dom_com_fifo')) {
            Schema::create('summary_age_analyst_report_dom_com_fifo', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('policy_id')->nullable()->index();
                $table->string('policyNumber', 120)->nullable()->index();
                $table->string('client_name', 220)->nullable();
                $table->string('product_name', 220)->nullable();
                $table->decimal('balance_outstanding', 16, 2)->nullable();
                $table->decimal('30_days', 16, 2)->nullable();
                $table->decimal('60_days', 16, 2)->nullable();
                $table->decimal('90_days', 16, 2)->nullable();
                $table->decimal('120_days_and_above', 16, 2)->nullable();
                $table->string('policy_status', 50)->nullable();
                $table->decimal('invoice_total', 16, 2)->nullable();
                $table->decimal('payment_total', 16, 2)->nullable();
                $table->decimal('refund_total', 16, 2)->nullable();
                $table->string('premium_freq', 50)->nullable();
                $table->string('agent_name', 220)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('from_date', 110)->nullable();
                $table->string('policy_created_at', 220)->nullable();
                $table->string('coverage_code', 220)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_age_analyst_report_dom_com_fifo');
    }
};
