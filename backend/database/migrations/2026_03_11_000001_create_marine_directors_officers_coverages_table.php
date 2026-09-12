<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marine_directors_officers_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('policy_coverage_id')->nullable()->index();
            $table->unsignedBigInteger('coverage_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->date('inception_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->decimal('limit_of_liability', 15, 2)->nullable();
            $table->json('insuring_clauses')->nullable();
            $table->json('extensions')->nullable();
            $table->json('coverage_extensions')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('premium', 15, 2)->nullable();
            $table->string('previous_insurer_name')->nullable();
            $table->string('previous_policy_type')->nullable();
            $table->string('previous_policyholder')->nullable();
            $table->string('previous_policy_number')->nullable();
            $table->string('previous_policy_period')->nullable();
            $table->date('backdated_continuity_date')->nullable();
            $table->string('jurisdictional_cover')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('is_project_specific')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('policy_wording')->nullable();
            $table->string('policy_wording_path')->nullable();
            $table->string('policy_wording_filename')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('term_id')->nullable();
            $table->bigInteger('action_id')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marine_directors_officers_coverages');
    }
};
