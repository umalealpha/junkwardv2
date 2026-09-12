<?php

namespace AlphaDirect\Imports;

use AlphaDirect\Models\RealpayTransactionsExcelData;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RealpayTransactionsImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {

        $contractSequence   = $row['contractsequence'];
        $trackingStartDate  = $this->transformDate($row['tracking_startdate'])->format('Y-m-d'); // Ensure date string

        return RealpayTransactionsExcelData::updateOrCreate(
            [
                'contractsequence'   => $contractSequence,
                'tracking_startdate' => $trackingStartDate,
            ],
            [
                'product'            => $row['product'],
                'beneficiarynumber'  => $row['beneficiarynumber'],
                'transaction_date'   => $this->transformDate($row['transaction_date']),
                'clientnumber'       => $row['clientnumber'],
                'domgcomg'           => $row['domgcomg'],
                'amountcollected'    => $row['amountcollected'],
                'status'             => 0, // default to 0 for processing
            ]
        );

    }

    protected function transformDate($value)
    {
        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
        } catch (\ErrorException $e) {
            return null;
        }
    }
}
