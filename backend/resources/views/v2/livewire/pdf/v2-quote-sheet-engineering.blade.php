<!Doctype html>
<html>
<head>
    <title>{{ ($policy->policyNumber ?? $policy->policy_number ?? 'Quote Sheet') }} - Engineering Quote Sheet</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/arial" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/montserrat" rel="stylesheet">
  
    <style>
        

        body {
            font-family: 'Montserrat', 'Arial', 'sans-serif';
            font-weight: 500;
            font-size: 11px;
            color: #000;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse !important;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #2e77c3;
            padding: 4px 6px;
            vertical-align: top;
        }

        .no-border {
            border: none !important;
        }

        .brand-blue {
            color: #2e77c3 !important;
        }

        .section-title {
            background: #d9f2d9;
            font-weight: 700;
        }

        .section-subtitle {
            background: #eef9ee;
            font-weight: 600;
        }

        .tiny {
            font-size: 10px;
        }

        .page-break {
            page-break-before: always;
        }

        .signature-line {
            height: 16px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #666;
        }

        .no-wrap {
            white-space: nowrap;
        }

        @media print {
            * {
                color: #000 !important;
                background: transparent !important;
            }
        }
    </style>
</head>
<body>
@php
    $today = $today ?? now()->format('d/m/Y');
    $policyNumber = $policy->policyNumber ?? $policy->policy_number ?? '';
    $lineOfBusiness = data_get($policy, 'product.name', 'Contractors All Risk');
    $currency = data_get($policy, 'currency', 'BWP');
    $insuredName = '';
    if (data_get($policy, 'profile.entity_type') === 'Organisation') {
        $insuredName = data_get($policy, 'profile.company.name', '');
    } else {
        $insuredName = trim(
            (data_get($policy, 'customer.firstName', '') . ' ' .
            data_get($policy, 'customer.middleName', '') . ' ' .
            data_get($policy, 'customer.lastName', ''))
        );
    }
    $insuredAddress = '';
    if (data_get($policy, 'profile.entity_type') === 'Organisation') {
        $insuredAddress = trim(
            (data_get($policy, 'profile.company.postal_address', '') ? data_get($policy, 'profile.company.postal_address', '') . ', ' : '') .
            data_get($policy, 'profile.company.cities.name', '')
        );
    } else {
        $insuredAddress = trim(
            (data_get($policy, 'profile.post_address', '') ? data_get($policy, 'profile.post_address', '') . ', ' : '') .
            data_get($policy, 'profile.cities.name', '')
        );
    }
@endphp

<p class="text-right" style="margin: 0 0 6px 0;">As On: {{ $today }}</p>

<table class="no-border">
    <tr>
        <td class="no-border" style="width: 50%;">
        <img style="width: 300px;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
            <div style="margin-top: 6px;">
                <div>Alpha Direct Insurance Co. (Pty) Ltd.</div>
                <div>Office: Floor 2, Bar 2, Botswana Innovation Hub,</div>
                <div>Icon Building, Plot 69184 Block 8 Industrial</div>
                <div>Postal Address: P.O. Box 26ADC,</div>
                <div>Gaborone, Botswana</div>
                <div>Phone +267 392 8264 | Fax +267 392 8265</div>
                <div>
                    @if($flag == 'v2_quotationPdfEngineering')
                       <span class="brand-blue">www.alphadirect.co.bw</span>
                    @else
                        debtors@alphadirect.co.bw | <span class="brand-blue">www.alphadirect.co.bw</span>
                    @endif</div>
            </div>
        </td>
        <td class="no-border text-right" style="width: 50%; vertical-align: middle;">
            <div class="brand-blue" style="font-size: 18px; font-weight: 700;">
            <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">
            @if($flag == 'v2_quotationPdfEngineering')
                ENGINEERING INSURANCE QUOTATION/PROPOSAL
            @else
                POLICY DOCUMENT
            @endif
            </p>
            </div>
        </td>
    </tr>
</table>

