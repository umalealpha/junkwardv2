<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class VcsReconsilationExport implements WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;

    private $headings = [
        'Reference',
        'Original Reference',
        'Account Number',
        'Sort Code',
        'Account Holder',
        'Amount',
        'Status',
        'Transaction Date',
        'Transaction ID',
        'Result Code',
        'Result Description',
        'Transaction Source',
        'Currency',
    ];


    public function headings() : array
    {
        return $this->headings;
    }
}
