<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;


class UserPassword extends Model
{
    use Notifiable;
    protected $table = 'new_user_password_url';

    protected $guarded = ['id'];
    
    protected $guard = 'new_user_password_url';

    protected $hidden = [
        'password', 'api_token',
    ];
}
