<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Beneficiary template export — produces an empty Excel file with the correct
 * column headers that MemberImport expects.
 */
class BeneficiaryTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        // One sample row so agents know the exact expected format
        return [
            ['John', 'A', 'Doe', 'Spouse', 'Male', '100', '123412345', '', '01/01/1985'],
        ];
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Middle Name',
            'Last Name',
            'Relation',
            'Gender',
            'Payment',
            'Omang',
            'Passport',
            'Date Of Birth',
        ];
    }
}
