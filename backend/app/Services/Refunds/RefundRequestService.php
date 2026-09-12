<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Models\RefundRequestDocument;
use AlphaDirect\Models\RefundRequestEvent;
use AlphaDirect\Services\Claims\OpenClaimHold;
use AlphaDirect\Services\Dpo\RefundService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RefundRequestService — the Customer Refund Engine state machine.
 *
 * Owns every transition of a refund_requests row, the SOP validations
 * (bank proof always; affidavit when the vehicle isn't the client's; the
 * 15:00 Africa/Gaborone cut-off flag; the >P50k CFO gate), the
 * separation-of-duties guards, area access, the append-only audit trail
 * (refund_request_events + Spatie activity), and the paid/posted leg that
 * reuses the existing idempotent RefundService::postExternalRefund().
 *
 * SOP source of truth: Motlatsi's Customer Refund SOP (CFO ask 2026-07-24).
 * Money leg: OmniHandoffService (gated OFF via IntegrationSettings until
 * Finance authority + four-eyes sign-off).
 */
class RefundRequestService
{
    public const CUTOFF_TIME = '15:00';
    public const CUTOFF_TZ   = 'Africa/Gaborone';

    /**
     * Free-text fields that must never carry a card or bank account number.
     * Requested by the CFO on 2026-07-30 after a customer's full Visa number was
     * typed into the reason box on three requests: that column is plain text, is
     * read by everyone with refund access, and sits in every database backup.
     * The bank account belongs in the account field, where it is encrypted and
     * only ever shown as last-4.
     */
    public const PII_GUARDED_FIELDS = ['reason', 'product_name', 'customer_name', 'agent_name'];

    public function __construct(
        private OmniHandoffService $omni,
        private RefundService $refunds,
        private RefundNotifier $notifier,
        private RefundAccountingService $accounting,
        private RefundFraudService $fraud,
        private OpenClaimHold $claimHold,
    ) {}

    // ─── Open-claim hold (CFO instruction, 7 September 2026) ─────────────────

    /**
     * Refuse to move a refund on a policy that has an open claim.
     *
     * The CFO's instruction was "nothing on those policies moves until Claims
     * have reviewed them". A refund is, in substance, our statement that we were
     * not on risk — so raising or approving one while the claim is still open
     * prejudices the claim decision and creates evidence against us.
     *
     * Overridable by {@see OpenClaimHold::PERM_OVERRIDE} (CFO), because 1,459
     * policies currently carry an open claim and a hold with no release valve
     * would freeze legitimate refunds indefinitely. The override records who
     * used it in the refund's own event trail.
     */
    private function assertNoOpenClaimHold(RefundRequest $r, $user, string $action): void
    {
        $blocking = $this->claimHold->blockingClaims(
            $r->policy_id ? (int) $r->policy_id : null,
            $r->policy_number ?: null,
        );

        if ($blocking === []) {
            return;
        }

        if ($this->claimHold->canOverride($user)) {
            // Not silent: the override is the interesting event, not the hold.
            $this->event($r, 'open_claim_hold_override', $r->status, $r->status, $user, null, [
                'action'         => $action,
                'open_claims'    => $blocking,
                'open_claim_qty' => count($blocking),
            ]);
            return;
        }

        throw new RefundWorkflowException($this->claimHold->message($blocking, $action), 409);
    }

    // ─── Area access (the DPA control) ───────────────────────────────────────

