{{-- CONTRACTORSALLRISKS (CAR) Coverage --}}
<div class="car-coverage-section">
    {{-- Policy Schedule --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Contractors All Risk - Policy Schedule</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.branch" placeholder="Branch">
                                <label>Branch</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.branch"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.policy_no" placeholder="Policy No." readonly>
                                <label>Policy No. (Auto-generated)</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.policy_no"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.currency">
                                    <option value="">- Select Currency -</option>
                                    <option value="BWP">BWP</option>
                                   
                                </select>
                                <label>Currency</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.currency"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.declaration_no" placeholder="Declaration No.">
                                <label>Declaration No.</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.declaration_no"/>
                            </div>
                        </div>
                    </div>

                    {{-- Name and Address of Insured --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="text-primary">Name and Address of Insured</h5>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.insured_name" placeholder="Name" value="{{ $carCoverage[$policyCoverage->id]['insured_name'] ?? '' }}">
                                <label>Name</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.insured_name"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.insured_street" placeholder="Street" value="{{ $carCoverage[$policyCoverage->id]['insured_street'] ?? '' }}">
                                <label>Street</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.insured_street"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.insured_postal_code" placeholder="Postal code and city" value="{{ $carCoverage[$policyCoverage->id]['insured_postal_code'] ?? '' }}">
                                <label>Postal code and city</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.insured_postal_code"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.title_of_contract" placeholder="Title of contract">
                                <label>Title of contract</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.title_of_contract"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.project_name" placeholder="Project Name">
                                <label>Project Name</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.project_name"/>
                            </div>
                        </div>
                    </div>

                    {{-- Address of Risk --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="text-primary">Address of Risk</h5>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.risk_street" placeholder="Street" value="{{ $carCoverage[$policyCoverage->id]['risk_street'] ?? '' }}">
                                <label>Street</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.risk_street"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.risk_postal_code" placeholder="Postal code and city" value="{{ $carCoverage[$policyCoverage->id]['risk_postal_code'] ?? '' }}">
                                <label>Postal code and city</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.risk_postal_code"/>
                            </div>
                        </div>
                    </div>

                    {{-- Policy Dates --}}
                    <div class="row mt-3">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model="carCoverage.{{ $policyCoverage->id }}.policy_inception_date" placeholder="Policy inception date"/>
                                <label>Policy inception date</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.policy_inception_date"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model="carCoverage.{{ $policyCoverage->id }}.policy_expiry_date" placeholder="Policy expiry date"/>
                                <label>Policy expiry date</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.policy_expiry_date"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model="carCoverage.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                <label>Today's date</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.today_date"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.new_altered">
                                    <option value="">- Select -</option>
                                    <option value="New">New</option>
                                    <option value="Altered">Altered</option>
                                </select>
                                <label>New/altered</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.new_altered"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model="carCoverage.{{ $policyCoverage->id }}.policy_period_months">
                                    <option value="">- Select Policy Period -</option>
                                    <option value="12">Allow up to 12 months max</option>
                                    <option value="24">Allow up to 24 months max</option>
                                    <option value="36">Allow up to 36 months max</option>
                                </select>
                                <label>Policy Period</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.policy_period_months"/>
                            </div>
                        </div>
                        @if(in_array($carCoverage[$policyCoverage->id]['policy_period_months'] ?? '', ['24', '36']))
                        <div class="col-sm-3">
                            <div class="mb-3">
                                @if(empty($carCoverage[$policyCoverage->id]['approved_by'] ?? null))
                                    <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'CAR')">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                @else
                                    @php
                                        $approver = \AlphaDirect\Models\User::find($carCoverage[$policyCoverage->id]['approved_by'] ?? null);
                                        $approvedAt = isset($carCoverage[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($carCoverage[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
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
                                    $policyPeriod = $carCoverage[$policyCoverage->id]['policy_period_months'] ?? '';
                                    $isDisabled = !empty($policyPeriod) && $policyPeriod != '12';
                                @endphp
                                <select class="form-select" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.is_renewable" @if($isDisabled) disabled @endif>
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Renewable Policy</option>
                                    <option value="No" @if($isDisabled) selected @endif>No</option>
                                </select>
                                <label>Renewable Policy</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.is_renewable"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.is_project_specific">
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Project Specific Policy</option>
                                    <option value="No">No</option>
                                </select>
                                <label>Project Specific Policy</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.is_project_specific"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.maintenance_period_months" placeholder="Maintenance Period" value="12">
                                <label>Maintenance Period (Months after expiry - Default: 12)</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.maintenance_period_months"/>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.city_town_village" placeholder="City, town, village of risk" value="{{ $carCoverage[$policyCoverage->id]['city_town_village'] ?? '' }}">
                                <label>City, town, village of risk</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.city_town_village"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Policy Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 1 - Material Damage --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Section 1 – Material damage</h3>
                </div>
                <div class="card-body">
                    @foreach($carSection1Items[$policyCoverage->id] ?? [0] as $index => $item)
                    <div class="row mb-3" wire:key="section1-{{ $policyCoverage->id }}-{{ $index }}">
                        <div class="col-sm-3">
                            <div class="mb-3">
                                <select class="form-select" wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.item_type">
                                    <option value="">- Select Item -</option>
                                    <option value="Contract works - Contract price">Contract works - Contract price (permanent and temporary works, including all materials to be incorporated herein)</option>
                                    <option value="Contract works - Materials or items supplied by Principal">Contract works - Materials or items supplied by the Principal(s)</option>
                                    <option value="Construction plant and equipment">Construction plant and equipment</option>
                                    <option value="Construction machinery according to attached list">Construction machinery according to attached list</option>
                                    <option value="Clearance of debris">Clearance of debris</option>
                                    <option value="Other">Other (Specify in description)</option>
                                </select>
                                <textarea class="form-control mt-2" wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.description" rows="2" placeholder="Additional description"></textarea>
                                <x-form-input-error name="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.description"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" 
                                    wire:change="calculateCarSection1ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    x-mask:dynamic="$money($input)" 
                                    placeholder="Sum Insured">
                                <label>Sum Insured</label>
                                <x-form-input-error name="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.sum_insured"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible">
                                <label>Deductible</label>
                                <x-form-input-error name="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.deductible"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                    wire:change="calculateCarSection1ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    placeholder="Rate (e.g. 5)">
                                <label>Rate %</label>
                                <x-form-input-error name="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.rate"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                    wire:change="calculateCarSection1ItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    x-mask:dynamic="$money($input)" 
                                    placeholder="Premium">
                                <label>Premium</label>
                                <x-form-input-error name="carSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                            </div>
                        </div>
                        <div class="col-sm-1">
                            <div class="mb-3">
                                @if($index == 0)
                                    <button type="button" class="btn btn-info btn-sm" wire:click="addCarSection1Row({{ $policyCoverage->id }})">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeCarSection1Row({{ $policyCoverage->id }}, {{ $index }})">
                                        <i class="fa fa-minus"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Total Sum Insured and Total Premium under Section 1 --}}
                    <div class="row mt-4">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateCarSection1TotalSumInsured($policyCoverage->id) }}" placeholder="Total Sum Insured" readonly>
                                <label><strong>Total Sum Insured under Section 1:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateCarSection1TotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium under Section 1:</strong></label>
                            </div>
                        </div>
                    </div>

                    {{-- Plant List Section --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">List of Plant (Adds up to Summary Sum Insured and Premium above)</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 5%">No.</th>
                                            <th style="width: 50%">Description of Plant</th>
                                            <th style="width: 20%">Sum Insured</th>
                                            <th style="width: 20%">Premium</th>
                                            <th style="width: 5%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($carPlantListItems[$policyCoverage->id] ?? [0] as $index => $item)
                                        <tr wire:key="plant-list-{{ $policyCoverage->id }}-{{ $index }}">
                                            <td>
                                                <input type="text" class="form-control" wire:model.defer="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.item_no" placeholder="{{ $index + 1 }}" value="{{ $index + 1 }}" readonly>
                                            </td>
                                            <td>
                                                <textarea class="form-control" wire:model.defer="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.description" rows="2" placeholder="Description of Plant"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" wire:model.defer="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" x-mask:dynamic="$money($input)" placeholder="Sum Insured" x-on:blur="formatNumberWithCommas($el)">
                                                <x-form-input-error name="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.sum_insured"/>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" wire:model.defer="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.premium" x-mask:dynamic="$money($input)" placeholder="Premium">
                                                <x-form-input-error name="carPlantListItems.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                                            </td>
                                            <td>
                                                @if($index == 0)
                                                    <button type="button" class="btn btn-info btn-sm" wire:click="addCarPlantListItem({{ $policyCoverage->id }})" title="Add Plant Item">
                                                        <i class="fa fa-plus"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeCarPlantListItem({{ $policyCoverage->id }}, {{ $index }})">
                                                        <i class="fa fa-minus"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end"><strong>Total:</strong></th>
                                            <th>
                                                <input type="text" class="form-control" value="{{ $this->calculateCarPlantListTotalSumInsured($policyCoverage->id) }}" readonly>
                                            </th>
                                            <th>
                                                <input type="text" class="form-control" value="{{ $this->calculateCarPlantListTotalPremium($policyCoverage->id) }}" readonly>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Risk Types --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Risk</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 30%">Risk</th>
                                            <th style="width: 15%">Select</th>
                                            <th style="width: 18%">Limit of indemnity</th>
                                            <th style="width: 18%">Deductible</th>
                                            <th style="width: 19%">Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <strong>Earthquake, volcanism, tsunami</strong>
                                            </td>
                                            <td>
                                                <select class="form-select" wire:model="carCoverage.{{ $policyCoverage->id }}.section1_risk_earthquake">
                                                    <option value="">- Select -</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_risk_earthquake"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_earthquake_limit_indemnity" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Limit of indemnity"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_earthquake'] ?? '') === 'No') disabled @endif>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_earthquake_limit_indemnity"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_earthquake_deductible" 
                                                    placeholder="Deductible"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_earthquake'] ?? '') === 'No') disabled @endif>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_earthquake_premium" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_earthquake'] ?? '') === 'No') disabled @endif>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_earthquake_premium"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Storm, cyclone, flood, inundation, landslide</strong>
                                            </td>
                                            <td>
                                                <select class="form-select" wire:model="carCoverage.{{ $policyCoverage->id }}.section1_risk_storm">
                                                    <option value="">- Select -</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_risk_storm"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_storm_limit_indemnity" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Limit of indemnity"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_storm'] ?? '') === 'No') disabled @endif>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_storm_limit_indemnity"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_storm_deductible" 
                                                    placeholder="Deductible"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_storm'] ?? '') === 'No') disabled @endif>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section1_storm_premium" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium"
                                                    @if(($carCoverage[$policyCoverage->id]['section1_risk_storm'] ?? '') === 'No') disabled @endif>
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section1_storm_premium"/>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 1
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 2 - Third Party Liability --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Section 2 – Third party liability</h3>
                </div>
                <div class="card-body">
                    @foreach($carSection2Items[$policyCoverage->id] ?? [0] as $index => $item)
                    <div class="row mb-3" wire:key="section2-{{ $policyCoverage->id }}-{{ $index }}">
                        <div class="col-sm-3">
                            <div class="mb-3">
                                <select class="form-select" wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.item_type">
                                    <option value="">- Select Item -</option>
                                   
                                    <option value="Bodily Injury">Bodily Injury</option>
                                    <option value="Bodily Injury - any one person">Bodily Injury - any one person</option>
                                    <option value="Bodily Injury - total">Bodily Injury - total</option>
                                    <option value="Property Damage">Property Damage</option>
                                    <option value="Other">Other (Specify below)</option>
                                </select>
                                <input type="text" class="form-control mt-2" wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Additional description (optional)">
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.item_type"/>
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.description"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity" 
                                    wire:change="calculateCarSection2ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    x-mask:dynamic="$money($input)" 
                                    placeholder="Limit">
                                <label>Limit of Indemnity</label>
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible">
                                <label>Deductible</label>
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.deductible"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                    wire:change="calculateCarSection2ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    placeholder="Rate (e.g. 5)">
                                <label>Rate %</label>
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.rate"/>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                    class="form-control" 
                                    wire:model.defer="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                    wire:change="calculateCarSection2ItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                    x-mask:dynamic="$money($input)" 
                                    placeholder="Premium">
                                <label>Premium</label>
                                <x-form-input-error name="carSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                            </div>
                        </div>
                        <div class="col-sm-1">
                            <div class="mb-3">
                                @if($index == 0)
                                    <button type="button" class="btn btn-info btn-sm" wire:click="addCarSection2Row({{ $policyCoverage->id }})">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeCarSection2Row({{ $policyCoverage->id }}, {{ $index }})">
                                        <i class="fa fa-minus"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Total Limit of Indemnity and Total Premium under Section 2 --}}
                    <div class="row mt-4">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateCarSection2TotalLimit($policyCoverage->id) }}" placeholder="Total Limit of Indemnity" readonly>
                                <label><strong>Total Limit of Indemnity under Section 2:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateCarSection2TotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium under Section 2:</strong></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 2
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3 - Principal's Loss of Profits --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Section 3 – Principal's loss of profits</h3>
                </div>
                <div class="card-body">
                    {{-- Gross Profit Section --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Gross Profit</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 30%">Insured interest</th>
                                            <th style="width: 25%" class="text-center">Annual sum insured</th>
                                            <th style="width: 20%" class="text-center">Rate %</th>
                                            <th style="width: 25%" class="text-center">Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    value="Gross profit" 
                                                    readonly>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_annual_sum_insured" 
                                                    wire:change="calculateCarSection3GrossProfitPremium({{ $policyCoverage->id }})"
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Annual sum insured"
                                                    x-on:blur="formatNumberWithCommas($el)">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_annual_sum_insured"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_rate" 
                                                    wire:change="calculateCarSection3GrossProfitPremium({{ $policyCoverage->id }})"
                                                    placeholder="Rate %">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_rate"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_premium" 
                                                    wire:change="calculateCarSection3GrossProfitRateFromPremium({{ $policyCoverage->id }})"
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_gross_profit_premium"/>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Increased Cost of Working Section --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Increased Cost of Working</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 30%">Insured interest</th>
                                            <th style="width: 25%" class="text-center">Sum insured for maximum indemnity period</th>
                                            <th style="width: 20%" class="text-center">Rate %</th>
                                            <th style="width: 25%" class="text-center">Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                    class="form-control" 
                                                    value="Increased cost of working" 
                                                    readonly>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_sum_insured" 
                                                    wire:change="calculateCarSection3IncreasedCostPremium({{ $policyCoverage->id }})"
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Sum insured for maximum indemnity period"
                                                    x-on:blur="formatNumberWithCommas($el)">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_sum_insured"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_rate" 
                                                    wire:change="calculateCarSection3IncreasedCostPremium({{ $policyCoverage->id }})"
                                                    placeholder="Rate %">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_rate"/>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                    class="form-control text-center" 
                                                    wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_premium" 
                                                    wire:change="calculateCarSection3IncreasedCostRateFromPremium({{ $policyCoverage->id }})"
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium">
                                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_increased_cost_premium"/>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Period of Insurance & Indemnity --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Period of Insurance & Indemnity</h5>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_period_insurance_from" placeholder="Period of insurance from"/>
                                <label>Period of insurance from</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_period_insurance_from"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_period_insurance_to" placeholder="to"/>
                                <label>to</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_period_insurance_to"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_maximum_indemnity" x-mask:dynamic="$money($input)" placeholder="Maximum indemnity" x-on:blur="formatNumberWithCommas($el)">
                                <label>Maximum indemnity</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_maximum_indemnity"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_time_excess" placeholder="Time excess (months)">
                                <label>Time excess (months)</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_time_excess"/>
                            </div>
                        </div>
                    </div>

                    {{-- Limits of Indemnity & Scheduled Dates --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Limits of Indemnity & Scheduled Dates</h5>
                        </div>
                        <div class="col-sm-12 mb-3">
                            <div class="row">
                                <div class="col-sm-6">
                                    <label class="form-label">¹ Limit of indemnity in respect of each and every loss or damage and/or series of losses arising out of any one event.</label>
                                </div>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_limit_indemnity_each_loss" x-mask:dynamic="$money($input)" placeholder="Limit of indemnity" x-on:blur="formatNumberWithCommas($el)">
                                    <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_limit_indemnity_each_loss"/>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 mb-3">
                            <div class="row">
                                <div class="col-sm-6">
                                    <label class="form-label">² Scheduled date of completion.</label>
                                </div>
                                <div class="col-sm-6">
                                    <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_scheduled_date_completion" placeholder="Scheduled date of completion"/>
                                    <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_scheduled_date_completion"/>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 mb-3">
                            <div class="row">
                                <div class="col-sm-6">
                                    <label class="form-label">³ Scheduled date of commencement of insured business, but not earlier than the scheduled date of completion.</label>
                                </div>
                                <div class="col-sm-6">
                                    <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.section3_scheduled_date_commencement" placeholder="Scheduled date of commencement"/>
                                    <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.section3_scheduled_date_commencement"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Contract Works Insured Under Section 3 --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Contract works insured under section 3</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 10%" class="text-center">Item No.</th>
                                            <th style="width: 60%" class="text-center">Description of items</th>
                                            <th style="width: 25%" class="text-center">Possible loss minimization</th>
                                            <th style="width: 5%" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $contractWorks = $carSection3ContractWorks[$policyCoverage->id] ?? [];
                                            if (empty($contractWorks)) {
                                                $contractWorks = [0 => []];
                                            }
                                        @endphp
                                        @foreach($contractWorks as $index => $item)
                                        <tr wire:key="section3-contract-works-{{ $policyCoverage->id }}-{{ $index }}">
                                            <td>
                                                <input type="text" class="form-control text-center" value="{{ $index + 1 }}" readonly>
                                            </td>
                                            <td>
                                                <textarea class="form-control" wire:model.defer="carSection3ContractWorks.{{ $policyCoverage->id }}.{{ $index }}.description" rows="2" placeholder="Description of items"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" wire:model.defer="carSection3ContractWorks.{{ $policyCoverage->id }}.{{ $index }}.loss_minimization" x-mask:dynamic="$money($input)" placeholder="Possible loss minimization" x-on:blur="formatNumberWithCommas($el)">
                                            </td>
                                            <td>
                                                @if($index == 0)
                                                    <button type="button" class="btn btn-info btn-sm" wire:click="addCarSection3ContractWorksRow({{ $policyCoverage->id }})" title="Add Row">
                                                        <i class="fa fa-plus"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeCarSection3ContractWorksRow({{ $policyCoverage->id }}, {{ $index }})" title="Remove Row">
                                                        <i class="fa fa-minus"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 3
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Endorsements/Extension --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Endorsements/Extension</h3>
                </div>
                <div class="card-body">
                    <p><strong>The following endorsements are attached to and forming part of this Policy:</strong></p>
                    <div class="row">
                        <div class="col-sm-12">
                            @php
                                $endorsements = $carEndorsements[$policyCoverage->id] ?? [];
                                if (empty($endorsements)) {
                                    $endorsements = [0 => ['text' => '']];
                                }
                            @endphp
                            @foreach($endorsements as $index => $endorsement)
                            <div class="mb-3" wire:key="car-endorsement-{{ $policyCoverage->id }}-{{ $index }}">
                                <div class="row">
                                    <div class="col-sm-1">
                                        <label class="form-label">{{ $index + 1 }}.</label>
                                    </div>
                                    <div class="col-sm-10">
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="carEndorsements.{{ $policyCoverage->id }}.{{ $index }}.text" 
                                            placeholder="Endorsement {{ $index + 1 }}">
                                    </div>
                                    <div class="col-sm-1">
                                        <button type="button" class="btn btn-info btn-sm" wire:click="addCarEndorsementRow({{ $policyCoverage->id }})" title="Add Endorsement">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                        @if($index > 0)
                                            <button type="button" class="btn btn-danger btn-sm mt-1" wire:click="removeCarEndorsementRow({{ $policyCoverage->id }}, {{ $index }})" title="Remove Endorsement">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Endorsements
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
                        <textarea class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.additional_notes" rows="4" placeholder="Additional Notes" style="height: 120px;"></textarea>
                        <label>Additional Notes</label>
                        <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.additional_notes"/>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
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
                    <p class="mb-3">In witness whereof the undersigned being duly authorized by the Insurers and on behalf of the Insurers has (have) hereunto set his (their) hand(s)</p>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.executed_at" placeholder="Executed at">
                                <label>Executed at</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.executed_at"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_execution_date form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.execution_date" data-policy-id="{{ $policyCoverage->id }}" placeholder="Date"/>
                                <label>Date</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.execution_date"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="carCoverage.{{ $policyCoverage->id }}.signature" placeholder="Signature">
                                <label>Signature</label>
                                <x-form-input-error name="carCoverage.{{ $policyCoverage->id }}.signature"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
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
                                <input type="file" class="form-control" wire:model="carPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only (Maximum DPI: 600)</small>
                                @if($carPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $carPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="carPolicyWording"/>
                            </div>
                        </div>
                        @if(isset($carCoverage[$policyCoverage->id]['policy_wording_path']) && !empty($carCoverage[$policyCoverage->id]['policy_wording_path']))
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <strong>Current Policy Wording:</strong><br>
                                <a href="{{ config('app.S3_BASE_URL') . $carCoverage[$policyCoverage->id]['policy_wording_path'] }}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="fa fa-download"></i> View/Download Policy Wording
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveCarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Policy Wording
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculatePremium(element, sectionType, policyCoverageId, index, sumFieldName) {
    // Find the row containing this input - try both .row and tr
    const row = element.closest('.row') || element.closest('tr');
    if (!row) return;
    
    // Get all inputs in the row
    const inputs = row.querySelectorAll('input[type="text"]');
    
    // Get sum insured/limit field based on section type
    let sumInput, rateInput, premiumInput;
    
    if (sectionType === 'carSection1Items') {
        // Section 1: sum_insured, deductible, rate, premium
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
    } else if (sectionType === 'carSection2Items') {
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

function calculateSection3GrossProfitPremium(element, policyCoverageId) {
    // Find the table row containing the inputs
    const row = element.closest('tr');
    if (!row) return;
    
    // Get all inputs in the row
    const inputs = row.querySelectorAll('input[type="text"]');
    
    // Find the specific inputs
    let annualSumInput, rateInput, premiumInput;
    
    annualSumInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_gross_profit_annual_sum_insured');
    });
    
    rateInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_gross_profit_rate');
    });
    
    premiumInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_gross_profit_premium');
    });
    
    if (!annualSumInput || !premiumInput) return;
    
    // Format annual sum insured with commas if it doesn't have them
    if (annualSumInput.value && !annualSumInput.value.includes(',')) {
        formatNumberWithCommas(annualSumInput);
    }
    
    // Get values and clean them (remove commas, currency symbols, etc.)
    let annualSumValue = annualSumInput.value.toString().replace(/[^\d.-]/g, '') || '0';
    let rateValue = rateInput ? rateInput.value.toString().replace(/[^\d.-]/g, '') || '0' : '0';
    
    annualSumValue = parseFloat(annualSumValue) || 0;
    rateValue = parseFloat(rateValue) || 0;
    
    // Calculate premium: Premium = (Annual Sum Insured × Rate) / 100
    // If rate is 0, premium should be 0
    let premium = 0;
    if (annualSumValue > 0 && rateValue > 0) {
        premium = (annualSumValue * rateValue) / 100;
    } else if (annualSumValue > 0 && rateValue == 0) {
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
    } else if (premium == 0 && annualSumValue > 0) {
        // If annual sum insured has value, always set premium to "0" (not empty) when rate is 0
        formattedPremium = '0';
    }
    
    // Update the premium input value immediately for visual feedback
    premiumInput.value = formattedPremium;
    
    // Trigger change event to sync with Livewire (same as calculatePremium function)
    const changeEvent = new Event('change', { bubbles: true });
    premiumInput.dispatchEvent(changeEvent);
}

function calculateSection3IncreasedCostPremium(element, policyCoverageId) {
    // Find the table row containing the inputs
    const row = element.closest('tr');
    if (!row) return;
    
    // Get all inputs in the row
    const inputs = row.querySelectorAll('input[type="text"]');
    
    // Find the specific inputs
    let sumInsuredInput, rateInput, premiumInput;
    
    sumInsuredInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_increased_cost_sum_insured');
    });
    
    rateInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_increased_cost_rate');
    });
    
    premiumInput = Array.from(inputs).find(input => {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
        return model && model.includes('section3_increased_cost_premium');
    });
    
    if (!sumInsuredInput || !premiumInput) return;
    
    // Format sum insured with commas if it doesn't have them
    if (sumInsuredInput.value && !sumInsuredInput.value.includes(',')) {
        formatNumberWithCommas(sumInsuredInput);
    }
    
    // Get values and clean them (remove commas, currency symbols, etc.)
    let sumInsuredValue = sumInsuredInput.value.toString().replace(/[^\d.-]/g, '') || '0';
    let rateValue = rateInput ? rateInput.value.toString().replace(/[^\d.-]/g, '') || '0' : '0';
    
    sumInsuredValue = parseFloat(sumInsuredValue) || 0;
    rateValue = parseFloat(rateValue) || 0;
    
    // Calculate premium: Premium = (Sum Insured × Rate) / 100
    // If rate is 0, premium should be 0
    let premium = 0;
    if (sumInsuredValue > 0 && rateValue > 0) {
        premium = (sumInsuredValue * rateValue) / 100;
    } else if (sumInsuredValue > 0 && rateValue == 0) {
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
    } else if (premium == 0 && sumInsuredValue > 0) {
        // If sum insured has value, always set premium to "0" (not empty) when rate is 0
        formattedPremium = '0';
    }
    
    // Update the premium input value immediately for visual feedback
    premiumInput.value = formattedPremium;
    
    // Trigger change event to sync with Livewire (same as calculatePremium function)
    const changeEvent = new Event('change', { bubbles: true });
    premiumInput.dispatchEvent(changeEvent);
}

