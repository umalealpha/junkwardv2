<?php

namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Models\Company;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubCompanyExport implements FromCollection,WithHeadings,WithMapping,WithColumnWidths,WithTitle,WithStyles
{
    public Policy $policy;

    public function __construct($policy)
    {
        $this->policy = $policy;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->policy->profile->company->subCompanies ?? collect();
    }

    public function headings(): array
    {
        return ['Company Name','VAT registration number','Registration Number'];
    }

    public function map($company): array
    {
        return [
            $company->name,
            $company->VAT_registration_number,
            $company->company_registration_number
        ];
    }

    public function columnWidths(): array
    {

        return [
            'A' => 30,
            'B' => 30,
            'C' => 20,
            'D' => 20,
        ];
    }

    public function title(): string
    {
        return "Subsidiary Companies";
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
