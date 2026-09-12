<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefundAccountingEntry — one row in the Finance review-and-post queue.
 *
 * Created automatically when a RETURN-PREMIUM refund reaches 'posted' (money
 * confirmed out via Omni). NEVER auto-posted: Finance reviews the prepared
 * figures (may adjust the earned/unearned split and dates, bounded by the
 * refund amount) and posts, which raises the Credit Note + ledger reversal
 * via RefundCreditNoteService — written premium drops and the numbers tie.
 * (CFO decision, 2026-07-26.)
 */
class RefundAccountingEntry extends Model
{
    public const STATUS_PENDING   = 'pending_review';
    /** Transient claim while a post is in flight — blocks a concurrent poster. */
    public const STATUS_POSTING   = 'posting';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_DISMISSED = 'dismissed';
    /**
     * Neutralised without being destroyed. For an entry that should never have
     * existed — the 11 rows written straight into PROD on 2026-08-13 are the
     * reason this exists. DISMISSED is a Finance decision on a real entry it has
     * reviewed; VOIDED says the entry itself is not evidence of anything.
     *
     * Voiding releases the refund's slot (see void_marker) so the genuine credit
     * note can finally be raised. The row is kept: it is the only trace of an
     * unexplained write to a production accounting table.
     */
    public const STATUS_VOIDED    = 'voided';

    protected $table = 'refund_accounting_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'voided_at'        => 'datetime',
        'refund_amount'    => 'float',
        'earned_premium'   => 'float',
        'unearned_premium' => 'float',
        'effective_date'   => 'date',
        'end_date'         => 'date',
        'posted_at'        => 'datetime',
        'dismissed_at'     => 'datetime',
    ];

    public function refundRequest()
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }
}
