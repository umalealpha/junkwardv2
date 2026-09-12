<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Transaction;
use AlphaDirect\Transactionsubtype;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClaimCoverageAllocationExport implements WithHeadings,WithMapping,FromCollection
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
                                'Date Of Loss',
                                'Reserve Date',
                                'Reported Date',
                                'Claim Type',
                                'Trans Type',
                                'Trans Sub Type',
                                'Group Name',
                                'Coverag Name',
                                'Reserves / Payment',
                                'Motor Desc',
                                'NetRetenstion %',
                                'Quota %',
                                'Surplus %',
                                'Facultative %',
                                'Netretention_Amt',
                                'Quotasharing_Amt',
                                'Surplus_Amt',
                                'Facultative_Amt',
                                's_TreatyName',
                                'PORiskMasterFK',
                                'ParentCoverageCode',

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
            $claimNumber = 'N/A';

        if($claim->claim_number !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            $policyNumber = $policy->policyNumber;
        } else
            $policyNumber = 'N/A';

        if($claim->claim_number !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            $product = Product::where('id',$policy->product_id)->first();
            $productName = $product->name;
        } else
            $productName = 'N/A';

        if($claim->claim_number !=null){
            $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
            if($customer)
            {
                $customerName = $customer->firstName.' '.$customer->lastName;
            }else{
                $customerName= 'NA';
            }
        }else{
            $customerName= 'NA';
        }

        if($claim->claim_number !=null){
            $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
            if($loss){
                $dateofLoss =$loss->lossDate;
            }else
                $dateofLoss= 'N/A';
        }else
            $dateofLoss= 'N/A';

        if($claim->claim_number !=null){
            $reserved_date = '-';
        }else
            $reserved_date= 'N/A';

        $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
        if($rep_date){
            $reportedDate = $rep_date->date_reported_to_alpha;
        }else{
            $reportedDate = "NA";
        }

        if($claim->claim_number !=null){
            $claim_type = $claim->claim_type;
        }else
            $claim_type= 'N/A';

        if($claim->claim_number !=null){
            $trans = Transaction::where('customer_id',$claim->customer_id)->first();
            if($trans){
                $transType = $trans->transactionType;
            }
            else{
                $transType = 'NA';
            }
        }else
            $transType= 'NA';

        if($claim->claim_number !=null){
            $trans = Transaction::where('customer_id',$claim->customer_id)->first();
            if($trans){
                $transType = Transactionsubtype::where('transaction_id',$trans->id)->first();
                if($transType){
                    $transSubType = $transType->name;
                }else{
                    $transSubType = 'NA';
                }
            }else{
                $transSubType = 'NA';
            }
        }else
            $transSubType= 'NA';

        if($claim->claim_number !=null){
            $GroupName = '-';
        }else
            $GroupName= 'NA';

        if($claim->claim_number !=null){
            $coveragName = '-';
        }else
            $coveragName= 'NA';
        if($claim->claim_number !=null) {
            $rservesPayments = ClaimAccident::where('claim_id', $claim->id)->first();
            if ($rservesPayments) {
                $rservesPaymentAmt = $rservesPayments->reserve_amount;
            } else {
                $rservesPaymentAmt = "NA";
            }
        } else {
            $rservesPaymentAmt = "NA";
        }

        if($claim->claim_number !=null){
            $motorDesc = '-';
        }else
            $motorDesc= 'NA';

        if($claim->claim_number !=null){
            $netPercent = '-';
        }else
            $netPercent= 'NA';

        if($claim->claim_number !=null){
            $quotaPercent = '-';
        }else
            $quotaPercent= 'NA';

        if($claim->claim_number !=null){
            $SurplusPercentercent = '-';
        }else
            $SurplusPercentercent= 'NA';

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
            $PORiskMasterFK = '-';
        }else
            $PORiskMasterFK= 'NA';

        if($claim->claim_number !=null){
            $ParentCoverageCode = '-';
        }else
            $ParentCoverageCode= 'NA';

        if($claim->claim_number !=null){
            $s_TreatyName = '-';
        }else
            $s_TreatyName= 'NA';

        return [
            $claimNumber,
            $policyNumber,
            $productName,
            $customerName,
            $dateofLoss,
            $reserved_date,
            $reportedDate,
            $claim_type,
            $transType,
            $transSubType,
            $GroupName,
            $coveragName,
            $rservesPaymentAmt,
            $motorDesc,
            $netPercent,
            $quotaPercent,
            $SurplusPercentercent,
            $Netretention_Amt,
            $Quotasharing_Amt,
            $Surplus_Amt,
            $Facultative_Amt,
            $s_TreatyName,
            $PORiskMasterFK,
            $ParentCoverageCode,
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
