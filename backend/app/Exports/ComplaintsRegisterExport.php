<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Quarterly regulatory Complaints Register export — headings + pre-built rows
 * assembled by AdminConfigController::exportComplaints(). Each row is an ordered
 * array of scalars (no per-row mapping). Mirrors SlaReportExport.
 *
 * NOTE: the exact column order/labels are drafted from the fields the requester
 * listed; align them to the regulator's register template before go-live.
 */
class ComplaintsRegisterExport implements FromCollection, WithHeadings
{
    use Exportable;

    public function __construct(private array $headingsRow, private array $rows)
    {
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headingsRow;
    }
}
