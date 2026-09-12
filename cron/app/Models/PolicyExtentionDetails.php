<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\TbCvgpcLimits;

class PolicyExtentionDetails extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $table = 'policy_extention_detail';
    protected $guarded = [];

    public function extention()
    {
        return $this->belongsTo(Extention::class,'extentions_id','id')->where('type','=','Extention')->where('s_DISPLAYTOUSER',1)->orderBy('n_DisplaySequence','asc');
    }

    public function perils()
    {
        return $this->belongsTo(Extention::class,'extentions_id','id')->where('type','=','Perils')->orderBy('n_DisplaySequence','asc');
    }

    public function extentionCvgpclimits()
    {
        return $this->belongsTo(TbCvgpcLimits::class,'extention_limit_id','n_PCLimitId_PK');
    }



}
