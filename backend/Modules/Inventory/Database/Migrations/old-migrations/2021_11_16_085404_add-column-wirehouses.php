<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnWireHouses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('contact_person', 220)->after('name')->nullable();
            $table->string('email_id', 220)->after('contact_person')->nullable();
            $table->string('mobile', 50)->after('email_id')->nullable();
			$table->index('email_id');
			$table->softDeletes($column = 'deleted_at', $precision = 0);
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
		Schema::table('warehouses', function (Blueprint $table) {
			$table->dropIndex(['email_id']);
			$table->dropIndex(['deleted_at']);
			$table->dropColumn(['deleted_at','mobile','email_id','contact_person']);
		});
	}
	
}
