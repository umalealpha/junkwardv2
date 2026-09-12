<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * UnionMember — a person enrolled under a union's group scheme.
 *
 * Uses the Members-module fields (id_number, member_name, member_type,
 * nationality …) while keeping the group-scheme linkage: customer_id (a full
 * customer + customer_profile record) and policy_id (the union's group policy).
 *
 * Auditable so create / update / status / import activity is recorded in
 * `audits` (member-activity audit-log requirement) — writes go through the model.
 */
class UnionMember extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'union_members';
    protected $auditTimestamps = true;

    protected $fillable = [
        'union_id', 'policy_id', 'customer_id',
        'id_number', 'member_name', 'member_type', 'date_of_birth', 'gender',
        'contact_number', 'email', 'nationality', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'gender'        => 'integer',
        'status'        => 'integer',
    ];

    public function union()
    {
        return $this->belongsTo(Union::class, 'union_id');
    }

    public function customer()
    {
        return $this->belongsTo(\AlphaDirect\Customer::class, 'customer_id', 'id');
    }

    public function policy()
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id', 'id');
    }
}
