<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank import template — the exact columns the importer expects, plus one
 * illustrative sample row the user can overwrite/delete.
 */
class UnionMembersTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [[
            '419217634', 'Kefilwe Moloi', 'Principal', '1990-05-14', 'Female',
            '72345678', 'k.moloi@example.bw', 'Motswana',
        ]];
    }

    public function headings(): array
    {
        return [
            'ID Number', 'Name', 'Type', 'Date Of Birth', 'Gender',
            'Contact No', 'Email Address', 'Nationality',
        ];
    }
}
