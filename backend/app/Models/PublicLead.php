<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Public-facing lead capture from start.alphadirect.co.bw.
 *
 * Distinct from AlphaDirect\Lead (agent CRM lead codes tied to customers).
 * A row here represents either a callback request or a support-ticket
 * intake from a visitor with no customer record yet.
 */
class PublicLead extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $table = 'public_leads';

    protected $fillable = [
        'kind', 'full_name', 'cellphone', 'email', 'product',
        'policy_number', 'category', 'message',
        'vehicle_details', 'sum_insured', 'quote_reference', 'underwriting_notified_at',
        'status', 'assigned_user_id', 'resolution_note', 'resolved_at',
        'ip', 'user_agent', 'source',
    ];

    protected $casts = [
        'resolved_at'               => 'datetime',
        'underwriting_notified_at'  => 'datetime',
        'sum_insured'               => 'decimal:2',
    ];

    public const KIND_CALLBACK = 'callback';
    public const KIND_ISSUE    = 'issue';

    public const STATUS_NEW         = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_SPAM        = 'spam';
}
