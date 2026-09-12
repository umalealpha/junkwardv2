<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDirectorsOfficersLiabilityCoveragesTable extends Migration
{
    public function up()
    {
        Schema::create('directors_officers_liability_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();

            // Schedule
            $table->string('policy_number')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->decimal('limit_of_liability', 15, 2)->nullable();

            // Sections (dynamic rows stored as JSON)
            $table->json('insuring_clauses')->nullable();
            $table->json('extensions')->nullable();
            $table->json('coverage_extensions')->nullable();

            // Additional Schedule Details
            $table->string('previous_insurer_name')->nullable();
            $table->string('previous_policy_type')->nullable();
            $table->string('previous_policyholder')->nullable();
            $table->string('previous_policy_number')->nullable();
            $table->string('previous_policy_period')->nullable();
            $table->date('backdated_continuity_date')->nullable();
            $table->string('jurisdictional_cover')->nullable();

            // Policy Wording document
            $table->string('policy_wording_path')->nullable();
            $table->string('policy_wording_filename')->nullable();

            // Note
            $table->text('note')->nullable();

            // Status & soft delete
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('policy_id');
            $table->index('policy_coverage_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('directors_officers_liability_coverages');
    }
}