<table class="no-border" style="margin-top: 6px;">
    <tr>
        <td class="no-border" style="width: 50%;">
            <div class="brand-blue" style="font-weight: 600;">To,</div>
            <div>{{ $insuredName ?: '________________' }}</div>
            <div>{{ $insuredAddress ?: '________________' }}</div>
        </td>
        <td class="no-border" style="width: 50%;">
            <table>
                <tr>
                    <td class="brand-blue no-wrap">Line of Business :</td>
                    <td>{{ $lineOfBusiness ?: 'Contractors All Risk' }}</td>
                </tr>
                <tr>
                    <td class="brand-blue no-wrap">Policy No. :</td>
                    <td>{{ $policyNumber ?: '________________' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top: 8px;">
    <tr>
        <td class="brand-blue" style="width: 25%;">Agency Name:</td>
        <td>{{ data_get($policy, 'agency.name', '') }}</td>
        
    </tr>
    <tr>
        <td class="brand-blue" style="width: 25%;">Agency Address:</td>
        <td>{{ data_get($policy, 'user.address', '') }}</td>
    </tr>
    <tr>
        <td class="brand-blue">Agent Name:</td>
        <td>{{ data_get($policy, 'user.firstName', '') }} {{ data_get($policy, 'user.lastName', '') }}</td>
        
    </tr>
    <tr>
        <td class="brand-blue">Policy Number:</td>
        <td>{{ $policyNumber }}</td>
    </tr>
    <tr>
        <td class="brand-blue">Insured Name:</td>
        <td>{{ $insuredName }}</td>
        
    </tr>
    <tr>
        <td class="brand-blue">Insured Mailing Address:</td>
        <td>{{ $insuredAddress }}</td>
    </tr>
    <!-- @if($flag == 'v2_quotationPdfEngineeringDocuments')
        <tr>
            <td class="brand-blue">Renewal Frequency:</td>
            <td>{{ ($policyTerm ?? null) && $policyTerm->frequency == 6 ? 'Manually Renewed' : '' }}</td>
        </tr>
    @endif -->

    {{-- On an ENDORSE show the endorsement period (action effective dates,
         passed as $endorStart/$endorENd) under a "Period of Endorsement:"
         label; every other transaction keeps the full term as Period of
         Insurance. --}}
    @if(($policyAction->transaction_type ?? '') === 'ENDORSE')
    <tr>
        <td class="brand-blue">Period of Endorsement:</td>
        <td>{{ $endorStart ?? '' }} - {{ $endorENd ?? '' }}</td>
    </tr>
    @else
    <tr>
        <td class="brand-blue">Period of Insurance:</td>
        <td>{{ ($policyTerm ?? null) ? fmtDate($policyTerm->term_start_date, 'd/m/Y') : '' }} - {{ ($policyTerm ?? null) ? fmtDate($policyTerm->term_end_date, 'd/m/Y') : '' }} </td>
    </tr>
    @endif
    <tr>
        <td colspan="2" class="brand-blue">The provision of this quotation is subject to:</td>
       
    </tr>
    <tr>
        <td  style="border-right: 0;"></td>
        <td style="border-left: 0;">
            a) An acceptable survey and a satisfactory claims history
            <br/>
            b) Acceptance within 30 days from the date hereof
            <br/>
            c) F &amp; I standard endorsements applicable to various policy sections
            <br/>
            d) The Motor excess conditions, where applicable
            <br/>
            e) FIA or other statutory requirements
            <br/>
        </td>  
    </tr>
    
</table>

<div style="margin-top: 6px;">
    <div class="tiny">
        
        
    </div>
</div>

