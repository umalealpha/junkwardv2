<?php

namespace AlphaDirect\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export for a policy's Activity Log (Logs tab). Takes the same
 * merged, newest-first rows PolicyController::buildActivityLogRows()
 * builds for the on-screen tab, but unbounded — audit/investigation work
 * needs the full history, not just the latest 200 shown in the UI.
 */
class PolicyActivityLogExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * 'URL' is the endpoint the change came through, captured by OwenIt on
     * every audit row. It is the field that separates "who changed this" from
     * "how did they change it" — an edit via /admin/policy/{id} is the legacy
     * V1 panel, one via /api/v1/... is the V2 frontend, and the two have
     * different side effects (the V1 policy edit writes billingStartDate
     * without touching the RealPay schedule — see
     * RealPayBillingDateSynchroniser). buildActivityLogRows() has always
     * returned it and the Logs tab already searches on it; only this export
     * was dropping it, which is precisely where an investigation needs it.
     */
    public function headings(): array
    {
        return ['Id', 'Action By', 'IP Address', 'Done From', 'Tag', 'URL', 'Old Values', 'New Data', 'Activity Done'];
    }

    public function map($row): array
    {
        $stringify = fn ($v) => $v === null ? '' : (is_string($v) ? $v : json_encode($v));

        return [
            $row['id'] ?? '',
            $row['activityBy'] ?? '',
            $row['ipAddress'] ?? '',
            $row['doneFrom'] ?? '',
            $row['activityTag'] ?? '',
            $row['url'] ?? '',
            $stringify($row['oldValues'] ?? null),
            $stringify($row['newValues'] ?? null),
            $row['activityDone'] ?? '',
        ];
    }
}
