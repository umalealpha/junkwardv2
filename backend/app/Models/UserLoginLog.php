<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only auth activity row (login / logout / failed / sso_blocked).
 * Single created_at timestamp set explicitly on insert — no updated_at.
 */
class UserLoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'created_at',
    ];
}
