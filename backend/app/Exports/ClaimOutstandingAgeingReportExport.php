<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClaimOutstandingAgeingReportExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
        'Claim No ',
        'Policy No ',
        'Product Name ',
        'Insured Name',
        'Date Of Loss',
        'Claim Reported Date',
        'Out Standing Reserve',
        'Out Standing Days',
        'Claim Status Code',
        'Claim Sub Status',
        'Under 30',
        '31 TO 60',
        '61 TO 90',
        '91 TO 120',
        'Over 120',

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


        if ($claim->claim_number != null)
        {
            $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
            if($customer != null) {
                $cust_name = $customer->firstName.' '.$customer->lastName;
            }
            else {
                $cust_name = 'N/A';
            }
        }else{
            $cust_name = 'N/A';
        }

        if($claim->claim_number !=null){
            $dateofLoss = $claim->dateofLoss;
        }else {
            $dateofLoss = 'N/A';
        }

        if($claim->claim_number !=null){
            $date_reported = $claim->date_reported;
        }else {
            $date_reported = 'N/A';
        }

        if($claim->claim_number !=null){
            $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
            if($loss) {
                $dateofLoss = $loss -> dateofLoss;
            }
        }
        else{
            $dateofLoss ='NA';
        }

        if($claim->claim_number !=null){
            $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
            if($loss) {
                $date_reported = $loss -> date_reported;
            }
        }
        else{
            $date_reported ='NA';
        }

        if($claim->claim_number !=null){
            $status = $claim->status;
        }else
            $status = 'N/A';

        if($claim->claim_number !=null){
            $status = $claim->status;
        }else
            $status = 'N/A';

        return [
            $claimNumber,
            $policyNumber,
            $productName,
            $cust_name,
            $dateofLoss,
            $date_reported,
            $status,
            $status,

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
