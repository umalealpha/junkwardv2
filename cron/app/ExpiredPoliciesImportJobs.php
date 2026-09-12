<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use  AlphaDirect\Country;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class ExpiredPoliciesImportJobs extends Model
{
    use HasFactory;

    protected $table = 'expired_policies_import';
    protected $guarded = ['id'];


    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'policyNumber', 'policyNumber');
    }
}
