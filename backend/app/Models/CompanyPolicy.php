<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyPolicy extends Model
{
    use HasFactory;
    protected $table = 'company_policies';
    public function company()
    {
        return $this->belongsTo('AlphaDirect\Models\CompanyName', 'company_id');
    }
}
