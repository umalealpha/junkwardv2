<?php

namespace AlphaDirect\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hidden helper sheet that holds the policy's risk-address names in column A
 * (one per row, no header). The per-coverage Specified Items sheets reference
 * this range in their "Risk Address" column data-validation dropdown, so the
 * operator PICKS an existing address instead of typing it — preventing
 * risk-address name mismatches on import.
 *
 * The title deliberately does NOT match any coverage code, so
 * SpecifiedItemsImport skips it (its sheet→coverage lookup fails →
 * currentSheetName stays null → all its rows are skipped). It is hidden in
 * AfterSheet so operators only see the coverage tabs.
 */
class RiskAddressOptionsSheetExport implements FromArray, WithTitle, WithEvents
{
    /** Sheet title referenced by the dropdown formula. Not a coverage code. */
    public const SHEET_TITLE = 'RiskAddressOptions';

    /** @var string[] */
    protected array $addresses;

    public function __construct(array $addresses)
    {
        $this->addresses = $addresses;
    }

    public function title(): string
    {
        return self::SHEET_TITLE;
    }

    public function array(): array
    {
        // One address per row in column A (A1, A2, …). No header row so the
        // range referenced by the dropdown is exactly the address list.
        return array_map(fn ($a) => [$a], $this->addresses);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Hide the helper sheet — operators should only see coverage tabs.
                $event->sheet->getDelegate()->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            },
        ];
    }
}
