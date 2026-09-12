<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLedgerAdjustmentsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ledger_adjustments')) {
            Schema::create('ledger_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_action_id')->index();
                $table->unsignedBigInteger('target_action_id')->index();
                $table->unsignedBigInteger('target_ledger_id')->index();
                $table->unsignedBigInteger('target_sub_ledger_id')->nullable()->index();
                $table->decimal('old_amount', 20, 2);
                $table->decimal('new_amount', 20, 2);
                $table->decimal('delta_amount', 20, 2);
                $table->string('reason', 64)->default('BACKDATED_REFRESH');
                $table->unsignedBigInteger('applied_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ledger_adjustments');
    }
}
