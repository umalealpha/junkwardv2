<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBackdatedEndorseRefreshLogTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('backdated_endorse_refresh_log')) {
            Schema::create('backdated_endorse_refresh_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_action_id')->index();
                $table->unsignedBigInteger('target_action_id')->index();
                $table->string('target_transaction_type', 32);
                $table->string('table_name', 64);
                $table->string('row_business_key', 255)->nullable();
                $table->json('fields_updated')->nullable();
                $table->json('fields_skipped_protected')->nullable();
                $table->boolean('premium_recalculated')->default(false);
                $table->decimal('premium_delta', 20, 2)->nullable();
                $table->string('idempotency_hash', 64)->index();
                $table->timestamp('applied_at')->useCurrent();
                $table->timestamps();

                $table->unique(['source_action_id', 'target_action_id', 'idempotency_hash'], 'bder_src_tgt_hash_uq');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('backdated_endorse_refresh_log');
    }
}
