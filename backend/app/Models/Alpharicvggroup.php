<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alpharicvggroup extends Model
{
    use HasFactory;
    protected $table = "tb_alpharicvggroups";
//    protected $primaryKey = "n_Id_PK";


    /**
     * Check the status is activated or not
     */
    public function scopeActivated($query)
    {
        return $query->where('s_Status', 'ACTIVE');
    }

}