<table style="margin-top: 6px;">
    <tr class="section-title">
        <td style="width: 65%;">General Questions</td>
        <td style="width: 35%;">Answers</td>
    </tr>
    <tr>
        <td>Date Business Established?</td>
        <td>
        @if(isset($policy->profile->date))
            {{ fmtDate($policy->profile->date, 'd/m/Y') }}
        @else
            N/A
        @endif
        </td>
    </tr>
    <tr>
        <td>Are you currently insured, if so who is your insurer?</td>
        @php $currentlyInsured = AlphaDirect\Lookup::where('id',$policy->profile?->insure)->where('key','are_you_currently_insured')->first()?->value; @endphp
        <td> {{ $currentlyInsured ?? "" }}</td>
    </tr>
    <tr>
        <td>Has any insurer ever?</td>
        <td></td>
    </tr>
    <tr>
        <td>(a) declined any proposal?</td>
        <td>@if(($policy->profile?->decline_proposal)==1)Yes @else No @endif</td>
    </tr>
    <tr>
        <td>(b) refused to renew any policy?</td>
        <td>@if(($policy->profile?->refused_policy)==1)Yes @else No @endif</td>
    </tr>
    <tr>
        <td>(c) cancelled any policy?</td>
        <td>{{ data_get($policy, 'cancelled_policy', '') }}</td>
    </tr>
    <tr>
        <td>Have you or any member of your firm ever made a compromise with creditors or been declared insolvent?</td>
        <td>@if(($policy?->profile->firm_member)==1)Yes @else No @endif</td>
    </tr>
    <tr>
        <td>Do you keep a complete set of books showing a true and accurate record of business transacted?</td>
        <td>@if(($policy->profile?->books)==1)Yes @else No @endif</td>
    </tr>
    <tr>
        <td>How did you hear about Alpha Direct?</td>
        @php $aboutAlpha = AlphaDirect\Lookup::where('id',$policy->profile->about_alpha)->where('key','hear_about_alphadirect')->first()?->value; @endphp
        <td>{{ $aboutAlpha ?? "" }}</td>
    </tr>
</table>

