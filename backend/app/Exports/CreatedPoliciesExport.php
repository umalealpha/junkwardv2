<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class CreatedPoliciesExport implements WithHeadings, WithMapping, FromCollection
{
    use Exportable;

    private $policyIds;

    private $headings = [
        'Policy Number',
        'Customer Cellphone',
        'Customer Email',
        'Status',
        'Created At',
    ];

    public function __construct(array $policyIds)
    {
        $this->policyIds = $policyIds;
    }

    public function collection()
    {
        return Policy::with('customer')
            ->whereIn('id', $this->policyIds)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function map($policy): array
    {
        $policyStatus = match ($policy->status) {
            0 => 'Deactivated',
            1 => 'Policy Activated',
            2 => 'Cancelled',
            3 => 'Expired',
            default => '-',
        };

        return [
            $policy->policyNumber,
            $policy->customer->cellphone ?? 'N/A',
            $policy->customer->email ?? 'N/A',
            $policyStatus,
            $policy->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
