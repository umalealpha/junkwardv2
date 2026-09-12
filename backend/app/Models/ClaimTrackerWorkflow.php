<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_tracker_workflow — the per-claim SLA STAGE timeline (1:1 with `claims`),
 * brought over from the standalone Claims Tracker. Holds the raw progressive
 * stage dates + classification the claims SLA engine and incentive reports read
 * from (Graphite's `claims` table only carries a coarse status/sub-status).
 *
 * Lives on the default connection alongside the `claims` table.
 * See migration create_claim_tracker_workflow_table.
 */
class ClaimTrackerWorkflow extends Model
{
    protected $table = 'claim_tracker_workflow';

    protected $guarded = ['id'];

    protected $casts = [
        'claim_docs_received'     => 'date',
        'assessor_allotment_date' => 'date',
        'file_uploaded_to_gt'     => 'date',
        'physical_assessment'     => 'date',
        'quote_request_date'      => 'date',
        'quote_finalisation'      => 'date',
        'assessment_report_date'  => 'date',
        'po_generation_date'      => 'date',
        'po_issue_date'           => 'date',
        'parts_eta'               => 'date',
        'parts_delivery_date'     => 'date',
        'confirmation_date'       => 'date',
        'replacement_date'        => 'date',
        'job_end_date'            => 'date',
    ];

    /** The claim this stage timeline belongs to (1:1). */
    public function claim()
    {
        return $this->belongsTo(\AlphaDirect\Claim::class, 'claim_id');
    }
}
