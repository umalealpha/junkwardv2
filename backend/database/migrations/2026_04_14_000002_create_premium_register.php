<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Premium Register — daily earned/unearned premium snapshots.
 *
 * Posted daily by pyengine/jobs/earned_premium_daily.py.
 * Used for: NBFIRA regulatory returns, reinsurance bordereaux,
 * management accounts, financial dashboards.
 */
class CreatePremiumRegister extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('premium_register')) {
            Schema::create('premium_register', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('policy_id');
                $table->string('policy_number', 50)->nullable();
                $table->unsignedInteger('product_id')->nullable();
                $table->unsignedInteger('agency_id')->nullable();
                $table->unsignedInteger('agent_id')->nullable();
                $table->decimal('written_premium', 14, 2)->default(0);
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('earned_premium', 14, 2)->default(0);
                $table->decimal('unearned_premium', 14, 2)->default(0);
                $table->decimal('daily_premium', 12, 2)->default(0);
                $table->unsignedSmallInteger('policy_days')->default(365);
                $table->unsignedSmallInteger('elapsed_days')->default(0);
                $table->unsignedSmallInteger('unexpired_days')->default(0);
                $table->date('posting_date');
                $table->string('transaction_type', 50)->nullable();
                $table->unsignedBigInteger('action_id')->nullable();
                $table->timestamps();

                $table->index('policy_id');
                $table->index('posting_date');
                $table->index('product_id');
                $table->index(['posting_date', 'product_id']);
                $table->unique(['policy_id', 'posting_date']); // one row per policy per day
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('premium_register');
    }
}
