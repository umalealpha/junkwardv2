<?php

namespace AlphaDirect;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use AlphaDirect\Models\CoverageMaster;

class Product extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'products';
    protected $fillable = [];

    const PRODUCT_TYPE_HEALTH = "Health";

    public function type()
    {
        return $this->hasOne('AlphaDirect\ProductType', 'id', 'product_type_id');
    }

    public function productPlans()
    {
        return $this->hasMany('Alphadirect\Productplan');
    }

    public function plans()
    {
        return $this->hasMany('AlphaDirect\Productplan');
    }

    public function policy()
    {
        return $this->hasMany(Policy::class);
    }

    public function claimReservesCoverages()
    {
        return $this->hasMany(ClaimReservesCoverage::class);
    }


    /**
     * Get the cvgpccoverage for the product_coverage.
     */
    public function coverageMaster()
    {
        return $this->belongsToMany(CoverageMaster::class, 'product_coverage', 'product_id', 'coverage_id');
    }


    /**
     * Check the status is activated or not
     */
    public function scopeActivated($query)
    {
        return $query->where('status', 1);
    }

    public function scopeWithPolicyCountBetweenDates($query,Carbon $startDate,Carbon $endDate,$alias = 'count')
    {
        return $query->withCount(['policy as '.$alias => function($query) use ($startDate,$endDate){
            $query->Status(1);
            $query->whereBetween('created_at', [$startDate->format('Y-m-d')." 00:00:00", $endDate->format('Y-m-d')." 23:59:59"]);
        }]);
    }

    // public function scopeWithClaimReservesCoverageSumBetweenDates($query,Carbon $startDate,Carbon $endDate,$columnNameForSum,$alias)
    // {
    //     return $query->withSum(['claimReservesCoverages as '.$alias => function($query) use ($startDate,$endDate){
    //         $query->whereBetween('created_at', [$startDate->format('Y-m-d')." 00:00:00", $endDate->format('Y-m-d')." 23:59:59"]);
    //     }],$columnNameForSum);
    // }

    public static function queryForClaimReservesCoverageSumDates(Carbon $startDate,Carbon $endDate,$columnNameForSum){
        $startDate = $startDate->format('Y-m-d')." 00:00:00";
        $endDate = $endDate->format('Y-m-d')." 23:59:59";
        $query = "select sum($columnNameForSum) from `policies` ".
                    "right join claims on claims.policy_id = policies.id ".
                    "right join claim_reserves_coverages on claims.id = claim_reserves_coverages.claim_id ".
                    "where claim_reserves_coverages.created_at between '$startDate' and  '$endDate' and policies.product_id = products.id" ;
        return $query;
    }

    public static function queryForTransactionBetweenDates(Carbon $startDate,Carbon $endDate,$columnNameForSum,$status=null){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        $query = "select sum($columnNameForSum) from `policies` ".
                    "inner join payment_transactions on payment_transactions.policyNumber = policies.policyNumber ".
                    "where payment_transactions.new_payment_date between '$startDate' and  '$endDate' and policies.product_id = products.id";
        if(!is_null($status)){
            $query = $query." and payment_transactions.status IN ($status)";
        }
        return $query;
    }

    public static function ProductsName()
    {
        return Product::Activated()->orderby( 'id', 'asc' )->pluck('name','id')->toArray();
    }


}
