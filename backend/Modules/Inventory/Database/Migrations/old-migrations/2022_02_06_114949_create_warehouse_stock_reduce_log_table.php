<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWarehouseStockReduceLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouse_stock_reduce_log', function (Blueprint $table) {
            $table->id();
			$table->integer('warehouses_id');
			$table->integer('product_id');
			$table->integer('plan_id');
			$table->string('reason','100');
			$table->integer('availabe_stock');
			$table->integer('damaged_stock');
			$table->integer('transfer_warehouses_id');
			$table->integer('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('warehouse_stock_reduce_log');
    }
}
