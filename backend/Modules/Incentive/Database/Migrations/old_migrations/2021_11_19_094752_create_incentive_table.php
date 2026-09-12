<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIncentiveTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incentive', function (Blueprint $table) {
			$table->charset = 'utf8';
			$table->collation = 'utf8_unicode_ci';
			$table->engine = 'InnoDB';
            $table->id();
			$table->string('incentive_type', 100)->comment('Incetive Type');
			$table->integer('product_id')->comment('Product ID');
			$table->integer('plan_id')->comment('Plan ID');
            $table->integer('Status')->comment('Incentive Status');
			$table->string('payment_type', 100)->comment('Incetive Payment Type');
			$table->integer('incentive_value')->comment('Incetive Amount or value');
			$table->integer('amount')->comment('Commision Amount');
			$table->integer('unlimited')->default(0)->comment('Unlimited');
			$table->softDeletes($column = 'deleted_at', $precision = 0);
            $table->timestamps();
			$table->index(['product_id','plan_id','incentive_type'],'ppi');
			$table->unique(['product_id','plan_id','incentive_type'],'type_plan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('incentive');
    }
}
