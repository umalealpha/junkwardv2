<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A RealPay settlement Excel upload and its preview -> confirm lifecycle.
 * See migration create_realpay_settlement_imports_table for the status states.
 */
class RealpaySettlementImport extends Model
{
    protected $table = 'realpay_settlement_imports';

    protected $fillable = [
        'file_name', 'file_path', 'uploaded_by', 'status',
        'row_count', 'preview_summary', 'commit_summary', 'error',
    ];

    protected $casts = [
        'preview_summary' => 'array',
        'commit_summary'  => 'array',
        'row_count'       => 'integer',
    ];

    public const STATUS_UPLOADED      = 'uploaded';
    public const STATUS_PREVIEWING    = 'previewing';
    public const STATUS_PREVIEW_READY = 'preview_ready';
    public const STATUS_COMMITTING    = 'committing';
    public const STATUS_COMMITTED     = 'committed';
    public const STATUS_FAILED        = 'failed';
}
