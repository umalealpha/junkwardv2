<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * UnionLegalClaim — the BONU Legal Claim Form filed against a union member.
 *
 * Links the union, member, group policy and member's customer, and carries every
 * field on the paper form. A standard claim (claims/new_claims/claim_legal) is
 * created alongside it so the claim is also visible in the Claims module —
 * `claim_id` / `claim_number` reference that record. Audited so filing activity
 * is recorded (writes go through the model).
 */
class UnionLegalClaim extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'union_legal_claims';
    protected $auditTimestamps = true;

    protected $fillable = [
        'union_id', 'union_member_id', 'policy_id', 'customer_id',
        'claim_id', 'claim_number', 'form_code', 'form_name',
        'policy_number', 'insured_name', 'region', 'omang_passport',
        'cellphone', 'email', 'claim_type',
        'matter_relates_to', 'child_financially_dependent',
        'dependent_omang_passport', 'dependent_dob',
        'matter_type', 'matter_arose_date', 'proposed_course_of_action',
        'declaration_signed', 'signatory_name', 'signed_date',
        'documentation_checklist', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'union_id'                    => 'integer',
        'union_member_id'             => 'integer',
        'policy_id'                   => 'integer',
        'customer_id'                 => 'integer',
        'claim_id'                    => 'integer',
        'child_financially_dependent' => 'integer',
        'declaration_signed'          => 'integer',
        'dependent_dob'               => 'date',
        'matter_arose_date'           => 'date',
        'signed_date'                 => 'date',
        'documentation_checklist'     => 'array',
    ];

    public function union()
    {
        return $this->belongsTo(Union::class, 'union_id');
    }

    public function member()
    {
        return $this->belongsTo(UnionMember::class, 'union_member_id');
    }

    public function policy()
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(\AlphaDirect\Customer::class, 'customer_id', 'id');
    }
}
