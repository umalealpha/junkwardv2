<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChangeDatatypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage', function (Blueprint $table) {
            $table->decimal('coverage_value',11,2)->change();
            $table->decimal('calculated_value',11,2)->change();
            $table->string('discount')->change();
            $table->decimal('value',11,2)->change();
            $table->decimal('rate',11,2)->change();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_coverage', function (Blueprint $table) {
            $table->decimal('coverage_value',11,2)->change();
            $table->decimal('calculated_value',11,2)->change();
            $table->string('discount')->change();
            $table->decimal('value',11,2)->change();
            $table->decimal('rate',11,2)->change();
        });
    }
}