    /** Areas the user may see/act on. Super Admin sees all. */
    public static function allowedAreas($user): array
    {
        if (!$user) return [];
        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return RefundRequest::AREAS;
        }
        $areas = [];
        if ($user->can('refund_area_mis')) {
            $areas[] = RefundRequest::AREA_MIS;
        }
        if ($user->can('refund_area_dc')) {
            $areas[] = RefundRequest::AREA_DOMESTIC;
            $areas[] = RefundRequest::AREA_COMMERCIAL;
        }
        return $areas;
    }

    public static function assertAreaAccess($user, RefundRequest $r): void
    {
        if (!in_array($r->area, self::allowedAreas($user), true)) {
            throw new RefundWorkflowException(
                'You are not assigned to this refund area (MIS and Domestic & Commercial are separate).', 403);
        }
    }

    // ─── Intake ──────────────────────────────────────────────────────────────

    public function createDraft(array $data, $user): RefundRequest
    {
        $area = strtolower((string) ($data['area'] ?? ''));
        if (!in_array($area, RefundRequest::AREAS, true)) {
            throw new RefundWorkflowException('area must be one of: ' . implode(', ', RefundRequest::AREAS));
        }
        $amount = (float) ($data['refund_amount'] ?? 0);
        if ($amount <= 0) {
            throw new RefundWorkflowException('refund_amount must be greater than zero.');
        }
        $currency = strtoupper((string) ($data['currency'] ?? 'BWP'));
        if ($currency !== 'BWP') {
            throw new RefundWorkflowException('Only BWP refunds are supported (Omni contract).');
        }

        return DB::transaction(function () use ($data, $user, $area, $amount) {
            $r = new RefundRequest();
            $r->graphite_ref  = 'RFND-PENDING-' . Str::uuid()->toString();
            $r->area          = $area;
            $r->status        = RefundRequest::STATUS_DRAFT;
            $r->refund_amount = $amount;
            $r->currency      = 'BWP';
            $r->created_by    = $user?->id;
            $this->fillEditable($r, $data);
            $this->resolvePolicy($r);
            $r->save();

            // Deterministic human ref, derived from the id — the Omni
            // idempotency key. RFND-000123 style.
            $r->graphite_ref = sprintf('RFND-%06d', $r->id);
            $r->save();

            $this->event($r, 'create', null, RefundRequest::STATUS_DRAFT, $user,
                null, ['amount' => $amount, 'area' => $area]);
            return $r;
        });
    }

    /**
     * Send a rejected refund back to DRAFT so the intaker owns it again
     * (Finance ask — Keetile 2026-08-18). Without this a rejected refund sat in
     * "Rejected" while the team was expected to edit it in place; raising a
     * fresh refund instead trips the duplicate-policy fraud flag, so correcting
     * the original is the intended route.
     *
     * Creator (or an admin) only, rejected only. Approval artefacts were already
     * voided at rejection; this just returns ownership and clears the reviewer's
     * verdict so the next submit starts a clean cycle.
     */
    public function resetToDraft(RefundRequest $r, $user): RefundRequest
    {
        $this->assertCreatorOrAdmin($r, $user);
        $this->assertStatus($r, [RefundRequest::STATUS_REJECTED], 'reset to draft');

        $from = $r->status;
        $r->status          = RefundRequest::STATUS_DRAFT;
        $r->submitted_at    = null;
        $r->reviewed_by     = null;
        $r->reviewed_at     = null;
        $r->review_comment  = null;
        $r->rejected_by     = null;
        $r->rejected_at     = null;
        // rejected_reason is deliberately KEPT until resubmission so the intaker
        // can still see what they were asked to fix while editing.
        $this->clearApprovalState($r);
        $this->saveGuarded($r, $from);

        $this->event($r, 'reset_to_draft', $from, $r->status, $user, null,
            ['previous_rejection' => mb_substr((string) $r->rejected_reason, 0, 500)]);
        return $r;
    }

    public function updateDraft(RefundRequest $r, array $data, $user): RefundRequest
    {
        $this->assertCreatorOrAdmin($r, $user);
        if (!$r->isEditable()) {
            throw new RefundWorkflowException("A {$r->status} request cannot be edited — only draft or rejected requests can.");
        }
        // Capture the material before-state so the audit trail records WHAT
        // changed (never the account number itself — last-4 only).
        $before = [
            'amount' => (float) $r->refund_amount,
            'acct4'  => $r->account_last4,
            'bindex' => $r->account_number_bindex,
            'name'   => $r->customer_name,
        ];

        if (array_key_exists('refund_amount', $data)) {
            $amount = (float) $data['refund_amount'];
            if ($amount <= 0) {
                throw new RefundWorkflowException('refund_amount must be greater than zero.');
            }
            $r->refund_amount = $amount;
        }
        $this->fillEditable($r, $data);
        $this->resolvePolicy($r);

        // A changed beneficiary account or amount voids any approval/bank
        // confirmation carried over from a previous cycle (fraud-laundering
        // path: reject → swap account → resubmit → clear on a stale confirm).
        $changed = [];
        if ((float) $r->refund_amount !== $before['amount']) {
            $changed['amount'] = ['from' => $before['amount'], 'to' => (float) $r->refund_amount];
        }
        if ((string) $r->account_number_bindex !== (string) $before['bindex']) {
            $changed['account'] = ['from_last4' => $before['acct4'], 'to_last4' => $r->account_last4];
        }
        if ((string) $r->customer_name !== (string) $before['name']) {
            $changed['customer_name'] = ['from' => $before['name'], 'to' => $r->customer_name];
        }
        if (isset($changed['amount']) || isset($changed['account'])) {
            $this->clearApprovalState($r);
        }
        $r->save();

        $this->event($r, 'update', $r->status, $r->status, $user, null,
            $changed ? ['changed' => $changed] : null);
        return $r;
    }

    public function addDocument(RefundRequest $r, UploadedFile $file, string $docType, $user): RefundRequestDocument
    {
        $this->assertCreatorOrAdmin($r, $user);
        if (!in_array($r->status, [RefundRequest::STATUS_DRAFT, RefundRequest::STATUS_REJECTED,
                                   RefundRequest::STATUS_SUBMITTED, RefundRequest::STATUS_UNDER_REVIEW], true)) {
            throw new RefundWorkflowException("Documents cannot be added to a {$r->status} request.");
        }
        if (!in_array($docType, RefundRequestDocument::TYPES, true)) {
            throw new RefundWorkflowException('doc_type must be one of: ' . implode(', ', RefundRequestDocument::TYPES));
        }

        // S3, bucket SSE. Served only via the area-scoped download endpoint.
        $dir  = sprintf('refund-requests/%s/%s/%s', $r->area, now()->format('Y-m'), $r->graphite_ref);
        $name = Str::uuid()->toString() . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = Storage::disk('s3')->putFileAs($dir, $file, $name);

        $doc = RefundRequestDocument::create([
            'refund_request_id' => $r->id,
            'doc_type'          => $docType,
            'file_path'         => $path,
            'original_name'     => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime'              => mb_substr((string) $file->getMimeType(), 0, 100),
            'size_bytes'        => $file->getSize() ?: null,
            'sha256'            => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by'       => $user?->id,
        ]);

        $this->event($r, 'upload_document', $r->status, $r->status, $user, null,
            ['doc_type' => $docType, 'original_name' => $doc->original_name]);
        return $doc;
    }

    // ─── Submit (Creator) ────────────────────────────────────────────────────

    public function submit(RefundRequest $r, $user): RefundRequest
    {
        $this->assertCreatorOrAdmin($r, $user);
        $this->assertStatus($r, [RefundRequest::STATUS_DRAFT, RefundRequest::STATUS_REJECTED], 'submit');

        // SOP: bank verification is ALWAYS required — no exceptions.
        $types = $r->documents()->pluck('doc_type')->all();
        if (!array_intersect(RefundRequestDocument::BANK_PROOF_TYPES, $types)) {
            throw new RefundWorkflowException(
                'A bank statement or bank confirmation letter is required before submitting — no exceptions.');
        }
        // SOP: vehicle not registered to the client → signed affidavit mandatory.
        if ($r->vehicle_not_client && !in_array(RefundRequestDocument::TYPE_AFFIDAVIT, $types, true)) {
            throw new RefundWorkflowException(
                'The vehicle is not registered to the client — a signed affidavit is required before submitting.');
        }
        if (empty($r->account_number_encrypted)) {
            throw new RefundWorkflowException('The client bank account number is required before submitting.');
        }
        // The flagship fraud control (same account paid out under different
        // customer names) keys off customer_name, so a blank name silently
        // disarmed it. It is mandatory from here on.
        if (trim((string) $r->customer_name) === '') {
            throw new RefundWorkflowException('The customer name is required before submitting (it drives the fraud checks).');
        }
        // Open claim on this policy → it does not enter the queue at all.
        $this->assertNoOpenClaimHold($r, $user, 'submitting this refund');

        $from = $r->status;
        // 15:00 Africa/Gaborone cut-off — submissions after it are accepted but
        // flagged for next-business-day processing (SOP).
        $local = Carbon::now(self::CUTOFF_TZ);
        $r->after_cutoff = $local->format('H:i') >= self::CUTOFF_TIME;
        $r->status       = RefundRequest::STATUS_SUBMITTED;
        $r->submitted_at = now();
        // A resubmission clears the previous rejection outcome.
        $r->rejected_by = null;
        $r->rejected_at = null;
        $r->save();

        // Fraud scan on intake — stamps fraud_flags / fraud_score so the reviewer
        // sees the alerts. Best-effort: a scan error must never block a submit.
        try {
            $this->fraud->applyScan($r);
        } catch (\Throwable $e) {
            Log::warning('refund fraud scan failed on submit', ['id' => $r->id, 'err' => $e->getMessage()]);
        }

        $this->event($r, 'submit', $from, $r->status, $user, null, [
            'after_cutoff' => $r->after_cutoff,
            'resubmission' => $from === RefundRequest::STATUS_REJECTED,
        ]);
        $this->notifier->submitted($r);
        return $r;
    }

    // ─── Review (Reviewer) ───────────────────────────────────────────────────

    /**
     * Mark a submitted refund reviewed, optionally with the reviewer's comment
     * (Finance ask, Keetile 2026-08-10 — parity with the inputter's "Reason
     * details" box, so the approver can see what the reviewer actually checked).
     */
    public function startReview(RefundRequest $r, $user, string $comment = ''): RefundRequest
    {
        $this->assertStatus($r, [RefundRequest::STATUS_SUBMITTED], 'review');
        if ($user && $r->created_by && (int) $user->id === (int) $r->created_by) {
            throw new RefundWorkflowException('The creator of a request cannot review it (separation of duties).', 403);
        }
        // Assignment routing: once an approver has routed the request to a
        // specific reviewer, only that person picks it up (approvers and
        // Super Admin may still step in, and may reassign).
        if ($user && $r->assigned_to && (int) $user->id !== (int) $r->assigned_to
            && !$user->can('refund-approve')
            && !(method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))) {
            throw new RefundWorkflowException('This request is assigned to another reviewer — ask an approver to reassign it.', 403);
        }

        $comment = trim($comment);
        if ($comment !== '') {
            // Same PII rule as the intake narrative — a free-text box is not the
            // place for a card or account number.
            self::assertNoCardOrAccountNumber($comment, 'review comment');
        }

        $from = $r->status;
        $r->status         = RefundRequest::STATUS_UNDER_REVIEW;
        $r->reviewed_by    = $user?->id;
        $r->reviewed_at    = now();
        $r->review_comment = $comment !== '' ? $comment : null;
        $r->save();

        $this->event($r, 'review', $from, $r->status, $user, $comment !== '' ? $comment : null);
        $this->notifier->readyForApproval($r);
        return $r;
    }

    /**
     * Route a submitted/under-review request to a specific reviewer.
     * Approvers/owners only (route gate: refund-approve). CFO 2026-07-26:
     * "when Motlatsi is out, Bharath can reassign to anyone in Unicoin";
     * D&C owners may assign to anyone in Finance.
     */
    public function assign(RefundRequest $r, $user, int $assigneeId): RefundRequest
    {
        $this->assertStatus($r, [RefundRequest::STATUS_SUBMITTED, RefundRequest::STATUS_UNDER_REVIEW], 'assign');
        $assignee = \AlphaDirect\User::find($assigneeId);
        if (!$assignee) {
            throw new RefundWorkflowException('Assignee not found.');
        }
        if (!$assignee->can($r->areaPermission())
            && !(method_exists($assignee, 'hasRole') && $assignee->hasRole('Super Admin'))) {
            throw new RefundWorkflowException(
                'That user is not in this refund area — assign them an area role first (Roles & Permissions).');
        }
        if ($r->created_by && (int) $assigneeId === (int) $r->created_by) {
            throw new RefundWorkflowException('The creator cannot review their own request (separation of duties).');
        }

        $r->assigned_to = $assigneeId;
        $r->assigned_by = $user?->id;
        $r->assigned_at = now();
        $r->save();

        $this->event($r, 'assign', $r->status, $r->status, $user, null, [
            'assigned_to'      => $assigneeId,
            'assigned_to_name' => $assignee->name ?? $assignee->email,
        ]);
        $this->notifier->assigned($r, $assigneeId, $user?->name ?? $user?->email ?? 'an approver');
        return $r;
    }

    public function reject(RefundRequest $r, $user, string $reason, array $missingDocs = []): RefundRequest
    {
        // APPROVAL_PENDING_2: the second approver on a >P5k refund must be able
        // to DISAGREE — without this, the only exit from a parked refund is
        // approving it, which defeats the second pair of eyes.
        $this->assertStatus($r, [RefundRequest::STATUS_SUBMITTED, RefundRequest::STATUS_UNDER_REVIEW,
                                 RefundRequest::STATUS_ESCALATED, RefundRequest::STATUS_CFO_PENDING,
                                 RefundRequest::STATUS_APPROVAL_PENDING_2], 'reject');
        $reason = trim($reason);
        if ($reason === '') {
            throw new RefundWorkflowException('A rejection reason is required (SOP).');
        }

        $from = $r->status;
        $r->status          = RefundRequest::STATUS_REJECTED;
        $r->rejected_by     = $user?->id;
        $r->rejected_at     = now();
        $r->rejected_reason = $reason;
        // Rejection voids the whole approval chain. Without this, a >P5k refund
        // rejected out of approval_pending_2 kept its first approval and its
        // bank_account_confirmed flag — so the creator could change the account
        // number, resubmit, and have the CFO leg clear it on a confirmation that
        // was given for a DIFFERENT account.
        $this->clearApprovalState($r);
        $this->saveGuarded($r, $from);

        $this->event($r, 'reject', $from, $r->status, $user, $reason,
            $missingDocs ? ['missing_docs' => array_values($missingDocs)] : null);
        $this->notifier->rejected($r);
        return $r;
    }

    public function escalate(RefundRequest $r, $user, string $reason): RefundRequest
    {
        $this->assertStatus($r, [RefundRequest::STATUS_SUBMITTED, RefundRequest::STATUS_UNDER_REVIEW], 'escalate');
        // Separation of duties: escalation is a review act, so the creator may
        // not escalate their own request. Without this the creator could push
        // their own refund straight to the CFO leg and bypass review entirely.
        if ($user && $r->created_by && (int) $user->id === (int) $r->created_by) {
            throw new RefundWorkflowException(
                'The creator of a request cannot escalate it (separation of duties).', 403);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RefundWorkflowException('An escalation reason is required.');
        }

        $from = $r->status;
        $r->status           = RefundRequest::STATUS_ESCALATED;
        $r->escalated_by     = $user?->id;
        $r->escalated_at     = now();
        $r->escalated_reason = $reason;
        $r->save();

        $this->event($r, 'escalate', $from, $r->status, $user, $reason);
        $this->notifier->escalated($r);
        return $r;
    }

    // ─── Approve (Administrator) + the CFO gate ──────────────────────────────

    public function approve(RefundRequest $r, $user, bool $bankAccountConfirmed, string $reason = ''): RefundRequest
    {
        // APPROVAL_PENDING_2 = a >P5k refund awaiting its second approver.
        $this->assertStatus($r, [RefundRequest::STATUS_UNDER_REVIEW, RefundRequest::STATUS_ESCALATED,
                                 RefundRequest::STATUS_APPROVAL_PENDING_2], 'approve');
        $reason = trim($reason);
        if ($reason === '') {
            throw new RefundWorkflowException('An approval reason is required (SOP).');
        }
        // Checked before the bank-account confirmation below, so an approver is
        // never asked to confirm bank details for a refund that cannot proceed.
        $this->assertNoOpenClaimHold($r, $user, 'approving this refund');
        if ($user && $r->created_by && (int) $user->id === (int) $r->created_by) {
            throw new RefundWorkflowException('The creator of a request cannot approve it (separation of duties).', 403);
        }
        if ($user && $r->reviewed_by && (int) $user->id === (int) $r->reviewed_by) {
            throw new RefundWorkflowException('The reviewer cannot also give final approval (four-eyes).', 403);
        }
        // SOP: the Administrator must confirm the client's bank account before
        // payment — an explicit act, recorded in the audit trail.
        if (!$bankAccountConfirmed) {
            throw new RefundWorkflowException(
                'You must confirm the client bank account before approving (bank_account_confirmed=true).');
        }

        // Fraud gate: re-scan at approval (velocity/structuring can turn true
        // since intake). A CRITICAL flag blocks a normal approval — it must be
        // escalated to the CFO, who can clear it via cfoApprove (the override).
        $this->fraud->applyScan($r);
        if ($this->fraud->hasCritical($r)) {
            throw new RefundWorkflowException(
                'Blocked — critical fraud flags on this refund. Escalate to the CFO to proceed.', 409);
        }

        $from = $r->status;
        $r->bank_account_confirmed = true;

        // Two-approver rule (CFO 2026-07-28): refunds ABOVE P5,000 need a second
        // distinct approver. The FIRST approval on such a refund parks it in
        // APPROVAL_PENDING_2 and does NOT arm the money leg.
        if ($r->needsSecondApproval() && $r->status !== RefundRequest::STATUS_APPROVAL_PENDING_2) {
            $r->approved_by     = $user?->id;
            $r->approved_at     = now();
            $r->approved_reason = $reason;
            $r->status          = RefundRequest::STATUS_APPROVAL_PENDING_2;
            $this->saveGuarded($r, $from);

            $this->event($r, 'approve', $from, $r->status, $user, $reason, [
                'amount'                   => (float) $r->refund_amount,
                'bank_account_confirmed'   => true,
                'first_approval'           => true,
                'awaiting_second_approver' => true,
            ]);
            $this->notifier->readyForSecondApproval($r);
            return $r;
        }

        // Either the SECOND approval on a >P5k refund, or the single approval on
        // a <=P5k refund.
        if ($r->status === RefundRequest::STATUS_APPROVAL_PENDING_2) {
            if ($user && $r->approved_by && (int) $user->id === (int) $r->approved_by) {
                throw new RefundWorkflowException(
                    'Refunds over P5,000 need a SECOND, different approver (four-eyes).', 403);
            }
            $r->second_approved_by = $user?->id;
            $r->second_approved_at = now();
        } else {
            $r->approved_by     = $user?->id;
            $r->approved_at     = now();
            $r->approved_reason = $reason;
        }

        $r->status = $r->needsCfoApproval()
            ? RefundRequest::STATUS_CFO_PENDING
            : RefundRequest::STATUS_APPROVED;
        $this->saveGuarded($r, $from);

        $this->event($r, 'approve', $from, $r->status, $user, $reason, [
            'amount'                 => (float) $r->refund_amount,
            'bank_account_confirmed' => true,
            'second_approval'        => $from === RefundRequest::STATUS_APPROVAL_PENDING_2,
            'cfo_gate'               => $r->status === RefundRequest::STATUS_CFO_PENDING,
        ]);

        if ($r->status === RefundRequest::STATUS_CFO_PENDING) {
            $this->notifier->cfoPending($r);
        } else {
            $this->notifier->approved($r);
            $this->maybeHandOff($r, $user);
        }
        return $r;
    }

    /**
     * The CFO leg. Two entry states:
     *   CFO_PENDING — the >P50k gate (any area), after a full approve() chain.
     *   ESCALATED   — a refund sent up for a senior decision (including one
     *                 blocked by a CRITICAL fraud flag); clearing it here IS
     *                 the documented fraud override, recorded on the event.
     *
     * SECURITY (hardening 2026-07-30): an ESCALATED request never passed through
     * approve(), so this path used to skip every control approve() enforces —
     * separation of duties (approved_by is NULL, making the old guard a no-op),
     * the >P5k second approver, the >P50k routing, the fraud re-scan and the
     * approval reason. One account holding create+submit+review+cfo-approve
     * could therefore pay itself. Every one of those controls is now applied
     * here too, so this leg is at least as strong as normal approval.
     */
    public function cfoApprove(RefundRequest $r, $user, bool $bankAccountConfirmed = false,
                              string $reason = '', bool $overrideFraud = false): RefundRequest
    {
        $this->assertStatus($r, [RefundRequest::STATUS_CFO_PENDING, RefundRequest::STATUS_ESCALATED], 'cfo-approve');

        // Gate A — the >P50,000 CFO gate is the CFO's alone (CFO 2026-08-24,
        // reaffirmed 2026-09-02). A deputy holding only refund-escalation-clear
        // may clear escalations; it may never clear cfo_pending. The route lets
        // either permission through, so this is where the two part company.
        if ($r->status === RefundRequest::STATUS_CFO_PENDING
            && $user && !$user->can(RefundRequest::PERM_CFO_APPROVE)) {
            throw new RefundWorkflowException(
                'This refund is above P50,000 and only the CFO can clear that gate. '
                . 'Escalation-clearing access does not extend to it.', 403);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RefundWorkflowException('A reason is required when clearing a refund (SOP).');
        }
        // The CFO holds the override, so for him this records the decision and
        // proceeds. For a deputy holding only refund-escalation-clear it stops
        // here — clearing an escalation must not become a way past the hold.
        $this->assertNoOpenClaimHold($r, $user, 'clearing this refund');

        // Separation of duties — the clearer must not be anyone already in the
        // chain. On the ESCALATED path approved_by is NULL, so the creator /
        // reviewer / escalator checks are what actually protect this leg.
        foreach ([
            'created_by'         => 'The creator of a request cannot clear it (separation of duties).',
            'reviewed_by'        => 'The reviewer cannot also clear the request (four-eyes).',
            'escalated_by'       => 'Whoever escalated the request cannot also clear it (four-eyes).',
            'approved_by'        => 'The administrator who approved cannot also clear the CFO gate (four-eyes).',
            'second_approved_by' => 'The second approver cannot also clear the CFO gate (four-eyes).',
        ] as $field => $message) {
            if ($user && $r->{$field} && (int) $user->id === (int) $r->{$field}) {
                throw new RefundWorkflowException($message, 403);
            }
        }

        // Re-scan: velocity/structuring can turn true after the flags were last
        // written, and an ESCALATED request may never have been scanned since
        // submit. A CRITICAL still lets the CFO through — that is the whole
        // point of the override — but it must be a DELIBERATE act, recorded.
        $this->fraud->applyScan($r);
        $fraudOverride = $this->fraud->hasCritical($r);

        // Gate B — clearing a CRITICAL-flagged refund IS the fraud override, and
        // the CFO kept that to himself. So a deputy can clear the ordinary
        // escalations and anything the engine has flagged CRITICAL still lands
        // with the CFO. Checked before the override prompt below, so the deputy
        // gets told who it needs rather than being invited to override.
        if ($fraudOverride && $user && !$user->can(RefundRequest::PERM_CFO_APPROVE)) {
            throw new RefundWorkflowException(
                'This refund carries CRITICAL fraud flags, so clearing it is a fraud override and only the CFO '
                . 'can do it. Leave it escalated and let him know.', 403);
        }

        if ($fraudOverride && !$overrideFraud) {
            throw new RefundWorkflowException(
                'This refund carries CRITICAL fraud flags. Clearing it is an override — resend with override_fraud=true '
                . 'to record it against your name.', 409);
        }

        // SOP: the client bank account must be explicitly confirmed before the
        // money leg arms — ALWAYS. An ESCALATED request skipped approve(), so
        // the confirmation is required here; the override cannot silently
        // bypass the bank check on exactly the riskiest path.
        if (!$r->bank_account_confirmed) {
            if (!$bankAccountConfirmed) {
                throw new RefundWorkflowException(
                    'You must confirm the client bank account before clearing this request (bank_account_confirmed=true).');
            }
            $r->bank_account_confirmed = true;
        }

        // An escalated refund above the two-approver threshold must still collect
        // its second signature — the CFO clearing a P200k refund alone was the
        // hole this closes. Route it back into the normal approval chain instead
        // of arming the money leg.
        $from = $r->status;
        if ($from === RefundRequest::STATUS_ESCALATED && $r->needsSecondApproval()
            && !$r->second_approved_by) {
            $r->approved_by      = $user?->id;
            $r->approved_at      = now();
            $r->approved_reason  = $reason;
            $r->cfo_approved_by  = $user?->id;
            $r->cfo_approved_at  = now();
            $r->status           = RefundRequest::STATUS_APPROVAL_PENDING_2;
            $this->saveGuarded($r, $from);

            $this->event($r, 'cfo_approve', $from, $r->status, $user, $reason, [
                'amount'                   => (float) $r->refund_amount,
                'fraud_override'           => $fraudOverride,
                'awaiting_second_approver' => true,
            ]);
            $this->notifier->readyForSecondApproval($r);
            return $r;
        }

        $r->status          = RefundRequest::STATUS_CFO_APPROVED;
        $r->cfo_approved_by = $user?->id;
        $r->cfo_approved_at = now();
        if (empty($r->approved_reason)) {
            $r->approved_reason = $reason;
        }
        $this->saveGuarded($r, $from);

        $this->event($r, 'cfo_approve', $from, $r->status, $user, $reason,
            ['amount' => (float) $r->refund_amount, 'fraud_override' => $fraudOverride]);

        $this->notifier->approved($r);
        $this->maybeHandOff($r, $user);
        return $r;
    }

    // ─── Money leg (Phase 2 — gated OFF until Finance sign-off) ─────────────

    /** Hand off to Omni when the integration is armed; a silent no-op when not. */
    public function maybeHandOff(RefundRequest $r, $user = null): void
    {
        try {
            $result = $this->omni->maybeSend($r, $user);
            if (($result['skipped'] ?? null) === 'disabled') {
                Log::info('Refund engine: Omni handoff skipped — integration disabled', [
                    'graphite_ref' => $r->graphite_ref,
                ]);
            }
        } catch (\Throwable $e) {
            // Approval must stand even if the handoff hiccups — the reconcile
            // command retries failed handoffs. Never bubble into the approve 200.
            Log::error('Refund engine: Omni handoff failed', [
                'graphite_ref' => $r->graphite_ref,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * The paid leg — called from the Omni callback AND the reconcile backstop,
     * so it is idempotent end-to-end: refund_requests, payment_refunds and the
     * ledger post (keyed REFUND-{payment_refund_id}) each no-op on re-run.
     */
    public function markPaid(RefundRequest $r, array $payload = [], ?string $source = 'callback'): RefundRequest
    {
        if ($r->status === RefundRequest::STATUS_POSTED) {
            return $r; // fully processed — duplicate callback is a no-op
        }
        // SECURITY (hardening 2026-07-30): only a refund we actually handed to
        // Omni may be marked paid. This used to accept APPROVED/CFO_APPROVED as
        // a convenience backstop, which turned the shared callback token into a
        // forgery key: a leaked token could POST {"graphite_ref":"RFND-000123",
        // "status":"paid"} against enumerable refs and manufacture a succeeded
        // payment_refunds row, a policy-ledger Refund, a queued credit note and
        // a customer-portal refund flag for money that never left FNB.
        if (!in_array($r->status, [RefundRequest::STATUS_HANDED_OFF, RefundRequest::STATUS_PAID], true)) {
            throw new RefundWorkflowException(
                "Refund {$r->graphite_ref} is {$r->status} — a paid callback is only valid once the refund has been handed to Omni.");
        }

        $paidRef = mb_substr((string) ($payload['fnb_reference'] ?? ''), 0, 120);

        // 1. The money-execution record. ensurePaymentRefund() also covers the
        //    "callback arrived but the handoff row was never created" backstop.
        $paymentRefundId = OmniHandoffService::ensurePaymentRefund($r);
        DB::table('payment_refunds')->where('id', $paymentRefundId)
            ->whereNotIn('status', ['succeeded'])
            ->update([
                'status'        => 'succeeded',
                'omni_paid_ref' => $paidRef !== '' ? $paidRef : null,
                'completed_at'  => now(),
                'updated_at'    => now(),
            ]);

        // 2. The workflow record.
        if ($r->status !== RefundRequest::STATUS_PAID) {
            $from = $r->status;
            $r->status       = RefundRequest::STATUS_PAID;
            $r->omni_status  = RefundRequest::OMNI_PAID;
            $r->omni_paid_ref = $paidRef !== '' ? $paidRef : $r->omni_paid_ref;
            $r->omni_paid_at = now();
            $r->portal_flag  = true; // customer portal shows the refund
            $r->payment_refund_id = $paymentRefundId;
            $r->save();
            $this->event($r, 'paid', $from, $r->status, null, null, [
                'source'        => $source,
                'omni_paid_ref' => $paidRef,
            ]);
            $this->notifier->paid($r);
            // CFO accounting decision (2026-07-26): a RETURN-PREMIUM refund
            // prepares a Credit Note entry for the Finance review-and-post
            // queue the moment the money is confirmed out — never auto-posted.
            try {
                $this->accounting->enqueueIfReturnPremium($r);
            } catch (\Throwable $e) {
                Log::error('Refund engine: accounting enqueue failed (retried below)', [
                    'graphite_ref' => $r->graphite_ref, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Belt-and-braces: the enqueue above only runs on the FIRST pass through
        // this block, so a transient failure there used to mean the credit note
        // was never queued and written premium stayed overstated forever (the
        // "re-runs via reconcile" comment was wrong — reconcile re-enters
        // markPaid, which skips the block once status is PAID). enqueue is
        // idempotent (unique refund_request_id), so it is safe to retry here on
        // every pass.
        try {
            $this->accounting->enqueueIfReturnPremium($r);
        } catch (\Throwable $e) {
            Log::error('Refund engine: accounting enqueue retry failed', [
                'graphite_ref' => $r->graphite_ref, 'error' => $e->getMessage(),
            ]);
        }

        // 3. Post to the policy — the existing idempotent dual-post (tx row
        //    is_refund=1 + policy_ledger 'Refund', ref REFUND-{id}). Reporting
        //    nets it automatically. A transient failure leaves the request in
        //    'paid'; the reconcile backstop re-runs this same path.
        try {
            $this->refunds->postExternalRefund($paymentRefundId);
            $from = $r->status;
            $r->status = RefundRequest::STATUS_POSTED;
            $r->save();
            $this->event($r, 'posted', $from, $r->status, null, null,
                ['payment_refund_id' => $paymentRefundId]);
        } catch (\Throwable $e) {
            Log::error('Refund engine: ledger posting failed (refund is PAID; will self-heal via reconcile)', [
                'graphite_ref'      => $r->graphite_ref,
                'payment_refund_id' => $paymentRefundId,
                'error'             => $e->getMessage(),
            ]);
        }
        return $r;
    }

    /**
     * Reveal the full bank account number to an authorised reviewer/approver so
     * they can verify it against the uploaded bank statement (CFO 2026-07-28:
     * the verifier needs the real number — not a DPA block). Every reveal is
     * written to the append-only audit trail (who looked, when). Area access is
     * asserted by the controller before this is called.
     */
    public function revealAccountNumber(RefundRequest $r, $user): string
    {
        // The reveal exists so a verifier can check the number against the bank
        // statement DURING review/approval. It is not a lookup tool for finished
        // work: allowing it in any status (including the CSV-backfilled history)
        // made it a bulk harvester — walk the ids, collect every account.
        $verifiable = [
            RefundRequest::STATUS_SUBMITTED, RefundRequest::STATUS_UNDER_REVIEW,
            RefundRequest::STATUS_ESCALATED, RefundRequest::STATUS_APPROVAL_PENDING_2,
            RefundRequest::STATUS_CFO_PENDING,
        ];
        if (!in_array($r->status, $verifiable, true)) {
            throw new RefundWorkflowException(
                'The full account number can only be revealed while a refund is under review or awaiting approval.', 403);
        }
        $number = (string) $r->account_number_encrypted; // decrypted by the model cast
        $this->event($r, 'view_account', $r->status, $r->status, $user, null,
            ['account_last4' => $r->account_last4]);
        return $number;
    }

    /**
     * Soft-delete a single refund request (Finance ask, Keetile 2026-07-30).
     * Recoverable (deleted_at only), audited, and BLOCKED once the money leg has
     * moved: a handed-off/paid/posted refund carries payment_refunds, ledger and
     * possibly credit-note records, so removing it from view would break the
     * statement and the audit trail. Fraud history still counts deleted rows
     * (the sibling queries use withTrashed) so this cannot whitewash an account.
     */
    public function softDelete(RefundRequest $r, $user, string $reason): RefundRequest
    {
        // settled_manual belongs here too: it means Finance has actually paid the
        // client outside Graphite, so the row is the ONLY record of that payment.
        // Deleting it would erase the evidence and let the same refund be raised
        // and paid a second time. Undo the manual record first if it was wrong.
        $locked = [RefundRequest::STATUS_HANDED_OFF, RefundRequest::STATUS_PAID,
                   RefundRequest::STATUS_POSTED, RefundRequest::STATUS_SETTLED_MANUAL];
        if (in_array($r->status, $locked, true)) {
            throw new RefundWorkflowException(
                "This refund is {$r->status} — money has already moved, so it cannot be deleted. "
                . 'Financial records must stay intact; raise a correction with Finance instead.', 409);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RefundWorkflowException('A clear reason (at least 10 characters) is required to delete a refund request.');
        }

        $this->event($r, 'deleted', $r->status, $r->status, $user, $reason, [
            'amount' => (float) $r->refund_amount,
            'status_at_deletion' => $r->status,
        ]);
        $r->delete(); // soft delete — restorable by clearing deleted_at
        return $r;
    }

    /**
     * Restore a soft-deleted refund request (CFO / Super Admin only — see route).
     * Reverses softDelete by clearing deleted_at so nothing is ever lost for good;
     * the restore is logged for the audit trail.
     */
    public function restore(RefundRequest $r, $user, string $reason = ''): RefundRequest
    {
        if (!$r->trashed()) {
            throw new RefundWorkflowException('This refund request is not deleted, so there is nothing to restore.', 409);
        }
        $r->restore(); // clears deleted_at
        $this->event($r, 'restored', $r->status, $r->status, $user, trim($reason) ?: null, [
            'amount' => (float) $r->refund_amount,
        ]);
        return $r;
    }

    /**
     * Record that Finance already paid this client OUTSIDE Graphite — a manual
     * FNB payment made while the Omni money leg was dark.
     *
     * Why this exists: 18 payout-eligible requests (BWP 10,739.03 on 1 Sep 2026)
     * had already been paid by hand. Arming the handoff would have paid those
     * customers a second time, and the only alternative was a hand-written
     * production UPDATE. Finance now closes them itself, with its own name on
     * each record.
     *
     * This does NOT create a payment_refunds row, post to the ledger, or queue a
     * credit note — our money leg never ran, and inventing those records would
     * misstate how the money moved. It records a fact and takes the request out
     * of the payout queue; the accounting catch-up for money paid outside the
     * system is a separate, Finance-approved step.
     *
     * Safety: the request becomes 'settled_manual', and maybeSend() only ever
     * sends 'approved'/'cfo_approved', so it can never be handed to Omni again.
     */
    public function settleManually(RefundRequest $r, $user, ?string $paidAt = null,
                                  ?string $paidRef = null, string $reason = ''): RefundRequest
    {
        $this->assertStatus($r, RefundRequest::MANUAL_SETTLE_FROM, 'settle-manually');

        // Belt and braces: if our own money leg has already touched this, the
        // manual route is the wrong tool — markPaid()/reconcile own that path.
        if ($r->omni_status !== RefundRequest::OMNI_NOT_SENT) {
            throw new RefundWorkflowException(
                "This refund has already been sent to Omni (status {$r->omni_status}) — it cannot be "
                . 'closed as a manual payment. Reconcile the Omni handoff instead.', 409);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RefundWorkflowException(
                'Please say who paid it and how it was verified (at least 10 characters) — '
                . 'this is the only record that the client was already refunded.');
        }

        $from = $r->status;
        $r->status          = RefundRequest::STATUS_SETTLED_MANUAL;
        $r->manual_paid_at  = $paidAt ?: null;
        $r->manual_paid_ref = $paidRef ? mb_substr(trim($paidRef), 0, 120) : null;
        // The row must carry its own actor. Without this the only record of WHO
        // asserted that money left the bank was the event log — which is exactly
        // what made the 2026-08-13 accounting rows 'unexplained'.
        $r->manual_paid_by  = $user?->id;
        $this->saveGuarded($r, $from);

        $this->event($r, 'settled_manual', $from, $r->status, $user, $reason, [
            'amount'          => (float) $r->refund_amount,
            'manual_paid_by'  => $user?->id,
            'manual_paid_at'  => $paidAt ?: null,
            'manual_paid_ref' => $r->manual_paid_ref,
            'no_money_moved'  => true,
        ]);
        return $r;
    }

    /**
     * Undo a manual settlement recorded in error, so a mistake never strands a
     * client's genuine refund.
     *
     * Returns the request to the status it held before it was settled, read from
     * its own audit trail. If that cannot be determined it lands in
     * 'under_review' rather than 'approved' — deliberately the safe direction:
     * a wrong guess must never re-arm a payout on its own.
     */
    public function reverseManualSettlement(RefundRequest $r, $user, string $reason = ''): RefundRequest
    {
        if ($r->status !== RefundRequest::STATUS_SETTLED_MANUAL) {
            throw new RefundWorkflowException(
                'This refund is not recorded as paid outside Graphite, so there is nothing to undo.', 409);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RefundWorkflowException(
                'Please explain why this manual payment record is being removed (at least 10 characters).');
        }

        $prior = RefundRequestEvent::where('refund_request_id', $r->id)
            ->where('action', 'settled_manual')
            ->orderByDesc('id')
            ->value('from_status');
        if (!in_array($prior, RefundRequest::MANUAL_SETTLE_FROM, true)) {
            $prior = RefundRequest::STATUS_UNDER_REVIEW;
        }

        $from = $r->status;
        $r->status          = $prior;
        $r->manual_paid_at  = null;
        $r->manual_paid_ref = null;
        $r->manual_paid_by  = null;
        $this->saveGuarded($r, $from);

        $this->event($r, 'settled_manual_reversed', $from, $r->status, $user, $reason, [
            'amount' => (float) $r->refund_amount,
        ]);
        return $r;
    }

    /**
     * Audit a PII-bearing read that is not a state change (document download,
     * CSV export). A single revealed account number was fully audited while a
     * 10,000-row export or a bank-statement download — both of which carry the
     * same or more PII — left no trace at all.
     */
    public function auditPiiAccess(?RefundRequest $r, $user, string $action, array $meta = []): void
    {
        if ($r !== null) {
            $this->event($r, $action, $r->status, $r->status, $user, null, $meta);
            return;
        }
        try {
            activity('refund_engine')
                ->causedBy($user)
                ->withProperties($meta)
                ->log("Refund engine: {$action}");
        } catch (\Throwable $e) {
            Log::warning('refund PII access audit failed: ' . $e->getMessage());
        }
    }

    /**
     * Persist a transition ONLY if nobody else has moved the request since we
     * read it — a compare-and-swap on the from-status.
     *
     * Why: every transition was read-check-save with no lock, so two users
     * acting at once both passed validation and the last write won. The
     * dangerous pair is reject + approve on the same refund: if approve
     * committed last, the row ended up APPROVED while still carrying
     * rejected_by/rejected_at, and maybeHandOff() fired — money leaving on a
     * refund a reviewer had just rejected.
     *
     * A conditional UPDATE is used rather than a row lock deliberately: these
     * methods go on to make outbound HTTP calls (the Omni handoff), and holding
     * a database lock across a network call is its own outage.
     */
    private function saveGuarded(RefundRequest $r, string $expectedStatus): void
    {
        $changes = $r->getDirty();
        if (empty($changes)) {
            return;
        }
        $changes['updated_at'] = now();

        $affected = RefundRequest::withTrashed()
            ->whereKey($r->getKey())
            ->where('status', $expectedStatus)
            ->update($changes);

        if ($affected === 0) {
            throw new RefundWorkflowException(
                'Someone else acted on this refund while you had it open. Refresh the page to see the current state, '
                . 'then try again.', 409);
        }
        $r->syncOriginal();
    }

    /**
     * Void every approval artefact on a request. Called on rejection and when a
     * material field (amount / bank account) changes, so no signature or bank
     * confirmation can ever outlive the thing it was given for.
     */
    private function clearApprovalState(RefundRequest $r): void
    {
        $r->bank_account_confirmed = false;
        $r->approved_by        = null;
        $r->approved_at        = null;
        $r->approved_reason    = null;
        $r->second_approved_by = null;
        $r->second_approved_at = null;
        $r->cfo_approved_by    = null;
        $r->cfo_approved_at    = null;
    }

    // ─── DPA helpers ─────────────────────────────────────────────────────────

    /**
     * Keyed HMAC-SHA256 blind index of a bank account number (digits only) —
     * dedupe/fraud matching without ever storing the number in clear. Mirrors
     * Omni's account_fingerprint so the two systems can compare notes.
     * Key: REFUND_ACCOUNT_INDEX_KEY env, else APP_KEY.
     */
    /**
     * Refuse a long digit run in a free-text field — a pasted card or bank
     * account number.
     *
     * Threshold is 12+ digits, counted after stripping the spaces and dashes
     * people type inside them ("4411 2103 7035 5331"). That covers every card
     * number (13–19 digits) and long account numbers, while deliberately NOT
     * tripping on a policy number such as MIS2025154632 (10 digits), which is
     * legitimate and common in a reason. A 10–11 digit account number can still
     * slip through; raising the threshold further would start rejecting real
     * text, so this is the honest trade-off rather than a complete filter.
     */
    public static function assertNoCardOrAccountNumber(string $value, string $field): void
    {
        // Collapse separators that appear BETWEEN digits only, so "12 34" reads
        // as a 4-digit run but "reference 12, total 34" does not.
        $collapsed = preg_replace('/(?<=\d)[ \-](?=\d)/', '', $value);
        if (preg_match('/\d{12,}/', (string) $collapsed)) {
            $label = str_replace('_', ' ', $field);
            throw new RefundWorkflowException(
                "That looks like a card or bank account number in the {$label} field. "
                . 'Never type card or account numbers into free text — it is stored unencrypted and kept in backups. '
                . 'Put the client bank account in the account number field instead, and attach the bank statement.');
        }
    }

    public static function accountFingerprint(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        // Canonicalise: leading zeros are not significant to the bank, but they
        // WERE significant to the hash — so "0621..." and "621..." fingerprinted
        // as two different accounts while FNB paid the same one, defeating the
        // same-account fraud check with a single keystroke.
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return '';
        }
        // config() not env(): under `php artisan config:cache` env() returns null
        // at runtime, which silently fell back to APP_KEY and changed every
        // fingerprint. Must be byte-identical to Omni's REFUND_ACCOUNT_INDEX_KEY
        // or cross-system matching returns zero hits while looking healthy.
        $key = (string) (config('services.omni_refunds.account_index_key') ?: config('app.key'));
        return hash_hmac('sha256', $digits, $key);
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    /** Creator-editable fields (identity/money/bank/SOP flags). */
    private function fillEditable(RefundRequest $r, array $data): void
    {
        foreach (['policy_number', 'product_name', 'customer_name', 'agent_name',
                  'reason', 'reason_code', 'bank_name', 'branch_name', 'branch_code'] as $f) {
            if (array_key_exists($f, $data)) {
                $value = $data[$f] !== null ? trim((string) $data[$f]) : null;
                if ($value !== null && in_array($f, self::PII_GUARDED_FIELDS, true)) {
                    self::assertNoCardOrAccountNumber($value, $f);
                }
                $r->{$f} = $value;
            }
        }
        if (array_key_exists('collection_method', $data)) {
            $raw = strtoupper(trim((string) $data['collection_method']));
            $canonical = null;
            foreach (RefundRequest::COLLECTION_METHODS as $opt) {
                if (strtoupper($opt) === $raw) { $canonical = $opt; break; }
            }
            if ($raw !== '' && $canonical === null) {
                throw new RefundWorkflowException(
                    'collection_method must be one of: ' . implode(', ', RefundRequest::COLLECTION_METHODS) . '.');
            }
            $r->collection_method = $canonical;
        }
        if (array_key_exists('vehicle_not_client', $data)) {
            $r->vehicle_not_client = (bool) $data['vehicle_not_client'];
        }
        if (array_key_exists('ai_greenlight', $data)) {
            $r->ai_greenlight = (bool) $data['ai_greenlight'];
        }
        if (array_key_exists('ai_evidence', $data) && is_array($data['ai_evidence'])) {
            $r->ai_evidence = $data['ai_evidence'];
        }
        if (array_key_exists('account_number', $data)) {
            $raw = trim((string) $data['account_number']);
            $r->account_number_encrypted = $raw !== '' ? $raw : null; // 'encrypted' cast
            $r->account_last4            = strlen($raw) >= 4 ? substr($raw, -4) : $raw;
            $r->account_number_bindex    = $raw !== '' ? self::accountFingerprint($raw) : null;
        }
    }

    /** Best-effort enrich from the policy — authoritative ids for the ledger post. */
    private function resolvePolicy(RefundRequest $r): void
    {
        if (empty($r->policy_number)) {
            throw new RefundWorkflowException('policy_number is required.');
        }
        $policy = DB::table('policies')->where('policyNumber', $r->policy_number)
            ->first(['id', 'customer_id', 'premium']);
        if ($policy) {
            $r->policy_id   = $policy->id;
            $r->customer_id = $policy->customer_id;
        }
    }

    private function assertCreatorOrAdmin(RefundRequest $r, $user): void
    {
        if (!$user) {
            throw new RefundWorkflowException('Unauthenticated.', 403);
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }
        if ($r->created_by && (int) $user->id !== (int) $r->created_by) {
            throw new RefundWorkflowException(
                'A submitted request is locked to its creator — only the creator can edit or resubmit it.', 403);
        }
    }

    private function assertStatus(RefundRequest $r, array $allowed, string $action): void
    {
        if (!in_array($r->status, $allowed, true)) {
            throw new RefundWorkflowException(
                "Cannot {$action} a {$r->status} request (allowed: " . implode(', ', $allowed) . ').');
        }
    }

    /** Append-only audit trail + Spatie activity (belt-and-braces). */
    private function event(RefundRequest $r, string $action, ?string $from, ?string $to,
                           $user = null, ?string $note = null, ?array $meta = null): void
    {
        RefundRequestEvent::create([
            'refund_request_id' => $r->id,
            'from_status'       => $from,
            'to_status'         => $to,
            'action'            => $action,
            'actor_id'          => $user?->id,
            'actor_name'        => $user?->name ?? $user?->email ?? null,
            'note'              => $note,
            'meta'              => $meta,
            'created_at'        => now(),
        ]);

        try {
            activity('refund_engine')
                ->performedOn($r)
                ->causedBy($user)
                ->withProperties(array_merge($meta ?? [], [
                    'graphite_ref' => $r->graphite_ref,
                    'from'         => $from,
                    'to'           => $to,
                ]))
                ->log("Refund {$r->graphite_ref}: {$action}" . ($note ? " — {$note}" : ''));
        } catch (\Throwable $e) {
            Log::warning('refund_engine activity log failed: ' . $e->getMessage());
        }
    }
}
