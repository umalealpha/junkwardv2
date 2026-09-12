<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * claim_tracker_workflow — brings the Claims Tracker's per-claim SLA STAGE
 * timeline into Graphite (1:1 with `claims`).
 *
 * Why: Graphite's `claims` table only has a coarse status / sub-status machine.
 * It does NOT store the tracker's stage dates (assessor allotment, file upload,
 * physical assessment, quote request/finalisation, assessment report, PO
 * generation/issue, parts/job) or the classification fields the incentive
 * report needs (customer_type, panel_beater, glass_supplier). This is the
 * stage-date INPUT layer that the claims SLA engine + incentive reports read
 * from. The computed SLA deadlines/breach flags live in a separate `claim_slas`
 * table built next; this table is the raw progressive stage record.
 *
 * All stage columns are nullable — a claim fills them in as it moves through
 * the stages. Additive, guarded and reversible. Table only — NO data migrated
 * here (that is the separate, reconciled data-migration step). Part of the
 * Claims Tracker -> Graphite migration (Job 1, Phase 1).
 */
class CreateClaimTrackerWorkflowTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('claim_tracker_workflow')) {
            return;
        }

        Schema::create('claim_tracker_workflow', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id')->unique(); // -> claims.id (1:1)

            // Channel / classification (drives incentive eligibility + SLA sub-type)
            $table->string('customer_type', 30)->nullable();        // MIS / DOM / FAC / Others
            $table->string('customer_type_other', 100)->nullable();
            $table->string('non_motor_sub_type', 100)->nullable();

            // Stage 1 — assessor allotment / file upload to GT Motive
            $table->date('claim_docs_received')->nullable();
            $table->date('assessor_allotment_date')->nullable();
            $table->string('assessor_name', 150)->nullable();
            $table->date('file_uploaded_to_gt')->nullable();
            $table->string('gt_number', 60)->nullable();
            $table->string('distance', 30)->nullable();             // "<50Km" / ">=50Km"
            $table->text('stage1_comment')->nullable();

            // Stage 2 — physical assessment
            $table->string('panel_beater_name', 150)->nullable();
            $table->string('panel_beater_other', 150)->nullable();
            $table->date('physical_assessment')->nullable();
            $table->text('physical_assessment_comment')->nullable();

            // Stage 3 — quote request
            $table->date('quote_request_date')->nullable();
            $table->text('quote_request_comment')->nullable();
            $table->string('under_warranty', 10)->nullable();       // Yes / No

            // Stage 4 — quote finalisation / assessment report
            $table->date('quote_finalisation')->nullable();
            $table->date('assessment_report_date')->nullable();
            $table->text('assessment_report_comment')->nullable();

            // Stage 5 — purchase order
            $table->date('po_generation_date')->nullable();
            $table->string('po_issue', 255)->nullable();            // comma-separated PO types
            $table->string('po_issue_other', 255)->nullable();
            $table->date('po_issue_date')->nullable();

            // Stage 6 — parts / job
            $table->date('parts_eta')->nullable();
            $table->date('parts_delivery_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->string('mismatch_reported', 10)->nullable();
            $table->date('replacement_date')->nullable();
            $table->date('job_end_date')->nullable();
            $table->string('job_end_status', 100)->nullable();

            // Non-motor / glass specifics
            $table->string('non_motor_assessor', 150)->nullable();
            $table->string('non_motor_assessor_other', 150)->nullable();
            $table->string('glass_supplier', 150)->nullable();
            $table->string('glass_supplier_other', 150)->nullable();

            // Provenance — the tracker row this stage data came from (migration key)
            $table->string('external_ref', 64)->nullable()->index(); // = tracker claim_id

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('claim_tracker_workflow');
    }
}
