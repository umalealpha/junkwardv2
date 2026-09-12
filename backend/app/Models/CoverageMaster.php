<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use Carbon\Carbon;

class CoverageMaster extends Model
{
    use HasFactory;

    protected $table = 'tb_cvgpccoverages';
	const CREATED_AT = 'd_CreatedDate';
	const UPDATED_AT = 'd_UpdatedDate';
	// protected $casts = [
    //     'd_EffectiveDt' => 'date',
    //     'd_ExpirationDt' => 'date',
    // ];

    // public function getDEffectiveDtAttribute($value)
    // {
    //     return (new Carbon($value))->format(config('constants.date.format'));
    // }

    // public function getDExpirationDtAttribute($value)
    // {
    //     return (new Carbon($value))->format(config('constants.date.format'));
    // }

//	public $specialColumns = [
//	    'mainCode' => 's_CoverageCode', // s_CoverageCode is the main code for a coverage
//        'parentCode' => 's_ParentCoverageCode' // s_ParentCoverageCode is the parent coverage code
//    ];
    public function specifiedCoverages(){
        return $this->hasMany(SpecifiedCoveragesItems::class,  'coverage_id','id');
    }

    public function tbValidOptionsCoverage(){
        return $this->hasOne(TbValidOptions::class,  'n_SourceOneFK','id')->where('s_OptionType','CVG_LIMIT');
    }

    
    public function scopePolicy($query,$policy_id){
        return $query->where('id', $policy_id);
    }

    public function scopeHasRiskAddress($query){
        return $query->where('has_risk_address', 1);
    }

    public function scopeHasVehicle($query){
        return $query->where('has_vehicle', 1);
    }

    public function scopeHasMember($query){
        return $query->where('has_member', 1);
    }

    public function scopeHasDevice($query){
        return $query->where('has_device', 1);
    }

    public function scopeOnlyParent($query){
        return $query->where('s_UsageType', 'PARENT');
    }
    public function scopeOnlyChiled($query){
        return $query->where('s_UsageType', 'CHILD');
    }

    public function scopeMainCoverageOnly($query){
        $query->where('s_CoverageGroupCode','MAIN')->where('s_UsageType','=','PARENT');
    }

    public function scopeMainCoverageCode($query,$code){
        $query->where('s_CoverageCode',$code);
    }

    public function scopeParentCoverageCode($query,$code){
        return $query->where('s_ParentCoverageCode', $code);
    }

    public function scopeName($query,$name){
        return $query->where('s_ScreenName', $name);
    }

    public function subCoverage() {
        return $this->hasMany(CoverageMaster::class, 's_ParentCoverageCode', 's_CoverageCode')
        ->where('s_DISPLAYTOUSER','=','1')
        ->orderBy('n_DisplaySequence','asc')
        ->OnlyChiled();
    }

    public function allExtention() {
        return $this->hasMany(Extention::class, 's_ParentCoverageCode', 's_CoverageCode')
            ->where('type', '=', 'Extention')
            ->where('s_DISPLAYTOUSER', '1')
            ->orderBy('n_DisplaySequence');
    }

    public function allFirstAmountPayable() {
        return $this->hasMany(Extention::class, 's_ParentCoverageID', 'id')->where('type','=','FirstAmountPayable');
    }

    public function allBurglarAlarmWarranty() {
        return $this->hasMany(Extention::class, 's_ParentCoverageID', 'id')->where('type','=','BurglarAlarmWarranty');
    }

    public function allExcess() {
        return $this->hasMany(Extention::class, 's_ParentCoverageID', 'id')->where('type','=','Excess');
    }

    public function allMemoranda() {
        return $this->hasMany(Extention::class, 's_ParentCoverageID', 'id')->where('type','=','Memoranda');
    }

    public function perentCoverage() {
        return $this->belongsTo(CoverageMaster::class, 's_ParentCoverageCode', 's_CoverageCode')->where('s_DISPLAYTOUSER','=','1')->orderBy('n_DisplaySequence','asc')->OnlyParent();
     // return $this->hasMany(CoverageMaster::class, 's_ParentCoverageCode', 's_CoverageCode')->OnlyChiled();
    }

    public static function getPolicyCoverage($policyId,$termId,$actionId,$product_id) {
        return static::select('tb_cvgpccoverages.id','tb_cvgpccoverages.s_CoverageCode')->whereIn('s_CoverageCode',
            CoverageMaster::select('policy_coverage.main')
                ->join('policy_coverage','policy_coverage.coverage_id','tb_cvgpccoverages.id')
                ->where('policy_coverage.policy_id',$policyId)
                ->where('policy_coverage.term_id',$termId)
                ->where('policy_coverage.action_id',$actionId)
                // ->where('tb_cvgpccoverages.s_DISPLAYTOUSER','=','1')
                ->groupBy('main')->get()->pluck('main')->toArray()
        )
                ->leftjoin('product_coverage','product_coverage.coverage_id','tb_cvgpccoverages.id')
                ->where('product_coverage.product_id',$product_id)
            ->OnlyParent()->get()->pluck('s_CoverageCode','id')->toArray();
    }

}
