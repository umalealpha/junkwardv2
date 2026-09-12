<?php

namespace AlphaDirect\Services\ClaimSla;

use AlphaDirect\Claim;
use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\Services\Backdate\BackdateGovernanceService;
use Illuminate\Support\Facades\DB;

/**
 * Reads and updates a claim's stage timeline (the claim_tracker_workflow row,
 * 1:1 with `claims`). Every field change is written to `claim_edit_log` using
 * the same field-diff shape the claims edit-log already uses (field / old_value
 * / new_value / changed_by / changed_by_name / created_at), with the field name
 * prefixed `workflow.` so stage edits are distinguishable from core claim edits
 * in the edit-history feed.
 *
 * ADDITIVE ONLY: this writes exclusively to claim_tracker_workflow (a new table)
 * and claim_edit_log (an existing audit table). It never touches the live
 * `claims` row or any financial table.
 */
class ClaimStageTimelineService
{
    /**
     * The stage / classification columns a user may record via PATCH. Excludes
     * id, claim_id, external_ref and timestamps (system-owned).
     */
    public const EDITABLE_FIELDS = [
        // classification
        'customer_type', 'customer_type_other', 'non_motor_sub_type',
        // stage 1 — assessor allotment / file upload
        'claim_docs_received', 'assessor_allotment_date', 'assessor_name',
        'file_uploaded_to_gt', 'gt_number', 'distance', 'stage1_comment',
        // stage 2 — physical assessment
        'panel_beater_name', 'panel_beater_other', 'physical_assessment', 'physical_assessment_comment',
        // stage 3 — quote request
        'quote_request_date', 'quote_request_comment', 'under_warranty',
        // stage 4 — quote finalisation / assessment report
        'quote_finalisation', 'assessment_report_date', 'assessment_report_comment',
        // stage 5 — purchase order
        'po_generation_date', 'po_issue', 'po_issue_other', 'po_issue_date',
        'contract_pricing_value', 'cil_value',
        // stage 6 — parts / job
        'parts_eta', 'parts_delivery_date', 'confirmation_date', 'mismatch_reported',
        'replacement_date', 'job_end_date', 'job_end_status',
        // non-motor / glass specifics
        'non_motor_assessor', 'non_motor_assessor_other', 'glass_supplier', 'glass_supplier_other',
    ];

    /** Date columns (validated as dates, echoed as Y-m-d). */
    public const DATE_FIELDS = [
        'claim_docs_received', 'assessor_allotment_date', 'file_uploaded_to_gt',
        'physical_assessment', 'quote_request_date', 'quote_finalisation',
        'assessment_report_date', 'po_generation_date', 'po_issue_date',
        'parts_eta', 'parts_delivery_date', 'confirmation_date',
        'replacement_date', 'job_end_date',
    ];

    /** Decimal/monetary columns (validated as numeric). */
    public const NUMERIC_FIELDS = [
        'contract_pricing_value', 'cil_value',
    ];

    /** The workflow row for a claim (create a transient one if none exists). */
    public function forClaim(int $claimId): ClaimTrackerWorkflow
    {
        return ClaimTrackerWorkflow::firstOrNew(['claim_id' => $claimId]);
    }

    /** Shape the timeline for the API. */
    public function present(int $claimId, ?Claim $claim = null): array
    {
        $wf     = $this->forClaim($claimId);
        $fields = [];
        foreach (self::EDITABLE_FIELDS as $f) {
            $val = $wf->{$f} ?? null;
            if (in_array($f, self::DATE_FIELDS, true) && $val) {
                $val = \Illuminate\Support\Carbon::parse($val)->toDateString();
            }
            $fields[$f] = $val;
        }

        return [
            'claim_id'      => $claimId,
            'claim_number'  => $claim?->claim_number,
            'claim_type'    => $claim?->claim_type,
            'exists'        => $wf->exists,
            'external_ref'  => $wf->external_ref,
            'fields'        => $fields,
            'updated_at'    => $wf->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Apply a partial stage update. Returns [timeline, changes[]].
     *
     * @param array<string,mixed> $input already-validated field => value
     * @param mixed               $user   the authenticated user (nullable)
     * @return array{timeline:array,changes:array<int,array<string,mixed>>}
     */
    public function update(int $claimId, array $input, $user = null, ?Claim $claim = null): array
    {
        $wf = $this->forClaim($claimId);
        if (!$wf->exists) {
            $wf->claim_id = $claimId;
        }

        // Only touch known editable fields.
        $payload = array_intersect_key($input, array_flip(self::EDITABLE_FIELDS));
        $wf->fill($payload);

        $dirty   = $wf->getDirty();
        $changes = [];
        $now     = now();
        $name    = $user ? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) : null;

        foreach ($dirty as $field => $newValue) {
            if ($field === 'claim_id') {
                continue;
            }
            $old = $wf->getOriginal($field);
            $changes[] = [
                'claim_id'        => $claimId,
                'field'           => 'workflow.' . $field,
                'old_value'       => $old === null ? null : (string) $old,
                'new_value'       => $newValue === null ? null : (string) $newValue,
                'changed_by'      => $user?->id,
                'changed_by_name' => $name !== '' ? $name : null,
                'created_at'      => $now,
            ];
        }

        // ── Backdate governance hook (ported from the Claims Tracker) ──────────
        // Inert unless the `claims_backdate_governance` runtime flag is on. When
        // OFF, enforce() returns a no-op sentinel WITHOUT inspecting/validating
        // anything and NEVER throws — so this whole block adds nothing to the
        // existing edit behaviour. When ON, it validates any change to a watched
        // date field against the FY floor / max-days cap / active-grant rules and
        // throws BackdateForbiddenException (mapped to 403 by the controller)
        // BEFORE any write happens; an allowed backdate is recorded after commit.
        $governance  = app(BackdateGovernanceService::class);
        $dateChanges = [];
        foreach ($governance->dateFields() as $df) {
            if (array_key_exists($df, $dirty)) {
                $dateChanges[] = [
                    'field' => $df,
                    'old'   => $wf->getOriginal($df),
                    'new'   => $dirty[$df],
                ];
            }
        }
        $backdate = $governance->enforce($dateChanges, $user);

        DB::transaction(function () use ($wf, $changes) {
            $wf->save();
            if (!empty($changes)) {
                DB::table('claim_edit_log')->insert($changes);
            }
        });

        // Record the backdate event + fire the (send-gated) alerts. Only ever
        // true when the flag is on and an allowed backdate occurred.
        if (!empty($backdate['isBackdate'])) {
            $governance->recordEvent($backdate['grantId'], $claimId, $claim?->claim_number, $user, $dateChanges);
        }

        return [
            'timeline' => $this->present($claimId, $claim),
            'changes'  => $changes,
        ];
    }
}
