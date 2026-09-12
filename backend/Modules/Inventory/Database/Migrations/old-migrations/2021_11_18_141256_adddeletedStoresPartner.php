<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AdddeletedStoresPartner extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('store_partners', function (Blueprint $table) {
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
        Schema::table('store_partners', function (Blueprint $table) {
			$table->dropIndex(['deleted_at']);
			$table->dropColumn(['deleted_at']);
		});
    }
}
