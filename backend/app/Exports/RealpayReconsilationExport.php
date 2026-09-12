<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class RealpayReconsilationExport implements WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;

    private $headings = [
        'Installment Date',
        'Merchant',
        'Client Number',
        'Client Name',
        'Contract Number',
        'Contract Sequence',
        'InstSeq',
        'Installment Amount',
        'Total Amount',
        'Collected Amount',
        'Current CycleHits',
        'HitsAllowed (CurrentTracking)',
        'Tracking',
        'Report Status',
        'Current Status',
        'Result',
        'Client Bank',
        'DATE',
    ];


    public function headings() : array
    {
        return $this->headings;
    }
}
