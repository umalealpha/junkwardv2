{{-- Professional Indemnity Coverage --}}
    <div class="professional-indemnity-section">
        <style>
            .pi-extension-titlecase {
                text-transform: capitalize;
            }
        </style>
    {{-- Professional Indemnity Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Professional Indemnity</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Basic Information --}}
                        <div class="col-sm-12">
                            
                            <div class="form-floating mb-3">
                                <x-form-input type="text" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.insured" placeholder="Insured" value="{{ $professionalIndemnity[$policyCoverage->id]['insured'] ?? '' }}" />
                                <label>Insured</label>
                                <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.insured"/>
                            </div>

                            <div class="form-floating mb-3">
                                <x-form-input type="text" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.profession_business" placeholder="Profession/Business" />
                                <label>Profession/Business</label>
                                <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.profession_business"/>
                            </div>

                            <div class="form-floating mb-3">
                                <select class="form-select" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.basis_of_cover">
                                    <option value="">- Select -</option>
                                    <option value="Claims-Made">Claims Made</option>
                                    <option value="Claims-Occurring<">Claims Occurring</option>
                                </select>
                                <label>Basis of cover</label>
                                <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.basis_of_cover"/>
                            </div>

                            <div class="row mt-3">
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="professionalIndemnity.{{ $policyCoverage->id }}.policy_inception_date" placeholder="Policy inception date"/>
                                        <label>Policy inception date</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.policy_inception_date"/>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="professionalIndemnity.{{ $policyCoverage->id }}.policy_expiry_date" placeholder="Policy expiry date"/>
                                        <label>Policy expiry date</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.policy_expiry_date"/>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="professionalIndemnity.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                        <label>Today's date</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.today_date"/>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.new_altered">
                                            <option value="">- Select -</option>
                                            <option value="New">New</option>
                                            <option value="Altered">Altered</option>
                                        </select>
                                        <label>New/altered</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.new_altered"/>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" wire:model="professionalIndemnity.{{ $policyCoverage->id }}.period_of_insurance">
                                            <option value="">- Select Policy Period -</option>
                                            <option value="12">Allow up to 12 months max</option>
                                            <option value="24">Allow up to 24 months max</option>
                                            <option value="36">Allow up to 36 months max</option>
                                        </select>
                                        <label>Policy Period</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.period_of_insurance"/>
                                    </div>
                                </div>
                                @if(in_array($professionalIndemnity[$policyCoverage->id]['period_of_insurance'] ?? '', ['24', '36']))
                                <div class="col-sm-3">
                                    <div class="mb-3">
                                        @if(empty($professionalIndemnity[$policyCoverage->id]['approved_by'] ?? null))
                                            <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'PI')">
                                                <i class="fa fa-check"></i> Approve
                                            </button>
                                        @else
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($professionalIndemnity[$policyCoverage->id]['approved_by'] ?? null);
                                                $approvedAt = isset($professionalIndemnity[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($professionalIndemnity[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
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
                                            $policyPeriod = $professionalIndemnity[$policyCoverage->id]['period_of_insurance'] ?? '';
                                            // $isDisabled = !empty($policyPeriod) && $policyPeriod != '12';
                                            $isDisabled = false;
                                            
                                        @endphp
                                        <select class="form-select" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.is_renewable" @if($isDisabled) disabled @endif>
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Renewable Policy</option>
                                            <option value="No" @if($isDisabled) selected @endif>No</option>
                                        </select>
                                        <label>Renewable Policy</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.is_renewable"/>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.is_project_specific">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Project Specific Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                        <label>Project Specific Policy</label>
                                        <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.is_project_specific"/>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.retroactive_date" id="retroactive_date_{{ $policyCoverage->id }}" placeholder="Retroactive date" />
                                <label>Retroactive date</label>
                                <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.retroactive_date"/>
                            </div>
                            <div class="form-floating mb-3">
                                <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnity.{{ $policyCoverage->id }}.premium" placeholder="Premium" value="{{ $professionalIndemnity[$policyCoverage->id]['premium'] ?? '' }}" />
                                <label>Premium</label>
                                <x-form-input-error name="professionalIndemnity.{{ $policyCoverage->id }}.premium"/>
                            </div>
                        </div>
                    </div>

                    {{-- Insured Persons Table --}}
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Insured Persons</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 20%">Description</th>
                                            <th style="width: 23%">Insured Persons</th>
                                            <th style="width: 14%">Length of service</th>
                                            <th style="width: 20%">Limit of liability</th>
                                            <th style="width: 19%">Designation</th>
                                            <th style="width: 4%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $insuredPersons = $professionalIndemnityInsuredPersons[$policyCoverage->id] ?? [];
                                            if (empty($insuredPersons) && !isset($professionalIndemnityInsuredPersons[$policyCoverage->id])) {
                                                $insuredPersons = [
                                                    0 => ['description' => '', 'insured_person' => '', 'length_of_service' => '', 'limit_of_liability' => '', 'designation' => '']
                                                ];
                                            }
                                        @endphp
                                        @foreach($insuredPersons as $index => $person)
                                        <tr wire:key="pi-insured-person-{{ $policyCoverage->id }}-{{ $index }}">
                                            <td>
                                                <select class="form-select" wire:model.defer="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.description" id="description_{{ $policyCoverage->id }}_{{ $index }}">
                                                    <option value="">Select Description</option>
                                                    <option value="Blanket">Blanket</option>
                                                    <option value="Named Persons">Named Persons</option>
                                                </select>
                                                <x-form-input-error name="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.description"/>
                                            </td>
                                            <td>
                                                <x-form-input type="text" wire:model.defer="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.insured_person" placeholder="Insured Person" />
                                            </td>
                                            <td>
                                                <x-form-input type="text" wire:model.defer="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.length_of_service" placeholder="Length of service" />
                                            </td>
                                            <td>
                                                <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.limit_of_liability" placeholder="Limit of liability" />
                                            </td>
                                            <td>
                                                <x-form-input type="text" wire:model.defer="professionalIndemnityInsuredPersons.{{ $policyCoverage->id }}.{{ $index }}.designation" placeholder="Designation" />
                                            </td>
                                            @if($index > 0)
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm" wire:click="removeProfessionalIndemnityInsuredPerson({{ $policyCoverage->id }}, {{ $index }})">
                                                    <i class="fa fa-minus"></i>
                                                </button>
                                            </td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if(empty($insuredPersons))
                                <div class="alert alert-info">
                                    <p>No insured persons added yet. Click the button below to add an insured person.</p>
                                </div>
                            @endif
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-info btn-sm" wire:click="addProfessionalIndemnityInsuredPerson({{ $policyCoverage->id }})">
                                    <i class="fa fa-plus"></i> Add Insured Person
                                </button>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>
        </div>
    </div>

    {{-- Extensions Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <!-- <div class="card-header">
                    <h3 class="card-title">Extensions</h3>
                </div> -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%">Extension</th>
                                    <th style="width: 23%">Limit of liability</th>
                                    <th style="width: 23%">Premium</th>
                                    <th style="width: 9%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $extensions = $professionalIndemnityExtensions[$policyCoverage->id] ?? [];
                                    if (empty($extensions)) {
                                        $extensions = [
                                            ['extension' => 'Sub Contracted Duties', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Liability Following Employee Dishonesty', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Mitigation of Loss', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Computer Crime', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Defamation', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Criminal and Statutory Defence Costs', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Loss Of Documents', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Fee Recovery', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Business Identity Theft', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Claims Preparation Costs', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Commercial Crime', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'Directors & Officers Liability', 'limit_of_liability' => '', 'premium' => ''],
                                            ['extension' => 'General Public Liability', 'limit_of_liability' => '', 'premium' => '']
                                        ];
                                    }
                                @endphp
                                @foreach($extensions as $index => $extension)
                                <tr wire:key="pi-extension-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input
                                            type="text"
                                            class="form-control {{ $policyCoverage->id == 19277 && $index == 12 ? 'pi-extension-titlecase' : '' }}"
                                            wire:model.defer="professionalIndemnityExtensions.{{ $policyCoverage->id }}.{{ $index }}.extension"
                                            placeholder="Extension"
                                            {{ $policyCoverage->id == 19277 && $index == 12 ? 'readonly' : '' }}
                                        />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExtensions.{{ $policyCoverage->id }}.{{ $index }}.limit_of_liability" placeholder="Limit of liability" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExtensions.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeProfessionalIndemnityBaseExtension({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                
                                {{-- Additional Extensions (Dynamic) --}}
                                @if(isset($professionalIndemnityAdditionalExtensions[$policyCoverage->id]) && !empty($professionalIndemnityAdditionalExtensions[$policyCoverage->id]))
                                    @foreach($professionalIndemnityAdditionalExtensions[$policyCoverage->id] as $addIndex => $addExtension)
                                    <tr wire:key="pi-additional-extension-{{ $policyCoverage->id }}-{{ $addIndex }}">
                                        <td>
                                            <input type="text" class="form-control" wire:model.defer="professionalIndemnityAdditionalExtensions.{{ $policyCoverage->id }}.{{ $addIndex }}.extension" placeholder="Extension" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityAdditionalExtensions.{{ $policyCoverage->id }}.{{ $addIndex }}.limit_of_liability" placeholder="Limit of liability" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityAdditionalExtensions.{{ $policyCoverage->id }}.{{ $addIndex }}.premium" placeholder="Premium" />
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeProfessionalIndemnityExtension({{ $policyCoverage->id }}, {{ $addIndex }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addProfessionalIndemnityExtension({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Extension
                        </button>
                        <span class="text-muted ms-2">* Allow more extensions to be added</span>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    {{-- Excesses Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <!-- <div class="card-header">
                    <h3 class="card-title">Excesses</h3>
                </div> -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th ><strong>Excess Type</strong></th>
                                    <th colspan="2"><strong>%</strong></th>
                                    <th><strong>Minimum Excess</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $excesses = $professionalIndemnityExcesses[$policyCoverage->id] ?? [];
                                    if (empty($excesses)) {
                                        $excesses = [
                                            'basic' => ['percent' => '', 'minimum_excess' => ''],
                                            'others' => [
                                                ['description' => '', 'percent' => '', 'minimum_excess' => '']
                                            ]
                                        ];
                                    }

                                    $otherExcesses = $excesses['others'] ?? [];
                                    if (empty($otherExcesses)) {
                                        $otherExcesses = [
                                            ['description' => '', 'percent' => '', 'minimum_excess' => '']
                                        ];
                                    } elseif (isset($otherExcesses['percent']) || isset($otherExcesses['minimum_excess']) || isset($otherExcesses['description'])) {
                                        $otherExcesses = [$otherExcesses];
                                    }
                                    $otherExcessesCount = count($otherExcesses);
                                @endphp
                                <tr>
                                    <td >
                                        <strong>Basic</strong>
                                    </td>
                                    
                                    <td colspan="2">
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExcesses.{{ $policyCoverage->id }}.basic.percent" placeholder="%" />
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExcesses.{{ $policyCoverage->id }}.basic.minimum_excess" placeholder="Minimum Excess" />
                                    </td>
                                </tr>
                                @foreach($otherExcesses as $otherIndex => $otherExcess)
                                <tr>
                                    
                                    <td><strong>Others</strong></td>
                                    <td>
                                        <x-form-input type="text"  wire:model.defer="professionalIndemnityExcesses.{{ $policyCoverage->id }}.others.{{ $otherIndex }}.description" placeholder="Description" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExcesses.{{ $policyCoverage->id }}.others.{{ $otherIndex }}.percent" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="amount-field" wire:model.defer="professionalIndemnityExcesses.{{ $policyCoverage->id }}.others.{{ $otherIndex }}.minimum_excess" placeholder="Minimum Excess" />
                                    </td>
                                    <td>
                                        
                                            @if($otherIndex === 0)                                                
                                                <button type="button" class="btn btn-info btn-sm" wire:click="addProfessionalIndemnityOtherExcess({{ $policyCoverage->id }})">
                                                    <i class="fa fa-plus"></i>
                                                </button>
                                            @endif
                                            @if($otherExcessesCount > 1 && $otherIndex > 0)
                                                <button type="button" class="btn btn-danger btn-sm" wire:click="removeProfessionalIndemnityOtherExcess({{ $policyCoverage->id }}, {{ $otherIndex }})">
                                                    <i class="fa fa-minus"></i>
                                                </button>
                                            @endif
                                        
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="saveProfessionalIndemnity({{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save
                        </button>
                    </div> -->
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
                                <input type="file" class="form-control" wire:model="piPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($piPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $piPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="piPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath =
                                $professionalIndemnity[$policyCoverage->id]['policy_wording_path']
                                ?? null;

                            $policyWordingFilename =
                                $professionalIndemnity[$policyCoverage->id]['policy_wording_filename']
                                ?? null;
                        @endphp

                        @if(!empty($policyWordingPath))
                            <div class="col-sm-12">
                                <div class="alert alert-info">
                                    <strong>Current Policy Wording:</strong><br>

                                    @if(!empty($policyWordingFilename))
                                        <div class="mt-1">
                                            <small>{{ $policyWordingFilename }}</small>
                                        </div>
                                    @endif

                                    <a href="{{ Storage::url($policyWordingPath) }}"
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
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Note</h3>
                </div>
                <div class="card-body">
                    <x-form-text-area wire:model.defer="policyCoverageNote.{{ $policyCoverage->id }}" class="h-100"  style="height: 15em !important;"/>
                <!-- <label for="policyCoverageNote.{{ $policyCoverage->id }}">Enter Your Note</label>
                <x-form-input-error name="policyCoverageNote.{{ $policyCoverage->id }}"/> -->
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Miscellaneous Items</h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" wire:click.prevent="addSpecifiedRow({{ $policyCoverage->id }})">
                            Add <Row></Row>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @foreach($this->specifiedRow[$policyCoverage->id] ?? [] as $key => $value)
                                                                <div class="row">
                                                                    <div class="col-sm-5">
                                                                        <div class="form-floating mb-3">
                                                                            <x-select wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"
                                                                                      :options="$this->specifiedItems[$policyCoverage->coverage_id]" />
                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item" required value="{{ __('Select Description Of Item') }}"/>
                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.selected_item"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-5">
                                                                        <div class="form-floating mb-3">
                                                                            <x-form-input wire:model.defer="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"
                                                                                          placeholder="Sum Insured" class="amount-field" class="amount-field"/>
                                                                            <x-form-label for="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured" required value="{{ __('Sum Insured') }}"/>
                                                                            <x-form-input-error name="specified_items.{{ $policyCoverage->id }}.{{ $key }}.sum_insured"/>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                       @if( isset($policyCoverage->specifedItems[$key])
                                                                                            && $policyCoverage->specifedItems[$key]->deleted_at === null)
                                                                                            <button type="button" class="btn btn-danger btn-small"
                                                                                                wire:click.prevent="removeRow(
                                                                                                    {{ $policyCoverage->id }},
                                                                                                    {{ $key }}
                                                                                                    @isset($policyCoverage->specifedItems[$key]->id),{{ $policyCoverage->specifedItems[$key]->id }}@endisset
                                                                                                )">
                                                                                                &times;
                                                                                            </button>
                                                                                              @elseif(!isset($policyCoverage->specifedItems[$key]))
                                                                                               <button type="button" class="btn btn-danger btn-small" wire:click.prevent="removeRow({{ $policyCoverage->id }},{{ $key }})">&times;</button>
                                                                                   
                                                                                        @else
                                                                                            <a wire:click.prevent="reinstateMisc('{{ $policyCoverage->specifedItems[$key]->id ?? '' }}')"
                                                                                            class="btn btn-sm btn-primary"
                                                                                            style="position: absolute; right: 75px;">
                                                                                            Reinstate
                                                                                            </a>
                                                                                        @endif  </div>
                                                                </div>
                                                            @endforeach
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12">
            <button type="button" class="btn btn-primary" wire:click="$emit('saveProfessionalIndemnity', {{ $policyCoverage->id }})">
                                <i class="fa fa-save"></i> Save Professional Indemnity
                            </button>
            </div>
        </div>
    </div>

</div>

