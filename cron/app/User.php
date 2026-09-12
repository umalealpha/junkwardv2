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

    protected $fillable = ['firstName', 'lastName', 'accountType', 'email', 'password','api_token'];

    protected $dates = ['created_at', 'updated_at', 'dob', 'password_changed_at'];

    protected $table = 'users';

    public function getFirstNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    public function getLastNameAttribute($value)
    {
        return ucwords(strtolower($value));
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
