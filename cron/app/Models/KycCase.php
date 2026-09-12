<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycCase extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id','status','sanctions_max','decision'];

    protected $casts = [
        'decision' => 'array', // Remove encryption to avoid size constraints
        'sanctions_max' => 'decimal:3',
    ];

    public function amlResults(){ return $this->hasMany(AmlResult::class); }
}
