<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class DpoReconsilationExport implements WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;

    private $headings = [
        'ref.',
        'company name',
        'date',
        'booking ref.',
        'policynumber',
        'service date',
        'customer name',
        'customer address',
        'customer e-mail',
        'customer phone number',
        'type',
        'status',
        'total',
        'dpo fee',
        'currency',
        'approval',
        'payment date',
        'payment method',
        'bank name',
        'mno name',
        'card holder',
        'user',
        'final payment',
        'final currency',
        'mcc',
        'token',

    ];


    public function headings() : array
    {
        return $this->headings;
    }
}
