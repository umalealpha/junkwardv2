{{-- Marine Directors & Officers Coverage --}}
<div class="marine-directors-officers-section">
    {{-- Policy Schedule --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">Policy Schedule</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 25%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Policy No:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured Name:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.company_name" placeholder="Insured Name" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Company Address:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.company_address" placeholder="Company Address" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Inception Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.inception_date" placeholder="Inception Date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Expiry Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.expiry_date" placeholder="Expiry Date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's Date</strong>
                                    </td>
                                    <td>
                                        <x-form-date type="text" class="kt_datepicker_1 form-control" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Renewable Policy</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.is_renewable">
                                            <option value="">- Select -</option>
                                            <option value="Yes">Yes - Renewable Policy</option>
                                            <option value="No">No</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Currency</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.currency" placeholder="Currency" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.premium" placeholder="Premium" readonly/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 1 - Equipment Damage and Breakdown (Specified Cover Basis) --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">SECTION 1 &ndash; Equipment Damage and Breakdown (Specified Cover Basis)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50%">Coverage Item / Description</th>
                                    <th style="width: 30%">Limit / Status</th>
                                    <th style="width: 20%">Premium</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section1Items = $marineDirectorsOfficersSection1Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section1Items as $index => $item)
                                <tr wire:key="mdo-s1-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersSection1Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Coverage Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersSection1Items.{{ $policyCoverage->id }}.{{ $index }}.limit_status" placeholder="Limit / Status" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" wire:change="recalculateMarineDirectorsOfficersPremium({{ $policyCoverage->id }})"/>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Machinery Listing --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #2196F3; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">Machinery Listing</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%">Item No</th>
                                    <th style="width: 8%">Quantity</th>
                                    <th style="width: 25%">Description of items (type, manufacturer, capacity)</th>
                                    <th style="width: 12%">Year of manufacture</th>
                                    <th style="width: 13%">Sum Insured</th>
                                    <th style="width: 10%">Deductible</th>
                                    <th style="width: 8%">Rate %</th>
                                    <th style="width: 13%">Premium</th>
                                    <th style="width: 6%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $machineryItems = $marineDirectorsOfficersInsuredPersonsListing[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($machineryItems as $index => $item)
                                <tr wire:key="mdo-ml-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.item_no" placeholder="#" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.quantity" placeholder="Qty" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Description" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.year_of_manufacture" placeholder="Year" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" placeholder="Sum Insured" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.lazy="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineDirectorsOfficersInsuredPersonsListing.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersMachineryItem({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersMachineryItem({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Machinery Item
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Extensions - Section 1 --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #2196F3; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">Extensions &ndash; Section 1 (limits per occurrence, on top of limit of liability)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 55%">Cover</th>
                                    <th style="width: 35%">Limit</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $extraCoverS1 = $marineDirectorsOfficersExtraCoverSection1[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($extraCoverS1 as $index => $item)
                                <tr wire:key="mdo-ec1-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExtraCoverSection1.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersExtraCoverSection1.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersExtraCoverSection1({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersExtraCoverSection1({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Extension Cover
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2 - Deterioration of Stock --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">SECTION 2 &ndash; Deterioration of Stock</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%">Item</th>
                                    <th style="width: 25%">Sum Insured</th>
                                    <th style="width: 15%">Rate %</th>
                                    <th style="width: 20%">Premium</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section2Items = $marineDirectorsOfficersSection2Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section2Items as $index => $item)
                                <tr wire:key="mdo-s2-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersSection2Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineDirectorsOfficersSection2Items.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Sum Insured" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.lazy="marineDirectorsOfficersSection2Items.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="marineDirectorsOfficersSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" />
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Extensions - Section 2 --}}
                    <div class="mt-4">
                        <h5 class="text-center p-2" style="background-color: #2196F3; color: #fff;">Extensions &ndash; Section 2</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 55%">Cover</th>
                                        <th style="width: 35%">Limit</th>
                                        <th style="width: 10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $extraCoverS2 = $marineDirectorsOfficersExtraCoverSection2[$policyCoverage->id] ?? [];
                                    @endphp
                                    @foreach($extraCoverS2 as $index => $item)
                                    <tr wire:key="mdo-ec2-{{ $policyCoverage->id }}-{{ $index }}">
                                        <td>
                                            <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExtraCoverSection2.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersExtraCoverSection2.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersExtraCoverSection2({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersExtraCoverSection2({{ $policyCoverage->id }})">
                                <i class="fa fa-plus"></i> Add Extension Cover
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 3 - Loss of Income --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">SECTION 3 &ndash; Loss of Income</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%">Item</th>
                                    <th style="width: 25%">Gross Profit</th>
                                    <th style="width: 15%">Rate %</th>
                                    <th style="width: 20%">Premium</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $section3Items = $marineDirectorsOfficersSection3Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section3Items as $index => $item)
                                <tr wire:key="mdo-s3-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersSection3Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersSection3Items.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Gross Profit" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersSection3Items.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersSection3Items.{{ $policyCoverage->id }}.{{ $index }}.premium" wire:change="recalculateMarineDirectorsOfficersPremium({{ $policyCoverage->id }})" placeholder="Premium" />
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Extensions - Section 3 --}}
                    <div class="mt-4">
                        <h5 class="text-center p-2" style="background-color: #2196F3; color: #fff;">Extensions &ndash; Section 3</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 55%">Cover</th>
                                        <th style="width: 35%">Limit</th>
                                        <th style="width: 10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $extraCoverS3 = $marineDirectorsOfficersExtraCoverSection3[$policyCoverage->id] ?? [];
                                    @endphp
                                    @foreach($extraCoverS3 as $index => $item)
                                    <tr wire:key="mdo-ec3-{{ $policyCoverage->id }}-{{ $index }}">
                                        <td>
                                            <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExtraCoverSection3.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersExtraCoverSection3.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersExtraCoverSection3({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersExtraCoverSection3({{ $policyCoverage->id }})">
                                <i class="fa fa-plus"></i> Add Extension Cover
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Extension - Applying to All Sections --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">Extension &ndash; Applying to All Sections</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 55%">Cover</th>
                                    <th style="width: 35%">Limit</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $extraCoverAll = $marineDirectorsOfficersExtraCoverAllSections[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($extraCoverAll as $index => $item)
                                <tr wire:key="mdo-eca-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExtraCoverAllSections.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersExtraCoverAllSections.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersExtraCoverAllSections({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersExtraCoverAllSections({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Extension Cover
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- EXCESS DETAILS --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">EXCESS DETAILS</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%">Description</th>
                                    <th style="width: 25%">Minimum Excess</th>
                                    <th style="width: 20%">Rate %</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $excessDetails = $marineDirectorsOfficersExcessDetails[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($excessDetails as $index => $item)
                                <tr wire:key="mdo-ed-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Description" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Minimum Excess" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="marineDirectorsOfficersExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMarineDirectorsOfficersExcessDetail({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMarineDirectorsOfficersExcessDetail({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Excess Detail
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ENDORSEMENTS --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header" style="background-color: #003366; color: #fff;">
                    <h3 class="card-title" style="color: #fff;">ENDORSEMENTS</h3>
                </div>
                <div class="card-body">
                    <x-form-text-area wire:model.defer="marineDirectorsOfficersEndorsements.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;" placeholder="Enter endorsements..."/>
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
                                <input type="file" class="form-control" wire:model="marineDirectorsOfficersPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($marineDirectorsOfficersPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $marineDirectorsOfficersPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="marineDirectorsOfficersPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $marineDirectorsOfficers[$policyCoverage->id]['policy_wording_path'] ?? null;
                            $policyWordingFilename = $marineDirectorsOfficers[$policyCoverage->id]['policy_wording_filename'] ?? null;
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
                    <x-form-text-area wire:model.defer="marineDirectorsOfficers.{{ $policyCoverage->id }}.notes" class="h-100" style="height: 15em !important;"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Miscellaneous Items --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Miscellaneous Items</h3>
                    <button type="button" class="btn btn-sm" style="background-color: #F4A623; color: #ffffff;" wire:click="addMarineDirectorsOfficersMiscItem({{ $policyCoverage->id }})">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>
                <div class="card-body">
                    @php
                        $miscItems = $marineDirectorsOfficersMiscItems[$policyCoverage->id] ?? [];
                    @endphp
                    @if (count($miscItems) > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Description</th>
                                        <th style="width: 25%;">Sum Insured</th>
                                        <th style="width: 25%;">Premium</th>
                                        <th style="width: 10%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($miscItems as $index => $item)
                                        <tr wire:key="mdo-misc-{{ $policyCoverage->id }}-{{ $index }}">
                                            <td>
                                                <x-form-input type="text" wire:model.defer="marineDirectorsOfficersMiscItems.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Description" />
                                            </td>
                                            <td>
                                                <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersMiscItems.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" placeholder="Sum Insured" />
                                            </td>
                                            <td>
                                                <x-form-input type="text" class="form-control amount-field" wire:model.defer="marineDirectorsOfficersMiscItems.{{ $policyCoverage->id }}.{{ $index }}.premium" wire:change="recalculateMarineDirectorsOfficersPremium({{ $policyCoverage->id }})" placeholder="Premium" />
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger" wire:click="removeMarineDirectorsOfficersMiscItem({{ $policyCoverage->id }}, {{ $index }})" title="Remove">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No miscellaneous items added. Click <strong>Add</strong> to insert a row.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="row mb-4">
        <div class="col-sm-12 text-end">
            <button type="button" class="btn btn-primary btn-lg" wire:click="$emit('saveMarineDirectorsOfficersCoverage', {{ $policyCoverage->id }})">
                <i class="fa fa-save"></i> Save Marine Directors &amp; Officers Coverage
            </button>
        </div>
    </div>
</div>
