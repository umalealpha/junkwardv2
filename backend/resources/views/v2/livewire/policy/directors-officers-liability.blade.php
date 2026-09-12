{{-- Directors & Officers Liability Coverage --}}
<div class="directors-officers-liability-section">
    {{-- Schedule - Policy Details Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Schedule</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 25%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy Number</strong>
                                    </td>
                                    <td style="width: 60%;">
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.policy_number"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2"><strong style="font-size: 20px;">Policyholder</strong></td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Company Name</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.company_name" placeholder="Company Name" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.company_name"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Company Address:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.company_address" placeholder="Company Address" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.company_address"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Inception Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.inception_date" placeholder="Inception Date"/>
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.inception_date"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Expiry Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.expiry_date" placeholder="Expiry Date"/>
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.expiry_date"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.today_date"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>New/Altered</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.new_altered">
                                            <option value="">- Select -</option>
                                            <option value="New">New</option>
                                            <option value="Altered">Altered</option>
                                        </select>
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.new_altered"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Renewable Policy</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.is_renewable">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Renewable Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.is_renewable"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Currency</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.currency" placeholder="Currency" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.currency"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.premium" placeholder="As agreed" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.premium"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Limit of Liability</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.limit_of_liability" placeholder="Limit of Liability" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.limit_of_liability"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Insuring Clauses Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Insuring Clauses</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%">Section</th>
                                    <th style="width: 30%">Insuring Clause</th>
                                    <th style="width: 18%">Included/Not Included</th>
                                    <th style="width: 20%">Insuring Clause Limit of Liability</th>
                                    <th style="width: 18%">Retention</th>
                                    <th style="width: 6%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $insuringClauses = $directorsOfficersLiabilityInsuringClauses[$policyCoverage->id] ?? [];
                                    if (empty($insuringClauses)) {
                                        $insuringClauses = [
                                            ['section'=> '1.1','name' => 'Side A – Directors & Officers Liability', 'included' => '', 'limit_of_liability' => '', 'retention' => ''],
                                            ['section'=> '1.2','name' => 'Side B – Organisation Reimbursement', 'included' => '', 'limit_of_liability' => '', 'retention' => ''],
                                            ['section'=> '1.3','name' => 'Side C – Organisation Liability for Securities Claims', 'included' => '', 'limit_of_liability' => '', 'retention' => ''],
                                            ['section'=> '1.4','name' => 'Investigations', 'included' => '', 'limit_of_liability' => '', 'retention' => ''],
                                        ];
                                    }
                                @endphp
                                @foreach($insuringClauses as $index => $clause)
                                <tr wire:key="dol-insuring-clause-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityInsuringClauses.{{ $policyCoverage->id }}.{{ $index }}.section" placeholder="Section" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityInsuringClauses.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Insuring Clause" />
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="directorsOfficersLiabilityInsuringClauses.{{ $policyCoverage->id }}.{{ $index }}.included">
                                            <option value="">- Select -</option>
                                            <option value="Included">Included</option>
                                            <option value="Not Included">Not Included</option>
                                        </select>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiabilityInsuringClauses.{{ $policyCoverage->id }}.{{ $index }}.limit_of_liability" placeholder="Limit of Liability" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiabilityInsuringClauses.{{ $policyCoverage->id }}.{{ $index }}.retention" placeholder="Retention" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeDirectorsOfficersLiabilityInsuringClause({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addDirectorsOfficersLiabilityInsuringClause({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Insuring Clause
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Extensions Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Extensions</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 6%">Section</th>
                                    <th style="width: 26%">Extension</th>
                                    <th style="width: 16%">Included/Not Included</th>
                                    <th style="width: 18%">Additional Limit / Sub Limit of Liability</th>
                                    <th style="width: 16%">Retention</th>
                                    <th style="width: 6%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $extensions = $directorsOfficersLiabilityExtensions[$policyCoverage->id] ?? [];
                                    if (empty($extensions)) {
                                        $extensions = [
                                            ['section'=>'2.1','name' => 'Additional Dedicated Limit of Liability for Directors & Officers', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.2','name' => 'Complimentary Legal Advice', 'included' => '', 'limit' => 'One hour per enquiry', 'retention' => 'Nil'],
                                            ['section'=>'2.3','name' => 'Court and Investigation Attendance and Expense', 'included' => '', 'limit' => '$500 Per Day', 'retention' => 'Nil'],
                                            ['section'=>'2.4','name' => 'Deprivation of Asset Expenses', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.5','name' => 'Derivative Investigation Costs', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.6','name' => 'Emergency Costs', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.7','name' => 'Extradition Costs', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.8','name' => 'Loss Mitigation', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.9','name' => 'Prosecution Costs', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.10','name' => 'Public Relations and Reputation Expenses', 'included' => '', 'limit' => '', 'retention' => ''],
                                            ['section'=>'2.11','name' => 'Work, Health and Safety Costs', 'included' => '', 'limit' => '', 'retention' => ''],
                                        ];
                                    }
                                @endphp
                                @foreach($extensions as $index => $extension)
                                <tr wire:key="dol-extension-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityExtensions.{{ $policyCoverage->id }}.{{ $index }}.section" placeholder="Section" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityExtensions.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Extension" />
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="directorsOfficersLiabilityExtensions.{{ $policyCoverage->id }}.{{ $index }}.included">
                                            <option value="">- Select -</option>
                                            <option value="Included">Included</option>
                                            <option value="Not Included">Not Included</option>
                                        </select>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiabilityExtensions.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="directorsOfficersLiabilityExtensions.{{ $policyCoverage->id }}.{{ $index }}.retention" placeholder="Retention" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeDirectorsOfficersLiabilityExtension({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addDirectorsOfficersLiabilityExtension({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Extension
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Coverage Extensions Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Coverage Extensions</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%">Section</th>
                                    <th style="width: 50%">Extension</th>
                                    <th style="width: 30%">Included/Not Included</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $coverageExtensions = $directorsOfficersLiabilityCoverageExtensions[$policyCoverage->id] ?? [];
                                    if (empty($coverageExtensions)) {
                                        $coverageExtensions = [
                                            ['section'=>'3.1','name' => 'Automatic Cover for New Subsidiaries', 'included' => ''],
                                            ['section'=>'3.2','name' => 'Backdated Continuity of Cover', 'included' => ''],
                                            ['section'=>'3.3','name' => 'Fines & Penalties', 'included' => ''],
                                            ['section'=>'3.4','name' => 'Continuity of Cover', 'included' => ''],
                                            ['section'=>'3.5','name' => 'Extended Discovery', 'included' => ''],
                                            ['section'=>'3.6','name' => 'Lifetime Cover for Retired Insured Persons', 'included' => ''],
                                            ['section'=>'3.7','name' => 'Outside Directorship Liability', 'included' => ''],
                                            ['section'=>'3.8','name' => 'Personal Taxation and Superannuation Liability', 'included' => ''],
                                            ['section'=>'3.9','name' => 'Run Off Cover for Prior Subsidiaries', 'included' => ''],
                                            ['section'=>'3.10','name' => 'Transaction Run-Off', 'included' => ''],
                                        ];
                                    }
                                @endphp
                                @foreach($coverageExtensions as $index => $covExt)
                                <tr wire:key="dol-coverage-ext-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityCoverageExtensions.{{ $policyCoverage->id }}.{{ $index }}.section" placeholder="Section" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="directorsOfficersLiabilityCoverageExtensions.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Extension" />
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="directorsOfficersLiabilityCoverageExtensions.{{ $policyCoverage->id }}.{{ $index }}.included">
                                            <option value="">- Select -</option>
                                            <option value="Included">Included</option>
                                            <option value="Not Included">Not Included</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeDirectorsOfficersLiabilityCoverageExtension({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addDirectorsOfficersLiabilityCoverageExtension({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Coverage Extension
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Schedule Details --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Additional Schedule Details</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <label class="form-label mb-1"><small>Name of Insurer:</small></label>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_insurer_name" placeholder="Name of Insurer" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_insurer_name"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <label class="form-label mb-1"><small>Type of Policy:</small></label>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_type" placeholder="Type of Policy" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_type"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Previous Policy</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <label class="form-label mb-1"><small>Policyholder:</small></label>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policyholder" placeholder="Policyholder" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policyholder"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <label class="form-label mb-1"><small>Policy Number:</small></label>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_number" placeholder="Policy Number" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_number"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <label class="form-label mb-1"><small>Policy Period:</small></label>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_period" placeholder="Policy Period" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.previous_policy_period"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 25%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Backdated Continuity Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.backdated_continuity_date" placeholder="Backdated Continuity Date" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.backdated_continuity_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Jurisdictional Cover</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="directorsOfficersLiability.{{ $policyCoverage->id }}.jurisdictional_cover" placeholder="Jurisdictional Cover" />
                                        <x-form-input-error name="directorsOfficersLiability.{{ $policyCoverage->id }}.jurisdictional_cover"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                                <input type="file" class="form-control" wire:model="directorsOfficersLiabilityPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($directorsOfficersLiabilityPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $directorsOfficersLiabilityPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="directorsOfficersLiabilityPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $directorsOfficersLiability[$policyCoverage->id]['policy_wording_path'] ?? null;
                            $policyWordingFilename = $directorsOfficersLiability[$policyCoverage->id]['policy_wording_filename'] ?? null;
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
                                <a href="{{ Storage::disk('public')->url($policyWordingPath) }}"
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

    {{-- Note --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Note</h3>
                </div>
                <div class="card-body">
                    <x-form-text-area wire:model.defer="policyCoverageNote.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12">
                <button type="button" class="btn btn-primary" wire:click="$emit('saveDirectorsOfficersLiabilityCoverage', {{ $policyCoverage->id }})">
                    <i class="fa fa-save"></i> Save Directors &amp; Officers Liability
                </button>
            </div>
        </div>
    </div>
</div>
