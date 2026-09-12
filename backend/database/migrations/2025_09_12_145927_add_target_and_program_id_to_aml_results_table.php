<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTargetAndProgramIdToAmlResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('aml_results', function (Blueprint $table) {
            $table->boolean('target')->nullable()->after('datasets');
            $table->json('programId')->nullable()->after('target');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aml_results', function (Blueprint $table) {
            $table->dropColumn(['target', 'programId']);
        });
    }
}
