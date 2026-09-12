<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelPolicy extends Model
{
    use HasFactory;

    protected $table = 'cancel_policies';
    protected $guarded = ['id'];
}
