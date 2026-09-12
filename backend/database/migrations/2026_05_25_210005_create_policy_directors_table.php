<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates policy_directors — shareholder / director metadata captured by
 * BizSure on Pty Ltd quotes. Sole-prop quotes send an empty directors[]
 * array and produce zero rows. Linked to policy via policy_id and the
 * BizSure term/action hierarchy via term_id + action_id.
 *
 * Related KYC document images are uploaded through /uploadKycImages as
 * `director_{i}_id` and stored in policy_kyc_documents — joined on
 * director_index.
 */
class CreatePolicyDirectorsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('policy_directors')) {
            return;
        }

        Schema::create('policy_directors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->unsignedInteger('director_index');
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->enum('id_type', ['Omang', 'Passport'])->default('Omang');
            $table->string('id_number', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->index('policy_id', 'policy_directors_policy_idx');
            $table->index(['policy_id', 'term_id', 'action_id'], 'policy_directors_hierarchy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_directors');
    }
}
