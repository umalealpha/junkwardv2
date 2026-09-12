<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Http\Controllers\Api\V1\KycAccessReportController;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export for the KYC Access Audit Report. Faithful port of
 * graphiteBWV8's KycAccessReportExport — reuses the controller's
 * getKycAccessUsers() so the export matches the on-screen report exactly.
 */
class KycAccessReportExport implements WithHeadings, WithMapping, FromCollection
{
    use Exportable;

    protected ?string $fromDate;
    protected ?string $toDate;

    private array $headings = [
        'User ID',
        'Full Name',
        'Email',
        'Roles',
        'KYC Permissions',
        'Current Status',
        'Total KYC Actions',
        'First Action',
        'Last Action',
        'User Created On',
    ];

    public function __construct(?string $fromDate = null, ?string $toDate = null)
    {
        $this->fromDate = $fromDate;
        $this->toDate   = $toDate;
    }

    public function map($row): array
    {
        // $row is a raw stdClass from getKycAccessUsers; reuse the
        // controller's display mapping so the file matches the UI 1:1.
        $mapped = (new KycAccessReportController())->mapRow($row);

        return [
            $mapped['id'],
            $mapped['full_name'],
            $mapped['email'],
            $mapped['roles'],
            $mapped['kyc_permissions'],
            $mapped['current_status'],
            $mapped['total_kyc_actions'],
            $mapped['first_action'],
            $mapped['last_action'],
            $mapped['user_created'],
        ];
    }

    public function collection()
    {
        $fromDate = $this->fromDate
            ?: Carbon::now()->subYear()->startOfDay()->format('Y-m-d H:i:s');
        $toDate = $this->toDate
            ?: Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        $rows = (new KycAccessReportController())->getKycAccessUsers($fromDate, $toDate);

        return collect($rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
