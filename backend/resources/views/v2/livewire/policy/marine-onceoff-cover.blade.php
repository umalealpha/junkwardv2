{{-- Marine Cargo Once Off Policy --}}
<div class="marine-onceoff-cover-section">

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
                                {{-- Policy Number --}}
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy Number</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.policy_number"/>
                                    </td>
                                </tr>

                                {{-- Name & Address of the Assured --}}
                                <tr>
                                    <td rowspan="2" style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Name & Address of the Assured</strong>
                                    </td>
                                    <td>
                                        <label class="form-label mb-1"><small>Name:</small></label>
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.assured_name" placeholder="Name of the Assured" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.assured_name"/>
                                    </td>
                                    <td rowspan="2" style="width: 35%; vertical-align: top;">
                                        <div>
                                            <label class="form-label mb-1"><small>Agent/Broker Code No.:</small></label>
                                            <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.agent_broker_code" placeholder="Agent/Broker Code No." />
                                            <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.agent_broker_code"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="form-label mb-1"><small>Address:</small></label>
                                        <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.assured_address" placeholder="Address of the Assured" rows="3"></textarea>
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.assured_address"/>
                                    </td>
                                </tr>

                                {{-- Policy Period --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong><h5 class="mt-4 mb-3"><strong><u>Policy Period</u></strong></h5>
                                        <p class="text-muted mb-2">This open policy is to remain in force a period of 12 months unless sum insured is previously exhausted by declarations.</p></strong>
                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From:</small></label>
                                                <x-form-input type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.policy_period_from" placeholder="From" />
                                                <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.policy_period_from"/>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To:</small></label>
                                                <x-form-input type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.policy_period_to" placeholder="To" />
                                                <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.policy_period_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Voyage --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Voyage</strong>
                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From:</small></label>
                                                <x-form-input type="date" class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.voyage_from" placeholder="From" />
                                                <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.voyage_from"/>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To:</small></label>
                                                <x-form-input type="date" class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.voyage_to" placeholder="To" />
                                                <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.voyage_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Conveyance --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Conveyance</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.conveyance" placeholder="Conveyance" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.conveyance"/>
                                    </td>
                                </tr>

                                {{-- Commodities Covered --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Commodities Covered</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.commodities_covered" placeholder="Commodities covered" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.commodities_covered"/>
                                    </td>
                                </tr>

                                {{-- Nature of Packing --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Nature of Packing</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.nature_of_packing" placeholder="Nature of packing" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.nature_of_packing"/>
                                    </td>
                                </tr>

                                {{-- Terms of Cover --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Terms of Cover</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.terms_of_cover" placeholder="Terms of cover" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.terms_of_cover"/>
                                    </td>
                                </tr>

                                {{-- Location Limit --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Location Limit</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.location_limit" placeholder="Location Limit" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.location_limit"/>
                                    </td>
                                </tr>

                                {{-- Premium Rate --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium Rates (percent)</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.lazy="marineOnceOffCover.{{ $policyCoverage->id }}.premium_rate" placeholder="Premium Rate (%)" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.premium_rate"/>
                                    </td>
                                </tr>

                                {{-- Sum Insured --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Sum Insured</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineOnceOffCover.{{ $policyCoverage->id }}.sum_insured" placeholder="Sum Insured" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.sum_insured"/>
                                    </td>
                                </tr>

                                {{-- Premium --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model="marineOnceOffCover.{{ $policyCoverage->id }}.premium" placeholder="Premium" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.premium"/>
                                    </td>
                                </tr>
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
                                                <input class="form-check-input" type="checkbox" wire:model.defer="marineOnceOffCoverClauses.{{ $policyCoverage->id }}.{{ $leftNum }}" id="moo_clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                            </div>
                                        </td>
                                        <td style="width: 45%;">
                                            <label class="form-check-label" for="moo_clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                                {{ $leftNum }}. {{ $clauseNames[$leftNum] }}
                                            </label>
                                        </td>
                                        @if ($rightNum <= 28)
                                            <td style="width: 5%;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" wire:model.defer="marineOnceOffCoverClauses.{{ $policyCoverage->id }}.{{ $rightNum }}" id="moo_clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
                                                </div>
                                            </td>
                                            <td style="width: 45%;">
                                                <label class="form-check-label" for="moo_clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
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
                                        <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.survey_claim_settlement" placeholder="In the event of loss or damage which may involve a claim under this Insurance, immediate notice thereof and application for survey should be given to:" rows="3"></textarea>
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.survey_claim_settlement"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable at</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.claim_payable_at" placeholder="Claim Payable at" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.claim_payable_at"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable by</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.claim_payable_by" placeholder="Claim Payable by" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.claim_payable_by"/>
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
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.place" placeholder="Place" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.place"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model="marineOnceOffCover.{{ $policyCoverage->id }}.signing_date" placeholder="Date"/>
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.signing_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Examined</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.examined_by" placeholder="Examined by" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.examined_by"/>
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

                    {{-- Basis of Valuation --}}
                    <h5 class="mb-3"><strong><u>Basis of Valuation / Inspection of Records</u></strong></h5>
                    <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.basis_of_valuation" placeholder="Basis of Valuation" rows="3"></textarea>
                    <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.basis_of_valuation"/>

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
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_rail" placeholder="Amount" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_rail"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>b. Per any one road vehicle</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_road" placeholder="Amount" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_road"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>c. Per any one air transit and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_air" placeholder="Amount" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_air"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>d. Per any one registered post & or courier</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_post" placeholder="Amount" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_post"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>e. Per any one vessel and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_vessel" placeholder="Amount" />
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.per_conveyance_vessel"/>
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
                    <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.deductible" placeholder="Deductible details" rows="3"></textarea>
                    <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.deductible"/>

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
                                        <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.notice_of_cancellation" placeholder="This policy is subject to cancellation by either side after giving 30 days time of cancellation in writing. SRCC risks are subject to 48 hours notice of cancellation." rows="3"></textarea>
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.notice_of_cancellation"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Refund</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.refund" placeholder="In the event of cancellation as above pro-rata refund of premium will be made in respect of undeclared balance." rows="3"></textarea>
                                        <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.refund"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Policy Wording (Attach File) --}}
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
                                <input type="file" class="form-control" wire:model="marineOnceOffCoverPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($marineOnceOffCoverPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $marineOnceOffCoverPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="marineOnceOffCoverPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $marineOnceOffCover[$policyCoverage->id]['policy_wording_path'] ?? null;
                            $policyWordingFilename = $marineOnceOffCover[$policyCoverage->id]['policy_wording_filename'] ?? null;
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

    {{-- Notes --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Additional Notes</h3>
                </div>
                <div class="card-body">
                    <textarea class="form-control" wire:model.defer="marineOnceOffCover.{{ $policyCoverage->id }}.notes" placeholder="Additional notes" rows="4"></textarea>
                    <x-form-input-error name="marineOnceOffCover.{{ $policyCoverage->id }}.notes"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12">
                <button type="button" class="btn btn-primary" wire:click="$emit('saveMarineOnceOffCover', {{ $policyCoverage->id }})">
                    <i class="fa fa-save"></i> Save Marine Cargo Once Off
                </button>
            </div>
        </div>
    </div>

</div>
