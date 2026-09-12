<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Union — a Legal Insurance Group Scheme (BONU, BOWASEWU, …).
 *
 * Each union owns one auto-created group policy (policy_id → policies.id, number
 * `policy_number`) on product_id 4 (Legal). The union is mapped to one Legal
 * Insurance product (`plan_id` → product_plans.id) and `monthly_premium` is
 * DERIVED from that plan's price on every save — treat the plan as the input and
 * the premium as its cached result. Total monthly premium = active members ×
 * monthly_premium.
 *
 * Implements OwenIt Auditable so create / update / status-change activities are
 * recorded in `audits` (user, action, old + new values) per the audit-log
 * requirement — writes therefore go through the model, not the query builder.
 */
class Union extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'unions';
    protected $auditTimestamps = true;

    protected $fillable = [
        'union_name', 'union_code', 'description', 'product_id', 'plan_id',
        'policy_id', 'policy_number', 'monthly_premium',
        'effective_date', 'expiry_date',
        'contact_person', 'contact_number', 'email', 'address',
        'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'monthly_premium' => 'decimal:2',
        'product_id'      => 'integer',
        'plan_id'         => 'integer',
        'policy_id'       => 'integer',
        'status'          => 'integer',
        'effective_date'  => 'date',
        'expiry_date'     => 'date',
    ];

    public function members()
    {
        return $this->hasMany(UnionMember::class, 'union_id');
    }

    public function policy()
    {
        return $this->belongsTo(\AlphaDirect\Policy::class, 'policy_id', 'id');
    }

    /**
     * The Legal Insurance product this union is registered under — a
     * `product_plans` row with product_id = 4. It is the source of
     * `monthly_premium`, which the controller derives on every save.
     */
    public function plan()
    {
        return $this->belongsTo(\AlphaDirect\Productplan::class, 'plan_id', 'id');
    }

    /** Active member count — drives the total-monthly-premium calc. */
    public function activeMembersCount(): int
    {
        return (int) $this->members()->whereNull('deleted_at')->where('status', 1)->count();
    }
}
