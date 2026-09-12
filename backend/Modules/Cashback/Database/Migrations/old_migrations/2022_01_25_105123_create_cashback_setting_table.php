<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCashbackSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cashback')) {
            Schema::create('cashback', function (Blueprint $table) {
                $table->charset = 'utf8';
                $table->collation = 'utf8_unicode_ci';
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('cashback_type', 100)->comment('Cashback Type');
                $table->integer('product_id')->comment('Product ID');
                $table->integer('plan_id')->comment('Plan ID');
                $table->string('payment_type', 100)->comment('Cashback Payment Type');
                $table->integer('cashback_value')->comment('Cashback Amount or value');
                $table->softDeletes($column = 'deleted_at', $precision = 0);
                $table->timestamps();
                $table->index(['product_id', 'plan_id', 'cashback_type'], 'ppi');
                $table->unique(['product_id', 'plan_id', 'cashback_type'], 'type_plan');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashback_setting');
    }
}