// Premium → rate is handled server-side via wire:change="calculateCarSection1ItemRateFromPremium" etc.

// Initialize execution date datepicker that depends on policy inception date
function initExecutionDatePicker() {
    if (typeof jQuery === 'undefined' || typeof $.fn.daterangepicker === 'undefined') {
        setTimeout(initExecutionDatePicker, 100);
        return;
    }
    
    $('.kt_datepicker_execution_date').each(function() {
        var $executionInput = $(this);
        var policyId = $executionInput.data('policy-id');
        
        // Get the policy inception date input for this policy
        var $inceptionInput = $('input[wire\\:model*="carCoverage.' + policyId + '.policy_inception_date"]');
        
        // Function to get max date based on inception date
        function getMaxDate() {
            var inceptionDateValue = $inceptionInput.val();
            if (!inceptionDateValue) {
                // If no inception date, allow dates up to today (original behavior)
                return moment().subtract(1, 'days');
            }
            
            // Parse the inception date (format: DD/MM/YYYY)
            var inceptionMoment = moment(inceptionDateValue, 'DD/MM/YYYY', true);
            if (!inceptionMoment.isValid()) {
                // Try parsing without strict format
                inceptionMoment = moment(inceptionDateValue);
            }
            
            if (inceptionMoment.isValid()) {
                // Add 7 days to inception date
                return inceptionMoment.clone().add(7, 'days');
            }
            
            // Fallback to yesterday if inception date is invalid
            return moment().subtract(1, 'days');
        }
        
        // Function to initialize or update the datepicker
        function initOrUpdateDatePicker() {
            var currentValue = $executionInput.val();
            var startDate = moment().subtract(1, 'days');
            
            // If there's a valid date value, use it
            if (currentValue && currentValue !== 'Invalid date' && currentValue !== 'undefinedInvalid date') {
                var parsed = moment(currentValue, 'DD/MM/YYYY', true);
                if (parsed.isValid()) {
                    startDate = parsed;
                } else {
                    parsed = moment(currentValue);
                    if (parsed.isValid()) {
                        startDate = parsed;
                    }
                }
            }
            
            // Destroy existing daterangepicker if it exists
            if ($executionInput.data('daterangepicker')) {
                $executionInput.data('daterangepicker').remove();
            }
            
            var maxDate = getMaxDate();
            
            $executionInput.daterangepicker({
                maxDate: maxDate,
                singleDatePicker: true,
                startDate: startDate,
                todayHighlight: true,
                templates: {
                    leftArrow: '<i class="la la-angle-right"></i>',
                    rightArrow: '<i class="la la-angle-left"></i>'
                },
                locale: {
                    format: 'DD/MM/YYYY'
                },
                autoUpdateInput: false,
                autoApply: true
            }).on('apply.daterangepicker', function(ev, picker) {
                var formattedDate = picker.startDate.format('DD/MM/YYYY');
                var inputElement = $executionInput[0];
                
                // Set the value first
                $executionInput.val(formattedDate);
                inputElement.value = formattedDate;
                
                // Use setTimeout to ensure value is set before dispatching events
                setTimeout(function() {
                    // Dispatch native events that Livewire can detect
                    var nativeInputEvent = new Event('input', { bubbles: true, cancelable: true });
                    var nativeChangeEvent = new Event('change', { bubbles: true, cancelable: true });
                    inputElement.dispatchEvent(nativeInputEvent);
                    inputElement.dispatchEvent(nativeChangeEvent);
                    
                    // Also trigger jQuery events for compatibility
                    $executionInput.trigger('input');
                    $executionInput.trigger('change');
                }, 10);
            });
        }
        
        // Initialize the datepicker
        initOrUpdateDatePicker();
        
        // Watch for changes to policy inception date and update execution date maxDate
        $inceptionInput.on('change input', function() {
            setTimeout(function() {
                var maxDate = getMaxDate();
                if ($executionInput.data('daterangepicker')) {
                    $executionInput.data('daterangepicker').setMaxDate(maxDate);
                } else {
                    initOrUpdateDatePicker();
                }
            }, 100);
        });
    });
}

