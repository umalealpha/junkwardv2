<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defensive migration — reality is that the V2 `claims` table on some
 * environments was hand-built, so Schema::hasColumn is the only honest check.
 *
 * Adds every column ClaimsController@store writes that might still be missing.
 * Mirrors the intent of graphiteBWV8 `new_claims` but written onto the unified
 * V2 `claims` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claims')) return;

        Schema::table('claims', function (Blueprint $t) {
            if (!Schema::hasColumn('claims', 'claim_sub_type'))        $t->string('claim_sub_type', 100)->nullable();
            if (!Schema::hasColumn('claims', 'document_1'))            $t->string('document_1', 500)->nullable();
            if (!Schema::hasColumn('claims', 'document_2'))            $t->string('document_2', 500)->nullable();
            if (!Schema::hasColumn('claims', 'document_3'))            $t->string('document_3', 500)->nullable();
            if (!Schema::hasColumn('claims', 'incident_date'))         $t->date('incident_date')->nullable();
            if (!Schema::hasColumn('claims', 'incident_time'))         $t->string('incident_time', 10)->nullable();
            if (!Schema::hasColumn('claims', 'incident_location'))     $t->string('incident_location', 500)->nullable();
            if (!Schema::hasColumn('claims', 'incident_description'))  $t->text('incident_description')->nullable();
            if (!Schema::hasColumn('claims', 'type_of_loss'))          $t->string('type_of_loss', 100)->nullable();
            if (!Schema::hasColumn('claims', 'event_name'))            $t->string('event_name', 200)->nullable();
            if (!Schema::hasColumn('claims', 'is_motor_claim'))        $t->tinyInteger('is_motor_claim')->default(0);
            if (!Schema::hasColumn('claims', 'vehicle_plate'))         $t->string('vehicle_plate', 50)->nullable();
            if (!Schema::hasColumn('claims', 'catastrophe_loss'))      $t->tinyInteger('catastrophe_loss')->default(0);
            if (!Schema::hasColumn('claims', 'attorney_involved'))     $t->tinyInteger('attorney_involved')->default(0);
            if (!Schema::hasColumn('claims', 'co_attorney_involved'))  $t->tinyInteger('co_attorney_involved')->default(0);
            if (!Schema::hasColumn('claims', 'dfs_complaint'))         $t->tinyInteger('dfs_complaint')->default(0);
            if (!Schema::hasColumn('claims', 'recovery_involved'))     $t->tinyInteger('recovery_involved')->default(0);
            if (!Schema::hasColumn('claims', 'third_party_insured_elsewhere')) $t->tinyInteger('third_party_insured_elsewhere')->default(0);
            if (!Schema::hasColumn('claims', 'driver_as_insured'))     $t->tinyInteger('driver_as_insured')->default(0);
            if (!Schema::hasColumn('claims', 'weather_condition'))     $t->string('weather_condition', 50)->nullable();
            if (!Schema::hasColumn('claims', 'fault_party'))           $t->string('fault_party', 50)->nullable();
            if (!Schema::hasColumn('claims', 'reason'))                $t->text('reason')->nullable();
            if (!Schema::hasColumn('claims', 'reported_by'))           $t->string('reported_by', 100)->nullable();
            if (!Schema::hasColumn('claims', 'reported_date'))         $t->date('reported_date')->nullable();
            if (!Schema::hasColumn('claims', 'form_template'))         $t->string('form_template', 30)->nullable();
            if (!Schema::hasColumn('claims', 'closed_note'))           $t->text('closed_note')->nullable();
            if (!Schema::hasColumn('claims', 'claim_allocated_to'))    $t->unsignedBigInteger('claim_allocated_to')->nullable();
        });
    }

    public function down(): void
    {
        // No-op: columns may have predated the migration.
    }
};
