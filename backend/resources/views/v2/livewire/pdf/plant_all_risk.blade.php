
{{-- PAR Coverage Details --}}
                     <table>
                            @php
                                $insuredItemsRaw = $coverages->parCoverage?->insured_items;
                                $parInsuredItems = is_array($insuredItemsRaw) ? $insuredItemsRaw : (json_decode($insuredItemsRaw ?? '[]', true) ?? []);
                                // Handle double-encoded JSON (string inside string)
                                if (is_string($parInsuredItems)) $parInsuredItems = json_decode($parInsuredItems, true) ?? [];

                                $section2Raw = $coverages->parCoverage?->section2_items;
                                $parSection2Items = is_array($section2Raw) ? $section2Raw : (json_decode($section2Raw ?? '[]', true) ?? []);
                                if (is_string($parSection2Items)) $parSection2Items = json_decode($parSection2Items, true) ?? [];
                            @endphp

                            {{-- PAR Policy Schedule --}}
                            <tr>
                                <td colspan="4">
                                    <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">{{ $coverages->coverage->s_ScreenName ?? $coverages->coverage->s_CoverageCode }}</p>
                                </td>
                            </tr>
                            <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: center; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Policy Schedule</p>
                                </td>
                            </tr>
                            
                            {{-- Policy Information --}}
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Name</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Policy Period</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->project_name ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($coverages->parCoverage->policy_period_months) )
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">
                                        Allow up to {{ $coverages->parCoverage->policy_period_months }} months max
                                    </p>
                                    @endif
                                </td>
                            </tr>
                            @if(!empty($coverages->parCoverage->policy_period_months) && ($coverages->parCoverage->policy_period_months == 24 || $coverages->parCoverage->policy_period_months == 36))
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
                                        @if(!empty($coverages->parCoverage->approved_by))
                                            @php
                                                $approver = \AlphaDirect\Models\User::find($coverages->parCoverage->approved_by);
                                            @endphp
                                            {{ $approver ? $approver->firstName . ' ' . $approver->lastName : 'N/A' }}
                                        @else
                                            Not Approved
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->parCoverage->approved_at))
                                            {{ fmtDate($coverages->parCoverage->approved_at, 'd/m/Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </td>
                            </tr>
                            @endif
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Renewable Policy</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Project Specific Policy</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->parCoverage->is_renewable) }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ yes_no($coverages->parCoverage->is_project_specific) }}</p>
                                </td>
                            </tr>
                            @if(!empty($coverages->parCoverage->reinsurance_fire_treaty))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Reinsurance to follow the fire treaty arrangements</p>
                                </td>
                            </tr>
                            @endif

                            {{-- PAR Insured Items --}}
                            @if(count($parInsuredItems) > 0)
                                {{-- Section 1 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Section 1 – Material damage</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Description of items</p>
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
                                @foreach($parInsuredItems as $item)
                                    @if(!empty($item['description']) || !empty($item['sum_insured']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                @if(!empty($item['item_no']))
                                                    {{ $item['item_no'] }}. 
                                                @endif
                                                {{ $item['description'] ?? '' }}
                                                @if(!empty($item['qty']))
                                                    (Qty: {{ $item['qty'] }})
                                                @endif
                                                @if(!empty($item['year_of_manufacture']))
                                                    (Year: {{ $item['year_of_manufacture'] }})
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
                                
                                {{-- Total Sum Insured and Premium for PAR Insured Items --}}
                                @php
                                    $totalSumInsuredPAR = 0;
                                    $totalPremiumPAR = 0;
                                    foreach ($parInsuredItems as $item) {

                                            if (isset($item['sum_insured']) && $item['sum_insured'] !== '') {
                                                $totalSumInsuredPAR += parseNumericValue($item['sum_insured']);
                                            }

                                            if (isset($item['premium']) && $item['premium'] !== '') {
                                                $totalPremiumPAR += parseNumericValue($item['premium']);
                                            }
                                        }

                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalSumInsuredPAR = round($totalSumInsuredPAR, 2);
                                    $totalPremiumPAR = round($totalPremiumPAR, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Total sum insured</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalSumInsuredPAR, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremiumPAR, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif

                            {{-- PAR Section II - Third Party Liability --}}
                            @if(count($parSection2Items) > 0)
                                {{-- Section 2 Heading --}}
                                <tr style="border-top: 2px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:11px;background-color:#E8F5E9;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 5px;color: black!important;font-weight:bold;">Section 2 – Third party liability</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color: black!important;font-weight:bold;">Insured items</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Limits of indemnity</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Deductibles</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Premium</p>
                                    </td>
                                </tr>
                                @foreach($parSection2Items as $item)
                                    @if(!empty($item['item_type']) || !empty($item['description']) || !empty($item['limit_of_indemnity']))
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="40%" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ ucfirst(strtolower($item['item_type'] ?? ($item['description'] ?? ''))) }}
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
                                
                                {{-- Total Limit of Indemnity and Premium under Section II --}}
                                @php
                                    $totalLimitIndemnityPAR = 0;
                                    $totalPremiumSection2PAR = 0;
                                    foreach($parSection2Items as $item) {
                                        $limitOfIndemnity = $item['limit_of_indemnity'] ?? '';
                                        if (!empty($limitOfIndemnity) || $limitOfIndemnity === '0' || $limitOfIndemnity === 0) {
                                            $limitValue = parseNumericValue($limitOfIndemnity);
                                            $totalLimitIndemnityPAR += $limitValue;
                                        }
                                        $premium = $item['premium'] ?? '';
                                        if (!empty($premium) || $premium === '0' || $premium === 0) {
                                            $premValue = parseNumericValue($premium);
                                            $totalPremiumSection2PAR += $premValue;
                                        }
                                    }
                                    // Round to 2 decimal places to avoid floating point issues
                                    $totalLimitIndemnityPAR = round($totalLimitIndemnityPAR, 2);
                                    $totalPremiumSection2PAR = round($totalPremiumSection2PAR, 2);
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">Total limit of indemnity under Section II</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalLimitIndemnityPAR, 2, '.', ',') }}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1"></td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-weight:bold;">{{ number_format($totalPremiumSection2PAR, 2, '.', ',') }}</p>
                                    </td>
                                </tr>
                            @endif

                            {{-- PAR Endorsements/Extension --}}
                            @if(!empty($coverages->parCoverage->endorsement_1) || !empty($coverages->parCoverage->endorsement_2) || 
                                !empty($coverages->parCoverage->endorsement_3) || !empty($coverages->parCoverage->endorsement_4))
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
                            @if(!empty($coverages->parCoverage->endorsement_1))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" width="5%">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">1.</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->endorsement_1 }}</p>
                                </td>
                            </tr>
                            @endif
                            @if(!empty($coverages->parCoverage->endorsement_2))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" width="5%">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">2.</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->endorsement_2 }}</p>
                                </td>
                            </tr>
                            @endif
                            @if(!empty($coverages->parCoverage->endorsement_3))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" width="5%">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">3.</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->endorsement_3 }}</p>
                                </td>
                            </tr>
                            @endif
                            @if(!empty($coverages->parCoverage->endorsement_4))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;" width="5%">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">4.</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->endorsement_4 }}</p>
                                </td>
                            </tr>
                            @endif
                            @endif

                            {{-- PAR Execution Details --}}
                            @if(!empty($coverages->parCoverage->executed_at) || !empty($coverages->parCoverage->execution_date) || !empty($coverages->parCoverage->signature))
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
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $coverages->parCoverage->executed_at ?? '' }}</p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @if(!empty($coverages->parCoverage->execution_date))
                                            {{ safeFormatDate($coverages->parCoverage->execution_date) }}
                                        @endif
                                    </p>
                                </td>
                                <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        @php
                                            $customerFullName = '';
                                            if(!empty($coverages->parCoverage->signature)){
                                                $customerFullName = $coverages->parCoverage->signature;
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

                            {{-- PAR Notes --}}
                            @if(!empty($coverages->parCoverage->additional_notes))
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
                                            {{ $coverages->parCoverage->additional_notes }}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif
                       </table>

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Subtotal reconciles with the misc premium folded into the PAR coverage
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