// Initialize on page load
jQuery(document).ready(function() {
    initExecutionDatePicker();
});

// Function to recalculate Section 3 premiums on page load
function recalculateSection3Premiums() {
    // Find all Section 3 tables
    const section3Tables = document.querySelectorAll('.car-coverage-section table');
    
    section3Tables.forEach(function(table) {
        // Check if this is a Gross Profit or Increased Cost table
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(function(row) {
            const inputs = row.querySelectorAll('input[type="text"]');
            
            // Check for Gross Profit row
            const grossProfitAnnualSum = Array.from(inputs).find(input => {
                const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                return model && model.includes('section3_gross_profit_annual_sum_insured');
            });
            const grossProfitRate = Array.from(inputs).find(input => {
                const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                return model && model.includes('section3_gross_profit_rate');
            });
            
            if (grossProfitAnnualSum && grossProfitRate && grossProfitAnnualSum.value && grossProfitRate.value) {
                const modelAttr = grossProfitAnnualSum.getAttribute('wire:model.defer') || grossProfitAnnualSum.getAttribute('wire:model');
                const match = modelAttr ? modelAttr.match(/carCoverage\.(\d+)\./) : null;
                const policyCoverageId = match ? parseInt(match[1]) : null;
                if (policyCoverageId) {
                    calculateSection3GrossProfitPremium(grossProfitAnnualSum, policyCoverageId);
                }
            }
            
            // Check for Increased Cost of Working row
            const increasedCostSum = Array.from(inputs).find(input => {
                const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                return model && model.includes('section3_increased_cost_sum_insured');
            });
            const increasedCostRate = Array.from(inputs).find(input => {
                const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                return model && model.includes('section3_increased_cost_rate');
            });
            
            if (increasedCostSum && increasedCostRate && increasedCostSum.value && increasedCostRate.value) {
                const modelAttr = increasedCostSum.getAttribute('wire:model.defer') || increasedCostSum.getAttribute('wire:model');
                const match = modelAttr ? modelAttr.match(/carCoverage\.(\d+)\./) : null;
                const policyCoverageId = match ? parseInt(match[1]) : null;
                if (policyCoverageId) {
                    calculateSection3IncreasedCostPremium(increasedCostSum, policyCoverageId);
                }
            }
        });
    });
    
    // Format Section 3 other fields (maximum_indemnity, limit_indemnity_each_loss, loss_minimization)
    const section3OtherFields = document.querySelectorAll('input[wire\\:model*="section3_maximum_indemnity"], input[wire\\:model*="section3_limit_indemnity_each_loss"], input[wire\\:model*="loss_minimization"]');
    section3OtherFields.forEach(function(input) {
        if (input.value && !input.value.includes(',') && !input.readOnly && !input.disabled) {
            formatNumberWithCommas(input);
        }
    });
    
    // Format Plant List sum_insured fields
    const plantListFields = document.querySelectorAll('input[wire\\:model*="carPlantListItems"]');
    plantListFields.forEach(function(input) {
        const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model') || '';
        if (model.includes('sum_insured') && input.value && !input.value.includes(',') && !input.readOnly && !input.disabled) {
            formatNumberWithCommas(input);
        }
    });
}

