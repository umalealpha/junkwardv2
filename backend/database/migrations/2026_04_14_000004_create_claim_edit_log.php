<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimEditLog extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('claim_edit_log')) {
            Schema::create('claim_edit_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('claim_id');
                $table->string('field', 100);
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->string('changed_by_name', 200)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('claim_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('claim_edit_log');
    }
}
