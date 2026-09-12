<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAmlResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aml_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_case_id')->constrained('kyc_cases')->cascadeOnDelete();
            $table->json('query');   // encrypted JSON
            $table->json('results'); // encrypted JSON
            $table->decimal('max_score',5,3)->index();
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
        Schema::dropIfExists('aml_results');
    }
}
