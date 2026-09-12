{{-- Medical Malpractice Insurance Coverage --}}
<div class="medical-malpractice-section">
    {{-- Policy Details Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Policy Details</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy Number</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.policy_number"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Type of Document</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.type_of_document" placeholder="Type of Document" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.type_of_document"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.insured" placeholder="Insured" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.insured"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured VAT Number</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.insured_vat_number" placeholder="Insured VAT Number" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.insured_vat_number"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Company Registration Number</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.company_registration_number" placeholder="Company Registration Number" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.company_registration_number"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured Business Description</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.insured_business_description" placeholder="Insured Business Description" rows="3"></textarea>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.insured_business_description"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured Postal Address</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.insured_postal_address" placeholder="Insured Postal Address" rows="3"></textarea>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.insured_postal_address"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Intermediary</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.intermediary" placeholder="Intermediary" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.intermediary"/>
                                    </td>
                                </tr>
                              
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy inception date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="medicalMalpractice.{{ $policyCoverage->id }}.policy_inception_date" placeholder="Policy inception date"/>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.policy_inception_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy expiry date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="medicalMalpractice.{{ $policyCoverage->id }}.policy_expiry_date" placeholder="Policy expiry date"/>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.policy_expiry_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="medicalMalpractice.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.today_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>New/altered</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.new_altered">
                                            <option value="">- Select -</option>
                                            <option value="New">New</option>
                                            <option value="Altered">Altered</option>
                                        </select>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.new_altered"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Period of Insurance</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.period_of_insurance" wire:change="updateMedicalMalpracticePeriodOfInsurance({{ $policyCoverage->id }})">
                                            <option value="">- Select Policy Period -</option>
                                            <option value="12">Allow up to 12 months max</option>
                                            <option value="24">Allow up to 24 months max</option>
                                            <option value="36">Allow up to 36 months max</option>
                                        </select>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.period_of_insurance"/>
                                    </td>
                                </tr>
                                @if(in_array($medicalMalpractice[$policyCoverage->id]['period_of_insurance'] ?? '', ['24', '36']))
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy Period Approval</strong>
                                    </td>
                                    <td>
                                        @if(empty($medicalMalpractice[$policyCoverage->id]['approved_by'] ?? null))
                                            <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'MM')">
                                                <i class="fa fa-check"></i> Approve
                                            </button>
                                        @else
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($medicalMalpractice[$policyCoverage->id]['approved_by'] ?? null);
                                                $approvedAt = isset($medicalMalpractice[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($medicalMalpractice[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
                                            @endphp
                                            <div class="alert alert-success mb-0 p-2">
                                                <strong>Approved</strong><br>
                                                <small>By: {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}</small><br>
                                                <small>At: {{ $approvedAt }}</small>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Renewable Policy</strong>
                                    </td>
                                    <td>
                                        @php
                                            $policyPeriod = $medicalMalpractice[$policyCoverage->id]['period_of_insurance'] ?? '';
                                            // $isDisabled = !empty($policyPeriod) && $policyPeriod != '12';
                                            $isDisabled = false;
                                        @endphp
                                        <select class="form-select" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.is_renewable" @if($isDisabled) disabled @endif>
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Renewable Policy</option>
                                            <option value="No" @if($isDisabled) selected @endif>No</option>
                                        </select>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.is_renewable"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Project Specific Policy</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.is_project_specific">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Project Specific Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.is_project_specific"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Anniversary/Renewal Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="medicalMalpracticeDate.{{ $policyCoverage->id }}.anniversary_renewal_date" id="anniversary_renewal_date_{{ $policyCoverage->id }}" placeholder="Anniversary/Renewal Date" />
                                        <x-form-input-error name="medicalMalpracticeDate.{{ $policyCoverage->id }}.anniversary_renewal_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Retroactive Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model="medicalMalpracticeDate.{{ $policyCoverage->id }}.retroactive_date" id="retroactive_date_{{ $policyCoverage->id }}" placeholder="Retroactive Date" />
                                        <x-form-input-error name="medicalMalpracticeDate.{{ $policyCoverage->id }}.retroactive_date"/>
                                    </td>
                                </tr>
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Type Of Contract</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.type_of_contract" placeholder="Type Of Contract" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.type_of_contract"/>
                                    </td>
                                </tr> -->
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Payment Frequency</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.payment_frequency" placeholder="Payment Frequency" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.payment_frequency"/>
                                    </td>
                                </tr> -->
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>
                                            @if(isset($medicalMalpractice[$policyCoverage->id]['premium_freq']))
                                                @if($medicalMalpractice[$policyCoverage->id]['premium_freq'] == 1)
                                                Monthly Premium
                                                @elseif($medicalMalpractice[$policyCoverage->id]['premium_freq'] == 5)
                                                    Quarterly Premium
                                                @elseif($medicalMalpractice[$policyCoverage->id]['premium_freq'] == 3)
                                                Annual Premium
                                                @else
                                                Premium
                                                @endif
                                            @else
                                            Premium
                                            @endif
                                        </strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.annual_premium" placeholder="Annual Premium" />
                                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.annual_premium"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Risk Details Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Risk Details</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%">Risk Details</th>
                                    <th style="width: 45%">Limit of Indemnity</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $riskDetails = $medicalMalpracticeRiskDetails[$policyCoverage->id] ?? [];                                    
                                @endphp
                                @foreach($riskDetails as $index => $riskDetail)
                                <tr wire:key="mm-risk-detail-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="medicalMalpracticeRiskDetails.{{ $policyCoverage->id }}.{{ $index }}.risk_detail" placeholder="Risk Detail" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpracticeRiskDetails.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Limit of Indemnity" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMedicalMalpracticeRiskDetail({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <div class="flex-grow-1"><label style="font-size: 12px;text-transform: uppercase;font-weight: bold;color: #ff0000;">* Do not add a new risk detail if the current row is blank.</label></div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMedicalMalpracticeRiskDetail({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Risk Detail
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Extensions Applicable Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Extensions Applicable</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%">Section Name</th>
                                    <th style="width: 19%">Limit of Indemnity</th>
                                    <th style="width: 19%">Basis of Limit</th>
                                    <th style="width: 16%">Deductible</th>
                                    <th style="width: 16%">Basis of Deductible</th>
                                    <th style="width: 5%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $extensions = $medicalMalpracticeExtensions[$policyCoverage->id] ?? [];
                                    if (empty($extensions)) {
                                        $extensions = [
                                            ['section_name' => 'Medical Malpractice', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Professional Indemnity', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Public Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Pollution Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Products Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Employers Liability', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Breach of Confidentiality', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Business Identity Theft', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Defamation', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Documents', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Statutory Defence Costs (Sub-Limit of Public Liability Section)', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                            ['section_name' => 'Wrongful Arrest (Sub-Limit of Public Liability Section)', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                        ];
                                    }
                                @endphp
                                @foreach($extensions as $index => $extension)
                                <tr wire:key="mm-extension-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="medicalMalpracticeExtensions.{{ $policyCoverage->id }}.{{ $index }}.section_name" placeholder="Section Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpracticeExtensions.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity" placeholder="Limit of Indemnity" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpracticeExtensions.{{ $policyCoverage->id }}.{{ $index }}.basis_of_limit" placeholder="Basis of Limit" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpracticeExtensions.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpracticeExtensions.{{ $policyCoverage->id }}.{{ $index }}.basis_of_deductible" placeholder="Basis of Deductible" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMedicalMalpracticeExtension({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                
                                {{-- Specific Deductible Section --}}
                                <tr>
                                    <td colspan="6" style="background-color: #f8f9fa;">
                                        <strong>Specific Deductible in respect of:</strong>
                                    </td>
                                </tr>
                                @php
                                    $specificDeductibles = $medicalMalpracticeSpecificDeductibles[$policyCoverage->id] ?? [];
                                    if (empty($specificDeductibles)) {
                                        $specificDeductibles = [
                                            ['section_name' => 'Online Therapy', 'limit_of_indemnity' => '', 'basis_of_limit' => '', 'deductible' => '', 'basis_of_deductible' => ''],
                                        ];
                                    }
                                @endphp
                                @foreach($specificDeductibles as $index => $deductible)
                                <tr wire:key="mm-deductible-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="medicalMalpracticeSpecificDeductibles.{{ $policyCoverage->id }}.{{ $index }}.section_name" placeholder="Section Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpracticeSpecificDeductibles.{{ $policyCoverage->id }}.{{ $index }}.limit_of_indemnity" placeholder="Limit of Indemnity" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpracticeSpecificDeductibles.{{ $policyCoverage->id }}.{{ $index }}.basis_of_limit" placeholder="Basis of Limit" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="medicalMalpracticeSpecificDeductibles.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="medicalMalpracticeSpecificDeductibles.{{ $policyCoverage->id }}.{{ $index }}.basis_of_deductible" placeholder="Basis of Deductible" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMedicalMalpracticeSpecificDeductible({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <div class="flex-grow-1"><label style="font-size: 12px;text-transform: uppercase;font-weight: bold;color: #ff0000;">* Do not add a new extension & specific deductible. if the current row is blank.</label></div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMedicalMalpracticeExtension({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Extension
                        </button>
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMedicalMalpracticeSpecificDeductible({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Specific Deductible
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
                                <input type="file" class="form-control" wire:model="medicalMalpracticePolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($medicalMalpracticePolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $medicalMalpracticePolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="medicalMalpracticePolicyWording"/>
                            </div>
                        </div>
                        @php
                        $policyWordingPath =
                                $medicalMalpractice[$policyCoverage->id]['policy_wording_path']
                                ?? null;

                            $policyWordingFilename =
                                $medicalMalpractice[$policyCoverage->id]['policy_wording_filename']
                                ?? null;
                        @endphp
                        @if(!empty($policyWordingPath))
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <strong>Current Policy Wording:</strong><br>
                                <a href="{{ Storage::url($medicalMalpractice[$policyCoverage->id]['policy_wording_path']) }}"
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

    {{-- Standard Policy Conditions Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Standard Policy Conditions</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p><strong>Medical Malpractice for Medical Professions Renewal Terms</strong></p>
                    </div>
                    <div class="mb-3">
                        <textarea class="form-control" wire:model.defer="medicalMalpractice.{{ $policyCoverage->id }}.standard_policy_conditions" placeholder="Standard Policy Conditions" rows="5">The Policy Wording, together with this Schedule and its endorsements as agreed to by the Insurer from time to time, shall be read together as one contract. This Schedule provides a summary of the cover under this policy, but it also includes additional terms and conditions of cover in the endorsements. Please read The Policy Wording together with all the endorsements on this Schedule carefully.</textarea>
                        <x-form-input-error name="medicalMalpractice.{{ $policyCoverage->id }}.standard_policy_conditions"/>
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
                                                                                          placeholder="Sum Insured" class="amount-field" x-mask:dynamic="$money($input)"/>
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
                <button type="button" class="btn btn-primary" wire:click="$emit('saveMedicalMalpracticeCoverage', {{ $policyCoverage->id }})">
                <i class="fa fa-save"></i> Save Medical Malpractice
                </button>
            </div>
        </div>
    </div>
    

</div>

