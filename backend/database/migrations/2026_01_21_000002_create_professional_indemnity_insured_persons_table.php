<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalIndemnityInsuredPersonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('professional_indemnity_insured_persons')) {
            Schema::create('professional_indemnity_insured_persons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('professional_indemnity_coverage_id')->nullable();
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->unsignedBigInteger('policy_coverage_id')->nullable();
                $table->unsignedBigInteger('coverage_id')->nullable();
                $table->unsignedInteger('sort_order')->nullable();

                $table->string('description')->nullable();
                $table->string('insured_person')->nullable();
                $table->string('length_of_service')->nullable();
                $table->string('limit_of_liability')->nullable();
                $table->string('designation')->nullable();

                $table->timestamps();

                $table->index('professional_indemnity_coverage_id', 'pi_insured_persons_coverage_id_index');
                $table->index('policy_id', 'pi_insured_persons_policy_id_index');
                $table->index('policy_coverage_id', 'pi_insured_persons_policy_coverage_id_index');
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
        Schema::dropIfExists('professional_indemnity_insured_persons');
    }
}
