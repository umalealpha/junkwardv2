<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyExtentionDetails;

class Extention extends Model
{
    use HasFactory;
    protected $table = 'extentions';

    
    public function scopeMainCoveragesCode($query){
        $query->where('s_SubCoverageID',null);
    }
    public function scopeSubCoveragesCode($query,$subCoverageID){
        $query->where('s_SubCoverageID',$subCoverageID);
    }

    public function policyExtentionDetails()
    {
        return $this->belongsTo(PolicyExtentionDetails::class,'id','extentions_id');
    }



}
