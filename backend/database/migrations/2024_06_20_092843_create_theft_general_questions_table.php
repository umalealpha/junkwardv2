<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTheftGeneralQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('theft_general_questions', function (Blueprint $table) {
            $table->id();
            $table->string('policy_coverage_id');
            $table->string('coverage_id');
            $table->string('physical_protection_implemented');
            $table->string('premises_alarmed');
            $table->string('subscribe_armed_security')->nullable();
            $table->string('security_company')->nullable();
            $table->string('maintenance_contract');
            $table->date('alarmed_installed_date');
            $table->string('opening_closing_signals');
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
        Schema::dropIfExists('theft_general_questions');
    }
}
