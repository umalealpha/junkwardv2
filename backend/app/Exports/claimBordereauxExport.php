<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
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

class ClaimBordereauxExport implements WithHeadings,WithMapping,FromCollection
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
                                'Reserve Payment Amt',
                                'Group Name',
                                'Coverag Name',
                                'Motor Desc',
                                'Motor Desc2',
                                'Net_percent',
                                'Quota_percent',
                                'Surplus_percent',
                                'Fac_percent',
                                'Netretention_Amt',
                                'Quotasharing_Amt',
                                'Surplus_Amt',
                                'Facultative_Amt',
                                'Netretention_SI',
                                'Quotasharing_SI',
                                'Surplus_SI',
                                'Facultative_SI',
                                'Tran#  PK',
                                'RISK Master FK',
                                'Motor PK',
                                'ParentCoverageCode',
                                's_TreatyName',
                                'RegulatoryMappingName',
                                'TermStartDate',
                                'TermEndDate',
                                'VATInclude',
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
            }else{
                $customerName= 'NA';
            }
        }else{
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
            $claim_type = $claim->claim_type;
        }else
            $claim_type= 'NA';

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
            $paymentDate = '-';
        }else
            $paymentDate= 'NA';

        if($claim->claim_number !=null){
            $trans = Transaction::where('customer_id',$claim->customer_id)->first();
            if($trans){
                $transType = $trans->transactionType;
            }else{
                $transType = 'NA';
            }
        }else{
            $transType = 'NA';
        }
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
            }else{
            $transSubType = 'NA';
        }
        if($claim->claim_number !=null){
            $rservesPayments = ClaimAccident::where('claim_id',$claim->id)->first();
            if($rservesPayments){
                $rservesPayment = $rservesPayments->reserve_amount;
            }else{
                $rservesPayment = "NA";
            }
        }else {
            $rservesPayment = "NA";
        }

        if($claim->claim_number !=null){
            $GroupName = '-';
        }else
            $GroupName= 'NA';

        if($claim->claim_number !=null){
            $coveragName = '-';
        }else
            $coveragName= 'NA';

        if($claim->claim_number !=null){
            $motorDesc = '-';
        }else
            $motorDesc= 'NA';

        if($claim->claim_number !=null){
            $motorDesc2 = '-';
        }else
            $motorDesc2= 'NA';

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
            $Netretention_SI = '-';
        }else
            $Netretention_SI= 'NA';

        if($claim->claim_number !=null){
            $Quotasharing_SI = '-';
        }else
            $Quotasharing_SI= 'NA';

        if($claim->claim_number !=null){
            $Surplus_SI = '-';
        }else
            $Surplus_SI= 'NA';

        if($claim->claim_number !=null){
            $Facultative_SI = '-';
        }else
            $Facultative_SI= 'NA';

        if($claim->claim_number !=null){
            $Tran_PK = '-';
        }else
            $Tran_PK= 'NA';

        if($claim->claim_number !=null){
            $RiskMasterFK = '-';
        }else
            $RiskMasterFK= 'NA';

        if($claim->claim_number !=null){
            $MotorPK = '-';
        }else
            $MotorPK= 'NA';

        if($claim->claim_number !=null){
            $ParentCoverageCode = '-';
        }else
            $ParentCoverageCode= 'NA';

        if($claim->claim_number !=null){
            $s_TreatyName = '-';
        }else
            $s_TreatyName= 'NA';

        if($claim->claim_number !=null){
            $regulatoryMappingName = '-';
        }else
            $regulatoryMappingName= 'NA';

        if($claim->claim_number !=null){
            $TermStartDate = '-';
        }else
            $TermStartDate= 'NA';

        if($claim->claim_number !=null){
            $TermEndDate = '-';
        }else
            $TermEndDate= 'NA';

        if($claim->claim_number !=null){
            $VATInclude = '-';
        }else
            $VATInclude= 'NA';

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
            $rservesPayment,
            $GroupName,
            $coveragName,
            $motorDesc,
            $motorDesc2,
            $netPercent,
            $quotaPercent,
            $surplusPercent,
            $facPercent,
            $Netretention_Amt,
            $Quotasharing_Amt,
            $Surplus_Amt,
            $Facultative_Amt,
            $Netretention_SI,
            $Quotasharing_SI,
            $Surplus_SI,
            $Facultative_SI,
            $Tran_PK,
            $RiskMasterFK,
            $MotorPK,
            $ParentCoverageCode,
            $s_TreatyName,
            $regulatoryMappingName,
            $TermStartDate,
            $TermEndDate,
            $VATInclude,
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
