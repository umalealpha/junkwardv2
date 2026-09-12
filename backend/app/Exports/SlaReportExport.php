<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Generic SLA report export — headings + pre-built rows assembled by
 * SlaReportService. Each row is an ordered array of scalars, so no per-row
 * mapping is needed. Used for the Compliance / Breach / Assignee / Monthly
 * reports in both XLSX and CSV.
 */
class SlaReportExport implements FromCollection, WithHeadings
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
