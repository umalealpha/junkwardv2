<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionTargetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_targets', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('name', 150);
            $table->enum('target_type', ['policy_count', 'premium_amount']);
            $table->decimal('target_value', 15, 2);
            $table->enum('period_type', ['monthly', 'quarterly', 'bimonthly', 'annual', 'custom']);
            $table->integer('period_days')->nullable();
            $table->enum('bonus_type', ['amount', 'percentage']);
            $table->decimal('bonus_value', 12, 2);
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('agent_id')->nullable();
            $table->unsignedInteger('agency_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index('product_id');
            $table->index('agent_id');
            $table->index('agency_id');
            $table->index('status');
            $table->index('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_targets');
    }
}
