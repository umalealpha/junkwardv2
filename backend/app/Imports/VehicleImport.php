<?php

namespace AlphaDirect\Imports;

use AlphaDirect\Vehicle;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\MotorType;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;


class VehicleImport implements ToModel,WithHeadingRow
{
    use Importable;
    public $policy;
    public $termId;
    public $actionId;
    private $riskAddressCache = [];
    private $currentRiskId = null;
    private $vehicleTypesMap = []; // motor_name => id mapping

    public function  __construct($policy,$termId,$actionId)
    {
        $this->policy= $policy;
        $this->termId= $termId;
        $this->actionId= $actionId;
        HeadingRowFormatter::default('none');
        
        // Load vehicle types mapping (motor_name => id) for lookup during import
        $this->vehicleTypesMap = MotorType::where('product_id', $this->policy->product_id)
            ->orderBy('motor_name')
            ->get()
            ->pluck('id', 'motor_name')
            ->toArray();
    }
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if (!isset($this->policy->id) or !isset($this->policy->customer_id)) {
            return null;
        }
        
        // Get Vehicle Number and validate it's not blank
        $vehicleNumber = trim($row['Vehicle Number'] ?? '');
        
        // Validate Vehicle Number is not blank
        if (empty($vehicleNumber)) {
            $errorMessage = "The Vehicle Number field is blank";
            \Log::error($errorMessage, ['row' => $row]);
            throw new \Exception($errorMessage);
        }
        
        // Handle Risk Address (similar to CoverageImport)
        // If Risk Address is provided, find the risk address ID
        // If empty, use the last risk_id from previous row (for grouped vehicles)
        $riskId = null;
        if (!empty($row['Risk Address'])) {
            $riskAddressName = trim($row['Risk Address']);
            
            // Use cache to avoid repeated queries (exclude soft-deleted risk addresses)
            if (!isset($this->riskAddressCache[$riskAddressName])) {
                $riskAddress = RiskAddress::where('policy_id', $this->policy->id)
                    ->where('term_id', $this->termId)
                    ->where('action_id', $this->actionId)
                    ->where('address_name', $riskAddressName)
                    ->whereNull('deleted_at') // Exclude soft-deleted risk addresses
                    ->first();
                
                $this->riskAddressCache[$riskAddressName] = $riskAddress ? $riskAddress->id : null;
            }
            
            $riskId = $this->riskAddressCache[$riskAddressName];
            
            // Validate Risk Address exists for this policy
            if ($riskId === null) {
                $errorMessage = "Risk address '{$riskAddressName}' does not exist for this policy";
                \Log::error($errorMessage, [
                    'risk_address' => $riskAddressName,
                    'vehicle_number' => $vehicleNumber ?? '',
                    'row' => $row
                ]);
                throw new \Exception($errorMessage);
            }
            
            $this->currentRiskId = $riskId; // Store for next row if empty
        } else {
            // If Risk Address is empty, use the last risk_id (for grouped vehicles)
            $riskId = $this->currentRiskId;
        }
        
        // Convert "Yes" to 1, "No" to 2 for is_imported
        $isImported = $row['Is Imported'] ?? '';
        $isImportedValue = $isImported;
        if (strtolower(trim($isImported)) === 'yes') {
            $isImportedValue = 1;
        } elseif (strtolower(trim($isImported)) === 'no') {
            $isImportedValue = 2;
        }
        
        // Convert Vehicle Type (motor_name) to ID if needed
        $vehicleType = trim($row['Vehicle Type'] ?? '');
        $vehicleTypeId = $vehicleType;
        if (!empty($vehicleType)) {
            // If it's a motor_name (exists in mapping), convert to ID
            if (isset($this->vehicleTypesMap[$vehicleType])) {
                $vehicleTypeId = $this->vehicleTypesMap[$vehicleType];
            } elseif (is_numeric($vehicleType)) {
                // If it's already numeric (ID), use it as-is
                $vehicleTypeId = $vehicleType;
            }
            // If it's neither a known motor_name nor numeric, use the value as-is
        }
        
        // Check if vehicle exists (including soft-deleted ones)
        // First check for non-deleted, then check for soft-deleted
        $vehicle = Vehicle::where('vehiclePlate', $vehicleNumber)
            ->where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->actionId)
            ->whereNull('deleted_at')
            ->first();
        
        // If not found, check for soft-deleted vehicle
        if (!$vehicle) {
            $vehicle = Vehicle::where('vehiclePlate', $vehicleNumber)
                ->where('policy_id', $this->policy->id)
                ->where('term_id', $this->termId)
                ->where('action_id', $this->actionId)
                ->whereNotNull('deleted_at')
                ->first();
        }
        
        if ($vehicle) {
            // Update existing vehicle (restore if soft-deleted)
            $vehicle->customer_id = $this->policy->customer_id;
            $vehicle->risk_id = $riskId;
            $vehicle->is_imported = $isImportedValue;
            $vehicle->vehicle_type = $vehicleTypeId;
            $vehicle->make = $row['Make'] ?? '';
            $vehicle->model = $row['Model'] ?? '';
            $vehicle->engineNo = $row['Engine Number'] ?? '';
            $vehicle->chassisNo = $row['Chassis Number'] ?? '';
            $vehicle->year = $row['Year'] ?? '';
            $vehicle->seats = $row['Number Of Seats'] ?? '';
            $vehicle->estimated_value = $row['Estimated Value'] ?? '';
            $vehicle->claim_count = $row['No Of Accidents'] ?? '';
            $vehicle->deleted_at = null; // Restore if soft-deleted
            $vehicle->save();
        } else {
            // Create new vehicle
            $vehicle = new Vehicle();
            $vehicle->policy_id = $this->policy->id;
            $vehicle->customer_id = $this->policy->customer_id;
            $vehicle->term_id = $this->termId;
            $vehicle->action_id = $this->actionId;
            $vehicle->vehiclePlate = $vehicleNumber;
            $vehicle->risk_id = $riskId;
            $vehicle->is_imported = $isImportedValue;
            $vehicle->vehicle_type = $vehicleTypeId;
            $vehicle->make = $row['Make'] ?? '';
            $vehicle->model = $row['Model'] ?? '';
            $vehicle->engineNo = $row['Engine Number'] ?? '';
            $vehicle->chassisNo = $row['Chassis Number'] ?? '';
            $vehicle->year = $row['Year'] ?? '';
            $vehicle->seats = $row['Number Of Seats'] ?? '';
            $vehicle->estimated_value = $row['Estimated Value'] ?? '';
            $vehicle->claim_count = $row['No Of Accidents'] ?? '';
            $vehicle->save();
        }
        
        return $vehicle;
    }
}
