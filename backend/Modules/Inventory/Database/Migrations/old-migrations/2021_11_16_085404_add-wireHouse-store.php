<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWireHouseStore extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->integer('warehouses_id')
                    ->after('partner_id')
					->nullable()
                    ->comment('warehouses id');
			$table->string('contact_person', 220)->after('state_id')->nullable();
            $table->string('email_id', 220)->after('contact_person')->nullable();
            $table->string('mobile', 50)->after('email_id')->nullable();
			$table->softDeletes($column = 'deleted_at', $precision = 0);
			$table->index('warehouses_id');
			$table->index('deleted_at');
			
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::table('stores', function (Blueprint $table) {
			$table->dropIndex(['warehouses_id']);
			$table->dropIndex(['deleted_at']);
			$table->dropColumn(['warehouses_id','mobile','email_id','contact_person','deleted_at']);
		});
    }
	
}
