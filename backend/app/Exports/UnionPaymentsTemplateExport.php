<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank monthly payment-list template: the exact columns the payments importer
 * expects, plus one sample row to overwrite. Only ID Number is required; the
 * month is chosen on screen, not in the file.
 */
class UnionPaymentsTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [[
            '419217634', 'Kefilwe Moloi', '55.00', '2026-09-05', 'BONU-SEP-0001',
        ]];
    }

    public function headings(): array
    {
        return ['ID Number', 'Name', 'Amount', 'Payment Date', 'Reference'];
    }
}
