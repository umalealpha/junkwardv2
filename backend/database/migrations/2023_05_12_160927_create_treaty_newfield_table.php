<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTreatyNewfieldTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reinsurance_treaty', function (Blueprint $table) {
            if (!Schema::hasColumn('reinsurance_treaty', 'provisional_commission')) {
                $table->string('provisional_commission')->nullable();
            }
            if (!Schema::hasColumn('reinsurance_treaty', 'proportional_share')) {
                $table->string('proportional_share')->nullable();
            }
            if (!Schema::hasColumn('reinsurance_treaty', 'cash_loss_advise')) {
                $table->string('cash_loss_advise')->nullable();
            }
            if (!Schema::hasColumn('reinsurance_treaty', 'event_limit')) {
                $table->string('event_limit')->nullable();
            }
            if (!Schema::hasColumn('reinsurance_treaty', 'exclusions')) {
                $table->string('exclusions')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('treaty_newfield');
        Schema::table('reinsurance_treaty', function (Blueprint $table) {
            $table->dropColumn('provisional_commission');
            $table->dropColumn('proportional_share');
            $table->dropColumn('cash_loss_advise');
            $table->dropColumn('event_limit');
            $table->dropColumn('exclusions');
        });
    }
}
