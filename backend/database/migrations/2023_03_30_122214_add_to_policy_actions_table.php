<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToPolicyActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_actions', function (Blueprint $table) {
            // if (!Schema::hasColumn('policy_actions', 'status')) {
            //     $table->dropColumn('status');
            // }

            if (!Schema::hasColumn('policy_actions', 'transaction_type')) {
                $table->enum('transaction_type',['NEWBUSINESS','CANCEL','ENDORSE','EXPIRE','REINSTATE','REISSUE','RENEW'])->after('premium')->default('NEWBUSINESS');
            }

            if (!Schema::hasColumn('policy_actions', 'transaction_reason')) {
                $table->enum('transaction_reason',[
                    'AGENTREQUEST','CEASEBUS','CONVCANCEL','CONVCORRECTION','MOVEDOFFICE','FINANCECO','INCREASEHAZARD',
                    'LOSSHISTORY','MOVEDPOLICY','CHANGEDSPE','NONE','NSFCHECK','CHGOWNER','REVERSAL','REALPAY'
                ])->after('transaction_type')->nullable();
            }

            if (!Schema::hasColumn('policy_actions', 'effective_from')) {
                $table->date('effective_from')->after('transaction_reason')->nullable();
            }
            if (!Schema::hasColumn('policy_actions', 'effective_to')) {
                $table->date('effective_to')->after('effective_from')->nullable();
            }
            if (!Schema::hasColumn('policy_actions', 'note')) {
                $table->string('note')->after('effective_to')->nullable();
            }
            if (!Schema::hasColumn('policy_actions', 'status')) {
                $table->enum('status',['QUOTE','ISSUED'])->default('QUOTE')->after('note');
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
        Schema::table('policy_actions', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
            $table->dropColumn('transaction_reason');
            $table->dropColumn('effective_from');
            $table->dropColumn('effective_to');
            $table->dropColumn('note');
            $table->dropColumn('status');
        });

        Schema::table('policy_actions', function (Blueprint $table) {
            $table->enum('status',['activated','completed'])->default('activated')->comment('once the status is completed then there is no option for add/edit record');
        });
    }
}
