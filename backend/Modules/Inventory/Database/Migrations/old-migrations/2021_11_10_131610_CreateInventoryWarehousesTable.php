<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInventoryWarehousesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->charset = 'utf8';
			$table->collation = 'utf8_unicode_ci';
			$table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 220);
            $table->integer('status')->default(1)->comment('1=>active 0=>In-active');
			$table->string('contact_person', 220);
            $table->string('email_id', 220);
            $table->string('mobile', 50);
            $table->text('address')->nullable();
			$table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
			$table->softDeletes($column = 'deleted_at', $precision = 0);
			$table->index('email_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       Schema::dropIfExists('inventory_warehouses');
    }
}
