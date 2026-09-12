{{-- TRAVEL INSURANCE Coverage --}}

<div class="travel-coverage-section">
    {{-- Policyholder and Policy Details --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Travel Insurance - Policy Details</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.policyholder" placeholder="Policyholder">
                                <label>Policyholder</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.policyholder"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.passport" placeholder="Passport">
                                <label>Passport</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.passport"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.phone_num" placeholder="Phone Number">
                                <label>Phone Number</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.phone_num"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" readonly>
                                <label>Policy Number (Auto-generated)</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.policy_number"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.number_passengers" placeholder="Number. Passengers">
                                <label>Number. Passengers</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.number_passengers"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Coverage Period --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Coverage Period</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model="travelCoverage.{{ $policyCoverage->id }}.effective_from" wire:change="calculateTravelPolicyPeriod({{ $policyCoverage->id }})" placeholder="Effective from"/>
                                <label>Effective from</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.effective_from"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model="travelCoverage.{{ $policyCoverage->id }}.expiry" wire:change="calculateTravelPolicyPeriod({{ $policyCoverage->id }})" placeholder="Expiry"/>
                                <label>Expiry</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.expiry"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Policy Financials --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Policy Financials</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model="travelCoverage.{{ $policyCoverage->id }}.policy_period_months">
                                    <option value="">- Select Policy Period -</option>
                                    <option value="12">Allow up to 12 months max</option>
                                  
                                </select>
                                <label>Policy Period</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.policy_period_months"/>
                            </div>
                        </div>
                        @if(in_array($travelCoverage[$policyCoverage->id]['policy_period_months'] ?? '', ['24', '36']))
                        <div class="col-sm-3">
                            <div class="mb-3">
                                @if(empty($travelCoverage[$policyCoverage->id]['approved_by'] ?? null))
                                    <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'TRAVEL')">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                @else
                                    @php
                                        $approver = \AlphaDirect\Models\User::find($travelCoverage[$policyCoverage->id]['approved_by'] ?? null);
                                        $approvedAt = isset($travelCoverage[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($travelCoverage[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
                                    @endphp
                                    <div class="alert alert-success mb-0 p-2">
                                        <strong>Approved</strong><br>
                                        <small>By: {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}</small><br>
                                        <small>At: {{ $approvedAt }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                @php
                                    $policyPeriod = $travelCoverage[$policyCoverage->id]['policy_period_months'] ?? '';
                                    $isDisabled = !in_array($policyPeriod, ['12']);
                                    $currentValue = $travelCoverage[$policyCoverage->id]['is_renewable'] ?? '';
                                    // Auto-set to "No" if policy period is not 12 months
                                    if ($isDisabled && $currentValue != 'No') {
                                        $this->travelCoverage[$policyCoverage->id]['is_renewable'] = 'No';
                                    }
                                @endphp
                                <select class="form-select" 
                                    wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.is_renewable"
                                    @if($isDisabled) disabled @endif>
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Renewable Policy</option>
                                    <option value="No">No</option>
                                </select>
                                <label>Renewable Policy</label>
                                @if($isDisabled)
                                    <small class="text-muted">Only available for 12-month policies</small>
                                @endif
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.is_renewable"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.policy_amount"                                     
                                    placeholder="Policy Amount"
                                    wire:change="calculateTravelVatAndGrandTotalPremium({{ $policyCoverage->id }})">
                                <label>Policy Amount</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.policy_amount"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.vat"                                     
                                    placeholder="VAT" 
                                    readonly>
                                <label>VAT (Auto-calculated)</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.vat"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.total" 
                                    placeholder="TOTAL" 
                                    wire:change="calculateTravelVatAndTotal({{ $policyCoverage->id }})">
                                <label>TOTAL (Auto-calculated)</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.total"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Destination and Origin Information --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Destination and Origin Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <textarea class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.destination_area" placeholder="Destination Area" rows="2"></textarea>
                                <label>Destination Area</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.destination_area"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.country_of_origin" placeholder="Country of Origin">
                                <label>Country of Origin</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.country_of_origin"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.product" placeholder="Product">
                                <label>Product</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.product"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.code" placeholder="Code">
                                <label>Code</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.code"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.insurance_company" placeholder="Insurance Company">
                                <label>Insurance Company</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.insurance_company"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.company_location" placeholder="Company Location">
                                <label>Company Location</label>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.company_location"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Benefits and Coverage --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Benefits and Coverage</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered benefits-coverage-table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">DESCRIPTION SUMMARY</th>
                                    <th style="width: 22%;">Sum Insured</th>
                                    <th style="width: 22%;">Excess</th>
                                    <th style="width: 6%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $benefitKeys = [
                                        'personal_assistance',
                                        'relay_urgent_messages',
                                        'dispatch_medication',
                                        'general_information',
                                        'hijack',
                                        'medical_transportation_repatriation',
                                        'medical_transportation_or_repatriation',
                                        'transport_person_hospitalisation',
                                        'max_10_days_excess',
                                        'transportation_repatriation_accompanying',
                                        'medical_expenses',
                                        'medical_expenses_abroad_covid',
                                        'compulsory_quarantine_covid',
                                        'repatriation_mortal_remains',
                                        'transport_repatriation_deceased',
                                        'baggage',
                                        'indemnity_checked_luggage',
                                        'compensation_baggage_delay',
                                        'location_forwarding_baggage',
                                        'cancellation',
                                        'reimbursement_cancellation_expenses',
                                        'curtailment',
                                        'curtailment_expenses',
                                        'early_return_family_matter',
                                        'personal_accident',
                                        'permanent_accidental_disability',
                                        'accidental_death_means_transport',
                                        'personal_liability',
                                        'personal_liability_material_damages',
                                        'legal_defence_not_traffic',
                                        'deposit_legal_costs',
                                        'personal_liability_physical_damages',
                                        'medical_complementary_services',
                                        'hospital_compensation',
                                        'cards',
                                        'replacement_passport_driving_licence',
                                        'delays',
                                        'indemnity_transport_departure_delay',
                                        'missed_connections',
                                    ];
                                    $headingKeys = [
                                        'personal_assistance',
                                        'medical_transportation_repatriation',
                                        'medical_expenses',
                                        'repatriation_mortal_remains',
                                        'baggage',
                                        'cancellation',
                                        'curtailment',
                                        'personal_accident',
                                        'personal_liability',
                                        'medical_complementary_services',
                                        'cards',
                                        'delays',
                                    ];
                                @endphp

                                @foreach($benefitKeys as $benefitKey)
                                    @php
                                        $isRemoved = $travelBenefits[$policyCoverage->id][$benefitKey]['_removed'] ?? false;
                                        $isHeading = in_array($benefitKey, $headingKeys);
                                    @endphp
                                    @if($isRemoved)
                                        @continue
                                    @endif
                                    <tr wire:key="travel-benefit-{{ $policyCoverage->id }}-{{ $benefitKey }}" class="{{ $isHeading ? 'benefit-heading-row table-light' : 'benefit-item-row' }}">
                                        <td class="{{ $isHeading ? 'fw-bold' : 'ps-4' }}">
                                            @if($isHeading)
                                                <span class="text-uppercase">{{ $travelBenefits[$policyCoverage->id][$benefitKey]['description'] ?? '' }}</span>
                                            @else
                                                <input type="text"
                                                    class="form-control form-control-sm border-0 bg-transparent px-0"
                                                    wire:model="travelBenefits.{{ $policyCoverage->id }}.{{ $benefitKey }}.description"
                                                    placeholder="Benefit Description"
                                                    readonly>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$isHeading)
                                                <input type="text"
                                                    class="form-control form-control-sm"
                                                    wire:model.defer="travelBenefits.{{ $policyCoverage->id }}.{{ $benefitKey }}.sum_insured"
                                                    placeholder="Sum Insured">
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$isHeading)
                                                <input type="text"
                                                    class="form-control form-control-sm"
                                                    wire:model.defer="travelBenefits.{{ $policyCoverage->id }}.{{ $benefitKey }}.excess"
                                                    placeholder="Excess">
                                            @endif
                                        </td>
                                        <td class="text-center align-middle">
                                            @if(!$isHeading)
                                                <button type="button"
                                                    class="btn btn-danger btn-sm"
                                                    wire:click="removeTravelBenefitRow({{ $policyCoverage->id }}, '{{ $benefitKey }}')"
                                                    title="Remove Benefit Row">
                                                    <i class="fa fa-minus"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                
                                {{-- Customer Benefits and Coverage heading --}}
                                <tr class="benefit-heading-row table-light">
                                    <td class="fw-bold">
                                        <span class="text-uppercase">Customer Benefits and Coverage</span>
                                    </td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                
                                {{-- Additional Custom Benefits (Repeater) --}}
                                @php
                                    $customBenefits = $travelCustomBenefits[$policyCoverage->id] ?? [];
                                @endphp
                                @if(!empty($customBenefits))
                                    @foreach($customBenefits as $index => $benefit)
                                    <tr wire:key="travel-custom-benefit-{{ $policyCoverage->id }}-{{ $index }}" class="table-info benefit-item-row">
                                        <td class="ps-4">
                                            <input type="text" 
                                                class="form-control form-control-sm border-0 bg-transparent px-0" 
                                                wire:model.defer="travelCustomBenefits.{{ $policyCoverage->id }}.{{ $index }}.description" 
                                                placeholder="Custom Benefit Description">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                class="form-control form-control-sm" 
                                                wire:model.defer="travelCustomBenefits.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" 
                                                placeholder="Sum Insured">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                class="form-control form-control-sm" 
                                                wire:model.defer="travelCustomBenefits.{{ $policyCoverage->id }}.{{ $index }}.excess" 
                                                placeholder="Excess">
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button"
                                                class="btn btn-danger btn-sm"
                                                wire:click="removeTravelCustomBenefitRow({{ $policyCoverage->id }}, {{ $index }})"
                                                title="Remove Benefit">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Add More Benefits Button --}}
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm" wire:click="addTravelCustomBenefitRow({{ $policyCoverage->id }})" title="Add Custom Benefit">
                            <i class="fa fa-plus"></i> Add More Benefits
                        </button>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Notes --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Additional Notes</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Enter Additional Notes</label>
                                <textarea class="form-control" wire:model.defer="travelCoverage.{{ $policyCoverage->id }}.additional_notes" rows="4" placeholder="Enter any additional notes or comments about this travel coverage..."></textarea>
                                <x-form-input-error name="travelCoverage.{{ $policyCoverage->id }}.additional_notes"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Policy Wording --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Policy Wording</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Upload Policy Wording Document</label>
                                <input type="file" class="form-control" wire:model="travelPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($travelPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $travelPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="travelPolicyWording"/>
                            </div>
                        </div>
                        @if(isset($travelCoverage[$policyCoverage->id]['policy_wording_path']) && !empty($travelCoverage[$policyCoverage->id]['policy_wording_path']))
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <strong>Current Policy Wording:</strong><br>
                                <a href="{{ Storage::url($travelCoverage[$policyCoverage->id]['policy_wording_path']) }}"
                                    target="_blank"
                                    class="btn btn-sm btn-primary mt-2">
                                        <i class="fa fa-download"></i> View / Download Policy Wording
                                    </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-success" wire:click="saveTravelCoverage({{ $policyCoverage->id }})">
                    <i class="fa fa-save"></i> Save Travel Coverage
                </button>
            </div>
        </div>
    </div>
</div>

<script>
console.log('🔵 Travel Coverage Script Loaded!');

// Function to format all numeric fields - runs multiple times to catch all data
function formatAllNumericFields() {
    console.log('🟢 formatAllNumericFields() called');
    formatNumericInputs();
}

// Run on DOM ready as well
document.addEventListener('DOMContentLoaded', function() {
    formatAllNumericFields();
    setTimeout(formatAllNumericFields, 100);
    setTimeout(formatAllNumericFields, 300);
    setTimeout(formatAllNumericFields, 500);
    setTimeout(formatAllNumericFields, 1000);
    setTimeout(formatAllNumericFields, 1500);
    setTimeout(formatAllNumericFields, 2000);
});

// Run on window load (after all resources loaded)
window.addEventListener('load', function() {
    formatAllNumericFields();
    setTimeout(formatAllNumericFields, 100);
    setTimeout(formatAllNumericFields, 300);
    setTimeout(formatAllNumericFields, 500);
});

// Reinitialize after Livewire updates
document.addEventListener('livewire:load', function () {
    // Run multiple times with delays to catch all data as it loads
    formatAllNumericFields();
    setTimeout(formatAllNumericFields, 100);
    setTimeout(formatAllNumericFields, 300);
    setTimeout(formatAllNumericFields, 600);
    setTimeout(formatAllNumericFields, 1000);
    setTimeout(formatAllNumericFields, 1500);
    setTimeout(formatAllNumericFields, 2000);
});

document.addEventListener('livewire:update', function () {
    setTimeout(function() {
        formatAllNumericFields();
    }, 100);
    // Run again after a longer delay to catch any late-loading data
    setTimeout(function() {
        formatAllNumericFields();
    }, 300);
    setTimeout(function() {
        formatAllNumericFields();
    }, 500);
    setTimeout(function() {
        formatAllNumericFields();
    }, 1000);
});

// Also listen for Livewire lifecycle hooks
if (typeof Livewire !== 'undefined' && typeof Livewire.hook === 'function') {
    Livewire.hook('message.processed', (message, component) => {
        setTimeout(formatAllNumericFields, 100);
        setTimeout(formatAllNumericFields, 300);
    });
}

// Function to format a numeric value with commas
function formatNumberWithCommas(value) {
    if (!value || value === '') return '';
    
    // If value is an element, format its value
    if (value && value.value !== undefined) {
        const element = value;
        let val = element.value;
        if (!val || val === '') return;
        
        // Remove existing commas and non-numeric characters except decimal point
        let numericValue = val.toString().replace(/,/g, '').replace(/[^0-9.]/g, '');
        
        // Check if it's a valid number
        if (isNaN(numericValue) || numericValue === '') return;
        
        // Split by decimal point
        let parts = numericValue.split('.');
        
        // Format integer part with commas
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        
        // Join back with decimal if exists
        const formatted = parts.length > 1 ? parts.join('.') : parts[0];
        element.value = formatted;
        return formatted;
    }
    
    // If value is a string/number, return formatted string
    // Remove existing commas and non-numeric characters except decimal point
    let numericValue = value.toString().replace(/,/g, '').replace(/[^0-9.]/g, '');
    
    // Check if it's a valid number
    if (isNaN(numericValue) || numericValue === '') return value;
    
    // Split by decimal point
    let parts = numericValue.split('.');
    
    // Format integer part with commas
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    // Join back with decimal if exists
    return parts.length > 1 ? parts.join('.') : parts[0];
}

// Function to format numeric inputs with commas
function formatNumericInputs() {
    // Find all numeric input fields in the travel coverage section
    const travelCoverageSection = document.querySelector('.travel-coverage-section');
    if (!travelCoverageSection) {
        console.log('Travel coverage section not found');
        return;
    }
    
    const allInputs = travelCoverageSection.querySelectorAll('input[type="text"]');
    console.log('Found ' + allInputs.length + ' text inputs in travel coverage');
    
    let formattedCount = 0;
    allInputs.forEach(function(input) {
        // Check if this input has a wire:model attribute
        const model = input.getAttribute('wire:model') || input.getAttribute('wire:model.defer') || '';
        if (!model) return;
        
        // Check if this is a numeric field based on model name
        // Exclude excess fields as they can contain mixed text and numbers
        const isNumericField = (model.includes('policy_amount') || 
                               model.includes('vat') || 
                               model.includes('total') ||
                               model.includes('sum_insured')) &&
                               !model.includes('excess');
        
        if (!isNumericField) return;
        
        // Get current value
        let value = input.value;
        if (!value || value.trim() === '') return;
        
        // Skip if already formatted (contains comma)
        if (value.toString().includes(',')) {
            return;
        }
        
        // Check if it's a valid number
        const numericValue = parseFloat(value.toString().replace(/[^\d.-]/g, ''));
        if (isNaN(numericValue) || numericValue === 0) return;
        
        // Format the value
        let formattedValue = formatNumberWithCommas(value);
        
        // Update value (including readonly fields on page load)
        if (input.value !== formattedValue && formattedValue !== '') {
            input.value = formattedValue;
            formattedCount++;
            console.log('Formatted field: ' + model + ' from ' + value + ' to ' + formattedValue);
        }
    });
    
    console.log('Formatted ' + formattedCount + ' fields');
}

// Format on blur for numeric fields and sync numeric value to Livewire
document.addEventListener('blur', function(e) {
    if (e.target.matches('input[type="text"]') && 
        (e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer'))) {
        
        const model = e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer') || '';
        
        // Check if this is a numeric field (exclude excess fields as they can contain text)
        if (model.includes('policy_amount') || 
            model.includes('vat') || 
            model.includes('total') ||
            model.includes('sum_insured')) {
            
            if (!e.target.readOnly && !e.target.disabled) {
                // Format for display
                let formattedValue = formatNumberWithCommas(e.target.value);
                if (e.target.value !== formattedValue) {
                    e.target.value = formattedValue;
                }
                
                // Sync numeric value (without commas) to Livewire for policy_amount
                if (window.Livewire && model.includes('policy_amount')) {
                    const component = e.target.closest('[wire\\:id]');
                    if (component) {
                        const componentId = component.getAttribute('wire:id');
                        const livewireComponent = window.Livewire.find(componentId);
                        if (livewireComponent) {
                            // Remove commas before syncing
                            const numericValue = e.target.value.replace(/,/g, '');
                            livewireComponent.set(model, numericValue);
                        }
                    }
                }
            }
        }
    }
}, true);

// Format on focus (when user clicks into field) - remove commas for easier editing
document.addEventListener('focus', function(e) {
    if (e.target.matches('input[type="text"]') && 
        (e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer'))) {
        
        const model = e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer') || '';
        
        // Check if this is a numeric field (exclude excess fields as they can contain text)
        if (model.includes('policy_amount') || 
            model.includes('vat') || 
            model.includes('total') ||
            model.includes('sum_insured')) {
            
            if (!e.target.readOnly && !e.target.disabled) {
                // Remove commas when user focuses on the field for easier editing
                let value = e.target.value;
                if (value && value.includes(',')) {
                    e.target.value = value.replace(/,/g, '');
                    // Move cursor to end
                    setTimeout(function() {
                        e.target.selectionStart = e.target.selectionEnd = e.target.value.length;
                    }, 0);
                }
            }
        }
    }
}, true);
</script>
