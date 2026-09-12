<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    use HasApiTokens;
    protected $auditTimestamps = true;

    use Notifiable;
    use HasRoles;
	use TwoFactorAuthenticatable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'password',
        'api_token',
        'pin',
        'llmUserStatus',
        'company_id',
        'agency_id',
        'commission',
        'is_graphite_login',
        'is_report_login',
        'bypass_500k',
        'is_first_login',
        'active',
    ];

    protected $dates = ['created_at', 'updated_at', 'dob', 'password_changed_at', 'last_login_at'];

    protected $table = 'users';
   
    public function getFirstNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    public function getLastNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }
   
	public function getFullNameAttribute($value)
	{
		return ucwords(strtolower($this->firstName)) .' '. ucwords(strtolower($this->lastName));
	}
   

    /**
     * `name`, for the many call sites that ask for it.
     *
     * THERE IS NO `name` COLUMN ON `users` — the table carries `firstName` and
     * `lastName`. Every `Auth::user()->name` in the codebase was therefore
     * reading an attribute that does not exist and getting null, and getting it
     * SILENTLY, because they are all written as `?? null` or `?: something`.
     *
     * That is why the FAC slip printed "PREPARED BY" with nothing after it, and
     * why `fac_placements.underwriter_name` is null on all six rows captured so
     * far: both default to `Auth::user()->name`. The same null reached
     * `captured_by_name` and `uploaded_by_name`. One site had already been
     * patched by hand with `?? Auth::user()->firstName` (FacRegisterService's
     * actor_name) — this fixes the class of bug once instead of once per site.
     *
     * Delegates to `full_name`, the idiom this model already had, so there is
     * one definition of a display name here and not two.
     *
     * Null rather than '' when both halves are missing, so the `?:` and `??`
     * fallbacks already written against it keep behaving as they do now.
     *
     * NOT added to $appends deliberately: that would put `name` on every
     * serialised user and change existing API payloads.
     */
    public function getNameAttribute(): ?string
    {
        $name = trim((string) $this->full_name);

        return $name !== '' ? $name : null;
    }

    public function banking()
    {
        return $this->hasOne('AlphaDirect\CustomerBanking', 'customer_id', 'id')->select();
    }

    public function agentType()
    {
        return $this->belongsTo('AlphaDirect\AgentTypes', 'bitrixId');
    }

    public function policy()
    {
        return $this->hasMany('AlphaDirect\Policy', 'agent_id', 'id');
    }

   

	public function agency(){
		return $this->belongsTo(\AlphaDirect\Agency::class, 'agency_id');
	}

	public function profile()
    {
        return $this->hasOne(\AlphaDirect\UserProfile::class,'user_id','id');
    }
}
