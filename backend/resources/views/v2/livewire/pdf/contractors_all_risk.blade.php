@php
                $section1Raw = $coverages->carCoverage?->section1_items;
                $carSection1Items = is_array($section1Raw) ? $section1Raw : (json_decode($section1Raw ?? '[]', true) ?? []);
                if (is_string($carSection1Items)) $carSection1Items = json_decode($carSection1Items, true) ?? [];

                $section2Raw = $coverages->carCoverage?->section2_items;
                $carSection2Items = is_array($section2Raw) ? $section2Raw : (json_decode($section2Raw ?? '[]', true) ?? []);
                if (is_string($carSection2Items)) $carSection2Items = json_decode($carSection2Items, true) ?? [];

                $section3Raw = $coverages->carCoverage?->section3_items;
                $carSection3Items = is_array($section3Raw) ? $section3Raw : (json_decode($section3Raw ?? '[]', true) ?? []);
                if (is_string($carSection3Items)) $carSection3Items = json_decode($carSection3Items, true) ?? [];

                $plantListRaw = $coverages->carCoverage?->plant_list_items;
                $carPlantListItems = is_array($plantListRaw) ? $plantListRaw : (json_decode($plantListRaw ?? '[]', true) ?? []);
                if (is_string($carPlantListItems)) $carPlantListItems = json_decode($carPlantListItems, true) ?? [];
                            @endphp

                            {{-- CAR Policy Schedule --}}
                            <table>
                                <tr>
                                    <td colspan="4">
                                        <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">{{ $coverages->coverage->s_ScreenName ?? $coverages->coverage->s_CoverageCode }}</p>
                                    </td>
                                </tr>
                            <tr>
                                <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                    <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Policy Schedule</p>
                                </td>
                            </tr>
                            
                            {{-- General Policy Information --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Branch</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy No. (Auto-Generated)</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Currency</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Declaration No.</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->branch ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{!! $policy->policyNumber ?? '' !!}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->currency ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->declaration_no ?? 'N/A' }}</p>
                                </td>
                            </tr>
                            
                            {{-- Name and Address of Insured --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;font-size:12px;text-align:center">Name and Address of Insured</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Name</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Street</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->insured_name ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->insured_street ?? '' }}</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Postal Code And City</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Title Of Contract</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->insured_postal_code ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->title_of_contract ?? '' }}</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Name</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->project_name ?? '' }}</p>
                                </td>
                            </tr>
                            
                            {{-- Address of Risk --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;font-size:12px;text-align:center">Address of Risk</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Street</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Postal Code And City</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->risk_street ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->risk_postal_code ?? '' }}</p>
                                </td>
                            </tr>
                            
                            {{-- Policy Dates and Period --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy Inception Date</p>   
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy Expiry Date</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Today's Date</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">New/Altered</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->policy_inception_date))
                                            {{ safeFormatDate($coverages->carCoverage->policy_inception_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->policy_expiry_date))
                                            {{ safeFormatDate($coverages->carCoverage->policy_expiry_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->today_date))
                                            {{ safeFormatDate($coverages->carCoverage->today_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->new_altered ?? '' }}</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy Period</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Renewable Policy</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Specific Policy</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($coverages->carCoverage->policy_period_months))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">
                                        Allow up to {{ $coverages->carCoverage->policy_period_months }} months max
                                    </p>
                                    @endif
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->carCoverage->is_renewable) }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->carCoverage->is_project_specific) }}</p>
                                </td>
                            </tr>
                            @if(!empty($coverages->carCoverage->policy_period_months) && ($coverages->carCoverage->policy_period_months == 24 || $coverages->carCoverage->policy_period_months == 36))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Approved By</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Approved At</p>
                                </td>
                            </tr>
                            
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->approved_by))
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($coverages->carCoverage->approved_by);
                                            @endphp
                                            {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}
                                        @else
                                            Not Approved
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->approved_at))
                                            {{ fmtDate($coverages->carCoverage->approved_at, 'd/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </td>
                            </tr>
                            @endif
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Maintenance Period (Months After Expiry - Default: 12)</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">City, Town, Village Of Risk</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->maintenance_period_months ?? '12' }} months</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->city_town_village ?? '' }}</p>
                                </td>
                            </tr>

                            {{-- CAR Section 1 - Material Damage --}}
                            @if(count($carSection1Items) > 0 || count($carPlantListItems) > 0)
                                {{-- Section 1 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;text-align:center">Section 1 – Material damage</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Insured item</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Sum insured</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Deductible</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                
                                {{-- Section 1 Items --}}
                                @foreach($carSection1Items as $item)
                                    @if(!empty($item['item_type']) || !empty($item['description']) || !empty($item['sum_insured']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ ucfirst(strtolower($item['item_type'] ?? '')) }}
                                                @if(!empty($item['description']))
                                                   {{ $item['description'] }}
                                                @endif
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $sumInsured = formatNumericValue($item['sum_insured'] ?? ''); @endphp
                                                @if(!empty($sumInsured))
                                                    {{ $sumInsured }}
                                                @endif
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $item['deductible'] ?? '' }}
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $premium = formatNumericValue($item['premium'] ?? ''); @endphp
                                                @if(!empty($premium))
                                                    {{ $premium }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                               
                                    <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">List Of Plant</p>
                                    </td>
                                    </tr>
                                {{-- Plant List Items (part of Section 1) --}}
                                @foreach($carPlantListItems as $item)
                                    @if(!empty($item['description']) || !empty($item['sum_insured']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @if(!empty($item['item_no']))
                                                    {{ $item['item_no'] }}. 
                                                @endif
                                                {{ $item['description'] ?? '' }}
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $sumInsured = formatNumericValue($item['sum_insured'] ?? ''); @endphp
                                                @if(!empty($sumInsured))
                                                    {{ $sumInsured }}
                                                @endif
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">-</p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $premium = formatNumericValue($item['premium'] ?? ''); @endphp
                                                @if(!empty($premium))
                                                    {{ $premium }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                
                                {{-- Total Sum Insured and Premium under Section 1 --}}
                                @php
                                    $totalSumInsured = 0;
                                    $totalPremium = 0;
                                    // Calculate from Section 1 items
                                    foreach ($carSection1Items as $item) {

                                        if (isset($item['sum_insured']) && $item['sum_insured'] !== '') {
                                            $totalSumInsured += parseNumericValue($item['sum_insured']);
                                        }

                                        if (isset($item['premium']) && $item['premium'] !== '') {
                                            $totalPremium += parseNumericValue($item['premium']);
                                        }
                                    }

                                    // Calculate from Plant List items
                                    
                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalSumInsured = round($totalSumInsured, 2);
                                    $totalPremium = round($totalPremium, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Total sum insured under Section 1</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalSumInsured, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremium, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif

                            {{-- CAR Risk Coverage (Sub-table under Section 1) --}}
                            @if(!empty($coverages->carCoverage->section1_earthquake_premium) || !empty($coverages->carCoverage->section1_storm_premium) || 
                                !empty($coverages->carCoverage->section1_risk_earthquake) || !empty($coverages->carCoverage->section1_risk_storm))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Risk</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Limit of indemnity</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Deductible</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                @if(!empty($coverages->carCoverage->section1_risk_earthquake) && $coverages->carCoverage->section1_risk_earthquake == 'Yes')
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="40%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">Earthquake, volcanism, tsunami</p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $limit = formatNumericValue($coverages->carCoverage->section1_earthquake_limit_indemnity ?? ''); @endphp
                                            @if(!empty($limit))
                                                {{ $limit }}
                                            @else
                                                Yes
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->carCoverage->section1_earthquake_deductible ?? '' }}
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->carCoverage->section1_earthquake_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                @if(!empty($coverages->carCoverage->section1_risk_storm) && $coverages->carCoverage->section1_risk_storm == 'Yes')
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="40%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">Storm, cyclone, flood, inundation, landslide</p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $limit = formatNumericValue($coverages->carCoverage->section1_storm_limit_indemnity ?? ''); @endphp
                                            @if(!empty($limit))
                                                {{ $limit }}
                                            @else
                                                Yes
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->carCoverage->section1_storm_deductible ?? '' }}
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->carCoverage->section1_storm_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                            @endif

                            {{-- CAR Section 2 - Third Party Liability --}}
                            @if(count($carSection2Items) > 0)
                                {{-- Section 2 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;text-align:center">Section 2 – Third party liability</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Insured item</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Limit of indemnity</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Deductible</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                @foreach($carSection2Items as $item)
                                    @if(!empty($item['item_type']) || !empty($item['description']) || !empty($item['limit_of_indemnity']))
                                    @php
                                        $itemDescription = $item['description'] ?? '';
                                        $itemType = $item['item_type'] ?? '';
                                    @endphp
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @if(stripos($itemType, 'Third party liability') !== false || stripos($itemDescription, 'Third party liability') !== false)
                                                    Third party liability total
                                                @else
                                                    @if(!empty($itemType))
                                                        {{ ucfirst(strtolower($itemType)) }}
                                                       
                                                    @elseif(!empty($itemDescription))
                                                        {{ ucfirst(strtolower($itemDescription)) }}
                                                    @endif
                                                @endif
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $limit = formatNumericValue($item['limit_of_indemnity'] ?? ''); @endphp
                                                @if(!empty($limit))
                                                    {{ $limit }}
                                                @endif
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $item['deductible'] ?? '' }}
                                            </p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $premium = formatNumericValue($item['premium'] ?? ''); @endphp
                                                @if(!empty($premium))
                                                    {{ $premium }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                
                                {{-- Total Limit of Indemnity and Premium under Section 2 --}}
                                @php
                                    $totalLimitIndemnity = 0;
                                    $totalPremiumSection2 = 0;
                                    foreach($carSection2Items as $item) {
                                        $limitOfIndemnity = $item['limit_of_indemnity'] ?? '';
                                        if (!empty($limitOfIndemnity) || $limitOfIndemnity === '0' || $limitOfIndemnity === 0) {
                                            $limitValue = parseNumericValue($limitOfIndemnity);
                                            $totalLimitIndemnity += $limitValue;
                                        }
                                        $premium = $item['premium'] ?? '';
                                        if (!empty($premium) || $premium === '0' || $premium === 0) {
                                            $premValue = parseNumericValue($premium);
                                            $totalPremiumSection2 += $premValue;
                                        }
                                    }
                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalLimitIndemnity = round($totalLimitIndemnity, 2);
                                    $totalPremiumSection2 = round($totalPremiumSection2, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Total limit of indemnity under Section 2</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalLimitIndemnity, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremiumSection2, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif

                            {{-- CAR Section 3 - Principal's Loss of Profits --}}
                            @if(!empty($coverages->carCoverage->section3_gross_profit_annual_sum_insured) || !empty($coverages->carCoverage->section3_increased_cost_sum_insured) || count($carSection3Items) > 0)
                                {{-- Section 3 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;text-align:center;">Section 3 – Principal's loss of profits</p>
                                    </td>
                                </tr>
                                
                                {{-- Gross Profit Section --}}
                                @if(!empty($coverages->carCoverage->section3_gross_profit_annual_sum_insured))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Insured interest</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Annual sum Insured</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Rate %</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="30%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            Gross profit
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $annualSum = formatNumericValue($coverages->carCoverage->section3_gross_profit_annual_sum_insured ?? ''); @endphp
                                            @if(!empty($annualSum))
                                                {{ $annualSum }}
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->carCoverage->section3_gross_profit_rate ?? '' }}
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->carCoverage->section3_gross_profit_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                
                                {{-- Increased Cost of Working Section --}}
                                @if(!empty($coverages->carCoverage->section3_increased_cost_sum_insured))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Insured interest</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Sum insured for maximum Indemnity period</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Rate %</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="30%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            Increased cost of working
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $sumInsured = formatNumericValue($coverages->carCoverage->section3_increased_cost_sum_insured ?? ''); @endphp
                                            @if(!empty($sumInsured))
                                                {{ $sumInsured }}
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->carCoverage->section3_increased_cost_rate ?? '' }}
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->carCoverage->section3_increased_cost_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                @foreach($carSection3Items as $item)
                                    @if(!empty($item['description']) || !empty($item['annual_sum_insured']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="30%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ ucfirst(strtolower($item['description'] ?? '')) }}
                                            </p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $annualSum = formatNumericValue($item['annual_sum_insured'] ?? ''); @endphp
                                                @if(!empty($annualSum))
                                                    {{ $annualSum }}
                                                @endif
                                            </p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">-</p>
                                        </td>
                                        <td width="20%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @php $premium = formatNumericValue($item['premium'] ?? ''); @endphp
                                                @if(!empty($premium))
                                                    {{ $premium }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach

                                {{-- Period of Insurance & Indemnity --}}
                                @if(!empty($coverages->carCoverage->section3_period_insurance_from) || !empty($coverages->carCoverage->section3_period_insurance_to) || !empty($coverages->carCoverage->section3_maximum_indemnity) || !empty($coverages->carCoverage->section3_time_excess))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Period of insurance from</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">to</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Maximum Indemnity</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Time excess (months)</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(!empty($coverages->carCoverage->section3_period_insurance_from))
                                                {{ safeFormatDate($coverages->carCoverage->section3_period_insurance_from) }}
                                            @endif
                                        </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(!empty($coverages->carCoverage->section3_period_insurance_to))
                                                {{ safeFormatDate($coverages->carCoverage->section3_period_insurance_to) }}
                                            @endif
                                        </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(!empty($coverages->carCoverage->section3_maximum_indemnity))
                                                @php $maxIndemnity = formatNumericValue($coverages->carCoverage->section3_maximum_indemnity ?? ''); @endphp
                                                {{ $maxIndemnity }}
                                            @endif
                                        </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(!empty($coverages->carCoverage->section3_time_excess))
                                                {{ $coverages->carCoverage->section3_time_excess }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                
                                {{-- Limits of Indemnity --}}
                                @if(!empty($coverages->carCoverage->section3_limit_indemnity_each_loss))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="30%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Limit of indemnity in respect of each and every loss or damage and/or series of losses arising out of any one event.</p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $limit = formatNumericValue($coverages->carCoverage->section3_limit_indemnity_each_loss ?? ''); @endphp
                                            @if(!empty($limit))
                                                {{ $limit }}
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2"></td>
                                </tr>
                                @endif
                                
                                {{-- Scheduled Dates --}}
                                @if(!empty($coverages->carCoverage->section3_scheduled_date_completion))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="30%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Scheduled date of completion.</p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ safeFormatDate($coverages->carCoverage->section3_scheduled_date_completion) }}
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2"></td>
                                </tr>
                                @endif
                                
                                @if(!empty($coverages->carCoverage->section3_scheduled_date_commencement))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="30%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">Scheduled date of commencement of insured business, but not earlier than the scheduled date of completion.</p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ safeFormatDate($coverages->carCoverage->section3_scheduled_date_commencement) }}
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2"></td>
                                </tr>
                                @endif
                                
                                {{-- Contract works insured under section 3 --}}
                                @php
                                    $carSection3ContractWorks = is_array($coverages->carCoverage->section3_contract_works) 
                                        ? $coverages->carCoverage->section3_contract_works 
                                        : (json_decode($coverages->carCoverage->section3_contract_works ?? '[]', true) ?? []);
                                @endphp
                                @if(count($carSection3ContractWorks) > 0)
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Contract works insured under section 3</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Item No.</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Description of items</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Possible loss minimization</p>
                                    </td>
                                </tr>
                                @foreach($carSection3ContractWorks as $index => $item)
                                    @if(!empty($item['description']) || !empty($item['loss_minimization']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $index + 1 }}
                                            </p>
                                        </td>
                                        <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $item['description'] ?? '' }}
                                            </p>
                                        </td>
                                        <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $item['loss_minimization'] ?? '' }}
                                            </p>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                @endif
                            @endif

                            {{-- CAR Endorsements/Extension --}}
                            @php
                                // Load endorsements from JSON in endorsement_1 (same logic as ManageCoverages.php)
                                $carEndorsements = [];
                                if (!empty($coverages->carCoverage->endorsement_1)) {
                                    $decoded = json_decode($coverages->carCoverage->endorsement_1, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                                        // Valid JSON array - extract text from each item
                                        foreach($decoded as $item) {
                                            if (is_array($item) && isset($item['text']) && !empty(trim($item['text']))) {
                                                $carEndorsements[] = trim($item['text']);
                                            } elseif (is_string($item) && !empty(trim($item))) {
                                                $carEndorsements[] = trim($item);
                                            }
                                        }
                                    } else {
                                        // Not JSON - backward compatibility: load from individual columns
                                        if (!empty($coverages->carCoverage->endorsement_1)) {
                                            $carEndorsements[] = trim($coverages->carCoverage->endorsement_1);
                                        }
                                        if (!empty($coverages->carCoverage->endorsement_2)) {
                                            $carEndorsements[] = trim($coverages->carCoverage->endorsement_2);
                                        }
                                        if (!empty($coverages->carCoverage->endorsement_3)) {
                                            $carEndorsements[] = trim($coverages->carCoverage->endorsement_3);
                                        }
                                        if (!empty($coverages->carCoverage->endorsement_4)) {
                                            // Check if endorsement_4 contains concatenated endorsements (separated by ';')
                                            $endorsement4Text = $coverages->carCoverage->endorsement_4;
                                            if (strpos($endorsement4Text, ';') !== false) {
                                                // Split by semicolon and add each as separate endorsement
                                                $parts = explode(';', $endorsement4Text);
                                                foreach ($parts as $part) {
                                                    $part = trim($part);
                                                    if (!empty($part)) {
                                                        $carEndorsements[] = $part;
                                                    }
                                                }
                                            } else {
                                                $carEndorsements[] = trim($endorsement4Text);
                                            }
                                        }
                                    }
                                }
                                // Filter out empty endorsements and re-index to ensure sequential numbering
                                $carEndorsements = array_values(array_filter($carEndorsements, function($text) {
                                    return !empty(trim($text));
                                }));
                            @endphp
                            @if(count($carEndorsements) > 0)
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 5px;color:black;font-weight:bold;text-align:center;font-size:11px">Endorsements/Extension</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color:black;font-weight:bold;">The following endorsements are attached to and forming part of this Policy:</p>
                                    </p>
                                </td>
                            </tr>
                            @foreach($carEndorsements as $index => $endorsementText)
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" width="5%">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $index + 1 }}.</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $endorsementText }}</p>
                                </td>
                            </tr>
                            @endforeach
                            @endif

                            {{-- CAR Execution Details --}}
                            @if(!empty($coverages->carCoverage->executed_at) || !empty($coverages->carCoverage->execution_date) || !empty($coverages->carCoverage->signature))
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <strong>In witness whereof the undersigned being duly authorized by the insurers and on behalf of the insurers has (have) hereunto set his (their) hand(s)</strong>
                                    </p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Executed at</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Date</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Signature</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->carCoverage->executed_at ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->carCoverage->execution_date))
                                            {{ safeFormatDate($coverages->carCoverage->execution_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @php
                                            $customerFullName = '';
                                            if(!empty($coverages->carCoverage->signature)){
                                                $customerFullName = $coverages->carCoverage->signature;
                                            }
                                            else if($policy->profile->entity_type == 'Organisation') {
                                                $customerFullName = $policy->profile->company->name ?? '';
                                            } else {
                                                $firstName = $policy->customer->firstName ?? '';
                                                $middleName = $policy->customer->middleName ?? '';
                                                $lastName = $policy->customer->lastName ?? '';
                                                $customerFullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                                            }
                                        @endphp
                                        {{ $customerFullName }}
                                    </p>
                                </td>
                            </tr>
                            @endif

                            {{-- CAR Notes --}}
                            @if(!empty($coverages->carCoverage->additional_notes))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Notes</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line; margin:0;">
                                        {{ $coverages->carCoverage->additional_notes }}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif
                            </table>

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Subtotal reconciles with the misc premium folded into the CAR coverage
     total/grand total in v2-quote-sheet-engineering.blade.php. --}}
@php
    $miscPolicyCoverageKey = $coverages->id ?? null;
    $miscSpecifiedItems = !empty($miscPolicyCoverageKey)
        ? \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
            ->where('policy_coverage_id', $miscPolicyCoverageKey)
            ->whereNull('deleted_at')
            ->get()
        : collect();
@endphp
@if($miscSpecifiedItems->isNotEmpty())
    @php $miscSubtotal = 0; @endphp
    <table style="margin-top: 6px;">
        <tr class="section-subtitle">
            <td colspan="4"><strong>Miscellaneous Items</strong></td>
        </tr>
        <tr>
            <td style="width: 46%; font-weight: bold;">Description Of Items</td>
            <td style="width: 22%; font-weight: bold;">Sum Insured</td>
            <td style="width: 12%; font-weight: bold;">Rate %</td>
            <td style="width: 20%; font-weight: bold;">Premium</td>
        </tr>
        @foreach($miscSpecifiedItems as $specifiedItem)
            @php
                $miscSi   = (float) str_replace(',', '', (string) ($specifiedItem->sum_insured ?? 0));
                $miscPrem = (float) str_replace(',', '', (string) ($specifiedItem->calculated_value ?? 0));
                $miscSubtotal += $miscPrem;
            @endphp
            <tr>
                <td>{{ $specifiedItem->specifiedCoveragesItems->specified_name ?? '' }}</td>
                <td>{{ number_format($miscSi, 2) }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ number_format($miscPrem, 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ number_format($miscSubtotal, 2) }}</td>
        </tr>
    </table>
@endif
