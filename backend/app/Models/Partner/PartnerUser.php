<?php

namespace AlphaDirect\Models\Partner;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/** A person at a partner company who may sign in on start. */
class PartnerUser extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $connection = 'mysql_system';
    protected $table      = 'partner_users';

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'is_active',
        'password_set_at', 'last_login_at', 'last_login_ip',
        'failed_logins', 'locked_until', 'created_by_user_id',
    ];

    protected $hidden = ['password'];

    /** Never store credential or counter churn in the audit trail. */
    protected $auditExclude = ['password', 'failed_logins', 'locked_until', 'last_login_at', 'last_login_ip'];

    protected $casts = [
        'is_active'       => 'boolean',
        'password_set_at' => 'datetime',
        'last_login_at'   => 'datetime',
        'locked_until'    => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(PartnerCompany::class, 'company_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