// Recalculate premiums for Section 1 and Section 2 on page load
let isRecalculating = false;
function recalculateSection1And2Premiums() {
    if (isRecalculating) return;
    isRecalculating = true;
    
    // Find all Section 1 and Section 2 rows
    const section1Rows = document.querySelectorAll('.car-coverage-section .row.mb-3');
    
    section1Rows.forEach(function(row) {
        const inputs = row.querySelectorAll('input[type="text"]');
        
        // Check if this is a Section 1 row (has sum_insured)
        const sumInsuredInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('sum_insured') && model.includes('carSection1Items');
        });
        
        if (sumInsuredInput) {
            const modelAttr = sumInsuredInput.getAttribute('wire:model.defer') || sumInsuredInput.getAttribute('wire:model');
            const match = modelAttr ? modelAttr.match(/carSection1Items\.(\d+)\.(\d+)/) : null;
            if (match) {
                const rateInput = Array.from(inputs).find(input => {
                    const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                    return model && model.includes('.rate') && !model.includes('premium') && model.includes('carSection1Items');
                });
                // Only recalc premium from rate when rate has a value - don't overwrite user-entered premium after livewire:update
                const rateVal = rateInput ? (rateInput.value || '').toString().replace(/[^\d.-]/g, '') : '';
                if (!rateVal || parseFloat(rateVal) === 0) {
                    // Skip - row has premium entered by user (rate derived); don't overwrite with 0
                } else {
                    const policyCoverageId = parseInt(match[1]);
                    const index = parseInt(match[2]);
                    // Format sum insured if it has a value and doesn't already have commas
                    if (sumInsuredInput.value && !sumInsuredInput.value.includes(',')) {
                        const numericValue = parseFloat(sumInsuredInput.value.replace(/[^\d.-]/g, '')) || 0;
                        if (numericValue > 0) {
                            sumInsuredInput.value = numericValue.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                    // Trigger premium calculation only when we have rate (premium = sum * rate / 100)
                    setTimeout(() => {
                        calculatePremium(sumInsuredInput, 'carSection1Items', policyCoverageId, index, 'sum_insured');
                    }, 100);
                }
            }
        }
        
        // Check if this is a Section 2 row (has limit_of_indemnity)
        const limitInput = Array.from(inputs).find(input => {
            const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
            return model && model.includes('limit_of_indemnity') && model.includes('carSection2Items');
        });
        
        if (limitInput) {
            const modelAttr = limitInput.getAttribute('wire:model.defer') || limitInput.getAttribute('wire:model');
            const match = modelAttr ? modelAttr.match(/carSection2Items\.(\d+)\.(\d+)/) : null;
            if (match) {
                const rateInput = Array.from(inputs).find(input => {
                    const model = input.getAttribute('wire:model.defer') || input.getAttribute('wire:model');
                    return model && model.includes('.rate') && !model.includes('premium') && model.includes('carSection2Items');
                });
                // Only recalc premium from rate when rate has a value - don't overwrite user-entered premium after livewire:update
                const rateVal = rateInput ? (rateInput.value || '').toString().replace(/[^\d.-]/g, '') : '';
                if (!rateVal || parseFloat(rateVal) === 0) {
                    // Skip - row has premium entered by user (rate derived); don't overwrite with 0
                } else {
                    const policyCoverageId = parseInt(match[1]);
                    const index = parseInt(match[2]);
                    // Format limit if it has a value and doesn't already have commas
                    if (limitInput.value && !limitInput.value.includes(',')) {
                        const numericValue = parseFloat(limitInput.value.replace(/[^\d.-]/g, '')) || 0;
                        if (numericValue > 0) {
                            limitInput.value = numericValue.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                    // Trigger premium calculation only when we have rate
                    setTimeout(() => {
                        calculatePremium(limitInput, 'carSection2Items', policyCoverageId, index, 'limit_of_indemnity');
                    }, 100);
                }
            }
        }
    });
    
    setTimeout(() => {
        isRecalculating = false;
    }, 500);
}

// Function to format all numeric fields - runs multiple times to catch all data
function formatAllNumericFields() {
    formatNumericInputs();
    recalculateSection3Premiums();
    if (!isRecalculating) {
        recalculateSection1And2Premiums();
    }
}

// Run on DOM ready as well
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(formatAllNumericFields, 500);
    setTimeout(formatAllNumericFields, 1000);
    setTimeout(formatAllNumericFields, 1500);
});

