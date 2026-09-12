{{-- EAR Coverage Details --}}
                       
                            @php
                            
                                $section1Raw = $coverages->earCoverage?->section1_items;
                                $earSection1Items = is_array($section1Raw) ? $section1Raw : (json_decode($section1Raw ?? '[]', true) ?? []);
                                if (is_string($earSection1Items)) $earSection1Items = json_decode($earSection1Items, true) ?? [];

                                $section3Raw = $coverages->earCoverage?->section3_items;
                                $earSection3Items = is_array($section3Raw) ? $section3Raw : (json_decode($section3Raw ?? '[]', true) ?? []);
                                if (is_string($earSection3Items)) $earSection3Items = json_decode($earSection3Items, true) ?? [];
                            @endphp
                            <table>
                                <tr>
                                    <td colspan="4">
                                        <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">{{ $coverages->coverage->s_ScreenName ?? $coverages->coverage->s_CoverageCode }}</p>
                                    </td>
                                </tr>
                            {{-- EAR Policy Schedule --}}
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Policy Schedule</p>
                                </td>
                            </tr>
                            
                            {{-- Name of Insured --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Name of Insured</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->name_of_insured ?? '' }}</p>
                                </td>
                            </tr>
                            
                            {{-- Site of Erection --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Site of Erection</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->site_of_erection ?? '' }}</p>
                                </td>
                            </tr>
                            
                            {{-- Project Name and Policy Options --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Name</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy Period</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->project_name ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($coverages->earCoverage->policy_period_months))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">
                                        Allow up to {{ $coverages->earCoverage->policy_period_months }} months max
                                    </p>
                                    @endif
                                </td>
                            </tr>
                            @if(!empty($coverages->earCoverage->policy_period_months) && ($coverages->earCoverage->policy_period_months == 24 || $coverages->earCoverage->policy_period_months == 36))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
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
                                        @if(!empty($coverages->earCoverage->approved_by))
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($coverages->earCoverage->approved_by);
                                            @endphp
                                            {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}
                                        @else
                                            Not Approved
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->earCoverage->approved_at))
                                            {{ fmtDate($coverages->earCoverage->approved_at, 'd/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </td>
                            </tr>
                            @endif
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Renewable Policy</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Specific Policy</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Maintenance Period (Months After Expiry - Default: 12)</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->earCoverage->is_renewable) }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->earCoverage->is_project_specific) }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->maintenance_period_months ?? '12' }} months</p>
                                </td>
                            </tr>
                            @if(!empty($coverages->earCoverage->reinsurance_fire_treaty))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Reinsurance to follow the fire treaty arrangements</p>
                                </td>
                            </tr>
                            @endif

                            {{-- EAR Section 1 - Material Damage --}}
                            @if(count($earSection1Items) > 0)
                                {{-- Section 1 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Section 1 – Material damage</p>
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
                                @foreach($earSection1Items as $item)
                                    @if(!empty($item['item_type']) || !empty($item['description']) || !empty($item['sum_insured']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ ucfirst(strtolower($item['item_type'] ?? '')) }}
                                                @if(!empty($item['description'])) {{ $item['description'] }}
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
                                
                                {{-- Total Sum Insured and Premium under Section 1 --}}
                                @php
                                    $totalSumInsuredEAR = 0;
                                    $totalPremiumEAR = 0;
                                    foreach ($earSection1Items as $item) {

                                        if (isset($item['sum_insured']) && $item['sum_insured'] !== '') {
                                            $totalSumInsuredEAR += parseNumericValue($item['sum_insured']);
                                        }

                                        if (isset($item['premium']) && $item['premium'] !== '') {
                                            $totalPremiumEAR += parseNumericValue($item['premium']);
                                        }
                                    }

                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalSumInsuredEAR = round($totalSumInsuredEAR, 2);
                                    $totalPremiumEAR = round($totalPremiumEAR, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;font-size:12px;">Total sum insured under Section 1</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalSumInsuredEAR, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremiumEAR, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif

                            {{-- EAR Section 2 - Risk Coverage --}}
                            @if(!empty($coverages->earCoverage->risk_earthquake_covered) || !empty($coverages->earCoverage->risk_storm_covered) || 
                                !empty($coverages->earCoverage->risk_earthquake_limit_indemnity) || !empty($coverages->earCoverage->risk_storm_limit_indemnity) ||
                                !empty($coverages->earCoverage->risk_earthquake_premium) || !empty($coverages->earCoverage->risk_storm_premium))
                                {{-- Section 2 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Section 2 – Risk Coverage</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;">Risk</p>
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
                                @if(!empty($coverages->earCoverage->risk_earthquake_covered) && $coverages->earCoverage->risk_earthquake_covered == 'Yes')
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="40%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Earthquake, volcanism, tsunami</p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $limit = formatNumericValue($coverages->earCoverage->risk_earthquake_limit_indemnity ?? ''); @endphp
                                            @if(!empty($limit))
                                                {{ $limit }}
                                            @else
                                                Yes
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->earCoverage->risk_earthquake_deductible ?? '' }}
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->earCoverage->risk_earthquake_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                @if(!empty($coverages->earCoverage->risk_storm_covered) && $coverages->earCoverage->risk_storm_covered == 'Yes')
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="40%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Storm, cyclone, flood, inundation, landslide</p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $limit = formatNumericValue($coverages->earCoverage->risk_storm_limit_indemnity ?? ''); @endphp
                                            @if(!empty($limit))
                                                {{ $limit }}
                                            @else
                                                Yes
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            {{ $coverages->earCoverage->risk_storm_deductible ?? '' }}
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @php $premium = formatNumericValue($coverages->earCoverage->risk_storm_premium ?? ''); @endphp
                                            @if(!empty($premium))
                                                {{ $premium }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                            @endif

                            {{-- EAR Section 3 - Third Party Liability --}}
                            @if(count($earSection3Items) > 0)
                                {{-- Section 3 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">
                                        @if(!empty($coverages->earCoverage->risk_earthquake_covered) || !empty($coverages->earCoverage->risk_storm_covered) || 
                                !empty($coverages->earCoverage->risk_earthquake_limit_indemnity) || !empty($coverages->earCoverage->risk_storm_limit_indemnity) ||
                                !empty($coverages->earCoverage->risk_earthquake_premium) || !empty($coverages->earCoverage->risk_storm_premium))
                                    Section 3 – Third party liability
                                    @else
                                        Section 2 – Third party liability
                                    @endif
                                    </p>
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
                                @foreach($earSection3Items as $item)
                                    @if(!empty($item['item_type']) || !empty($item['description']) || !empty($item['limit_of_indemnity']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ ucfirst(strtolower($item['item_type'] ?? '')) }}
                                                @if(!empty($item['description']))
                                                    <br>{{ $item['description'] }}
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
                                
                                {{-- Total Limit of Indemnity and Premium under Section 3 --}}
                                @php
                                    $totalLimitIndemnityEAR = 0;
                                    $totalPremiumSection3EAR = 0;
                                    foreach($earSection3Items as $item) {
                                        $limitOfIndemnity = $item['limit_of_indemnity'] ?? '';
                                        if (!empty($limitOfIndemnity) || $limitOfIndemnity === '0' || $limitOfIndemnity === 0) {
                                            $limitValue = parseNumericValue($limitOfIndemnity);
                                            $totalLimitIndemnityEAR += $limitValue;
                                        }
                                        $premium = $item['premium'] ?? '';
                                        if (!empty($premium) || $premium === '0' || $premium === 0) {
                                            $premValue = parseNumericValue($premium);
                                            $totalPremiumSection3EAR += $premValue;
                                        }
                                    }
                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalLimitIndemnityEAR = round($totalLimitIndemnityEAR, 2);
                                    $totalPremiumSection3EAR = round($totalPremiumSection3EAR, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;font-size:12px;">Total limit of indemnity under Section 3</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalLimitIndemnityEAR, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremiumSection3EAR, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif
                            
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                <td style="border: 1px solid #2e77c3!important;" >
                                    <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;font-size:12px;">Period of Insurance</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Period of insurance from</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Period of insurance To </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Number of Weeks of Testing</p>
                                </td>
                            </tr>
                            @if(!empty($coverages->earCoverage->period_from) || !empty($coverages->earCoverage->period_to) )
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ fmtDate($coverages->earCoverage->period_from, 'd/m/Y') }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ fmtDate($coverages->earCoverage->period_to, 'd/m/Y') }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->weeks_of_testing ?? '' }}</p>
                                </td>
                            </tr>  
                            @else
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ fmtDate($policyTerm->term_start_date, 'd/m/Y')}} </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ fmtDate($policyTerm->term_end_date, 'd/m/Y') }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"></p>
                                </td>
                            </tr>
                            @endif

                            {{-- EAR Endorsements/Extension --}}
                            @php
                                // Load endorsements from JSON in endorsement_1 (same logic as ManageCoverages.php)
                                $earEndorsements = [];
                                if (!empty($coverages->earCoverage->endorsement_1)) {
                                    $decoded = json_decode($coverages->earCoverage->endorsement_1, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                                        // Valid JSON array - extract text from each item
                                        foreach($decoded as $item) {
                                            if (is_array($item) && isset($item['text']) && !empty(trim($item['text']))) {
                                                $earEndorsements[] = trim($item['text']);
                                            } elseif (is_string($item) && !empty(trim($item))) {
                                                $earEndorsements[] = trim($item);
                                            }
                                        }
                                    } else {
                                        // Not JSON - backward compatibility: load from individual columns
                                        if (!empty($coverages->earCoverage->endorsement_1)) {
                                            $earEndorsements[] = trim($coverages->earCoverage->endorsement_1);
                                        }
                                        if (!empty($coverages->earCoverage->endorsement_2)) {
                                            $earEndorsements[] = trim($coverages->earCoverage->endorsement_2);
                                        }
                                        if (!empty($coverages->earCoverage->endorsement_3)) {
                                            $earEndorsements[] = trim($coverages->earCoverage->endorsement_3);
                                        }
                                        if (!empty($coverages->earCoverage->endorsement_4)) {
                                            // Check if endorsement_4 contains concatenated endorsements (separated by ';')
                                            $endorsement4Text = $coverages->earCoverage->endorsement_4;
                                            if (strpos($endorsement4Text, ';') !== false) {
                                                // Split by semicolon and add each as separate endorsement
                                                $parts = explode(';', $endorsement4Text);
                                                foreach ($parts as $part) {
                                                    $part = trim($part);
                                                    if (!empty($part)) {
                                                        $earEndorsements[] = $part;
                                                    }
                                                }
                                            } else {
                                                $earEndorsements[] = trim($endorsement4Text);
                                            }
                                        }
                                    }
                                }
                                // Filter out empty endorsements and re-index to ensure sequential numbering
                                $earEndorsements = array_values(array_filter($earEndorsements, function($text) {
                                    return !empty(trim($text));
                                }));
                            @endphp
                            @if(count($earEndorsements) > 0)
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Endorsements/Extension</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <strong>The following endorsements are attached to and forming part of this Policy:</strong>
                                    </p>
                                </td>
                            </tr>
                            @foreach($earEndorsements as $index => $endorsementText)
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

                            {{-- EAR Execution Details --}}
                            @if(!empty($coverages->earCoverage->executed_at) || !empty($coverages->earCoverage->execution_date) || !empty($coverages->earCoverage->signature))
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
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->earCoverage->executed_at ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->earCoverage->execution_date))
                                            {{ safeFormatDate($coverages->earCoverage->execution_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @php
                                            $customerFullName = '';
                                            if(!empty($coverages->earCoverage->signature)){
                                                $customerFullName = $coverages->earCoverage->signature;
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

                            {{-- EAR Notes --}}
                            @if(!empty($coverages->earCoverage->additional_notes))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;background-color:#C6EED8;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;font-size:12px;">Notes</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line; margin:0;">
                                        {{ $coverages->earCoverage->additional_notes }}
                                        </pre>
                                    </p>
                                </td>
                            </tr>

                        @endif
                        </table>

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Subtotal reconciles with the misc premium folded into the EAR coverage
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
