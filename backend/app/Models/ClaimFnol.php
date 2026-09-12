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
        'reported_date',
        'claim_allocated_on',
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
        // ── Claims-Tracker "New Claim" Basic-Information fields (additive; only
        //    written by the unified tracker-style FNOL create form, flag-gated) ──
        'channel',
        'broker_name',
        'claims_handler',
        'plate_number',
        'reserve_amount',
        'claim_paid_amount',
        'customer_type',
        'customer_type_other',
        'non_motor_sub_type',
        'assessor',
        'assessor_other',
        'glass_supplier',
        'glass_supplier_other',
        'reinsurer',
        'is_fac',
        'comment_status',
        'comment_sub_reason',
        // Tracker stage-timeline captured at intake (no claim_id yet); applied to
        // claim_tracker_workflow on convert. Keys mirror EDITABLE_FIELDS.
        'stage_data',
    ];

    protected $casts = [
        'policy_id'          => 'integer',
        'loss_date'          => 'date',
        'reported_date'      => 'date',
        'claim_allocated_on' => 'date',
        'estimate_amount'    => 'decimal:2',
        'outstanding_docs'   => 'array',
        'reminder_count'     => 'integer',
        'last_reminder_at'   => 'datetime',
        'converted_claim_id' => 'integer',
        // Tracker Basic-Info numeric / boolean fields.
        'reserve_amount'     => 'decimal:2',
        'claim_paid_amount'  => 'decimal:2',
        'is_fac'             => 'boolean',
        'stage_data'         => 'array',
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
            // ── Claims-Tracker "New Claim" Basic-Information fields (additive) ──
            'channel'              => $this->channel,
            'broker_name'          => $this->broker_name,
            'claims_handler'       => $this->claims_handler,
            'plate_number'         => $this->plate_number,
            'reserve_amount'       => $this->reserve_amount !== null ? (float) $this->reserve_amount : null,
            'claim_paid_amount'    => $this->claim_paid_amount !== null ? (float) $this->claim_paid_amount : null,
            'customer_type'        => $this->customer_type,
            'customer_type_other'  => $this->customer_type_other,
            'non_motor_sub_type'   => $this->non_motor_sub_type,
            'assessor'             => $this->assessor,
            'assessor_other'       => $this->assessor_other,
            'glass_supplier'       => $this->glass_supplier,
            'glass_supplier_other' => $this->glass_supplier_other,
            'reinsurer'            => $this->reinsurer,
            'is_fac'               => (bool) $this->is_fac,
            'comment_status'       => $this->comment_status,
            'comment_sub_reason'   => $this->comment_sub_reason,
            'claim_allocated_on'   => optional($this->claim_allocated_on)->toDateString(),
            'stage_data'           => is_array($this->stage_data) ? $this->stage_data : null,
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