// Reinitialize after Livewire updates
document.addEventListener('livewire:load', function () {
    initExecutionDatePicker();
    // Run multiple times with delays to catch all data as it loads
    formatAllNumericFields();
    setTimeout(formatAllNumericFields, 300);
    setTimeout(formatAllNumericFields, 600);
    setTimeout(formatAllNumericFields, 1000);
    setTimeout(formatAllNumericFields, 1500);
});

document.addEventListener('livewire:update', function () {
    setTimeout(function() {
        initExecutionDatePicker();
        // Only format numbers on update - do NOT run recalculateSection1And2Premiums here,
        // so we don't overwrite user-entered premium/calculated rate after they blur
        formatNumericInputs();
        recalculateSection3Premiums();
    }, 100);
    setTimeout(function() {
        formatNumericInputs();
        recalculateSection3Premiums();
    }, 500);
});

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
    // Find all numeric input fields - use broader selectors to catch all fields
    const selectors = [
        'input[wire\\:model*="sum_insured"]',
        'input[wire\\:model*="premium"]',
        'input[wire\\:model*="limit_of_indemnity"]',
        'input[wire\\:model*="section3_gross_profit_annual_sum_insured"]',
        'input[wire\\:model*="section3_increased_cost_sum_insured"]',
        'input[wire\\:model*="section3_gross_profit_premium"]',
        'input[wire\\:model*="section3_increased_cost_premium"]',
        'input[wire\\:model*="section3_maximum_indemnity"]',
        'input[wire\\:model*="section3_limit_indemnity_each_loss"]',
        'input[wire\\:model*="loss_minimization"]',
        'input[wire\\:model*="carPlantListItems"]',
        'input[wire\\:model*="earthquake_limit_indemnity"]',
        'input[wire\\:model*="earthquake_premium"]',
        'input[wire\\:model*="storm_limit_indemnity"]',
        'input[wire\\:model*="storm_premium"]',
        'input[wire\\:model*="section1_earthquake"]',
        'input[wire\\:model*="section1_storm"]'
    ];
    
    // Also find all inputs within the car-coverage-section that might have numeric values
    const carCoverageSection = document.querySelector('.car-coverage-section');
    if (carCoverageSection) {
        const allInputs = carCoverageSection.querySelectorAll('input[type="text"]');
        allInputs.forEach(function(input) {
            // Skip if readonly or disabled
            if (input.readOnly || input.disabled) {
                return;
            }
            
            // Check if this input has a wire:model attribute
            const model = input.getAttribute('wire:model') || input.getAttribute('wire:model.defer') || '';
            if (!model) return;
            
            // Check if this is a numeric field based on model name
            // NOTE: Exclude deductible fields as they can contain text (e.g., "10% of claim minimum 100 000.00")
            const isNumericField = (model.includes('sum_insured') || 
                                   model.includes('premium') || 
                                   model.includes('limit_of_indemnity') ||
                                   model.includes('maximum_indemnity') ||
                                   model.includes('limit_indemnity') ||
                                   model.includes('loss_minimization')) &&
                                   !model.includes('deductible');
            
            if (!isNumericField) return;
            
            // Get current value
            let value = input.value;
            if (!value || value.trim() === '') return;
            
            // Skip if already formatted (contains comma) or is not a number
            if (value.toString().includes(',')) {
                return;
            }
            
            // Check if it's a valid number
            const numericValue = parseFloat(value.toString().replace(/[^\d.-]/g, ''));
            if (isNaN(numericValue) || numericValue === 0) return;
            
            // Format the value
            let formattedValue = formatNumberWithCommas(value);
            
            // Only update if different to avoid cursor issues
            if (input.value !== formattedValue && formattedValue !== '') {
                input.value = formattedValue;
            }
        });
    }
    
    // Also use the specific selectors for targeted formatting
    selectors.forEach(function(selector) {
        const inputs = document.querySelectorAll(selector);
        inputs.forEach(function(input) {
            // Skip if readonly or disabled
            if (input.readOnly || input.disabled) {
                return;
            }
            
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
            
            // Only update if different to avoid cursor issues
            if (input.value !== formattedValue && formattedValue !== '') {
                input.value = formattedValue;
            }
        });
    });
}

