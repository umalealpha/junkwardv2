<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class MemberExport implements WithHeadings
{
    public function headings(): array
    {
        return [
            "First Name",
            "Middle Name" ,
            "Last Name" ,
            "Relation" ,
            "Gender" ,
            "Payment" ,
            "Omang" ,
            "Passport" ,
            "Date Of Birth"
        ];
    }
}
