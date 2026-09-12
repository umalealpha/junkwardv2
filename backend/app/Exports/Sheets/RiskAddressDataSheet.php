<?php

namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class RiskAddressDataSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, WithEvents
{
    public Policy $policy;
    public $termId;
    public $actionId;
    /** @var RiskAddressReferenceOptionsSheetExport|null Source of the reference dropdown lists, if attached. */
    public $referenceOptions;

    public function __construct($policy, $termId = null, $actionId = null, ?RiskAddressReferenceOptionsSheetExport $referenceOptions = null)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
        $this->referenceOptions = $referenceOptions;
    }

    public function query()
    {
        // Get ALL existing risk addresses for this policy
        $query = RiskAddress::with(['state:id,name', 'city:id,name'])
            ->where('policy_id', $this->policy->id);
        
        // Optionally filter by term and action if provided
        if ($this->termId) {
            $query->where('term_id', $this->termId);
        }
        if ($this->actionId) {
            $query->where('action_id', $this->actionId);
        }
        
        return $query;
    }

    public function headings(): array
    {
        return [
            'Address Name',
            'lat',
            'lng',
            'Physical Address',
            'Risk District',
            'Risk City',
            'Extension',
            'Occupation',
            'Town Class',
            'Risk Class',
            'ISO RCV',
            'Year Built',
            'Area',
            'Structure Type',
            'Construction Type',
            'Distance To Water',
            'Distance To Fire',
            'Distance To Hydrant',
            'Usage',
            'Occupancy Type',
            'Central Fire Alarm',
            'Central Burglar Alarm',
            'Gated Community',
            'Automatic Sprinklers'
        ];
    }

    public function map($riskAddress): array
    {
        if (!$riskAddress) {
            return array_fill(0, 23, '');
        }

        // Helper to convert 0/1 to No/Yes
        $boolToYesNo = fn($value) => ($value == 1) ? 'Yes' : 'No';

        return [
            $riskAddress->address_name ?? '',
            $riskAddress->lat ?? '',
            $riskAddress->lng ?? '',
            $riskAddress->physical_address ?? '',
            $riskAddress->state->name ?? '',
            $riskAddress->city->name ?? '',
            $riskAddress->extension ?? '',
            $riskAddress->occupation ?? '',
            $riskAddress->town_class ?? '',
            $riskAddress->risk_class ?? '',
            $riskAddress->iso_rcv ?? '',
            $riskAddress->year_built ?? '',
            $riskAddress->area ?? '',
            $riskAddress->structure_type ?? '',
            $riskAddress->const_type ?? '',
            $riskAddress->distance_to_water ?? '',
            $riskAddress->distance_to_fire ?? '',
            $riskAddress->distance_to_hydrant ?? '',
            $riskAddress->usage ?? '',
            $riskAddress->occupancy_type ?? '',
            $boolToYesNo($riskAddress->central_fire),
            $boolToYesNo($riskAddress->central_burglar),
            $boolToYesNo($riskAddress->gated_community),
            $boolToYesNo($riskAddress->automatic)
        ];
    }

    public function title(): string
    {
        return "Risk Address";
    }

    /**
     * Attach in-cell dropdowns (data validation lists) to the reference
     * columns — Risk District, Risk City, Extension, Occupation, Structure
     * Type, Construction Type, Usage, Occupancy Type — so operators pick a
     * valid value instead of free-typing it. This prevents typos that
     * otherwise fail opaquely on import (District/City are hard-validated
     * by RiskAddressImport; the rest are guardrails for data consistency).
     *
     * Each dropdown's list lives on the hidden RiskAddressReferenceOptions
     * sheet (built and passed in via $this->referenceOptions) and is
     * referenced by a cross-sheet range, same pattern as the Specified
     * Items "Risk Address" dropdown (see SpecifiedItemsSheetExport).
     *
     * $this->referenceOptions is optional: when this sheet is reused
     * without it (e.g. by another export that doesn't wire up the lookup
     * sheet), no dropdowns are attached and the sheet behaves exactly as
     * before.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (!$this->referenceOptions) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $sheetTitle = RiskAddressReferenceOptionsSheetExport::SHEET_TITLE;

                // dataColumn => [reference sheet column, options key, prompt label]
                $dropdowns = [
                    'E' => [RiskAddressReferenceOptionsSheetExport::COL_DISTRICT, 'district', 'Risk District'],
                    'F' => [RiskAddressReferenceOptionsSheetExport::COL_CITY, 'city', 'Risk City'],
                    'G' => [RiskAddressReferenceOptionsSheetExport::COL_EXTENSION, 'extension', 'Extension'],
                    'H' => [RiskAddressReferenceOptionsSheetExport::COL_OCCUPATION, 'occupation', 'Occupation'],
                    'N' => [RiskAddressReferenceOptionsSheetExport::COL_STRUCTURE_TYPE, 'structure_type', 'Structure Type'],
                    'O' => [RiskAddressReferenceOptionsSheetExport::COL_CONSTRUCTION_TYPE, 'construction_type', 'Construction Type'],
                    'S' => [RiskAddressReferenceOptionsSheetExport::COL_USAGE, 'usage', 'Usage'],
                    'T' => [RiskAddressReferenceOptionsSheetExport::COL_OCCUPANCY_TYPE, 'occupancy_type', 'Occupancy Type'],
                ];

                foreach ($dropdowns as $dataColumn => [$refColumn, $optionsKey, $label]) {
                    $count = $this->referenceOptions->count($optionsKey);
                    if ($count < 1) {
                        continue; // No options for this field — nothing to attach.
                    }

                    $listRange = "'{$sheetTitle}'!\${$refColumn}\$1:\${$refColumn}\${$count}";

                    for ($rowNo = 2; $rowNo <= 501; $rowNo++) {
                        $dv = $sheet->getCell($dataColumn . $rowNo)->getDataValidation();
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
                        $dv->setPromptTitle($label);
                        $dv->setPrompt('Pick a value from the list.');
                        $dv->setErrorTitle('Invalid ' . $label);
                        $dv->setError('Choose a value from the dropdown list.');
                        $dv->setFormula1($listRange);
                    }
                }
            },
        ];
    }
}

