<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSectionEnabledToPpkRatingConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ppk_rating_config', function (Blueprint $table) {
            $table->json('section_enabled')->nullable()->after('is_active')->comment('Section enable/disable status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ppk_rating_config', function (Blueprint $table) {
            $table->dropColumn('section_enabled');
        });
    }
}
