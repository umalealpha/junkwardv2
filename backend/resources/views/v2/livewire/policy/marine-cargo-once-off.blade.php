{{-- Marine Cargo Once Off Policy --}}
<div class="marine-cargo-once-off-section">

    {{-- Schedule Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">MARINE CARGO - Once Off Policy Schedule</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                {{-- Name & Address of the Assured --}}
                                <tr>
                                    <td rowspan="2" style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Name & Address of the Assured</strong>
                                    </td>
                                    <td>
                                        <label class="form-label mb-1"><small>Name:</small></label>
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.assured_name" placeholder="Name of the Assured" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.assured_name"/>
                                    </td>
                                    <td rowspan="2" style="width: 35%; vertical-align: top;">
                                        <div class="mb-2">
                                            <label class="form-label mb-1"><small>Open Policy No.:</small></label>
                                            <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.open_policy_no" placeholder="Open Policy No." />
                                            <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.open_policy_no"/>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1"><small>Agent/Broker Code No.:</small></label>
                                            <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.agent_broker_code" placeholder="Agent/Broker Code No." />
                                            <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.agent_broker_code"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="form-label mb-1"><small>Address:</small></label>
                                        <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.assured_address" placeholder="Address of the Assured" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.assured_address"/>
                                    </td>
                                </tr>

                                {{-- Conveyance & Voyage --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Conveyance</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.conveyance" placeholder="Conveyance" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.conveyance"/>
                                    </td>
                                    
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong><h5 class="mt-4 mb-3"><strong><u>Policy Period</u></strong></h5>
                                        <p class="text-muted mb-2">This open policy is to remain in force a period of 12 months unless sum insured is previously exhausted by declarations.</p></strong>
                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From:</small></label>
                                                <x-form-input type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.policy_period_from" placeholder="From" />
                                                <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.policy_period_from"/>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To:</small></label>
                                                <x-form-input type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.policy_period_to" placeholder="To" />
                                                <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.policy_period_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: top;">
                                        <label class="form-label mb-1"><strong>Voyage</strong></label>

                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From:</small></label>
                                                <x-form-input type="date" pattern="\d{2}/\d{2}/\d{4}" class="form-control" wire:model="marineCargoOnceOffVoyageDate.{{ $policyCoverage->id }}.voyage_from" placeholder="From" />
                                                <x-form-input-error name="marineCargoOnceOffVoyageDate.{{ $policyCoverage->id }}.voyage_from"/>
                                            </div>                                            
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To:</small></label>
                                                <x-form-input type="date" pattern="\d{2}/\d{2}/\d{4}" class="form-control" wire:model="marineCargoOnceOffVoyageDate.{{ $policyCoverage->id }}.voyage_to" placeholder="To" />
                                                <x-form-input-error name="marineCargoOnceOffVoyageDate.{{ $policyCoverage->id }}.voyage_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Commodities & Nature of Packing --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Commodities Covered</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.commodities_covered" placeholder="Commodities covered" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.commodities_covered"/>
                                    </td>
                                    
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Nature of Packing</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.nature_of_packing" placeholder="Nature of packing" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.nature_of_packing"/>
                                    </td>
                                </tr>

                                {{-- Terms of Cover & Annual Estimated Turnover --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Terms of Cover</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.terms_of_cover" placeholder="Terms of cover" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.terms_of_cover"/>
                                    </td>
                                    
                                </tr>
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Annual Estimated Turnover</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.annual_estimated_turnover" placeholder="Annual Estimated Turnover" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.annual_estimated_turnover"/>
                                    </td>  
                                </tr> -->
                                {{-- Location Limit --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Location Limit</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.location_limit" placeholder="Location Limit" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.location_limit"/>
                                    </td>
                                </tr>

                                {{-- Premium Rate & Sum Insured --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium Rates (percent)</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.lazy="marineCargoOnceOff.{{ $policyCoverage->id }}.premium_rate" placeholder="Premium Rate (%)" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.premium_rate"/>
                                    </td>
                                    
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Sum Insured</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineCargoOnceOff.{{ $policyCoverage->id }}.sum_insured" placeholder="Sum Insured" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.sum_insured"/>
                                    </td>
                                </tr>

                                {{-- Premium --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model="marineCargoOnceOff.{{ $policyCoverage->id }}.premium" placeholder="Premium"  />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.premium"/>
                                    </td>
                                </tr>

                                {{-- Today's Date --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's Date</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="marineCargoOnceOff.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.today_date"/>
                                    </td>
                                </tr>

                                {{-- New/Altered --}}
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>New/Altered</strong>
                                    </td>
                                    <td colspan="2">
                                        <select class="form-select" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.new_altered">
                                            <option value="">- Select -</option>
                                            <option value="New">New</option>
                                            <option value="Altered">Altered</option>
                                        </select>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.new_altered"/>
                                    </td>
                                </tr>

                                {{-- Period of Insurance --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Period of Insurance</strong>
                                    </td>
                                    <td colspan="2">
                                        <select class="form-select" wire:model="marineCargoOnceOff.{{ $policyCoverage->id }}.period_of_insurance">
                                            <option value="">- Select Policy Period -</option>
                                            <option value="12">Allow up to 12 months max</option>
                                            <option value="24">Allow up to 24 months max</option>
                                            <option value="36">Allow up to 36 months max</option>
                                        </select>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.period_of_insurance"/>
                                    </td>
                                </tr> -->

                                {{-- Policy Period Approval (only for 24/36 months) --}}
                                @if(in_array($marineCargoOnceOff[$policyCoverage->id]['period_of_insurance'] ?? '', ['24', '36']))
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy Period Approval</strong>
                                    </td>
                                    <td colspan="2">
                                        @if(empty($marineCargoOnceOff[$policyCoverage->id]['approved_by'] ?? null))
                                            <button type="button" class="btn btn-success" wire:click="approvePolicyPeriod({{ $policyCoverage->id }}, 'MCOO')">
                                                <i class="fa fa-check"></i> Approve
                                            </button>
                                        @else
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($marineCargoOnceOff[$policyCoverage->id]['approved_by'] ?? null);
                                                $approvedAt = isset($marineCargoOnceOff[$policyCoverage->id]['approved_at']) ? \Carbon\Carbon::parse($marineCargoOnceOff[$policyCoverage->id]['approved_at'])->format('d/m/Y H:i') : '';
                                            @endphp
                                            <div class="alert alert-success mb-0 p-2">
                                                <strong>Approved</strong><br>
                                                <small>By: {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}</small><br>
                                                <small>At: {{ $approvedAt }}</small>
                                            </div>
                                        @endif
                                    </td>
                                </tr> -->
                                @endif

                                {{-- Renewable Policy --}}
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Renewable Policy</strong>
                                    </td>
                                    <td colspan="2">
                                        <select class="form-select" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.is_renewable">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Renewable Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.is_renewable"/>
                                    </td>
                                </tr> -->

                                {{-- Project Specific Policy --}}
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Project Specific Policy</strong>
                                    </td>
                                    <td colspan="2">
                                        <select class="form-select" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.is_project_specific">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Project Specific Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.is_project_specific"/>
                                    </td>
                                </tr> -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Clauses Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="text-muted mb-0">Special Conditions & Warranties: This Insurance is subject to the following Clause & Conditions as printed herein or attached hereto:</h3>
                    <p class="card-title">Clauses (tick the applicable clauses)</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                @php
                                    $clauseNames = [
                                        1 => 'Institute Cargo Clause (A)',
                                        2 => 'Institute Cargo Clause (B)',
                                        3 => 'Institute Cargo Clause (C)',
                                        4 => 'Malicious Damage Clause',
                                        5 => 'Institute Theft, Pilferage, Nondelivery Clause',
                                        6 => 'Institute Replacement Clause',
                                        7 => 'Replacement Clause (Second Hand Machinery)',
                                        8 => 'Label Clause',
                                        9 => 'Pair and Set Clause',
                                        10 => 'Institute War Clause (Cargo)',
                                        11 => 'Institute Strikes Clause (Cargo)',
                                        12 => 'Institute Cargo Clause (Air) (excluding sendings by post)',
                                        13 => 'Institute War Clauses (sendings by post)',
                                        14 => 'Institute War Clauses (Air Cargo) (excluding sendings by post)',
                                        15 => 'Institute Strikes Clause (Air Cargo)',
                                        16 => 'Institute War Cancellation Clause (Cargo)',
                                        17 => 'Institute Classification Clause',
                                        18 => 'Inland Transit (Rail or Road) A – All Risks',
                                        19 => 'Inland Transit (Rail or Road) B – Basic Cover',
                                        20 => 'Inland Transit (Rail or Road) C – Fire Risk',
                                        21 => 'Inland SRCC Clause',
                                        22 => 'Inland Transit (Inland Vessels) Clause',
                                        23 => 'Sailing Vessels Clause',
                                        24 => 'Important Notice',
                                        25 => 'Duty Clause',
                                        26 => 'Increased Value Insurance Clause',
                                        27 => 'Institute Radioactive Contamination Exclusion Clause',
                                        28 => 'Terrorism Exclusion Clause',
                                    ];
                                @endphp
                                @for ($row = 0; $row < 14; $row++)
                                    <tr>
                                        @php $leftNum = $row + 1; $rightNum = $row + 15; @endphp
                                        <td style="width: 5%;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" wire:model.defer="marineCargoOnceOffClauses.{{ $policyCoverage->id }}.{{ $leftNum }}" id="clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                            </div>
                                        </td>
                                        <td style="width: 45%;">
                                            <label class="form-check-label" for="clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                                {{ $leftNum }}. {{ $clauseNames[$leftNum] }}
                                            </label>
                                        </td>
                                        @if ($rightNum <= 28)
                                            <td style="width: 5%;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" wire:model.defer="marineCargoOnceOffClauses.{{ $policyCoverage->id }}.{{ $rightNum }}" id="clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
                                                </div>
                                            </td>
                                            <td style="width: 45%;">
                                                <label class="form-check-label" for="clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
                                                    {{ $rightNum }}. {{ $clauseNames[$rightNum] }}
                                                </label>
                                            </td>
                                        @else
                                            <td colspan="2"></td>
                                        @endif
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Survey and Claim Settlement Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Survey and Claim Settlement</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">THE ATTACHED CLAUSES AND ENDORSEMENTS FORM PART OF THE POLICY</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Survey and Claim Settlement</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.survey_claim_settlement" placeholder="In the event of loss or damage which may involve a claim under this Insurance, immediate notice thereof and application for survey should be given to:" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.survey_claim_settlement"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable at</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.claim_payable_at" placeholder="Claim Payable at" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.claim_payable_at"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable by</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.claim_payable_by" placeholder="Claim Payable by" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.claim_payable_by"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Witness / Signing Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">In Witness Whereof</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">IN WITNESS WHEREOF signed for and on behalf of the Company</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Place</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.place" placeholder="Place" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.place"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="marineCargoOnceOff.{{ $policyCoverage->id }}.signing_date" placeholder="Date"/>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.signing_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Examined</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.examined_by" placeholder="Examined by" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.examined_by"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Memorandum Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Memorandum Attaching to and Forming Part of Policy</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                {{-- Basis of Valuation --}}
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Basis of Valuation / Inspection of Records</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.basis_of_valuation" placeholder="Basis of Valuation" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.basis_of_valuation"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Per Conveyance Limit --}}
                    <h5 class="mt-4 mb-3"><strong><u>Per Conveyance Limit</u></strong></h5>
                    <p class="text-muted">Warranted that the limit of the Insurer's liability in respect of any one accident or series of accidents arising from the same events shall not exceed:</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>a. Per any one rail transit</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_rail" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_rail"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>b. Per any one road vehicle</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_road" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_road"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>c. Per any one air transit and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_air" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_air"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>d. Per any one registered post & or courier</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_post" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_post"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>e. Per any one vessel and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_vessel" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.per_conveyance_vessel"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Location Limit Note --}}
                    <h5 class="mt-4 mb-3"><strong><u>Location Limit</u></strong></h5>
                    <p class="text-muted">Not withstanding anything to the contrary stated herein, in the event of loss and/or damage to the subject matter insured or any expense incurred by way of sue and labour, the total liability of the company in any particular location, for all the insured consignments covered under this Open Policy in respect of any one accident/occurrence or a series of accidents and/or occurrences arising out of the same event, shall not exceed Location Limit stated in the Schedule.</p>

                    {{-- Deductible --}}
                    <h5 class="mt-4 mb-3"><strong><u>Deductible</u></strong></h5>
                    <p class="text-muted mb-2">The Policy is subject to the following Deductibles (Deductible is the amount of loss to borne by the Insured under each and every loss)</p>
                    <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.deductible" placeholder="Deductible details" rows="3"></textarea>
                    <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.deductible"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Notice of Cancellation & Refund --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Notice of Cancellation & Refund</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Notice of Cancellation</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.notice_of_cancellation" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.notice_of_cancellation"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Refund</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.refund" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.refund"/>
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
                                <input type="file" class="form-control" wire:model="marineCargoOnceOffPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($marineCargoOnceOffPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $marineCargoOnceOffPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="marineCargoOnceOffPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $marineCargoOnceOff[$policyCoverage->id]['policy_wording_path'] ?? null;
                            
                            $policyWordingFilename = $marineCargoOnceOff[$policyCoverage->id]['policy_wording_filename'] ?? null;
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

    {{-- Notes --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Additional Notes</h3>
                </div>
                <div class="card-body">
                    <textarea class="form-control" wire:model.defer="marineCargoOnceOff.{{ $policyCoverage->id }}.notes" placeholder="Additional notes" rows="4"></textarea>
                    <x-form-input-error name="marineCargoOnceOff.{{ $policyCoverage->id }}.notes"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Miscellaneous Items --}}
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
                                                  placeholder="Sum Insured" class="amount-field"/>
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
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12">
                <button type="button" class="btn btn-primary" wire:click="$emit('saveMarineCargoOnceOffCoverage', {{ $policyCoverage->id }})">
                    <i class="fa fa-save"></i> Save Marine Cargo Once Off
                </button>
            </div>
        </div>
    </div>

</div>
