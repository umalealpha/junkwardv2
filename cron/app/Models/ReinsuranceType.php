<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ReinsuranceType extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'reinsurance_type';
    protected $fillable = [];
    protected $guarded = ['id'];

    // protected $casts = [
    //     'created_at'  => 'datetime',
    // ];

    // public function getCreatedAtAttribute($value)
    // {
    //     return (Carbon::createFromFormat('Y-m-d H:i:s', $value)->format('d/m/Y H:i'));
    // }

    public function getStatus(){
        return $this->status==1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-info">In-Active</span>' ;
    }

}
