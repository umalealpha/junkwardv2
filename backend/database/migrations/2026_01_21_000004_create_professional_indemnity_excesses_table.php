<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalIndemnityExcessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('professional_indemnity_excesses', function (Blueprint $table) {
            $table->id();

            // Foreign keys / references
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();

            // Excess details
            $table->string('excess_type')->nullable(); // Fixed / Percentage
            $table->decimal('excess_amount', 15, 2)->nullable();
            $table->decimal('excess_percentage', 8, 2)->nullable();

            // Optional description
            $table->text('description')->nullable();

            // Status & audit
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['policy_id', 'coverage_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('professional_indemnity_excesses');
    }
}
