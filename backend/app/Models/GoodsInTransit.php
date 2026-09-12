<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsInTransit extends Model
{
    use HasFactory;

    protected $table = 'goods_in_transit_claim';

    protected $guarded = ['id'];

    protected $casts = [
        'is_carrier_contracted'         => 'integer',
        'carrier_has_own_GIT_ins'       => 'integer',
        'other_insurance_against_theft' => 'integer',
    ];
}
