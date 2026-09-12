<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RefundRequest — the governance/workflow record of the Customer Refund Engine.
 *
 * Lifecycle (RefundRequestService owns every transition):
 *   draft → submitted → under_review → approved | rejected | escalated
 *   approved → cfo_pending (any area > P50k) → cfo_approved
 *   approved/cfo_approved → handed_off → paid → posted
 *   rejected → (creator edits) → submitted
 *
 * The money-execution record stays payment_refunds (created at Omni handoff,
 * source='omni'); this entity carries the SOP: roles, area separation,
 * documents, escalation, the CFO gate and the Omni lifecycle.
 *
 * DPA: account_number_encrypted uses Laravel's `encrypted` cast — never store
 * or log the raw number. account_number_bindex is a keyed-HMAC blind index
 * (see RefundRequestService::accountFingerprint) and account_last4 is the only
 * display form. The full number is decrypted ONLY at Omni handoff time.
 */
class RefundRequest extends Model
{
    use SoftDeletes;

    // ── Statuses ─────────────────────────────────────────────────────────────
    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED     = 'approved';
    /** > P5,000: first approval recorded, awaiting a second distinct approver. */
    public const STATUS_APPROVAL_PENDING_2 = 'approval_pending_2';
    public const STATUS_CFO_PENDING  = 'cfo_pending';
    public const STATUS_CFO_APPROVED = 'cfo_approved';
    public const STATUS_REJECTED     = 'rejected';
    public const STATUS_ESCALATED    = 'escalated';
    public const STATUS_HANDED_OFF   = 'handed_off';
    public const STATUS_PAID         = 'paid';
    public const STATUS_POSTED       = 'posted';
    /**
     * Finance already paid this client outside Graphite (manual FNB payment
     * while the Omni money leg was dark). A terminal state that deliberately
     * does NOT mean our money leg ran: no payment_refunds row, no ledger post.
     * Its whole job is to take the request out of the payout queue — and that
     * falls out for free, because OmniHandoffService::maybeSend() only sends
     * 'approved' / 'cfo_approved'.
     */
    public const STATUS_SETTLED_MANUAL = 'settled_manual';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED, self::STATUS_APPROVAL_PENDING_2,
        self::STATUS_CFO_PENDING, self::STATUS_CFO_APPROVED,
        self::STATUS_REJECTED, self::STATUS_ESCALATED, self::STATUS_HANDED_OFF,
        self::STATUS_PAID, self::STATUS_POSTED, self::STATUS_SETTLED_MANUAL,
    ];

    /**
     * States a request may be marked settled-outside-Graphite from. Everything
     * before a decision (draft/submitted/under_review) is excluded — closing
     * those would skip review entirely — and so is anything our own money leg
     * has touched (handed_off/paid/posted), which markPaid() owns.
     */
    public const MANUAL_SETTLE_FROM = [
        self::STATUS_APPROVED, self::STATUS_CFO_APPROVED,
        self::STATUS_APPROVAL_PENDING_2, self::STATUS_ESCALATED,
        self::STATUS_CFO_PENDING,
    ];

    // ── Collection methods (how the premium was collected — SOP field) ──────
    // Expanded on Finance's request (Keetile 2026-07-28). Values fit the 12-char
    // column. Stored in this canonical casing.
    public const COLLECTION_METHODS = ['DPO', 'RealPay', 'VCS', 'PM8', 'CASH', 'N-GENIUS'];

    /**
     * Two-approver threshold (CFO 2026-07-28): refunds ABOVE P5,000 need a
     * second distinct approver before the money leg arms; at/under it a single
     * approval stands. Independent of the >P50k CFO gate below.
     */
    public const DUAL_APPROVAL_THRESHOLD = 5000.00;

    public function needsSecondApproval(): bool
    {
        return (float) $this->refund_amount > self::DUAL_APPROVAL_THRESHOLD;
    }

    // ── Areas (segment naming shared with Omni) ─────────────────────────────
    public const AREA_MIS        = 'mis';
    public const AREA_DOMESTIC   = 'domestic';
    public const AREA_COMMERCIAL = 'commercial';
    public const AREAS = [self::AREA_MIS, self::AREA_DOMESTIC, self::AREA_COMMERCIAL];

    // ── Omni handoff states ──────────────────────────────────────────────────
    public const OMNI_NOT_SENT = 'not_sent';
    public const OMNI_SENT     = 'sent';
    public const OMNI_PAID     = 'paid';
    public const OMNI_FAILED   = 'failed';

    /**
     * The two permissions that can clear a held refund, and they are NOT equal.
     *
     * CFO 2026-09-02: *"Bharath clears escalations only. The over-P50,000 gate
     * and the fraud-flag override stay with me alone, so my 24 August rule
     * holds."*
     *
     *   PERM_CFO_APPROVE       everything — the >P50k gate, and clearing an
     *                          escalation whether or not it is fraud-flagged
     *                          (clearing a CRITICAL one IS the fraud override)
     *   PERM_ESCALATION_CLEAR  escalations ONLY, and only while the request
     *                          carries no CRITICAL fraud flag
     *
     * Enforced in RefundRequestService::cfoApprove(). The route accepts either;
     * the service decides which is sufficient for the request in front of it.
     */
    public const PERM_CFO_APPROVE      = 'refund-cfo-approve';
    public const PERM_ESCALATION_CLEAR = 'refund-escalation-clear';

    /**
     * Refunds above this need the CFO step (decision-gate B). Applies to ALL
     * areas — MIS, Domestic and Commercial. It was commercial-only while the
     * routing recipient was undecided; the CFO settled it on 2026-08-24: the
     * gate is about the AMOUNT leaving the bank, not which book it sits in.
     */
    public const CFO_THRESHOLD = 50000.00;

    // ── Reason-code taxonomy (CFO accounting decision, 2026-07-26) ──────────
    // RETURN-PREMIUM reasons additionally raise a Credit Note / premium
    // reversal into the Finance review-and-post queue when paid (written +
    // earned premium drop). PLAIN reasons are cash-only — the is_refund
    // netting is their whole accounting story.
    public const RETURN_PREMIUM_REASON_CODES = [
        'cooling_off'    => 'Cooling-off cancellation',
        'cancellation'   => 'Policy cancellation',
        'over_insurance' => 'Over-insurance',
        'duplicate_cover'=> 'Duplicate cover',
    ];
    public const PLAIN_REASON_CODES = [
        'overpayment'  => 'Overpayment',
        'double_debit' => 'Double debit',
        'goodwill'     => 'Goodwill / service recovery',
        'other'        => 'Other (cash refund only)',
    ];

    public function isReturnPremium(): bool
    {
        return array_key_exists((string) $this->reason_code, self::RETURN_PREMIUM_REASON_CODES);
    }

    protected $table = 'refund_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'account_number_encrypted' => 'encrypted',
        'ai_evidence'              => 'array',
        'fraud_flags'              => 'array',
        'fraud_reviewed_at'        => 'datetime',
        'ai_greenlight'            => 'boolean',
        'vehicle_not_client'       => 'boolean',
        'bank_account_confirmed'   => 'boolean',
        'after_cutoff'             => 'boolean',
        'portal_flag'              => 'boolean',
        'refund_amount'            => 'float',
        'submitted_at'             => 'datetime',
        'reviewed_at'              => 'datetime',
        'approved_at'              => 'datetime',
        'cfo_approved_at'          => 'datetime',
        'rejected_at'              => 'datetime',
        'escalated_at'             => 'datetime',
        'handed_off_at'            => 'datetime',
        'omni_paid_at'             => 'datetime',
        'manual_paid_at'           => 'date',
    ];

    /** Never serialise the encrypted account number into API responses. */
    protected $hidden = ['account_number_encrypted', 'account_number_bindex'];

    public function documents()
    {
        return $this->hasMany(RefundRequestDocument::class, 'refund_request_id');
    }

    public function events()
    {
        return $this->hasMany(RefundRequestEvent::class, 'refund_request_id');
    }

    /** The Spatie permission that grants access to this request's area. */
    public function areaPermission(): string
    {
        return $this->area === self::AREA_MIS ? 'refund_area_mis' : 'refund_area_dc';
    }

    public function needsCfoApproval(): bool
    {
        return (float) $this->refund_amount > self::CFO_THRESHOLD;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }
}
