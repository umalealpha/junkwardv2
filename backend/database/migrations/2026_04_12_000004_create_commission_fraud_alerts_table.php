<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionFraudAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_fraud_alerts', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedInteger('policy_id')->nullable();
            $table->unsignedInteger('agent_id')->nullable();
            $table->unsignedInteger('agency_id')->nullable();
            $table->enum('alert_type', [
                'churning',
                'reactivation_fraud',
                'excessive_cancellation',
                'ghost_policy',
                'premium_manipulation',
                'ntu',
                'cluster_cancellation',
                'duplicate_policy',
                'same_day_multiple',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('details');
            $table->json('related_policy_ids')->nullable();
            $table->enum('status', ['open', 'reviewing', 'resolved', 'dismissed'])->default('open');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('status');
            $table->index('alert_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_fraud_alerts');
    }
}
