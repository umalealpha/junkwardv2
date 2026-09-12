<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ReinsuranceFormula extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='reinsurance_formula';

    public  static function getReinsuranceFormulaByType($reinsurance_type_id){
        $currentDate = Carbon::now()->format('Y-m-d');
        $reinsuranceFormulaRecord = ReinsuranceFormula::rightJoin('reinsurance_formula_details', 'reinsurance_formula.id', '=', 'reinsurance_formula_details.formula_id')
                                                        //->where('reinsurance_formula.product_id',$productId)
                                                        ->where('reinsurance_formula.status',1)
                                                        ->where('reinsurance_formula.reinsurance_type_id',$reinsurance_type_id)
                                                        ->whereDate('reinsurance_formula_details.date_from', '<=', $currentDate)
                                                        ->whereDate('reinsurance_formula_details.date_to', '>=', $currentDate)
                                                        ->whereNotNull('reinsurance_formula_details.operator')
                                                        ->whereNotNull('reinsurance_formula_details.si_allocation')
                                                        ->select('reinsurance_formula.id','reinsurance_formula_details.id as formula_details_id','reinsurance_formula_details.operator','reinsurance_formula_details.group_id','reinsurance_formula_details.si_allocation')
                                                        ->get();
        return  $reinsuranceFormulaRecord;
    }


}

