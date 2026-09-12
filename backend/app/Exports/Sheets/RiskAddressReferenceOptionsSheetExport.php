<?php

namespace AlphaDirect\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hidden helper sheet holding the valid option lists for the Risk Address
 * template's reference columns (District, City, Extension, Occupation,
 * Structure Type, Construction Type, Usage, Occupancy Type) — one list per
 * column, no header row, so a cross-sheet range like
 * "'RiskAddressReferenceOptions'!$A$1:$A$29" is exactly that field's options.
 *
 * RiskAddressDataSheet's AfterSheet handler attaches a TYPE_LIST data
 * validation to each reference column pointing at the matching column here.
 *
 * Mirrors RiskAddressOptionsSheetExport (single-column risk-address-name
 * lookup for Specified Items), just with several columns side by side.
 *
 * The title deliberately does not match any coverage code / import sheet
 * name so the importer ignores it, and it is hidden in AfterSheet so
 * operators only see the "Risk Address" data tab.
 */
class RiskAddressReferenceOptionsSheetExport implements FromArray, WithTitle, WithEvents
{
    /** Sheet title referenced by the dropdown formulas. */
    public const SHEET_TITLE = 'RiskAddressRefOptions';

    /** Column letters, in the same left-to-right order as the data sheet. */
    public const COL_DISTRICT = 'A';
    public const COL_CITY = 'B';
    public const COL_EXTENSION = 'C';
    public const COL_OCCUPATION = 'D';
    public const COL_STRUCTURE_TYPE = 'E';
    public const COL_CONSTRUCTION_TYPE = 'F';
    public const COL_USAGE = 'G';
    public const COL_OCCUPANCY_TYPE = 'H';

    /** Keys expected in the $options array, in column order. */
    private const KEYS_IN_COLUMN_ORDER = [
        'district',
        'city',
        'extension',
        'occupation',
        'structure_type',
        'construction_type',
        'usage',
        'occupancy_type',
    ];

    /** @var array<string, string[]> */
    protected array $options;

    /**
     * @param array<string, string[]> $options Keyed by the names in
     *  KEYS_IN_COLUMN_ORDER, each an array of option strings (already the
     *  exact list the caller wants offered — ordering/sorting is the
     *  caller's responsibility).
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function title(): string
    {
        return self::SHEET_TITLE;
    }

    /**
     * Number of options for a given key — the row count of the range a
     * dropdown formula should reference (e.g. "$A$1:$A$" . count(...)).
     */
    public function count(string $key): int
    {
        return count($this->options[$key] ?? []);
    }

    public function array(): array
    {
        $lists = [];
        foreach (self::KEYS_IN_COLUMN_ORDER as $key) {
            $lists[] = array_values($this->options[$key] ?? []);
        }

        $maxLength = 0;
        foreach ($lists as $list) {
            $maxLength = max($maxLength, count($list));
        }

        $rows = [];
        for ($i = 0; $i < $maxLength; $i++) {
            $row = [];
            foreach ($lists as $list) {
                $row[] = $list[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Hide the helper sheet — operators should only see the data tab.
                $event->sheet->getDelegate()->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            },
        ];
    }
}
