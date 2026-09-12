<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
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

class ClaimPaymentBordereauxExport implements WithHeadings,WithMapping,FromCollection
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
        'Claim Type',
        'Risk Address',
        'Payment Date',
        'Trans Type',
        'Trans Sub Type',
        's_GroupName',
        'Coverag Name',
        'Rserve / Payment Amt',
        'Motor Desc',
        'Net_percent',
        'Quota_percent',
        'Surplus_percent',
        'Fac_percent',
        'Netretention_Amt',
        'Quotasharing_Amt',
        'Surplus_Amt',
        'Facultative_Amt',
        's_TreatyName',
        'VATInclude',
        'Regulatory Mapping Name',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    public function map($id): array
    {
        $claim = Claim::where('id', $id)->first();

        if ($claim->claim_number != null) {
            $claimNumber = $claim->claim_number;
        } else
            $claimNumber = 'N/A';

        if ($claim->claim_number != null) {
            $policy = Policy::where('id', $claim->policy_id)->first();
            $policyNumber = $policy->policyNumber;
        } else
            $policyNumber = 'N/A';

        if ($claim->claim_number != null) {
            $policy = Policy::where('id', $claim->policy_id)->first();
            $product = Product::where('id', $policy->product_id)->first();
            $productName = $product->name;
        } else
            $productName = 'N/A';

        if ($claim->claim_number != null) {
            $customer = Customer::where('id', $claim->customer_id)->first(array('firstName', 'lastName'));
            if ($customer) {
                $customerName = $customer->firstName . ' ' . $customer->lastName;
            } else {
                $customerName = 'NA';
            }
        } else {
            $customerName = 'NA';
        }

        if ($claim->claim_number != null) {
            $rep_date = ClaimCellphone::where('claim_id', $claim->id)->first();
            if ($rep_date) {
                $reportedDate = $rep_date->date_reported_to_alpha;
            } else {
                $reportedDate = "NA";
            }
        } else {
            $reportedDate = "NA";
        }

        if ($claim->claim_number != null) {
            $loss = ClaimCellphone::where('claim_id', $claim->id)->first();
            if ($loss) {
                $dateofLoss = $loss->lossDate;
            } else {
                $dateofLoss = 'NA';
            }
        } else {
            $dateofLoss = 'NA';
        }

        if ($claim->claim_number != null) {
            $claim_type = $claim->claim_type;
        } else
            $claim_type = 'N/A';

        if ($claim->claim_number != null) {
            $riskAdd = CustomerProfile::where('customer_id', $claim->customer_id)->first();
            if ($riskAdd) {
                $riskAddress = $riskAdd->address;
            } else {
                $riskAddress = 'N/A';
            }
        } else {
            $riskAddress = 'N/A';
        }

        if ($claim->claim_number != null) {
            $paymentDate = '-';
        } else
            $paymentDate = 'N/A';

        if ($claim->claim_number != null) {
            $trans = Transaction::where('customer_id', $claim->customer_id)->first();
            if ($trans) {
                $transType = $trans->transactionType;
            } else {
                $transType = 'NA';
            }
        } else
            $transType = 'N/A';

        if ($claim->claim_number != null) {
            if ($trans) {
                $transType = Transactionsubtype::where('transaction_id', $trans->id)->first();
                if ($transType) {
                    $transSubType = $transType->name;
                } else {
                    $transSubType = 'NA';
                }
            } else {
                $transSubType = 'NA';
            }
        } else{
            $transSubType = 'NA';
        }

        if($claim->claim_number !=null){
            $GroupName = '-';
        }else
            $GroupName= 'N/A';

        if($claim->claim_number !=null){
            $coveragName = '-';
        }else
            $coveragName= 'N/A';

        if($claim->claim_number !=null){
            $reservePaymentAmt = '-';
        }else
            $reservePaymentAmt= 'N/A';

        if($claim->claim_number !=null){
            $motorDesc = '-';
        }else
            $motorDesc= 'N/A';

        if($claim->claim_number !=null){
            $netPercent = '-';
        }else
            $netPercent= 'N/A';

        if($claim->claim_number !=null){
            $quotaPercent = '-';
        }else
            $quotaPercent= 'N/A';

        if($claim->claim_number !=null){
            $quotaPSurplusPercentercent = '-';
        }else
            $quotaPSurplusPercentercent= 'N/A';

        if($claim->claim_number !=null){
            $facPercent = '-';
        }else
            $facPercent= 'N/A';

        if($claim->claim_number !=null){
            $Netretention_Amt = '-';
        }else
            $Netretention_Amt= 'N/A';

        if($claim->claim_number !=null){
            $Quotasharing_Amt = '-';
        }else
            $Quotasharing_Amt= 'N/A';

        if($claim->claim_number !=null){
            $Surplus_Amt = '-';
        }else
            $Surplus_Amt= 'N/A';

        if($claim->claim_number !=null){
            $Facultative_Amt = '-';
        }else
            $Facultative_Amt= 'N/A';

        if($claim->claim_number !=null){
            $s_TreatyName = '-';
        }else
            $s_TreatyName= 'N/A';

        if($claim->claim_number !=null){
            $VATInclude = '-';
        }else
            $VATInclude= 'N/A';

        if($claim->claim_number !=null){
            $Regulatory_MappingName = '-';
        }else
            $Regulatory_MappingName= 'N/A';

        return [
            $claimNumber,
            $policyNumber,
            $productName,
            $customerName,
            $reportedDate,
            $dateofLoss,
            $claim_type,
            $riskAddress,
            $paymentDate,
            $transType,
            $transSubType,
            $GroupName,
            $coveragName,
            $reservePaymentAmt,
            $motorDesc,
            $netPercent,
            $quotaPercent,
            $quotaPSurplusPercentercent,
            $facPercent,
            $Netretention_Amt,
            $Quotasharing_Amt,
            $Surplus_Amt,
            $Facultative_Amt,
            $s_TreatyName,
            $VATInclude,
            $Regulatory_MappingName,
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
