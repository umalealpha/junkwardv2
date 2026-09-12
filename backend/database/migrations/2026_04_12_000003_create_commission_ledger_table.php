<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionLedgerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_ledger', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedInteger('policy_id');
            $table->unsignedInteger('agent_id');
            $table->unsignedInteger('agency_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->enum('entry_type', ['earned', 'clawback', 'bonus', 'adjustment']);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('premium_amount', 12, 2)->nullable();
            $table->string('commission_type', 100)->nullable();
            $table->enum('status', ['pending', 'approved', 'paid', 'held', 'cancelled'])->default('pending');
            $table->date('qualifying_date');
            $table->date('cooling_period_end')->nullable();
            $table->tinyInteger('payment_gate_met')->default(0);
            $table->tinyInteger('kyc_compliant')->default(0);
            $table->tinyInteger('preinspection_compliant')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_batch_id', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index('policy_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('qualifying_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_ledger');
    }
}
