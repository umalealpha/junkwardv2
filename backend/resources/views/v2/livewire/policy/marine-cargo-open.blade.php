{{-- Marine Cargo Open Policy --}}
<div class="marine-cargo-open-section">

    {{-- Schedule Section --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">MARINE CARGO - Open Policy Schedule</h3>
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
                                        <label class="form-label mb-1"><small>Name: <span class="text-muted">(auto-filled from policy)</span></small></label>
                                        <x-form-input type="text" readonly wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.assured_name" placeholder="Name of the Assured" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.assured_name"/>
                                    </td>
                                    <td rowspan="2" style="width: 35%; vertical-align: top;">
                                        <div class="mb-2">
                                            <label class="form-label mb-1"><small>Open Policy No.:</small></label>
                                            <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.open_policy_no" placeholder="Open Policy No." />
                                            <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.open_policy_no"/>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1"><small>Agent/Broker Code No.:</small></label>
                                            <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.agent_broker_code" placeholder="Agent/Broker Code No." />
                                            <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.agent_broker_code"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="form-label mb-1"><small>Address:</small></label>
                                        <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.assured_address" placeholder="Address of the Assured" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.assured_address"/>
                                    </td>
                                </tr>

                                {{-- Conveyance --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong><h5 class="mt-4 mb-3"><strong><u>Policy Period</u></strong></h5>
                                        <p class="text-muted mb-2">This open policy is to remain in force a period of 12 months unless sum insured is previously exhausted by declarations.</p></strong>
                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From: <span class="text-muted">(auto-filled from policy)</span></small></label>
                                                <x-form-input type="text" readonly class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.policy_period_from" placeholder="From" />
                                                <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.policy_period_from"/>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To: <span class="text-muted">(auto-filled from policy)</span></small></label>
                                                <x-form-input type="text" readonly class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.policy_period_to" placeholder="To" />
                                                <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.policy_period_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Conveyance</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.conveyance" placeholder="Conveyance" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.conveyance"/>
                                    </td>
                                </tr>

                                {{-- Voyage --}}
                                <tr>
                                    <td style="vertical-align: top; background-color: #f8f9fa;">
                                        <strong>Voyage</strong>
                                    </td>
                                    <td colspan="2">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>From:</small></label>
                                                <x-form-input type="date" pattern="\d{2}/\d{2}/\d{4}" class=" form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.voyage_from" placeholder="From" />
                                                <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.voyage_from"/>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label mb-1"><small>To:</small></label>
                                                <x-form-input type="date" pattern="\d{2}/\d{2}/\d{4}" class="kt_datepicker_1 form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.voyage_to" placeholder="To" />
                                                <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.voyage_to"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Commodities Covered --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Commodities Covered</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.commodities_covered" placeholder="Commodities covered" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.commodities_covered"/>
                                    </td>
                                </tr>

                                {{-- Nature of Packing --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Nature of Packing</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.nature_of_packing" placeholder="Nature of packing" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.nature_of_packing"/>
                                    </td>
                                </tr>

                                {{-- Terms of Cover --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Terms of Cover</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.terms_of_cover" placeholder="Terms of cover" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.terms_of_cover"/>
                                    </td>
                                </tr>

                                {{-- Annual Estimated Turnover --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Annual Estimated Turnover</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.annual_estimated_turnover" placeholder="Annual Estimated Turnover" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.annual_estimated_turnover"/>
                                    </td>
                                </tr>

                                {{-- Location Limit --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Location Limit</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.location_limit" placeholder="Location Limit" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.location_limit"/>
                                    </td>
                                </tr>

                                {{-- Premium Rate --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium Rates (percent)</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" wire:model.lazy="marineCargoOpen.{{ $policyCoverage->id }}.premium_rate" placeholder="Premium Rate (%)" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.premium_rate"/>
                                    </td>
                                </tr>

                                {{-- Sum Insured --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Sum Insured</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineCargoOpen.{{ $policyCoverage->id }}.sum_insured" placeholder="Sum Insured" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.sum_insured"/>
                                    </td>
                                </tr>

                                {{-- Premium --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-input type="text" class="form-control amount-field" wire:model="marineCargoOpen.{{ $policyCoverage->id }}.premium" placeholder="Premium" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.premium"/>
                                    </td>
                                </tr>

                                {{-- Today's Date --}}
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's Date</strong>
                                    </td>
                                    <td colspan="2">
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="marineCargoOpen.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.today_date"/>
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
                                                <input class="form-check-input" type="checkbox" wire:model.defer="marineCargoOpenClauses.{{ $policyCoverage->id }}.{{ $leftNum }}" id="open_clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                            </div>
                                        </td>
                                        <td style="width: 45%;">
                                            <label class="form-check-label" for="open_clause_{{ $policyCoverage->id }}_{{ $leftNum }}">
                                                {{ $leftNum }}. {{ $clauseNames[$leftNum] }}
                                            </label>
                                        </td>
                                        @if ($rightNum <= 28)
                                            <td style="width: 5%;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" wire:model.defer="marineCargoOpenClauses.{{ $policyCoverage->id }}.{{ $rightNum }}" id="open_clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
                                                </div>
                                            </td>
                                            <td style="width: 45%;">
                                                <label class="form-check-label" for="open_clause_{{ $policyCoverage->id }}_{{ $rightNum }}">
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
                                        <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.survey_claim_settlement" placeholder="In the event of loss or damage which may involve a claim under this Insurance, immediate notice thereof and application for survey should be given to:" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.survey_claim_settlement"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable at</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.claim_payable_at" placeholder="Claim Payable at" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.claim_payable_at"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Claim Payable by</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.claim_payable_by" placeholder="Claim Payable by" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.claim_payable_by"/>
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
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.place" placeholder="Place" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.place"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="marineCargoOpen.{{ $policyCoverage->id }}.signing_date" placeholder="Date"/>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.signing_date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Examined</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.examined_by" placeholder="Examined by" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.examined_by"/>
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
                    <h3 class="card-title">Memorandum Attaching to and Forming Part of Open Policy</h3>
                </div>
                <div class="card-body">

                    {{-- Declaration --}}
                    <h5 class="mb-3"><strong><u>Declaration</u></strong></h5>
                    <textarea class="form-control mb-4" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.declaration" placeholder="Declaration" rows="5"></textarea>
                    <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.declaration"/>

                    {{-- Basis of Valuation --}}
                    <h5 class="mt-4 mb-3"><strong><u>Basis of Valuation / Inspection of Records</u></strong></h5>
                    <p class="text-muted mb-2">The Company and / or its agents will have the right at any time during business hours to inspect assured's records of dispatches made within the terms of the policy.</p>
                    <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.basis_of_valuation" placeholder="Basis of Valuation" rows="3"></textarea>
                    <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.basis_of_valuation"/>

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
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_rail" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_rail"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>b. Per any one road vehicle</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_road" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_road"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>c. Per any one air transit and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_air" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_air"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>d. Per any one registered post & or courier</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_post" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_post"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>e. Per any one vessel and connecting conveyance</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_vessel" placeholder="Amount" />
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.per_conveyance_vessel"/>
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
                    <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.deductible" placeholder="Deductible details" rows="3"></textarea>
                    <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.deductible"/>

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
                                        <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.notice_of_cancellation" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.notice_of_cancellation"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Refund</strong>
                                    </td>
                                    <td>
                                        <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.refund" rows="3"></textarea>
                                        <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.refund"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Over Declaration --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Over Declaration</h3>
                </div>
                <div class="card-body">
                    <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.over_declaration" placeholder="Over Declaration" rows="5"></textarea>
                    <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.over_declaration"/>
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
                                <input type="file" class="form-control" wire:model="marineCargoOpenPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($marineCargoOpenPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $marineCargoOpenPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="marineCargoOpenPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $marineCargoOpen[$policyCoverage->id]['policy_wording_path'] ?? null;
                            $policyWordingFilename = $marineCargoOpen[$policyCoverage->id]['policy_wording_filename'] ?? null;
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
                    <textarea class="form-control" wire:model.defer="marineCargoOpen.{{ $policyCoverage->id }}.notes" placeholder="Additional notes" rows="4"></textarea>
                    <x-form-input-error name="marineCargoOpen.{{ $policyCoverage->id }}.notes"/>
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
                <button type="button" class="btn btn-primary" wire:click="$emit('saveMarineCargoOpenCoverage', {{ $policyCoverage->id }})">
                    <i class="fa fa-save"></i> Save Marine Cargo Open Policy
                </button>
            </div>
        </div>
    </div>

</div>
