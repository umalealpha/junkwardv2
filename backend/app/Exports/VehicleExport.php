<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Vehicle;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\MotorType;
use AlphaDirect\Http\Traits\Excel\ExportTrait;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class VehicleExport implements WithHeadings, FromCollection, WithEvents
{
    public $policy;
    public $termId;
    public $actionId;
    public $maxLength = 100;
    private $vehicleTypesMap = [];

    public function __construct($policy, $termId, $actionId)
    {
        $this->policy = $policy;
        $this->termId = $termId;
        $this->actionId = $actionId;
        
        // Load vehicle types mapping (id => motor_name) for lookup
        $this->vehicleTypesMap = MotorType::where('product_id', $this->policy->product_id)
            ->orderBy('motor_name')
            ->get()
            ->pluck('motor_name', 'id')
            ->toArray();
    }

    public function headings(): array
    {
        return ["Risk Address", "Vehicle Number", "Is Imported", "Vehicle Type", "Make", "Model", "Engine Number", "Chassis Number", "Year", "Number Of Seats", "Estimated Value", "No Of Accidents"];
    }

    public function collection()
    {
        // Fetch vehicles with risk address relationship (exclude soft-deleted)
        $vehicles = Vehicle::where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->whereNull('deleted_at') // Exclude soft-deleted vehicles
            ->with('risk:id,address_name')
            ->orderBy('risk_id')
            ->orderBy('id')
            ->get();

        // Group vehicles by risk_id (similar to how CoverageExport groups by policyCoverage)
        $vehiclesByRisk = [];
        foreach ($vehicles as $vehicle) {
            $riskId = $vehicle->risk_id ?? 'no_risk';
            if (!isset($vehiclesByRisk[$riskId])) {
                $vehiclesByRisk[$riskId] = [];
            }
            $vehiclesByRisk[$riskId][] = $vehicle;
        }

        $headers = $this->headings();
        
        // Initialize default values for all rows
        // Note: We don't include headers here because WithHeadings interface adds them automatically
        $defaultValues = array_fill(0, count($headers), '');
        $data = [];
        for ($i = 0; $i < $this->maxLength; $i++) {
            $data[($i + 1)] = $defaultValues;
        }

        $generalCount = 0;
        
        // Process vehicles grouped by risk address (similar to CoverageExport's policyCoverage loop)
        foreach ($vehiclesByRisk as $riskId => $vehiclesInGroup) {
            foreach ($vehiclesInGroup as $key => $vehicle) {
                $generalCount++;
                $isFirstInGroup = ($key === 0);
                
                // Map vehicle data to row (similar to mapDataToRow in CoverageExport)
                $this->mapVehicleDataToRow($data, $generalCount, $headers, $vehicle, $isFirstInGroup);
            }
        }

        return new Collection($data);
    }

    /**
     * Map vehicle data to row (similar to mapDataToRow in CoverageExport)
     */
    private function mapVehicleDataToRow(&$data, $rowIndex, $headers, $vehicle, $isFirstInGroup)
    {
        // Risk Address column (similar to CoverageExport's riskAddress.address_name handling)
        $riskAddressIndex = array_search('Risk Address', $headers);
        if ($riskAddressIndex !== false) {
            if ($isFirstInGroup) {
                $data[$rowIndex][$riskAddressIndex] = $vehicle->risk->address_name ?? '';
            }
        }

        // Vehicle Number column
        $vehicleNumberIndex = array_search('Vehicle Number', $headers);
        if ($vehicleNumberIndex !== false) {
            $data[$rowIndex][$vehicleNumberIndex] = $vehicle->vehiclePlate ?? '';
        }

        // Is Imported column - convert 1 to "Yes", 2 to "No"
        $isImportedIndex = array_search('Is Imported', $headers);
        if ($isImportedIndex !== false) {
            $isImportedValue = $vehicle->is_imported ?? '';
            if ($isImportedValue == 1) {
                $data[$rowIndex][$isImportedIndex] = 'Yes';
            } elseif ($isImportedValue == 2) {
                $data[$rowIndex][$isImportedIndex] = 'No';
            } else {
                $data[$rowIndex][$isImportedIndex] = '';
            }
        }

        // Vehicle Type column - convert ID to motor_name if needed
        $vehicleTypeIndex = array_search('Vehicle Type', $headers);
        if ($vehicleTypeIndex !== false) {
            $vehicleType = $vehicle->vehicle_type ?? '';
            // If vehicle_type is numeric (ID), look up the motor_name
            if (!empty($vehicleType) && is_numeric($vehicleType) && isset($this->vehicleTypesMap[$vehicleType])) {
                $data[$rowIndex][$vehicleTypeIndex] = $this->vehicleTypesMap[$vehicleType];
            } else {
                // Otherwise use the value as-is (might already be a name)
                $data[$rowIndex][$vehicleTypeIndex] = $vehicleType;
            }
        }

        // Make column
        $makeIndex = array_search('Make', $headers);
        if ($makeIndex !== false) {
            $data[$rowIndex][$makeIndex] = $vehicle->make ?? '';
        }

        // Model column
        $modelIndex = array_search('Model', $headers);
        if ($modelIndex !== false) {
            $data[$rowIndex][$modelIndex] = $vehicle->model ?? '';
        }

        // Engine Number column
        $engineNoIndex = array_search('Engine Number', $headers);
        if ($engineNoIndex !== false) {
            $data[$rowIndex][$engineNoIndex] = $vehicle->engineNo ?? '';
        }

        // Chassis Number column
        $chassisNoIndex = array_search('Chassis Number', $headers);
        if ($chassisNoIndex !== false) {
            $data[$rowIndex][$chassisNoIndex] = $vehicle->chassisNo ?? '';
        }

        // Year column
        $yearIndex = array_search('Year', $headers);
        if ($yearIndex !== false) {
            $data[$rowIndex][$yearIndex] = $vehicle->year ?? '';
        }

        // Number Of Seats column
        $seatsIndex = array_search('Number Of Seats', $headers);
        if ($seatsIndex !== false) {
            $data[$rowIndex][$seatsIndex] = $vehicle->seats ?? '';
        }

        // Estimated Value column
        $estimatedValueIndex = array_search('Estimated Value', $headers);
        if ($estimatedValueIndex !== false) {
            $data[$rowIndex][$estimatedValueIndex] = $vehicle->estimated_value ?? '';
        }

        // No Of Accidents column
        $claimCountIndex = array_search('No Of Accidents', $headers);
        if ($claimCountIndex !== false) {
            $data[$rowIndex][$claimCountIndex] = $vehicle->claim_count ?? '';
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $maxLength = $this->maxLength + 2; // +2 for header row

                // Set risk address dropdown (column A) - start from row 2 (skip header)
                $riskAddress = $this->getRiskAddress();
                for ($i = 2; $i < $maxLength; $i++) {
                    ExportTrait::generateDropDown($event, 'A' . $i, $riskAddress);
                }

                // Set Is Imported dropdown (column C) with Yes/No options - start from row 2 (skip header)
                $isImportedOptions = ['Yes', 'No'];
                for ($i = 2; $i < $maxLength; $i++) {
                    ExportTrait::generateDropDown($event, 'C' . $i, $isImportedOptions);
                }

                // Set Vehicle Type dropdown (column D) - start from row 2 (skip header)
                $vehicleTypes = $this->getVehicleTypes();
                for ($i = 2; $i < $maxLength; $i++) {
                    ExportTrait::generateDropDown($event, 'D' . $i, $vehicleTypes);
                }
            },
        ];
    }

    public function getRiskAddress()
    {
        return RiskAddress::select('id', 'address_name')
            ->Policy($this->policy->id)
            ->term($this->termId)
            ->action($this->actionId)
            ->get()
            ->pluck('address_name', 'id')
            ->toArray();
    }

    public function getVehicleTypes()
    {
        return MotorType::where('product_id', $this->policy->product_id)
            ->orderBy('motor_name')
            ->get()
            ->pluck('motor_name', 'id')
            ->toArray();
    }
}
