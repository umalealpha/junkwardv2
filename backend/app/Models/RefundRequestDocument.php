<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefundRequestDocument — supporting evidence for a refund request.
 *
 * SOP rules (enforced in RefundRequestService, not the DB):
 *   - a bank_statement OR bank_confirmation is ALWAYS required before submit;
 *   - an affidavit is additionally required when vehicle_not_client is set.
 *
 * Files live on S3 (bucket SSE) and are served only through the area-scoped,
 * Bearer-authenticated download endpoint — never a public URL.
 */
class RefundRequestDocument extends Model
{
    public const TYPE_BANK_STATEMENT    = 'bank_statement';
    public const TYPE_BANK_CONFIRMATION = 'bank_confirmation';
    public const TYPE_AFFIDAVIT         = 'affidavit';
    public const TYPE_OTHER             = 'other';

    public const TYPES = [
        self::TYPE_BANK_STATEMENT, self::TYPE_BANK_CONFIRMATION,
        self::TYPE_AFFIDAVIT, self::TYPE_OTHER,
    ];

    /** Types that satisfy the "bank verification always required" rule. */
    public const BANK_PROOF_TYPES = [self::TYPE_BANK_STATEMENT, self::TYPE_BANK_CONFIRMATION];

    protected $table = 'refund_request_documents';

    protected $guarded = ['id'];

    public function refundRequest()
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }
}
