<?php

namespace AlphaDirect\Exports;


use AlphaDirect\Policy;
use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReinsuranceRiskProfilesSummaryExport implements WithHeadings, FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    use Exportable;

    private $headings = [
        'Risk Band',
        'Sum Insured',
        'Policy Count',
        'Premium',
        'Claim Count',
        'Payments',
        'Total Reserve',
    ];
   

    function __construct() {
    }
    public function collection()
    {
        $fromDate = '2020-04-01';
        $toDate = '2021-03-31';
        $life = array();
        $life['policy_count'] = Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $life['premium'] = Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $claims = Claim::where('claim_type', 'Life')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        if($claims == NULL)
        {
            $life['claim_count'] = 0;
            $life['payment'] = 0;
            $life['reserve'] = 0;
        } else {
            $t = $life['claim_count'] = $claims->count();
            $life['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $life['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $vehicle = array();
        $vehicle[0]['risk_band'] = '0 to 250 000';
        $vehicle[0]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[0]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[0]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[0]['claim_count'] = 0;
            $vehicle[0]['payment'] = 0;
            $vehicle[0]['reserve'] = 0;
        } else {
            $vehicle[0]['claim_count'] = $claims->count();
            $vehicle[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[1]['risk_band'] = '250 001 - 500 000';
        $vehicle[1]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[1]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[1]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[1]['claim_count'] = 0;
            $vehicle[1]['payment'] = 0;
            $vehicle[1]['reserve'] = 0;
        } else {
            $vehicle[1]['claim_count'] = $claims->count();
            $vehicle[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[2]['risk_band'] = '500 000 +';
        $vehicle[2]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[2]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[2]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[2]['claim_count'] = 0;
            $vehicle[2]['payment'] = 0;
            $vehicle[2]['reserve'] = 0;
        } else {
            $vehicle[2]['claim_count'] = $claims->count();
            $vehicle[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $motorComp = array();
        $motorComp[0]['risk_band'] = '0 to 250 000';
        $motorComp[0]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[0]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[0]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[0]['claim_count'] = 0;
            $motorComp[0]['payment'] = 0;
            $motorComp[0]['reserve'] = 0;
        } else {
            $motorComp[0]['claim_count'] = $claims->count();
            $motorComp[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[1]['risk_band'] = '250 001 - 500 000';
        $motorComp[1]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[1]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[1]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[1]['claim_count'] = 0;
            $motorComp[1]['payment'] = 0;
            $motorComp[1]['reserve'] = 0;
        } else {
            $motorComp[1]['claim_count'] = $claims->count();
            $motorComp[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[2]['risk_band'] = '500 000 +';
        $motorComp[2]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[2]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[2]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[2]['claim_count'] = 0;
            $motorComp[2]['payment'] = 0;
            $motorComp[2]['reserve'] = 0;
        } else {
            $motorComp[2]['claim_count'] = $claims->count();
            $motorComp[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        return collect([
            [
                'Risk Band' => 0,
                'Sum Insured' => 0,
                'Policy Count' => Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count(),
                'Premium' => Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium'),
                'Claim Count' => 0,
                'Payments' => 0,
                'Total Reserve' => 0
            ],
            [
                'Risk Band' => '0 to 250 000',
                'Sum Insured' => 0,
                'Policy Count' => Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count(),
                'Premium' => Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium'),
                'Claim Count' => 0,
                'Payments' => 0,
                'Total Reserve' => 0
            ]
        ]);
    }

    

    public function headings() : array
    {
        return $this->headings;
    }
}