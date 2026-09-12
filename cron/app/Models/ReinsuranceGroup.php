<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ReinsuranceGroup extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'reinsurance_group';
    protected $fillable = [];
    protected $guarded = ['id'];

    // protected $casts = [
    //     'created_at'  => 'date',
    // ];

    // public function getCreatedAtAttribute($value)
    // {
    //     return (new Carbon($value))->format('d/m/Y H:i');

    // }

    public function getStatus(){
        return $this->status==1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-info">In-Active</span>' ;
    }

    public function product()
    {
        return $this->hasOne('AlphaDirect\Product', 'id', 'product_id')->select('id', 'name');
    }

    public  static function getReinsuranceGroupByGroupId($groupId){
        $reinsuranceGroupRecord = ReinsuranceGroup::rightJoin('reinsurance_group_coverage', 'reinsurance_group.id', '=', 'reinsurance_group_coverage.group_id')
                                                    ->where('reinsurance_group.status',1)
                                                    ->where('reinsurance_group.id',$groupId)
                                                    ->whereNotNull('reinsurance_group_coverage.si_premium')
                                                    ->whereNotNull('reinsurance_group_coverage.ri_limit')
                                                    ->whereNotNull('reinsurance_group_coverage.coverage_id')
                                                    ->select('reinsurance_group.id','reinsurance_group_coverage.coverage_id','reinsurance_group_coverage.coverage_name'
                                                    ,'reinsurance_group_coverage.si_premium','reinsurance_group_coverage.ri_limit','reinsurance_group_coverage.limit_value')
                                                    ->get();
        return  $reinsuranceGroupRecord;
    }


}
