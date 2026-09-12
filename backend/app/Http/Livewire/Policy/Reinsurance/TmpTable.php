<?php

namespace AlphaDirect\Http\Livewire\Policy\Reinsurance;


use AlphaDirect\Services\Reinsurance\CessionSource;
use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\Alpharicvggroup;
use Illuminate\Support\Facades\Redirect;
use AlphaDirect\Models\RiskInsurance;
use DB;
use Carbon\Carbon;
class TmpTable extends Component
{
    public $policy_id;
    public $action_id;
    public function render()
    {
      // dd("render");
        // $this->policyId = $this->policy_id;
        // $this->Reinsurance = RiskInsurance::join('tb_alpharicvggroups', 'tb_alpharicvggroups.n_Id_PK', '=', 'risk.risk_category')
        // ->join('risk_address', 'risk_address.id', '=', 'risk.risk_id')
        // ->where('risk.policy_id',$this->policy_id)->get(array('policy_number','policy_premium_frequency','premium_amount','earned_premium','unearned_premium','s_GroupName','address_name',
        // 'risk_start_date','policy_start_date','policy_end_date'));
        // //dd($this->Reinsurance);
        // return view('v2.livewire.policy.reinsurance.tmp-table');
        return view('v2.livewire.policy.reinsurance.tmp-table');
    }

    public function getReinsuranceProperty(){

      
        $policy = Policy::find($this->policy_id);
        $premium_freq = $policy->premium_freq;
        // if($premium_freq == 3)
        // {
        //     $checkGoodInt = DB::table('policy_reinsurance')
        //     ->where('group_id', 8)
        //     ->where('policy_id', $this->policy_id)
        //     ->where('coverage_id', 71)
        //     ->get();
        //     if(count($checkGoodInt) != 0)
        //     {
        //         DB::table('policy_reinsurance')
        //         ->where('group_id', 8)
        //         ->where('policy_id', $this->policy_id)
        //         ->where('coverage_id', 72)
        //         ->delete();

        //     }
        // }
        // else
        // {
        //     DB::table('policy_reinsurance')
        //     ->where('group_id', 8)
        //     ->where('policy_id', $this->policy_id)
        //     ->where('coverage_id', 71)
        //     ->delete();

        // }

        $checkAnyMotorPresent = DB::table('policy_reinsurance')
        ->whereIn('group_id', [14,13,28,15])
        ->where('policy_id', $this->policy_id)
        ->get();

        $checkAnyMotorOtherPresent = DB::table('policy_reinsurance')
        ->whereNotIn('group_id', [14, 13, 28,15])
        ->where('policy_id', $this->policy_id)
        ->get();


        $finalData = [];
        // BOTH TAB QUERIES NOW LIVE IN CessionSource, MOVED NOT REWRITTEN --
        // including the parts that look wrong: the motor branch's SUM(DISTINCT),
        // which collapses two vehicles carrying identical figures into one, and
        // the non-motor branch's nil-premium filter the motor branch does not
        // apply. Preserved exactly: a move that fixes things on the way past
        // cannot tell you whether the move itself was safe.
        //
        // Proved identical to the original pair before wiring -- 50 policy/action
        // pairs, zero differences, database/manual/prove_action_seam_equivalence.php.
        // On the legacy basis this changes nothing; it follows the flag instead of
        // being hard-wired to one table.
        //
        // FETCHED LAZILY, AND AT MOST ONCE. It cannot be hoisted above the two
        // branches outright: the motor branch soft-deletes rows first, and
        // reading before those deletes would return cession the tab has just
        // removed. Nor can it live inside the motor branch alone, or a policy
        // with non-motor rows and no motor rows loses its non-motor half.
        $rowsByBranch = null;
        $cession = fn () => $rowsByBranch ??= app(CessionSource::class)
            ->layerRowsForAction((int) $this->policy_id, (int) $this->action_id);

        if (isset($checkAnyMotorPresent) && count($checkAnyMotorPresent) > 0) {
// dd($checkAnyMotorPresent);
            $dataMotor = DB::table('policy_reinsurance')
            ->where('policy_id', $this->policy_id)
            ->where('group_id', 15)
            ->where('coverage_id', 27)
            ->update(['deleted_at' => Carbon::now()]);

            $dataMotor = DB::table('policy_reinsurance')
            ->where('policy_id', $this->policy_id)
            ->where('group_id', 29)
            ->where('coverage_id', 27)
            ->update(['deleted_at' => Carbon::now()]);

            // $dataMotor = DB::select(DB::raw("DELETE FROM `policy_reinsurance` 
            // WHERE (`policy_id` = '".$this->policy_id."' 
            // AND `group_id` = 15 AND coverage_id = 27)"));
            // $dataMotor = DB::select(DB::raw("DELETE FROM `policy_reinsurance` 
            // WHERE (`policy_id` = '".$this->policy_id."' 
            // AND `group_id` = 29 AND coverage_id = 27)"));
            $dataMotor = $cession()['motor'];

        } else {
            $dataMotor = [];
        }        

        if (isset($checkAnyMotorOtherPresent) && count($checkAnyMotorOtherPresent) > 0) {
          
            $dataNonMotor = $cession()['non_motor'];

       //  dd($ReinsuranceQuery);
        } else {
            $dataNonMotor = [];
        }
// dd($ReinsuranceQuery);
        // Merging both datasets
        $finalData = array_merge($dataMotor, $dataNonMotor);

        // Return or process the final dataset
        return $finalData;

        
       
    }
}
