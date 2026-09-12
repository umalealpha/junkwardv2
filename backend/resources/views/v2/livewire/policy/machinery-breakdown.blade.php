{{-- Machinery Breakdown Coverage --}}
<div class="machinery-breakdown-section">
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
                                        <x-form-input type="text" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.policy_number" placeholder="Policy Number" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured Name:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.company_name" placeholder="Insured Name" value="{{ $machineryBreakdown[$policyCoverage->id]['company_name'] ?? '' }}" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Company Address:</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.company_address" placeholder="Company Address" value="{{ $machineryBreakdown[$policyCoverage->id]['company_address'] ?? '' }}" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Inception Date</strong>
                                    </td>
                                    <td>
                                        <div class="row align-items-center">
                                            <div class="col-sm-6">
                                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="machineryBreakdown.{{ $policyCoverage->id }}.inception_date" placeholder="Inception Date"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Expiry Date</strong>
                                    </td>
                                    <td>
                                        <div class="row align-items-center">
                                            <div class="col-sm-6">
                                                <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="machineryBreakdown.{{ $policyCoverage->id }}.expiry_date" placeholder="Expiry Date"/>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Today's Date</strong>
                                    </td>
                                    <td>
                                        <x-form-dateCARPAREAR type="text" class="kt_datepicker_1 form-control" wire:model="machineryBreakdown.{{ $policyCoverage->id }}.today_date" placeholder="Today's date"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Renewable Policy</strong>
                                    </td>
                                    <td>
                                        <select class="form-select" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.is_renewable">
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
                                        <x-form-input type="text" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.currency" placeholder="Currency" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Premium</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.premium" placeholder="Premium" readonly/>
                                    </td>
                                </tr>
                                <!-- <tr>
                                    <td style="vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Sum Insured</strong>
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.sum_insured" placeholder="Sum Insured" />
                                    </td>
                                </tr> -->
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
                                    $section1Items = $machineryBreakdownSection1Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section1Items as $index => $item)
                                <tr wire:key="mb-s1-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownSection1Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Coverage Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownSection1Items.{{ $policyCoverage->id }}.{{ $index }}.limit_status" placeholder="Limit / Status" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownSection1Items.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" wire:change="recalculateMachineryBreakdownPremium({{ $policyCoverage->id }})"/>
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
                                    $machineryItems = $machineryBreakdownMachineryListing[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($machineryItems as $index => $item)
                                <tr wire:key="mb-ml-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.item_no" placeholder="#" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.quantity" placeholder="Qty" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.description" placeholder="Description" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.year_of_manufacture" placeholder="Year" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.sum_insured" placeholder="Sum Insured" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control " wire:model.defer="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.deductible" placeholder="Deductible" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.lazy="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownMachineryItem({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- <tr wire:key="mb-ml-chk-{{ $policyCoverage->id }}-{{ $index }}" style="border-bottom: 2px solid #000 !important;">
                                    <td colspan="9">
                                        <div class="d-flex flex-wrap gap-4 ps-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="damage_insured_{{ $policyCoverage->id }}_{{ $index }}" wire:model="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.damage_to_insured_property" />
                                                <label class="form-check-label" for="damage_insured_{{ $policyCoverage->id }}_{{ $index }}">Damage to insured property (per occurrence)</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="cost_replacing_{{ $policyCoverage->id }}_{{ $index }}" wire:model="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.cost_replacing_non_compatible_parts" />
                                                <label class="form-check-label" for="cost_replacing_{{ $policyCoverage->id }}_{{ $index }}">Cost of replacing undamaged non-compatible parts</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="total_insured_{{ $policyCoverage->id }}_{{ $index }}" wire:model="machineryBreakdownMachineryListing.{{ $policyCoverage->id }}.{{ $index }}.total_insured_value" />
                                                <label class="form-check-label" for="total_insured_{{ $policyCoverage->id }}_{{ $index }}">Total insured value</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>                             -->
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownMachineryItem({{ $policyCoverage->id }})">
                            <i class="fa fa-plus"></i> Add Machinery Item
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Extra Cover - Section 1 --}}
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
                                    $extraCoverS1 = $machineryBreakdownExtraCoverSection1[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($extraCoverS1 as $index => $item)
                                <tr wire:key="mb-ec1-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownExtraCoverSection1.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownExtraCoverSection1.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownExtraCoverSection1({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownExtraCoverSection1({{ $policyCoverage->id }})">
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
                                    $section2Items = $machineryBreakdownSection2Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section2Items as $index => $item)
                                <tr wire:key="mb-s2-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownSection2Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="machineryBreakdownSection2Items.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Sum Insured" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.lazy="machineryBreakdownSection2Items.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.lazy="machineryBreakdownSection2Items.{{ $policyCoverage->id }}.{{ $index }}.premium" placeholder="Premium" />
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Extra Cover - Section 2 --}}
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
                                        $extraCoverS2 = $machineryBreakdownExtraCoverSection2[$policyCoverage->id] ?? [];
                                    @endphp
                                    @foreach($extraCoverS2 as $index => $item)
                                    <tr wire:key="mb-ec2-{{ $policyCoverage->id }}-{{ $index }}">
                                        <td>
                                            <input type="text" class="form-control" wire:model.defer="machineryBreakdownExtraCoverSection2.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownExtraCoverSection2.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownExtraCoverSection2({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownExtraCoverSection2({{ $policyCoverage->id }})">
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
                                    $section3Items = $machineryBreakdownSection3Items[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($section3Items as $index => $item)
                                <tr wire:key="mb-s3-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownSection3Items.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Item" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownSection3Items.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Gross Profit" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownSection3Items.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownSection3Items.{{ $policyCoverage->id }}.{{ $index }}.premium" wire:change="recalculateMachineryBreakdownPremium({{ $policyCoverage->id }})" placeholder="Premium" />
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Extra Cover - Section 3 --}}
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
                                        $extraCoverS3 = $machineryBreakdownExtraCoverSection3[$policyCoverage->id] ?? [];
                                    @endphp
                                    @foreach($extraCoverS3 as $index => $item)
                                    <tr wire:key="mb-ec3-{{ $policyCoverage->id }}-{{ $index }}">
                                        <td>
                                            <input type="text" class="form-control" wire:model.defer="machineryBreakdownExtraCoverSection3.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                        </td>
                                        <td>
                                            <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownExtraCoverSection3.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownExtraCoverSection3({{ $policyCoverage->id }}, {{ $index }})">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownExtraCoverSection3({{ $policyCoverage->id }})">
                                <i class="fa fa-plus"></i> Add Extension Cover
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- EXTRA COVER - Applying to All Sections --}}
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
                                    $extraCoverAll = $machineryBreakdownExtraCoverAllSections[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($extraCoverAll as $index => $item)
                                <tr wire:key="mb-eca-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownExtraCoverAllSections.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Cover Name" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownExtraCoverAllSections.{{ $policyCoverage->id }}.{{ $index }}.limit" placeholder="Limit" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownExtraCoverAllSections({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownExtraCoverAllSections({{ $policyCoverage->id }})">
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
                                    $excessDetails = $machineryBreakdownExcessDetails[$policyCoverage->id] ?? [];
                                @endphp
                                @foreach($excessDetails as $index => $item)
                                <tr wire:key="mb-ed-{{ $policyCoverage->id }}-{{ $index }}">
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.name" placeholder="Description" />
                                    </td>
                                    <td>
                                        <x-form-input type="text" class="form-control amount-field" wire:model.defer="machineryBreakdownExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.value" placeholder="Minimum Excess" />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" wire:model.defer="machineryBreakdownExcessDetails.{{ $policyCoverage->id }}.{{ $index }}.rate" placeholder="%" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeMachineryBreakdownExcessDetail({{ $policyCoverage->id }}, {{ $index }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-info btn-sm" wire:click="addMachineryBreakdownExcessDetail({{ $policyCoverage->id }})">
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
                    <x-form-text-area wire:model.defer="machineryBreakdownEndorsements.{{ $policyCoverage->id }}" class="h-100" style="height: 15em !important;" placeholder="Enter endorsements..."/>
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
                                <input type="file" class="form-control" wire:model="machineryBreakdownPolicyWording" accept=".pdf">
                                <small class="form-text text-muted">Accepted formats: PDF only</small>
                                @if($machineryBreakdownPolicyWording)
                                    <div class="mt-2">
                                        <small class="text-success">File selected: {{ $machineryBreakdownPolicyWording->getClientOriginalName() }}</small>
                                    </div>
                                @endif
                                <x-form-input-error name="machineryBreakdownPolicyWording"/>
                            </div>
                        </div>
                        @php
                            $policyWordingPath = $machineryBreakdown[$policyCoverage->id]['policy_wording_path'] ?? null;
                            $policyWordingFilename = $machineryBreakdown[$policyCoverage->id]['policy_wording_filename'] ?? null;
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
                    <x-form-text-area wire:model.defer="machineryBreakdown.{{ $policyCoverage->id }}.notes" class="h-100" style="height: 15em !important;"/>
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
    <div class="row mb-4">
        <div class="col-sm-12 text-end">
            <button type="button" class="btn btn-primary btn-lg" wire:click="$emit('saveMachineryBreakdownCoverage', {{ $policyCoverage->id }})">
                <i class="fa fa-save"></i> Save Machinery Breakdown Coverage
            </button>
        </div>
    </div>
</div>
