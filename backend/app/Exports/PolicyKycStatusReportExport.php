<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyTerm;
use AlphaDirect\CustomerBanking;
use Illuminate\Support\Collection;

class PolicyKycStatusReportExport implements WithHeadings,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;
    private $headings = [
                            "Policy Number",
                            "KYC Status "
                            
    ];

  

    public function collection(): Collection
    {
        $policies = Policy::Join('customer_kyc','customer_kyc.customer_id','policies.customer_id')
            ->orderBy('policies.id', 'desc')
            ->select(['policies.policyNumber', 'customer_kyc.status'])
            ->get();

        return $policies;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
