<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds claim incident/workflow columns that were being sent by the frontend
 * but silently dropped because they weren't columns on the claims table.
 *
 * Mirrors graphiteBWV8's PolicyController::storeClaim where these fields go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $t) {
            $cols = Schema::getColumnListing('claims');
            if (!in_array('incident_date', $cols))        $t->date('incident_date')->nullable()->after('category');
            if (!in_array('incident_time', $cols))        $t->string('incident_time', 10)->nullable();
            if (!in_array('incident_location', $cols))    $t->string('incident_location', 500)->nullable();
            if (!in_array('incident_description', $cols)) $t->text('incident_description')->nullable();
            if (!in_array('type_of_loss', $cols))         $t->string('type_of_loss', 100)->nullable();
            if (!in_array('event_name', $cols))           $t->string('event_name', 200)->nullable();
            if (!in_array('is_motor_claim', $cols))       $t->tinyInteger('is_motor_claim')->default(0);
            if (!in_array('vehicle_plate', $cols))        $t->string('vehicle_plate', 50)->nullable();
            if (!in_array('catastrophe_loss', $cols))     $t->tinyInteger('catastrophe_loss')->default(0);
            if (!in_array('attorney_involved', $cols))    $t->tinyInteger('attorney_involved')->default(0);
            if (!in_array('co_attorney_involved', $cols)) $t->tinyInteger('co_attorney_involved')->default(0);
            if (!in_array('dfs_complaint', $cols))        $t->tinyInteger('dfs_complaint')->default(0);
            if (!in_array('recovery_involved', $cols))    $t->tinyInteger('recovery_involved')->default(0);
            if (!in_array('third_party_insured_elsewhere', $cols)) $t->tinyInteger('third_party_insured_elsewhere')->default(0);
            if (!in_array('driver_as_insured', $cols))    $t->tinyInteger('driver_as_insured')->default(0);
            if (!in_array('weather_condition', $cols))    $t->string('weather_condition', 50)->nullable();
            if (!in_array('fault_party', $cols))          $t->string('fault_party', 50)->nullable();
            if (!in_array('reason', $cols))               $t->text('reason')->nullable();
            if (!in_array('reported_by', $cols))          $t->string('reported_by', 100)->nullable();
            if (!in_array('reported_date', $cols))        $t->date('reported_date')->nullable();
            if (!in_array('form_template', $cols))        $t->string('form_template', 30)->nullable();
            if (!in_array('closed_note', $cols))          $t->text('closed_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $t) {
            foreach ([
                'incident_date','incident_time','incident_location','incident_description',
                'type_of_loss','event_name','is_motor_claim','vehicle_plate',
                'catastrophe_loss','attorney_involved','co_attorney_involved','dfs_complaint',
                'recovery_involved','third_party_insured_elsewhere','driver_as_insured',
                'weather_condition','fault_party','reason','reported_by','reported_date','form_template'
            ] as $c) {
                if (Schema::hasColumn('claims', $c)) $t->dropColumn($c);
            }
        });
    }
};