<table style="margin-top: 6px;">
    <tr class="section-title text-center">
        <td colspan="5">Index of Sections</td>
    </tr>
    <tr class="section-subtitle">
        <td style="width: 32%;">Policy Section Available</td>
        <td style="width: 12%;" class="text-center">Section Taken</td>
        <td style="width: 18%;" class="text-center">Pro Rata Refund</td>
        <td style="width: 19%;" class="text-center">Pro Rata Premium incl. VAT</td>
        <td style="width: 19%;" class="text-center">Gross incl. VAT</td>
    </tr>
    @php

    // Helper function to parse comma-separated values and convert to float
        if (!function_exists('parseNumericValue')) {
            function parseNumericValue($value) {
                // Handle null, empty string, or false
                if ($value === null || $value === false || $value === '') return 0;
                
                // If already a number, return it
                if (is_numeric($value) && !is_string($value)) {
                    return (float)$value;
                }
                
                // Convert to string
                $value = trim((string)$value);
                
                // Handle empty after trim
                if ($value === '' || $value === '-') return 0;
                
                // Remove all commas (thousands separators)
                $value = str_replace(',', '', $value);
                
                // Remove currency symbols and spaces
                $value = str_replace(['P', 'p', '$', '€', '£', ' ', 'R'], '', $value);
                
                // Remove any non-numeric characters except decimal point and minus sign
                $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                
                // Handle empty result or invalid formats
                if (empty($cleaned) || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') return 0;
                
                // Convert to float
                $result = (float)$cleaned;
                
                // Check for NaN or infinity
                if (is_nan($result) || is_infinite($result)) return 0;
                
                return $result;
            }
        }
        
        if (!function_exists('formatNumericValue')) {
            function formatNumericValue($value) {
                $num = parseNumericValue($value);
                return $num > 0 ? number_format($num, 2, '.', ',') : '';
            }
        }
        
        // Helper function to safely parse and format dates
        if (!function_exists('safeFormatDate')) {
            function safeFormatDate($dateValue, $format = 'd/m/Y') {
                if (empty($dateValue)) {
                    return '';
                }
                
                // Convert to string if not already
                $dateValue = (string) $dateValue;
                $dateValue = trim($dateValue);
                
                if (empty($dateValue)) {
                    return '';
                }
                
                try {
                    // If already in the desired format (d/m/Y), return as-is
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                        return $dateValue;
                    }
                    
                    // Try parsing as d/m/Y format first (common display format)
                    if (strpos($dateValue, '/') !== false && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateValue)) {
                        try {
                            $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue);
                            return $parsed->format($format);
                        } catch (\Exception $e) {
                            // Continue to next format
                        }
                    }
                    
                    // Try parsing as Y-m-d format (database format)
                    if (strpos($dateValue, '-') !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $dateValue)) {
                        try {
                            $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', substr($dateValue, 0, 10));
                            return $parsed->format($format);
                        } catch (\Exception $e) {
                            // Continue to next format
                        }
                    }
                    
                    // Try Carbon's default parse (handles various formats like Y-m-d H:i:s)
                    try {
                        $parsed = \Carbon\Carbon::parse($dateValue);
                        return $parsed->format($format);
                    } catch (\Exception $e) {
                        // If all parsing fails, return the original value
                        return $dateValue;
                    }
                } catch (\Exception $e) {
                    // If all parsing fails, return the original value
                    return $dateValue;
                }
            }
        }
        // Helper function to parse comma-separated values and convert to float
                            if (!function_exists('parseNumericValue')) {
                                function parseNumericValue($value) {
                                    // Handle null, empty string, or false
                                    if ($value === null || $value === false || $value === '') return 0;
                                    
                                    // If already a number, return it
                                    if (is_numeric($value) && !is_string($value)) {
                                        return (float)$value;
                                    }
                                    
                                    // Convert to string
                                    $value = trim((string)$value);
                                    
                                    // Handle empty after trim
                                    if ($value === '' || $value === '-') return 0;
                                    
                                    // Remove all commas (thousands separators)
                                    $value = str_replace(',', '', $value);
                                    
                                    // Remove currency symbols and spaces
                                    $value = str_replace(['P', 'p', '$', '€', '£', ' ', 'R'], '', $value);
                                    
                                    // Remove any non-numeric characters except decimal point and minus sign
                                    $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                                    
                                    // Handle empty result or invalid formats
                                    if (empty($cleaned) || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') return 0;
                                    
                                    // Convert to float
                                    $result = (float)$cleaned;
                                    
                                    // Check for NaN or infinity
                                    if (is_nan($result) || is_infinite($result)) return 0;
                                    
                                    return $result;
                                }
                            }
                            
                            if (!function_exists('formatNumericValue')) {
                                function formatNumericValue($value) {
                                    $num = parseNumericValue($value);
                                    return $num > 0 ? number_format($num, 2, '.', ',') : '';
                                }
                            }

                            if (!function_exists('decodeItemsToArray')) {
                                function decodeItemsToArray($value) {
                                    if (is_array($value)) return $value;
                                    if ($value === null || $value === '') return [];
                                    $decoded = json_decode($value, true);
                                    return is_array($decoded) ? $decoded : [];
                                }
                            }

        $coverageTotals = [];
        $grandTotalPremium = 0;
        foreach ($policy_coverages as $coverages) { 
            $sum = 0;
            $sum_insured = 0;
            $coverageName = $coverages->coverage->s_ScreenName;
            if ($coverageName === 'Contractors All Risk') {
                $carSection1Items   = decodeItemsToArray($coverages->carCoverage?->section1_items);
                $carSection2Items   = decodeItemsToArray($coverages->carCoverage?->section2_items);
                $carSection3Items   = decodeItemsToArray($coverages->carCoverage?->section3_items);
                $carPlantListItems  = decodeItemsToArray($coverages->carCoverage?->plant_list_items);
                    
                    
                    foreach($carSection1Items as $item) {
                        $sum += parseNumericValue($item['premium'] ?? '');
                        $sum_insured += parseNumericValue($item['sum_insured'] ?? '');
                    }
                    
                    foreach($carSection2Items as $item) {
                        $sum += parseNumericValue($item['premium'] ?? '');
                        $sum_insured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                    }
                    
                    // Add Section 3 main coverage - Gross Profit
                    if (!empty($coverages->carCoverage->section3_gross_profit_annual_sum_insured)) {
                        $sum_insured += parseNumericValue($coverages->carCoverage->section3_gross_profit_annual_sum_insured);
                    }
                    if (!empty($coverages->carCoverage->section3_gross_profit_premium)) {
                        $sum += parseNumericValue($coverages->carCoverage->section3_gross_profit_premium);
                    }
                    // Add Section 3 main coverage - Increased Cost of Working
                    if (!empty($coverages->carCoverage->section3_increased_cost_sum_insured)) {
                        $sum_insured += parseNumericValue($coverages->carCoverage->section3_increased_cost_sum_insured);
                    }
                    if (!empty($coverages->carCoverage->section3_increased_cost_premium)) {
                        $sum += parseNumericValue($coverages->carCoverage->section3_increased_cost_premium);
                    }
                    foreach($carSection3Items as $item) {
                        $sum += parseNumericValue($item['premium'] ?? '');
                        $sum_insured += parseNumericValue($item['annual_sum_insured'] ?? '');
                    }
                    // Add specified items (miscellaneous items)
                    foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                        if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                            $sum_insured += parseNumericValue($specifed_items->sum_insured ?? 0);
                            $sum += parseNumericValue($specifed_items->calculated_value ?? 0);
                        }
                    }


                    // Add risk premiums and sum insured (limit of indemnity)

                    $sumIndexInsuredCalculated = round($sum_insured, 2);
                    $SumIndexExtCalculated = 0;
                    $sum = round($sum, 2);
                    
            }
            elseif($coverageName == 'Erection All Risk'){
                // Calculate EAR premium and sum insured - matching Total of subcoverages calculation
                $earSection1Items = decodeItemsToArray($coverages->earCoverage?->section1_items);
                $earSection3Items = decodeItemsToArray($coverages->earCoverage?->section3_items);
                
                
                foreach($earSection1Items as $item) {
                    $sum += parseNumericValue($item['premium'] ?? '');
                    $sum_insured += parseNumericValue($item['sum_insured'] ?? '');
                }
                foreach($earSection3Items as $item) {
                    $sum += parseNumericValue($item['premium'] ?? '');
                    $sum_insured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                }
                // Add risk sum insured (limit of indemnity) and premiums
                $sum_insured += parseNumericValue($coverages->earCoverage->risk_earthquake_limit_indemnity ?? '');
                $sum += parseNumericValue($coverages->earCoverage->risk_earthquake_premium ?? '');
                $sum_insured += parseNumericValue($coverages->earCoverage->risk_storm_limit_indemnity ?? '');
                $sum += parseNumericValue($coverages->earCoverage->risk_storm_premium ?? '');
                // Add specified items (miscellaneous items)
                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                    if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                        $sum_insured += parseNumericValue($specifed_items->sum_insured ?? 0);
                        $sum += parseNumericValue($specifed_items->calculated_value ?? 0);
                    }
                }
                // Use calculated sum instead of overwriting with total_premium field
                // The calculated sum includes all Section 1, Section 2 (risk), and Section 3 premiums
                $sumIndexInsuredCalculated = round($sum_insured, 2);
                $SumIndexExtCalculated = 0;
                $sum = round($sum, 2);
                
            }
            elseif($coverageName == 'Plant All Risk'){
                // Calculate PAR premium and sum insured - matching Total of subcoverages calculation
                $parInsuredItems  = decodeItemsToArray($coverages->parCoverage?->insured_items);
                $parSection2Items = decodeItemsToArray($coverages->parCoverage?->section2_items);


                foreach($parInsuredItems as $item) {
                    $sum += parseNumericValue($item['premium'] ?? '');
                    $sum_insured += parseNumericValue($item['sum_insured'] ?? '');
                }
                foreach($parSection2Items as $item) {
                    $sum += parseNumericValue($item['premium'] ?? '');
                    $sum_insured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                }
                // Add specified items (miscellaneous items)
                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                    if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                        $sum_insured += parseNumericValue($specifed_items->sum_insured ?? 0);
                        $sum += parseNumericValue($specifed_items->calculated_value ?? 0);
                    }
                }
                $sumIndexInsuredCalculated = round($sum_insured, 2);
                $SumIndexExtCalculated = 0;
                $sum = round($sum, 2);

            }
            elseif($coverageName == 'Machinery Breakdown' || strtoupper((string) ($coverages->coverage->s_CoverageCode ?? '')) === 'MACHINERYBREAKDOWN'){
                // Pull premium from machinery_breakdown_coverages joined to this policy_coverage row.
                // Sum insured isn't a single field — derive from machinery_listing items.
                $mbRow = \AlphaDirect\Models\MachineryBreakdownCoverage::where('policy_coverage_id', $coverages->id)->first();
                if ($mbRow) {
                    // Premium is rebuilt from the section item premiums
                    // (SECTION 1 + Machinery Listing + SECTION 2 + SECTION 3),
                    // matching EAR/PAR above and the Rate button. Falls back to
                    // the scalar column when the sections carry no premium.
                    $sum += \AlphaDirect\Models\MachineryBreakdownCoverage::resolvedPremium($mbRow);
                    foreach (decodeItemsToArray($mbRow->machinery_listing) as $item) {
                        $sum_insured += parseNumericValue($item['sum_insured'] ?? '');
                    }
                    foreach (decodeItemsToArray($mbRow->section1_items) as $item) {
                        $sum_insured += parseNumericValue($item['limit_status'] ?? '');
                    }
                    foreach (decodeItemsToArray($mbRow->section2_items) as $item) {
                        $sum_insured += parseNumericValue($item['value'] ?? '');
                    }
                    foreach (decodeItemsToArray($mbRow->section3_items) as $item) {
                        $sum_insured += parseNumericValue($item['value'] ?? '');
                    }
                }
                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                    if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                        $sum_insured += parseNumericValue($specifed_items->sum_insured ?? 0);
                        $sum += parseNumericValue($specifed_items->calculated_value ?? 0);
                    }
                }
                $sumIndexInsuredCalculated = round($sum_insured, 2);
                $SumIndexExtCalculated = 0;
                $sum = round($sum, 2);
            }
            // ✅ Round once
            $sum = round($sum, 2);
            $sum_insured = round($sum_insured, 2);

            // ✅ Store per coverage
            $coverageTotals[$coverageName] = [
                'premium' => $sum,
                'sum_insured' => $sum_insured,
            ];

            // ✅ ADD TO GRAND TOTAL
            $grandTotalPremium += $sum;
        }
    @endphp

    @foreach ($indexSections as $section)
            <tr>
            <td>{{ $section->name }}</td>
            <td class="text-center">
            @php
                                    $x = 0 ;
                                    $y = 0 ;
                                    $z = 0 ;
                                @endphp
                                @foreach($policy_coverages as $index => $coverages)
                                    @if ($section->name == $coverages->coverage->s_ScreenName)
                                        @php
                                            $z = 1 ;
                                        @endphp
                                        @else
                                        @php
                                            $y = 0 ;
                                        @endphp
                                    @endif
                                @endforeach
                                @php
                                $x = $z+$y;
                                @endphp

                                @if ($x > 0)
                                    Yes
                                @else
                                    No
                                @endif</td>
            {{-- Pro-rata computed in PHP (SpecialistEndorseCalculator::proRataInclVatByScreenName),
                 passed via $specialistProRata keyed by section name — no calculation here. --}}
            <td class="text-center">@if(($specialistProRata[$section->name]['refundInclVat'] ?? 0) > 0)P - {{ number_format($specialistProRata[$section->name]['refundInclVat'], 2, '.', ',') }}@else P 0.00 @endif</td>
            <td class="text-center">P {{ number_format($specialistProRata[$section->name]['premiumInclVat'] ?? 0, 2, '.', ',') }}</td>
            <td class="text-center">
            P {{ number_format($coverageTotals[$section->name]['premium'] ?? 0, 2, '.', ',') }}
        </td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2" class="text-left">Total Premium</td>
        <td class="text-center">@if(($specialistProRataTotalRefundInclVat ?? 0) > 0)P - {{ number_format($specialistProRataTotalRefundInclVat, 2, '.', ',') }}@else P 0.00 @endif</td>
        <td class="text-center">P {{ number_format($specialistProRataTotalPremiumInclVat ?? 0, 2, '.', ',') }}</td>
        <td class="text-center">P {{ number_format($grandTotalPremium, 2) }}</td>
    </tr>
    <tr>
    @php
        $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
        
        $premiumExcludingVAT =  ($grandTotalPremium) / (1 + ($regionVat/100));
        
        if(($policy->premium_freq)==1){
            $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
        }

        $vat = $premiumExcludingVAT * ($regionVat/100);
        $ServiceCharge8 = $premiumExcludingVAT*(8/100);
        $vatServiceCharge = $ServiceCharge8*($regionVat/100);
        $vat_pro_data=0;
        $vat_pro_data = $vat;
    @endphp
    
        <td colspan="4" class="text-left">VAT</td>
        <td class="text-center">P {{ number_format($vat_pro_data, 2) }}</td>
    </tr>
    @if(($policy->premium_freq)==1)
    <tr>
        <td colspan="4" class="text-left">8% Service Charge On Monthly Payment</td>
        <td class="text-center">P {{ number_format($ServiceCharge8, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left">14% VAT On Service Charge</td>
        <td class="text-center">P {{ number_format($vatServiceCharge, 2) }}</td>
    </tr>
    @endif
</table>
@foreach($policy_coverages as $index => $coverages)
    @php $covCode = strtoupper((string) ($coverages->coverage->s_CoverageCode ?? '')); @endphp
    @if($coverages->coverage->s_ScreenName == 'Contractors All Risk')
    @include('v2/livewire/pdf/contractors_all_risk')
    @elseif($coverages->coverage->s_ScreenName == 'Erection All Risk')
    @include('v2/livewire/pdf/erection_all_risk')
    @elseif($coverages->coverage->s_ScreenName == 'Plant All Risk')
    @include('v2/livewire/pdf/plant_all_risk')
    @elseif($coverages->coverage->s_ScreenName == 'Machinery Breakdown' || $covCode === 'MACHINERYBREAKDOWN')
    @include('v2/livewire/pdf/machinery_breakdown_pdf')
    @endif

@endforeach

<table>
    {{-- On an ENDORSE the Final Premium reflects the pro-rata charge (computed
         in PHP and passed in); every other transaction shows the full annual. --}}
    @if(($policyAction->transaction_type ?? '') === 'ENDORSE')
    <tr>
        <td colspan="4" class="text-left"><b>Final Premium Excluding VAT</b></td>
        <td class="text-center">P {{ number_format($specialistProRataFinalExclVat ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left"><b>VAT</b></td>
        <td class="text-center">P {{ number_format($specialistProRataFinalVat ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left"><b>Final Premium Including VAT</b></td>
        <td class="text-center">P {{ number_format($specialistProRataFinalInclVat ?? 0, 2) }}</td>
    </tr>
    @else
    <tr>
        <td colspan="4" class="text-left"><b>Final Premium Excluding VAT</b></td>
        <td class="text-center">P {{ number_format($premiumExcludingVAT, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left"><b>VAT</b></td>
        <td class="text-center">P {{ number_format($vat_pro_data, 2) }}</td>
    </tr>
    @if(($policy->premium_freq)==1)
    <tr>
        <td colspan="4" class="text-left"><b>8% Service Charge On Monthly Payment</b></td>
        <td class="text-center">P {{ number_format($ServiceCharge8, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left"><b>14% VAT On Service Charge</b></td>
        <td class="text-center">P {{ number_format($vatServiceCharge, 2) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="4" class="text-left"><b>Final Premium Including VAT</b></td>
        <td class="text-center">P {{ number_format($grandTotalPremium , 2) }}</td>
    </tr>
    @endif
</table>
</body>
</html>