// Format on blur for numeric fields and sync numeric value to Livewire
document.addEventListener('blur', function(e) {
    if (e.target.matches('input[type="text"]') && 
        (e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer'))) {
        
        const model = e.target.getAttribute('wire:model') || e.target.getAttribute('wire:model.defer') || '';
        
        if (model.includes('sum_insured') || 
            model.includes('premium') || 
            model.includes('limit_of_indemnity') || 
            model.includes('section3_gross_profit_annual_sum_insured') ||
            model.includes('section3_increased_cost_sum_insured') ||
            model.includes('section3_gross_profit_premium') ||
            model.includes('section3_increased_cost_premium') ||
            model.includes('section3_maximum_indemnity') ||
            model.includes('section3_limit_indemnity_each_loss') ||
            model.includes('loss_minimization') ||
            model.includes('plant_list') ||
            model.includes('maximum_indemnity') ||
            model.includes('limit_indemnity')) {
            
            if (!e.target.readOnly && !e.target.disabled) {
                // Format for display
                let formattedValue = formatNumberWithCommas(e.target.value);
                if (e.target.value !== formattedValue) {
                    e.target.value = formattedValue;
                }
                
                // Sync numeric value (without commas) to Livewire for Section 3 fields
                if (model.includes('section3_gross_profit_annual_sum_insured') || 
                    model.includes('section3_increased_cost_sum_insured')) {
                    if (window.Livewire) {
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
    }
}, true);

// Handle Policy Period change to auto-select and disable Renewable Policy field
document.addEventListener('change', function(e) {
    if (e.target && e.target.matches('select[wire\\:model*="policy_period_months"]')) {
        const policyPeriod = e.target.value;
        const model = e.target.getAttribute('wire:model');
        
        // Extract the policy coverage ID from the wire:model attribute
        const match = model.match(/carCoverage\.(\d+)\.policy_period_months/);
        if (match) {
            const policyCoverageId = match[1];
            
            // Find the renewable policy select field for this policy coverage
            const renewableSelect = document.querySelector(`select[wire\\:model\\.defer="carCoverage.${policyCoverageId}.is_renewable"]`);
            
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

// Update expiry date picker maxDate when inception date changes
document.addEventListener('DOMContentLoaded', function() {
    function updateExpiryDateRestriction() {
        if (typeof jQuery === 'undefined' || typeof $.fn.daterangepicker === 'undefined') {
            setTimeout(updateExpiryDateRestriction, 100);
            return;
        }

        // Handle CAR coverage: when inception date changes, update expiry date maxDate
        $('input[wire\\:model*="carCoverage"][wire\\:model*="policy_inception_date"]').each(function() {
            var $inceptionInput = $(this);
            var model = $inceptionInput.attr('wire:model');
            var match = model.match(/carCoverage\.(\d+)\.policy_inception_date/);
            
            if (match) {
                var policyCoverageId = match[1];
                var $expiryInput = $('input[wire\\:model="carCoverage.' + policyCoverageId + '.policy_expiry_date"]');
                
                if ($expiryInput.length > 0) {
                    // Function to update maxDate
                    function updateMaxDate() {
                        var inceptionValue = $inceptionInput.val();
                        if (inceptionValue) {
                            var inceptionMoment = moment(inceptionValue, 'DD/MM/YYYY', true);
                            if (!inceptionMoment.isValid()) {
                                inceptionMoment = moment(inceptionValue);
                            }
                            if (inceptionMoment.isValid()) {
                                var maxExpiryDate = moment(inceptionMoment).add(3, 'years');
                                if ($expiryInput.data('daterangepicker')) {
                                    $expiryInput.data('daterangepicker').setMaxDate(maxExpiryDate);
                                }
                            }
                        }
                    }
                    
                    // Remove existing handlers to avoid duplicates
                    $inceptionInput.off('apply.daterangepicker.expiryRestriction');
                    $inceptionInput.off('change.expiryRestriction');
                    
                    // When inception date changes via datepicker
                    $inceptionInput.on('apply.daterangepicker.expiryRestriction', function(ev, picker) {
                        var inceptionDate = picker.startDate;
                        var maxExpiryDate = moment(inceptionDate).add(3, 'years');
                        
                        if ($expiryInput.data('daterangepicker')) {
                            $expiryInput.data('daterangepicker').setMaxDate(maxExpiryDate);
                        } else {
                            setTimeout(function() {
                                if ($expiryInput.data('daterangepicker')) {
                                    $expiryInput.data('daterangepicker').setMaxDate(maxExpiryDate);
                                }
                            }, 300);
                        }
                    });
                    
                    // Also check on change event (manual typing or Livewire update)
                    $inceptionInput.on('change.expiryRestriction', function() {
                        setTimeout(updateMaxDate, 100);
                    });
                    
                    // Set restriction on page load if inception date exists
                    setTimeout(updateMaxDate, 600);
                }
            }
        });
    }
    
    updateExpiryDateRestriction();
    
    // Reinitialize after Livewire updates
    document.addEventListener('livewire:update', function() {
        setTimeout(updateExpiryDateRestriction, 300);
    });
});
</script>
