<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A First Notification of Loss (FNOL) intake record.
 *
 * Additive + inert: nothing reads/writes this model until the `claims_fnol`
 * runtime flag is on. An FNOL captures a reported loss that is not yet a
 * registrable claim; on convert it spawns a real Graphite claim (via the
 * existing ClaimsController::store path) and is marked converted.
 *
 * @property int         $id
 * @property string      $fnol_number
 * @property string      $claimant_name
 * @property string|null $policy_number
 * @property int|null    $policy_id
 * @property string|null $claim_type
 * @property string|null $loss_date
 * @property string      $description
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property float|null  $estimate_amount
 * @property array       $outstanding_docs
 * @property string      $status              open|converted|closed
 * @property int         $reminder_count
 * @property string|null $last_reminder_at
 * @property int|null    $converted_claim_id
 * @property string      $source
 * @property string|null $external_ref
 */
class ClaimFnol extends Model
{
    protected $table = 'claim_fnol';

    public const STATUS_OPEN      = 'open';
    public const STATUS_CONVERTING = 'converting';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CLOSED    = 'closed';

    protected $fillable = [
        'fnol_number',
        'claimant_name',
        'policy_number',
        'policy_id',
        'claim_type',
        'loss_date',
        'description',
        'contact_phone',
        'contact_email',
        'estimate_amount',
        'outstanding_docs',
        'status',
        'reminder_count',
        'last_reminder_at',
        'converted_claim_id',
        'source',
        'external_ref',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'policy_id'          => 'integer',
        'loss_date'          => 'date',
        'estimate_amount'    => 'decimal:2',
        'outstanding_docs'   => 'array',
        'reminder_count'     => 'integer',
        'last_reminder_at'   => 'datetime',
        'converted_claim_id' => 'integer',
    ];

    /**
     * The canonical JSON shape returned by the API. Hand-built (not $appends)
     * so the contract is explicit and stable for the frontend.
     */
    public function toApiArray(): array
    {
        return [
            'id'                 => (int) $this->id,
            'fnol_number'        => $this->fnol_number,
            'claimant_name'      => $this->claimant_name,
            'policy_number'      => $this->policy_number,
            'policy_id'          => $this->policy_id !== null ? (int) $this->policy_id : null,
            'claim_type'         => $this->claim_type,
            'loss_date'          => optional($this->loss_date)->toDateString(),
            'description'        => $this->description,
            'contact_phone'      => $this->contact_phone,
            'contact_email'      => $this->contact_email,
            'estimate_amount'    => $this->estimate_amount !== null ? (float) $this->estimate_amount : null,
            'outstanding_docs'   => is_array($this->outstanding_docs) ? array_values($this->outstanding_docs) : [],
            'status'             => $this->status,
            'reminder_count'     => (int) $this->reminder_count,
            'last_reminder_at'   => optional($this->last_reminder_at)->toIso8601String(),
            'converted_claim_id' => $this->converted_claim_id !== null ? (int) $this->converted_claim_id : null,
            'source'             => $this->source,
            'external_ref'       => $this->external_ref,
            'created_at'         => optional($this->created_at)->toIso8601String(),
            'updated_at'         => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Reserve the next FNOL number of the form FNOL{YEAR}{6-digit-seq}. The
     * sequence is the table's max id + 1 (mirrors ClaimsController::store's
     * G{YEAR}{id} scheme). Called inside the create path.
     */
    public static function nextFnolNumber(): string
    {
        $nextId = (int) (static::max('id') ?? 0) + 1;
        return 'FNOL' . date('Y') . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}
