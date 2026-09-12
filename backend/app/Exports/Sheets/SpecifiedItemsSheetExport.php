<?php

namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\PolicyCoverage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Illuminate\Support\Collection;

class SpecifiedItemsSheetExport implements WithTitle, FromCollection, WithHeadings, WithMapping, WithEvents
{
    public $policy;
    public $termId;
    public $actionId;
    public $coverageCode;
    public $policyCoverages;
    /** @var string[] Existing risk-address names for the Risk Address dropdown. */
    public $riskAddresses;

    public function __construct($policy, $termId, $actionId, $coverageCode, $policyCoverages, array $riskAddresses = [])
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
        $this->coverageCode = $coverageCode;
        $this->policyCoverages = $policyCoverages;
        $this->riskAddresses = $riskAddresses;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return self::excelSheetTitle($this->coverageCode ?? 'NA');
    }

    /**
     * Excel caps worksheet (tab) titles at 31 characters and forbids the
     * characters : \ / ? * [ ]. A coverage code longer than 31 chars (or with
     * an illegal char) would otherwise make PhpSpreadsheet throw and 500 the
     * whole export. Produce a safe title.
     *
     * NOTE: the importer applies the SAME transform when matching a sheet back
     * to its coverage code (see SpecifiedItemsImport::excelSheetTitle), so a
     * truncated title still round-trips to the full code on re-import. Keep the
     * two implementations in sync.
     */
    public static function excelSheetTitle(?string $code): string
    {
        $t = (string) ($code ?? '');
        $t = preg_replace('/[:\\\\\/?*\[\]]/u', ' ', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        if ($t === '') {
            $t = 'NA';
        }
        return mb_substr($t, 0, 31);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Get policy coverage IDs for this coverage
        $policyCoverageIds = collect($this->policyCoverages)->pluck('id')->toArray();

        // withTrashed() so soft-deleted (Deactive) items are also exported —
        // the Status column shows their state and lets the operator manage it.
        return PolicySpecifiedItem::withTrashed()
        ->with([
            'specifiedCoveragesItems',
            'policyCoverage.riskAddress',
            'policyCoverage.coverage'
        ])
        ->whereIn('policy_coverage_id', $policyCoverageIds)
        ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Risk Address',
            'Item Description',
            'Sum Insured',
            'Status',
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        if (!$row) {
            return ['', '', '', ''];
        }

        return [
            $row->policyCoverage->riskAddress->address_name ?? '',
            $row->specifiedCoveragesItems->specified_name ?? '',
            number_format($row->sum_insured ?? 0, 2, '.', ''),
            $row->deleted_at ? 'Deactive' : 'Active',
        ];
    }

    /**
     * Attach a dropdown (data validation list) to the "Risk Address" column (A)
     * so operators pick an existing address instead of typing it — preventing
     * risk-address name mismatches on import. The list references the hidden
     * RiskAddressOptions sheet built by SpecifiedItemsExport.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Status dropdown (column D) — Active / Deactive. Always
                // attached (independent of risk addresses). Uses an inline
                // list so no lookup sheet is needed. Blank/Active keeps the
                // item; Deactive soft-deletes it on import.
                for ($rowNo = 2; $rowNo <= 501; $rowNo++) {
                    $sdv = $sheet->getCell('D' . $rowNo)->getDataValidation();
                    $sdv->setType(DataValidation::TYPE_LIST);
                    $sdv->setErrorStyle(DataValidation::STYLE_STOP);
                    $sdv->setAllowBlank(true);
                    $sdv->setShowDropDown(true); // show the in-cell arrow (see note below)
                    $sdv->setShowInputMessage(true);
                    $sdv->setShowErrorMessage(true);
                    $sdv->setPromptTitle('Status');
                    $sdv->setPrompt('Active (or blank) keeps the item; Deactive removes it.');
                    $sdv->setErrorTitle('Invalid status');
                    $sdv->setError('Choose Active or Deactive.');
                    $sdv->setFormula1('"Active,Deactive"');
                }

                $count = count($this->riskAddresses);
                if ($count < 1) {
                    return; // No risk addresses → no Risk Address dropdown to attach.
                }

                // Range on the hidden lookup sheet holding the address names.
                $listRange = "'" . RiskAddressOptionsSheetExport::SHEET_TITLE . "'!\$A\$1:\$A\$" . $count;

                // Apply to the data rows under the header. Cap at 500 rows —
                // plenty for a template; rows beyond still import, just without
                // the picker.
                for ($rowNo = 2; $rowNo <= 501; $rowNo++) {
                    $dv = $sheet->getCell('A' . $rowNo)->getDataValidation();
                    $dv->setType(DataValidation::TYPE_LIST);
                    $dv->setErrorStyle(DataValidation::STYLE_STOP);
                    $dv->setAllowBlank(true);
                    // The in-cell dropdown arrow. The OOXML `showDropDown`
                    // attribute is inverted (1 = HIDE the arrow), and
                    // PhpSpreadsheet's writer inverts getShowDropDown() again
                    // (writes !value): so setShowDropDown(true) => showDropDown="0"
                    // => the arrow SHOWS. Leaving it at the default (false) writes
                    // showDropDown="1" and hides the arrow. Must be true here.
                    $dv->setShowDropDown(true);
                    $dv->setShowInputMessage(true);
                    $dv->setShowErrorMessage(true);
                    $dv->setPromptTitle('Risk Address');
                    $dv->setPrompt('Pick an existing risk address from the list.');
                    $dv->setErrorTitle('Unknown risk address');
                    $dv->setError('This address is not on the policy — pick one from the dropdown.');
                    $dv->setFormula1($listRange);
                }
            },
        ];
    }
}

