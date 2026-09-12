<?php

namespace AlphaDirect\Models\Partner;

use Illuminate\Database\Eloquent\Model;

/** Opaque partner session token (stored hashed). */
class PartnerUserToken extends Model
{
    protected $connection = 'mysql_system';
    protected $table      = 'partner_user_tokens';

    protected $fillable = ['partner_user_id', 'token_hash', 'expires_at', 'revoked_at', 'last_used_at', 'ip'];

    protected $casts = [
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(PartnerUser::class, 'partner_user_id');
    }
}
