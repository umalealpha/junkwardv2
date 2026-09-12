<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ReinsuranceTreaty extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='reinsurance_treaty';
    protected $fillable = [];
    protected $guarded = ['id'];

    // protected $casts = [
    //     'effective_from'  => 'date',
    //     'effective_to'  => 'date',
    // ];

    // public function getEffectiveFromAttribute($value)
    // {
    //     return (new Carbon($value))->format(config('constants.date.format'));
    // }

    // public function getEffectiveToAttribute($value)
    // {
    //     return (new Carbon($value))->format(config('constants.date.format'));
    // }

    public  static function getActiveReinsuranceTreatyDetails(){
        $currentDate = Carbon::now()->format('Y-m-d');
        $reinsuranceTreatyRecord = ReinsuranceTreaty::rightJoin('treaty_details', 'reinsurance_treaty.id', '=', 'treaty_details.treaty_id')
                                                        ->where('reinsurance_treaty.status',1)
                                                        ->whereDate('reinsurance_treaty.effective_from', '<=', $currentDate)
                                                        ->whereDate('reinsurance_treaty.effective_to', '>=', $currentDate)
                                                        ->select('reinsurance_treaty.id','treaty_details.formula_attached')
                                                        ->get();
        return  $reinsuranceTreatyRecord;
    }

}

