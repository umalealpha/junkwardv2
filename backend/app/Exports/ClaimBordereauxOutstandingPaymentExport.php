<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClaimBordereauxOutstandingPaymentExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Claim No',
        'Policy Number',
        'Product Name',
        'Insured Name',
        'Reported Date',
        'Date Of Loss',
        'Risk Address',
        's_Group Name',
        'Motor Desc',
        'Total Outstanding Amt',
        'Net_percent',
        'Quota_percent',
        'Surplus_percent',
        'Fac_percent',
        'Netretention_Amt',
        'Quotasharing_Amt',
        'Surplus_Amt',
        'Facultative_Amt',
        'Treaty Name',
        'Claim Sub Type',
        'Regulatory Mapping Name',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $claim = Claim::where('id',$id)->first();

        if($claim->claim_number !=null){
            $claimNumber = $claim->claim_number;
        }else
            $claimNumber = 'NA';

        if($claim->claim_number !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            $policyNumber = $policy->policyNumber;
        } else
            $policyNumber = 'NA';

        if($claim->claim_number !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            $product = Product::where('id',$policy->product_id)->first();
            $productName = $product->name;
        } else
            $productName = 'NA';

        if($claim->claim_number !=null){
            $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
            if($customer)
            {
                $customerName = $customer->firstName.' '.$customer->lastName;
            }
            else
            {
                $customerName= 'NA';
            }

        }else
        {
            $customerName= 'NA';
        }


        if($claim->claim_number !=null){
            $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
            if($rep_date){
                $reportedDate = $rep_date->date_reported_to_alpha;
            }else{
                $reportedDate = "NA";
            }
        }else
            $reportedDate= 'NA';

        if($claim->claim_number !=null){
            $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
            if($loss){
                $dateofLoss =$loss->lossDate;
            }else
                $dateofLoss= 'NA';
        }else
            $dateofLoss= 'NA';

        if($claim->claim_number !=null){
            $riskAdd = CustomerProfile::where('customer_id',$claim->customer_id)->first();
            if($riskAdd){
                $riskAddress = $riskAdd->address;
            }else{
                $riskAddress= 'NA';
            }
        }else{
            $riskAddress= 'NA';
        }

        if($claim->claim_number !=null){
            $GroupName = '-';
        }else
            $GroupName= 'NA';

        if($claim->claim_number !=null){
            $motorDesc = '-';
        }else
            $motorDesc= 'A';

        if($claim->claim_number !=null){
            $totalOutstandingAmt = '-';
        }else
            $totalOutstandingAmt= 'NA';

        if($claim->claim_number !=null){
            $netPercent = '-';
        }else
            $netPercent= 'NA';

        if($claim->claim_number !=null){
            $quotaPercent = '-';
        }else
            $quotaPercent= 'NA';

        if($claim->claim_number !=null){
            $surplusPercent = '-';
        }else
            $surplusPercent= 'NA';

        if($claim->claim_number !=null){
            $facPercent = '-';
        }else
            $facPercent= 'NA';

        if($claim->claim_number !=null){
            $Netretention_Amt = '-';
        }else
            $Netretention_Amt= 'NA';

        if($claim->claim_number !=null){
            $Quotasharing_Amt = '-';
        }else
            $Quotasharing_Amt= 'NA';

        if($claim->claim_number !=null){
            $Surplus_Amt = '-';
        }else
            $Surplus_Amt= 'NA';

        if($claim->claim_number !=null){
            $Facultative_Amt = '-';
        }else
            $Facultative_Amt= 'NA';

        if($claim->claim_number !=null){
            $s_TreatyName = '-';
        }else
            $s_TreatyName= 'NA';

        if($claim->claim_number !=null){
            $claimSubTypes = ClaimAccident::where('claim_id',$claim->id)->first();
            if($claimSubTypes){
                $claimSubType = $claimSubTypes->claim_sub_type;
            }else{
                $claimSubType = "NA";
            }
        }else
            $claimSubType= 'NA';

        if($claim->claim_number !=null){
            $regulatoryMappingName = '-';
        }else
            $regulatoryMappingName= 'NA';

        return [
            $claimNumber,
            $policyNumber,
            $productName,
            $customerName,
            $reportedDate,
            $dateofLoss,
            $riskAddress,
            $GroupName,
            $motorDesc,
            $totalOutstandingAmt,
            $netPercent,
            $quotaPercent,
            $surplusPercent,
            $facPercent,
            $Netretention_Amt,
            $Quotasharing_Amt,
            $Surplus_Amt,
            $Facultative_Amt,
            $s_TreatyName,
            $claimSubType,
            $regulatoryMappingName,
        ];
    }


    public function collection()
    {
        $query = Claim::orderBy('created_at', 'DESC');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        $query = $query->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
