<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalIndemnityExtensionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('professional_indemnity_extensions')) {
            Schema::create('professional_indemnity_extensions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('policy_id')->nullable();
                $table->unsignedBigInteger('policy_coverage_id')->nullable();

                $table->string('extension')->nullable();
                $table->string('limit_of_liability')->nullable();
                $table->string('premium')->nullable();

                $table->timestamps();

                $table->index('policy_id', 'pi_extensions_policy_id_index');
                $table->index('policy_coverage_id', 'pi_extensions_policy_coverage_id_index');
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
        Schema::dropIfExists('professional_indemnity_extensions');
    }
}
