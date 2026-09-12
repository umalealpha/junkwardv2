<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export of a union's members, columns matching the member list / import template.
 * Pass pre-built rows (already scoped to the union) from the controller.
 */
class UnionMembersExport implements FromArray, WithHeadings
{
    /** @param array<int,array<int,mixed>> $rows */
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'ID Number', 'Name', 'Type', 'Date Of Birth', 'Gender',
            'Contact No', 'Email Address', 'Nationality', 'Union', 'Status',
        ];
    }
}
