{{-- PLANTALLRISKS (PAR) Coverage --}}
<div class="par-coverage-section">
    {{-- Policy Information --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Policy Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.project_name" placeholder="Project Name">
                                <label>Project Name</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.project_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model="parCoverage.{{ $policyCoverage->id }}.policy_period_months">
                                    <option value="">- Select Policy Period -</option>
                                    <option value="12">Allow up to 12 months max</option>
                                    <option value="24">Allow up to 24 months max</option>
                                    <option value="36">Allow up to 36 months max</option>
                                </select>
                                <label>Policy Period</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.policy_period_months"/>
                            </div>
                        </div>
                        @if(in_array($parCoverage[$policyCoverage->id]['policy_period_months'] ?? '', ['24', '36']))
                        <div class="col-sm-3">
                            <div class="mb-3">
                                @if(empty($parCoverage[$policyCoverage->id]['approved_by'] ?? null))
                                    <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'PAR')">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                @else
                                    @php
                                        $approver = \AlphaDirect\Models\User::find($parCoverage[$policyCoverage->id]['approved_by'] ?? null);
                                        $approvedAt = isset($parCoverage[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($parCoverage[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
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
                                    $policyPeriod = $parCoverage[$policyCoverage->id]['policy_period_months'] ?? '';
                                    $isDisabled = !empty($policyPeriod) && $policyPeriod != '12';
                                @endphp
                                <select class="form-select" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.is_renewable" @if($isDisabled) disabled @endif>
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Renewable Policy</option>
                                    <option value="No" @if($isDisabled) selected @endif>No</option>
                                </select>
                                <label>Renewable Policy</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.is_renewable"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.is_project_specific">
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Project Specific Policy</option>
                                    <option value="No">No</option>
                                </select>
                                <label>Project Specific Policy</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.is_project_specific"/>
                            </div>
                        </div>
                     
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Policy Information
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Specification of Insured Items --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Specification of Insured Items</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 7%">Item No</th>
                                    <th style="width: 7%">Qty</th>
                                    <th style="width: 25%">Description of items (type, manufacturer, capacity)</th>
                                    <th style="width: 10%">Year of manufacture</th>
                                    <th style="width: 13%">Sum insured</th>
                                    <th style="width: 10%">Deductible</th>
                                    <th style="width: 10%">Rate %</th>
                                    <th style="width: 13%">Premium</th>
                                    <th style="width: 5%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $items = $parInsuredItems[$policyCoverage->id] ?? [];
                                    if (empty($items)) {
                                        $items = [0 => []];
                                    }
                                @endphp
                                @foreach($items as $index => $item)
                                <tr wire:key="par-item-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control text-center" value="{{ $index + 1 }}" readonly>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.qty" placeholder="Qty">
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.description" rows="2" placeholder="Description of items (type, manufacturer, capacity)"></textarea>
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.year_of_manufacture" 
                                            placeholder="Year of manufacture (4 digits)"
                                            maxlength="4"
                                            pattern="[0-9]{4}"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
                                            title="Please enter a 4-digit year (e.g., 2024)">
                                        <x-form-input-error name="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.year_of_manufacture"/>
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" 
                                            wire:change="calculateParItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Sum insured">
                                        <x-form-input-error name="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.sum_insured"/>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                            wire:change="calculateParItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            placeholder="Rate %">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                            wire:change="calculateParItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Premium">
                                        <x-form-input-error name="parInsuredItems.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                                    </td>
                                    <td>
                                        @if($index == 0)
                                            <button type="button" class="btn btn-info btn-sm" wire:click="addParInsuredItemRow({{ $policyCoverage->id }})" title="Add Item">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm mt-1" wire:click="addParMiscellaneousItem({{ $policyCoverage->id }})" title="Add Miscellaneous Item">
                                                <i class="fa fa-plus-circle"></i> Misc
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeParInsuredItemRow({{ $policyCoverage->id }}, {{ $index }})" title="Remove Item">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Total Sum Insured and Total Premium --}}
                    <div class="row mt-4">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateParTotalSumInsured($policyCoverage->id) }}" placeholder="Total Sum Insured" readonly>
                                <label><strong>Total Sum Insured:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateParTotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium:</strong></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Insured Items
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section II - Third Party Liability --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Section II – Third Party Liability</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 28%">Insured items</th>
                                    <th style="width: 18%">Limits of indemnity ¹</th>
                                    <th style="width: 15%">Deductibles</th>
                                    <th style="width: 12%">Rate %</th>
                                    <th style="width: 15%">Premium</th>
                                    <th style="width: 12%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section2Items = $parSection2Items[$policyCoverage->id] ?? [];
                                    if (empty($section2Items)) {
                                        $section2Items = [0 => ['item_type' => 'Bodily Injury']];
                                    }
                                @endphp
                                @foreach($section2Items as $index => $item)
                                <tr wire:key="par-section2-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <select class="form-select mb-2" wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.item_type">
                                            <option value="">- Select Item -</option>
                                            <option value="Bodily Injury">Bodily Injury</option>
                                            <option value="Anyone person">- anyone person</option>
                                            <option value="Total">- total</option>
                                            <option value="Property Damage">Property Damage</option>
                                            <option value="Other">Other (Specify below)</option>
                                        </select>
                                        <input type="text" class="form-control" wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Additional description (optional)">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity" 
                                            wire:change="calculateParSection2ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Limits of indemnity">
                                        <x-form-input-error name="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity"/>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductibles">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                            wire:change="calculateParSection2ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            placeholder="Rate %">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                            wire:change="calculateParSection2ItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Premium">
                                        <x-form-input-error name="parSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                                    </td>
                                    <td>
                                        @if($index == 0)
                                            <button type="button" class="btn btn-info btn-sm" wire:click="addParSection2Row({{ $policyCoverage->id }})" title="Add Item">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm mt-1" wire:click="addParSection2MiscItem({{ $policyCoverage->id }})" title="Add Miscellaneous Item">
                                                <i class="fa fa-plus-circle"></i> Misc
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeParSection2Row({{ $policyCoverage->id }}, {{ $index }})" title="Remove Item">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Totals --}}
                    <div class="row mt-4">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateParSection2TotalLimit($policyCoverage->id) }}" placeholder="Total Limit of Indemnity" readonly>
                                <label><strong>Total limit of indemnity under Section II:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateParSection2TotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total premium under Section II:</strong></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section II
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Additional Notes</h3>
                </div>
                <div class="card-body">
                    <div class="form-floating">
                        <textarea class="form-control" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.additional_notes" rows="4" placeholder="Additional Notes" style="height: 120px;"></textarea>
                        <label>Additional Notes</label>
                        <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.additional_notes"/>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Notes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Execution Details --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Execution Details</h3>
                </div>
                <div class="card-body">
                    <p class="mb-3"><strong>In witness whereof the undersigned being duly authorized by the Insurers and on behalf of the Insurers has (have) hereunto set his (their) hand(s)</strong></p>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.executed_at" placeholder="Executed at">
                                <label>Executed at</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.executed_at"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.execution_date" placeholder="Date"/>
                                <label>Date</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.execution_date"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="parCoverage.{{ $policyCoverage->id }}.signature" placeholder="Signature">
                                <label>Signature</label>
                                <x-form-input-error name="parCoverage.{{ $policyCoverage->id }}.signature"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Execution Details
                        </button>
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
                                <input type="file" class="form-control" wire:model="parPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only (Maximum DPI: 600)</small>
                                @if($parPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $parPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="parPolicyWording"/>
                            </div>
                        </div>
                        @if(isset($parCoverage[$policyCoverage->id]['policy_wording_path']) && !empty($parCoverage[$policyCoverage->id]['policy_wording_path']))
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <strong>Current Policy Wording:</strong><br>
                                <a href="{{ config('app.S3_BASE_URL') . $parCoverage[$policyCoverage->id]['policy_wording_path'] }}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="fa fa-download"></i> View/Download Policy Wording
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveParCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Policy Wording
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculateParPremium(element, sectionType, policyCoverageId, index, sumFieldName) {
    // Find the row containing this input
    const row = element.closest('tr');
    if (!row) return;
    
    // Get all inputs in the row
    const inputs = row.querySelectorAll('input[type="text"]');
    
    // Get sum insured/limit field based on section type
    let sumInput, rateInput, premiumInput;
    
    if (sectionType === 'parInsuredItems') {
        // Insured Items: sum_insured, deductible, rate, premium
        sumInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('sum_insured');
        });
        rateInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('.rate') && !model.includes('premium');
        });
        premiumInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('.premium');
        });
    } else if (sectionType === 'parSection2Items') {
        // Section 2: limit_of_indemnity, deductible, rate, premium
        sumInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('limit_of_indemnity');
        });
        rateInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('.rate') && !model.includes('premium');
        });
        premiumInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('.premium');
        });
    }
    
    if (!sumInput || !rateInput || !premiumInput) {
        return;
    }
    
    // Get values and clean them (remove commas, currency symbols, etc.)
    let sumValue = sumInput.value.toString().replace(/[^\d.-]/g, '') || '0';
    let rateValue = rateInput.value.toString().replace(/[^\d.-]/g, '') || '0';
    
    sumValue = parseFloat(sumValue) || 0;
    rateValue = parseFloat(rateValue) || 0;
    
    // Calculate premium: Premium = (Sum Insured × Rate) / 100
    // If rate is 0, premium should be 0
    let premium = 0;
    if (sumValue > 0 && rateValue > 0) {
        premium = (sumValue * rateValue) / 100;
    } else if (sumValue > 0 && rateValue == 0) {
        premium = 0;
    }
    
    // Format premium value with commas and 2 decimal places
    // Always set a value (even if 0) so validation doesn't treat it as empty
    let formattedPremium = '';
    if (premium > 0) {
        formattedPremium = premium.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else if (premium == 0 && sumValue > 0) {
        // If sum insured has value, always set premium to "0" (not empty) when rate is 0
        formattedPremium = '0';
    }
    
    // Update the premium input value immediately for visual feedback
    premiumInput.value = formattedPremium;
    
    // Trigger change event to sync with Livewire
    const changeEvent = new Event('change', { bubbles: true });
    premiumInput.dispatchEvent(changeEvent);
}

