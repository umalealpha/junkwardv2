<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReinsurerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('policy_coverage_entities')) {
            Schema::create('reinsurer', function (Blueprint $table) {
                $table->id();
                $table->string('company_name')->nullable();
                $table->string('email')->nullable();
                $table->string('cellphone')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('reinsurer');
    }
}
