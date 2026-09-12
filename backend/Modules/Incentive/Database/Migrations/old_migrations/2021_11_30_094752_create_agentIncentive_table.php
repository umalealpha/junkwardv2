<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgentIncentiveTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incentive_agent', function (Blueprint $table) {
			$table->charset = 'utf8';
			$table->collation = 'utf8_unicode_ci';
			$table->engine = 'InnoDB';
            $table->id();
			$table->integer('agent_id')->comment('Agent ID');
			$table->string('policy', 100)->comment('Policy Number');
			$table->integer('product_id')->comment('Product ID');
			$table->integer('plan_id')->comment('Plan ID');
			$table->integer('incentive_type')->comment('Incetive Payment Type');
			$table->double('amount', 8, 2)->comment('Incetive Amount');
            $table->string('payment_type')->comment('Incentive payment type');
            $table->integer('incentive_value')->comment('Incentive value');
			$table->softDeletes($column = 'deleted_at', $precision = 0);
            $table->timestamps();
			$table->index('agent_id');
			$table->unique(['policy','agent_id','incentive_type'],'pap');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('incentive_agent');
    }
}
