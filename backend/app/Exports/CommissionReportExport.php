<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\User;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CommissionReportExport implements WithHeadings,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
        'Policy Number',
        'User ID',
        'Agent Name',
        'Product',
        'Premium',
        'Payment Reference',
        'Payment Success<',
        'KYC',
        'Pre-Inspection',
        'Commission Basis',
        'Commission',
        'Date of Transaction',

    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }

    

    public function collection()
    {
        //
        $query = Policy::join('products', 'products.id', '=', 'policies.product_id')
        ->join('users', 'users.id', '=', 'policies.agent_id')
        ->join('payment_transactions', 'payment_transactions.policyNumber', '=', 'policies.policyNumber')
        ->select('policies.created_at','policies.id','policies.policyNumber', 'policies.premium', 'policies.preinspection','policies.payment_reference', 'products.name', 'users.id', 'users.firstName', 'users.lastName', 'payment_transactions.status')
        ->orderBy('policies.created_at', 'DESC');
        // ->get();
        // echo "<pre>";
        // print_r($query); exit;
        

        // $query = Policy::orderBy('created_at', 'DESC');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
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