// Premium → rate is handled server-side via wire:change="calculateParItemRateFromPremium" etc.

// Handle Policy Period change to auto-select and disable Renewable Policy field
document.addEventListener('change', function(e) {
    if (e.target && e.target.matches('select[wire\\:model*="policy_period_months"]')) {
        const policyPeriod = e.target.value;
        const model = e.target.getAttribute('wire:model');
        
        // Extract the policy coverage ID from the wire:model attribute
        const match = model.match(/parCoverage\.(\d+)\.policy_period_months/);
        if (match) {
            const policyCoverageId = match[1];
            
            // Find the renewable policy select field for this policy coverage
            const renewableSelect = document.querySelector(`select[wire\\:model\\.defer="parCoverage.${policyCoverageId}.is_renewable"]`);
            
            if (renewableSelect) {
                if (policyPeriod && policyPeriod !== '12') {
                    // If policy period is not 12, set to "No" and disable
                    renewableSelect.value = 'No';
                    renewableSelect.disabled = true;
                    
                    // Trigger change event to sync with Livewire
                    const changeEvent = new Event('change', { bubbles: true });
                    renewableSelect.dispatchEvent(changeEvent);
                } else if (policyPeriod === '12') {
                    // If policy period is 12, enable the field
                    renewableSelect.disabled = false;
                }
            }
        }
    }
}, true);
</script>
