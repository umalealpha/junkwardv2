<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationRuleGroupMasterDetail extends Model
{
    use HasFactory;
    protected $table = "tb_prvalidationrulegroupdetails";

    public function ValidationRuleMaster()
    {
        return $this->belongsTo(ValidationRuleMaster::class);
    }
}
