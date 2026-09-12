<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class UserProfile extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $fillable = ['user_id',
        'department_id',
        'dob',
        'gender',
        'omang',
        'passport',
        'address',
        'cellphone',
        'created_at',
        'updated_at',
        'profile_photo',
        'work_position',
        'uf_department',
        'uf_interest',
        'uf_skills',
        'work_phone',
        'work_phone_inner',
        'whatsapp_access',
        'whatsapp_visibility',
    ];
    protected $table = 'user_profile';

    public function user()
    {
        return $this->belongsTo('AlphaDirect\User', 'id', 'user_id');
    }
}
