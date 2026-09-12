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
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Exportable;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyTerm;
use AlphaDirect\CustomerBanking;
use Illuminate\Support\Facades\DB;


class PolicyBillingReportExport implements WithHeadings, FromQuery, WithMapping, WithChunkReading
{
    use Exportable;

    private $headings = [
                            "Policy Number",
                            "Policy Status",
                            "Customer Billing"

    ];

    public function query()
    {
        // Alpha Brain scope (CFO 2026-08-08): Domestic + Commercial (incl. specialist
        // commercial lines) in full; Instant/retail limited to active policies only.
        return Policy::query()
    ->join('customer_banking', 'customer_banking.policy_id', '=', 'policies.id')
    ->leftJoin('products', 'products.id', '=', 'policies.product_id') // classify only — must not filter out orphaned product_id
    ->where(function ($q) {
        $q->whereIn('products.product_type_id', [7, 8]) // Commercial, Domestic
          ->orWhereIn('products.type', ['ENGINEERING', 'LIABILITY', 'MARINE', 'GUARANTEE', 'MISCELLANEOUS', 'SPECIALISTPRODUCT'])
          ->orWhere('policies.status', 1); // Instant/retail (and any unclassified): active only
    })
    ->orderBy('policies.id', 'desc')
    ->orderBy('customer_banking.id') // stable tiebreaker for LIMIT/OFFSET chunk paging
    ->select([
        'policies.policyNumber',
        DB::raw("CASE
                    WHEN policies.status = 0 THEN 'deactive'
                    WHEN policies.status = 1 THEN 'active'
                    WHEN policies.status = 2 THEN 'cancel'
                    WHEN policies.status = 3 THEN 'expired'
                    ELSE 'deactive' END AS status"),
        'customer_banking.billing as billing_amount', // alias for 'billing' column
    ]);
    }

    public function map($policy): array
    {
        return [
            $policy->policyNumber,
            $policy->status,
            $policy->billing_amount,
        ];
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
