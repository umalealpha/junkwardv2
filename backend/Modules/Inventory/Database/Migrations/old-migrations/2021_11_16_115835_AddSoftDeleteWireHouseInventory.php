<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSoftDeleteWireHouseInventory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouses_inteventories', function (Blueprint $table) {
            $table->softDeletes($column = 'deleted_at', $precision = 0);
			$table->index('deleted_at');
			$table->index('counter');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::table('warehouses_inteventories', function (Blueprint $table) {
			$table->dropIndex(['deleted_at']);
			$table->dropIndex(['counter']);
			$table->dropColumn(['deleted_at']);
		});
	}
	
}
