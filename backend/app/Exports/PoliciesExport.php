<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PoliciesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $policies;

    public function __construct($policies)
    {
        $this->policies = $policies;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        // Convert array of stdClass to Collection
        return collect($this->policies);
    }

    public function map($policy): array
    {
        // Format premium_freq column
        switch ($policy->premium_freq) {
            case 1:
                $policy->premium_freq = 'MONTHLY';
                break;
            case 2:
                $policy->premium_freq = '3 INSTALLMENTS';
                break;
            case 3:
                $policy->premium_freq = 'ANNUAL';
                break;
            case 4:
                $policy->premium_freq = 'SEMIANNUAL';
                break;
            case 5:
                $policy->premium_freq = 'QUARTERLY';
                break;
            default:
                $policy->premium_freq = 'N/A';
                break;
        }

        return [
            $policy->policyNumber,
            $policy->billingStartDate,
            $policy->billing,
            $policy->customerName,
            $policy->agentName,
            $policy->agencyName,
            $policy->branchName,
            $policy->productName,
            $policy->productPlanName,
            $policy->storeName,
            $policy->premium,
            $policy->kycStatus,
            $policy->customer_kyc_status,
            $policy->policyStatus,
            $policy->created_at,
            $policy->premium_freq
        ];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'policyNumber',
            'billingStartDate',
            'billing',
            'customerName',
            'agentName',
            'agencyName',
            'branchName',
            'productName',
            'productPlanName',
            'storeName',
            'premium',
            'kycStatus',
            'customer_kyc_status',
            'policyStatus',
            'created_at',
            'premium_freq'
        ];
    }
}
