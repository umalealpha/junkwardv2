<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMotorIdAndNoteToMotorSpecifiedItems extends Migration
{
    /**
     * Add motor_id to policy_specified_items so items can be linked to a
     * specific vehicle rather than just the coverage row.
     * Add note to motor table for per-vehicle notes.
     */
    public function up()
    {
        // Per-motor specified items linkage
        if (!Schema::hasColumn('policy_specified_items', 'motor_id')) {
            Schema::table('policy_specified_items', function (Blueprint $table) {
                $table->unsignedBigInteger('motor_id')->nullable()->after('policy_coverage_id');
            });
        }

        // Per-vehicle note
        if (!Schema::hasColumn('motor', 'note')) {
            Schema::table('motor', function (Blueprint $table) {
                $table->text('note')->nullable()->after('specified_accessories');
            });
        }
    }

    public function down()
    {
        Schema::table('policy_specified_items', function (Blueprint $table) {
            $table->dropColumn('motor_id');
        });
        Schema::table('motor', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
