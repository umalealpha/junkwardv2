{{-- ERECTIONALLRISKSs (EAR) Coverage --}}
<div class="ear-coverage-section">
    {{-- Policy Schedule --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Erection All Risks - Policy Schedule</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <p class="mb-3"><strong>The following endorsements are attached to and forming part of this Policy:</strong></p>
                        </div>
                    </div>

                    {{-- Name of Insured --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="text-primary">Name of Insured</h5>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.name_of_insured" placeholder="Name of Insured">
                                <label>Name of Insured</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.name_of_insured"/>
                            </div>
                        </div>
                    </div>

                    {{-- Site of Erection --}}
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <h5 class="text-primary">Site of Erection</h5>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.site_of_erection" placeholder="Site of Erection">
                                <label>Site of Erection</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.site_of_erection"/>
                            </div>
                        </div>
                    </div>

                    {{-- Project Name and Policy Options --}}
                    <div class="row mt-3">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.project_name" placeholder="Project Name">
                                <label>Project Name</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.project_name"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model="earCoverage.{{ $policyCoverage->id }}.policy_period_months">
                                    <option value="">- Select Policy Period -</option>
                                    <option value="12">Allow up to 12 months max</option>
                                    <option value="24">Allow up to 24 months max</option>
                                    <option value="36">Allow up to 36 months max</option>
                                </select>
                                <label>Policy Period</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.policy_period_months"/>
                            </div>
                        </div>
                        @if(in_array($earCoverage[$policyCoverage->id]['policy_period_months'] ?? '', ['24', '36']))
                        <div class="col-sm-3">
                            <div class="mb-3">
                                @if(empty($earCoverage[$policyCoverage->id]['approved_by'] ?? null))
                                    <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'EAR')">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                @else
                                    @php
                                        $approver = \AlphaDirect\Models\User::find($earCoverage[$policyCoverage->id]['approved_by'] ?? null);
                                        $approvedAt = isset($earCoverage[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($earCoverage[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
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
                                    $policyPeriod = $earCoverage[$policyCoverage->id]['policy_period_months'] ?? '';
                                    $isDisabled = !empty($policyPeriod) && $policyPeriod != '12';
                                @endphp
                                <select class="form-select" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.is_renewable" @if($isDisabled) disabled @endif>
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Renewable Policy</option>
                                    <option value="No" @if($isDisabled) selected @endif>No</option>
                                </select>
                                <label>Renewable Policy</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.is_renewable"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.is_project_specific">
                                    <option value="">- Select -</option>
                                    <option value="Yes">Yes - Project Specific Policy</option>
                                    <option value="No">No</option>
                                </select>
                                <label>Project Specific Policy</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.is_project_specific"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.maintenance_period_months" placeholder="Maintenance Period" value="12">
                                <label>Maintenance Period (Months after expiry - Default: 12)</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.maintenance_period_months"/>
                            </div>
                        </div>
                      
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
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
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%">Insured Items</th>
                                    <th style="width: 18%">Sum Insured</th>
                                    <th style="width: 15%">Deductible</th>
                                    <th style="width: 12%">Rate %</th>
                                    <th style="width: 18%">Premium</th>
                                    <th style="width: 12%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section1Items = $earSection1Items[$policyCoverage->id] ?? [];
                                    if (empty($section1Items)) {
                                        $section1Items = [0 => []];
                                    }
                                @endphp
                                @foreach($section1Items as $index => $item)
                                <tr wire:key="ear-section1-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <select class="form-select" wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.item_type">
                                            <option value="">- Select Item -</option>
                                            <option value="Erection Work">Erection Work</option>
                                            <option value="Items to be erected">Items to be erected (which require install and/or testing)</option>
                                            <option value="Freight">Freight</option>
                                            <option value="Customs Duties and Dues">Customs Duties and Dues</option>
                                            <option value="Cost of Erection">Cost of Erection</option>
                                            <option value="Civil Engineering Work">Civil Engineering Work</option>
                                            <option value="Clearance of Debris">Clearance of Debris</option>
                                            <option value="Other">Other (Specify in notes)</option>
                                        </select>
                                        <textarea class="form-control mt-2" wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.description" rows="2" placeholder="Additional description"></textarea>
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" 
                                            wire:change="calculateEarSection1ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Sum Insured">
                                        <x-form-input-error name="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.sum_insured"/>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                            wire:change="calculateEarSection1ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            placeholder="Rate %">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                            wire:change="calculateEarSection1ItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Premium">
                                        <x-form-input-error name="earSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                                    </td>
                                    <td>
                                        @if($index == 0)
                                            <button type="button" class="btn btn-info btn-sm" wire:click="addEarSection1Row({{ $policyCoverage->id }})" title="Add Item">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm mt-1" wire:click="addEarMiscellaneousItem({{ $policyCoverage->id }})" title="Add Miscellaneous Item">
                                                <i class="fa fa-plus-circle"></i> Misc
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeEarSection1Row({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Total Sum Insured and Total Premium under Section 1 --}}
                    <div class="row mt-3">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateEarSection1TotalSumInsured($policyCoverage->id) }}" placeholder="Total Sum Insured" readonly>
                                <label><strong>Total Sum Insured under Section 1:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateEarSection1TotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium under Section 1:</strong></label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 1
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 2 - Risk Coverage --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Risk Coverage</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Risk</h5>
                        </div>
                    </div>

                    {{-- Earthquake, volcanism, tsunami --}}
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3">Earthquake, volcanism, tsunami - Limit of indemnity</h6>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" wire:model="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_covered">
                                                    <option value="">- Select -</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                                <label>Covered?</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_limit_indemnity"
                                                    wire:change="$refresh" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Limit of indemnity"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_earthquake_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Limit of indemnity</label>
                                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_limit_indemnity"/>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_deductible"
                                                    placeholder="Deductible"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_earthquake_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Deductible</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_premium"
                                                    wire:change="calculateEarTotalPremium({{ $policyCoverage->id }})" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_earthquake_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Premium</label>
                                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.risk_earthquake_premium"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Storm, cyclone, flood, inundation, landslide --}}
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3">Storm, cyclone, flood, inundation, landslide</h6>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" wire:model="earCoverage.{{ $policyCoverage->id }}.risk_storm_covered">
                                                    <option value="">- Select -</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                                <label>Covered?</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_storm_limit_indemnity"
                                                    wire:change="$refresh" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Limit of indemnity"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_storm_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Limit of indemnity</label>
                                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.risk_storm_limit_indemnity"/>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_storm_deductible"
                                                    placeholder="Deductible"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_storm_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Deductible</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-floating mb-3">
                                                <input type="text"
                                                    class="form-control"
                                                    wire:model.defer="earCoverage.{{ $policyCoverage->id }}.risk_storm_premium"
                                                    wire:change="calculateEarTotalPremium({{ $policyCoverage->id }})" 
                                                    x-mask:dynamic="$money($input)" 
                                                    placeholder="Premium"
                                                    @if(($earCoverage[$policyCoverage->id]['risk_storm_covered'] ?? '') === 'No') disabled @endif>
                                                <label>Premium</label>
                                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.risk_storm_premium"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 2
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3 - Third Party Liability --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Section 3 – Third Party Liability</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%">Insured Items</th>
                                    <th style="width: 18%">Limits of Indemnity ¹</th>
                                    <th style="width: 15%">Deductibles</th>
                                    <th style="width: 12%">Rate %</th>
                                    <th style="width: 18%">Premium</th>
                                    <th style="width: 12%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section3Items = $earSection3Items[$policyCoverage->id] ?? [];
                                    if (empty($section3Items)) {
                                        $section3Items = [0 => ['item_type' => 'Bodily Injury']];
                                    }
                                @endphp
                                @foreach($section3Items as $index => $item)
                                <tr wire:key="ear-section3-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <select class="form-select mb-2" wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.item_type">
                                            <option value="">- Select Item -</option>
                                            <option value="Bodily Injury">Bodily Injury</option>
                                            <option value="Each and every person">- Each and every person</option>
                                            <option value="total">- total</option>
                                            <option value="Property Damage">Property Damage</option>
                                            <option value="Other">Other (Specify below)</option>
                                        </select>
                                        <input type="text" class="form-control" wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Additional description (optional)">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity" 
                                            wire:change="calculateEarSection3ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Limit">
                                        <x-form-input-error name="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity"/>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.rate" 
                                            wire:change="calculateEarSection3ItemPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            placeholder="Rate %">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.premium" 
                                            wire:change="calculateEarSection3ItemRateFromPremium({{ $policyCoverage->id }}, {{ $index }})"
                                            x-mask:dynamic="$money($input)" 
                                            placeholder="Premium">
                                        <x-form-input-error name="earSection3Items.{{ $policyCoverage->id }}.{{ $index }}.premium"/>
                                    </td>
                                    <td>
                                        @if($index == 0)
                                            <button type="button" class="btn btn-info btn-sm" wire:click="addEarSection3Row({{ $policyCoverage->id }})" title="Add Item">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm mt-1" wire:click="addEarMiscellaneousItem({{ $policyCoverage->id }})" title="Add Miscellaneous Item">
                                                <i class="fa fa-plus-circle"></i> Misc
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeEarSection3Row({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Total Limit of Indemnity and Total Premium under Section 3 --}}
                    <div class="row mt-4">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateEarSection3TotalLimit($policyCoverage->id) }}" placeholder="Total Limit of Indemnity" readonly>
                                <label><strong>Total Limit of Indemnity under Section 3:</strong></label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateEarSection3TotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium under Section 3:</strong></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Section 3
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Period of Insurance --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Period of Insurance</h3>
                </div>
                <div class="card-body">
                    <p class="mb-3"><strong>Subject to the provisions concerning the Period of Cover</strong></p>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.period_from" placeholder="From"/>
                                <label>From</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.period_from"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.period_to" placeholder="To"/>
                                <label>to __(0) week of testing</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.period_to"/>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.weeks_of_testing" placeholder="Weeks of testing">
                                <label>Number of weeks of testing</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.weeks_of_testing"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Period of Insurance
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
                                $endorsements = $earEndorsements[$policyCoverage->id] ?? [];
                                if (empty($endorsements)) {
                                    $endorsements = [0 => ['text' => '']];
                                }
                            @endphp
                            @foreach($endorsements as $index => $endorsement)
                            <div class="mb-3" wire:key="ear-endorsement-{{ $policyCoverage->id }}-{{ $index }}">
                                <div class="row">
                                    <div class="col-sm-1">
                                        <label class="form-label">{{ $index + 1 }}.</label>
                                    </div>
                                    <div class="col-sm-10">
                                        <input type="text" 
                                            class="form-control" 
                                            wire:model.defer="earEndorsements.{{ $policyCoverage->id }}.{{ $index }}.text" 
                                            placeholder="Endorsement {{ $index + 1 }}">
                                    </div>
                                    <div class="col-sm-1">
                                        <button type="button" class="btn btn-info btn-sm" wire:click="addEarEndorsementRow({{ $policyCoverage->id }})" title="Add Endorsement">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                        @if($index > 0)
                                            <button type="button" class="btn btn-danger btn-sm mt-1" wire:click="removeEarEndorsementRow({{ $policyCoverage->id }}, {{ $index }})" title="Remove Endorsement">
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
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Endorsements
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Premium --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Total Premium</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" value="{{ $this->calculateEarTotalPremium($policyCoverage->id) }}" placeholder="Total Premium" readonly>
                                <label><strong>Total Premium (inclusive of extra premiums for the abovementioned endorsements)</strong></label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.total_premium"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Total Premium
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
                        <textarea class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.additional_notes" rows="4" placeholder="Additional Notes" style="height: 120px;"></textarea>
                        <label>Additional Notes</label>
                        <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.additional_notes"/>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
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
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.executed_at" placeholder="Executed at">
                                <label>Executed at</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.executed_at"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_2_unrestricted form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.execution_date" placeholder="Date"/>
                                <label>Date</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.execution_date"/>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" wire:model.defer="earCoverage.{{ $policyCoverage->id }}.signature" placeholder="Signature">
                                <label>Signature</label>
                                <x-form-input-error name="earCoverage.{{ $policyCoverage->id }}.signature"/>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
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
                                <input type="file" class="form-control" wire:model="earPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only (Maximum DPI: 600)</small>
                                @if($earPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $earPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="earPolicyWording"/>
                            </div>
                        </div>
                        @if(isset($earCoverage[$policyCoverage->id]['policy_wording_path']) && !empty($earCoverage[$policyCoverage->id]['policy_wording_path']))
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <strong>Current Policy Wording:</strong><br>
                                <a href="{{ config('app.S3_BASE_URL') . $earCoverage[$policyCoverage->id]['policy_wording_path'] }}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="fa fa-download"></i> View/Download Policy Wording
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveEarCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Policy Wording
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function calculateEarPremium(element, sectionType, policyCoverageId, index, sumFieldName) {
    // Find the row containing this input
    const row = element.closest('tr');
    if (!row) return;
    
    // Get all inputs in the row
    const inputs = row.querySelectorAll('input[type="text"]');
    
    // Get sum insured/limit field based on section type
    let sumInput, rateInput, premiumInput;
    
    if (sectionType === 'earSection1Items') {
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
    } else if (sectionType === 'earSection3Items') {
        // Section 3: limit_of_indemnity, deductible, rate, premium
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

// Premium → rate is handled server-side via wire:change="calculateEarSection1ItemRateFromPremium" etc.

// Handle Policy Period change to auto-select and disable Renewable Policy field
document.addEventListener('change', function(e) {
    if (e.target && e.target.matches('select[wire\\:model*="policy_period_months"]')) {
        const policyPeriod = e.target.value;
        const model = e.target.getAttribute('wire:model');
        
        // Extract the policy coverage ID from the wire:model attribute
        const match = model.match(/earCoverage\.(\d+)\.policy_period_months/);
        if (match) {
            const policyCoverageId = match[1];
            
            // Find the renewable policy select field for this policy coverage
            const renewableSelect = document.querySelector(`select[wire\\:model\\.defer="earCoverage.${policyCoverageId}.is_renewable"]`);
            
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

// Update period_to date picker maxDate when period_from changes
document.addEventListener('DOMContentLoaded', function() {
    function updatePeriodToRestriction() {
        if (typeof jQuery === 'undefined' || typeof $.fn.daterangepicker === 'undefined') {
            setTimeout(updatePeriodToRestriction, 100);
            return;
        }

        // Handle EAR coverage: when period_from changes, update period_to maxDate
        $('input[wire\\:model\\.defer*="earCoverage"][wire\\:model\\.defer*="period_from"]').each(function() {
            var $fromInput = $(this);
            var model = $fromInput.attr('wire:model.defer');
            var match = model.match(/earCoverage\.(\d+)\.period_from/);
            
            if (match) {
                var policyCoverageId = match[1];
                var $toInput = $('input[wire\\:model\\.defer="earCoverage.' + policyCoverageId + '.period_to"]');
                
                if ($toInput.length > 0) {
                    // Function to update maxDate
                    function updateMaxDate() {
                        var fromValue = $fromInput.val();
                        if (fromValue) {
                            var fromMoment = moment(fromValue, 'DD/MM/YYYY', true);
                            if (!fromMoment.isValid()) {
                                fromMoment = moment(fromValue);
                            }
                            if (fromMoment.isValid()) {
                                var maxToDate = moment(fromMoment).add(3, 'years');
                                if ($toInput.data('daterangepicker')) {
                                    $toInput.data('daterangepicker').setMaxDate(maxToDate);
                                }
                            }
                        }
                    }
                    
                    // Remove existing handlers to avoid duplicates
                    $fromInput.off('apply.daterangepicker.periodRestriction');
                    $fromInput.off('change.periodRestriction');
                    
                    // When period_from changes via datepicker
                    $fromInput.on('apply.daterangepicker.periodRestriction', function(ev, picker) {
                        var fromDate = picker.startDate;
                        var maxToDate = moment(fromDate).add(3, 'years');
                        
                        if ($toInput.data('daterangepicker')) {
                            $toInput.data('daterangepicker').setMaxDate(maxToDate);
                        } else {
                            setTimeout(function() {
                                if ($toInput.data('daterangepicker')) {
                                    $toInput.data('daterangepicker').setMaxDate(maxToDate);
                                }
                            }, 300);
                        }
                    });
                    
                    // Also check on change event (manual typing or Livewire update)
                    $fromInput.on('change.periodRestriction', function() {
                        setTimeout(updateMaxDate, 100);
                    });
                    
                    // Set restriction on page load if period_from exists
                    setTimeout(updateMaxDate, 600);
                }
            }
        });
    }
    
    updatePeriodToRestriction();
    
    // Reinitialize after Livewire updates
    document.addEventListener('livewire:update', function() {
        setTimeout(updatePeriodToRestriction, 300);
    });
});
</script>
