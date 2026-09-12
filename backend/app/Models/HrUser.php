<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class HrUser extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'hr_users';

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'password',
        'employer_group_id',
        'is_active',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function employerGroup()
    {
        return $this->belongsTo(EmployerGroup::class, 'employer_group_id');
    }
}
