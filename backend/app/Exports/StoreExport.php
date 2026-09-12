<?php

namespace AlphaDirect\Exports;

use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\Quote;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Stores;
use AlphaDirect\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StoreExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    private $headings = [
        'ID',
        'Name',
        'Partner',
        'City',
        'Status'
    ];

    public function map($id): array
    {

        return [


        ];
    }

    public function collection()
    {
        $query = Stores::get();
        return $query->pluck('id');
    }

    public function headings() : array
    {
        return $this->headings;
    }
}
