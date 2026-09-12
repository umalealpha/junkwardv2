
@php
                       
    $isCarCoverage = ($coverages->coverage->s_CoverageCode == "CONTRACTORSALLRISKS" || $coverages->coverage->s_CoverageCode == "CAR");
    $isEarCoverage = ($coverages->coverage->s_CoverageCode == "ERECTIONALLRISKS" || $coverages->coverage->s_CoverageCode == "EAR");
    $isParCoverage = ($coverages->coverage->s_CoverageCode == "PLANTALLRISKS" || $coverages->coverage->s_CoverageCode == "PAR");
    $sumSubCarCoverage = $sum = $sum_insured = 0;
    if($allcoverage->s_ScreenName == 'Contractors All Risk' && $coverages->carCoverage) {
                                            // Calculate CAR premium and sum insured - matching Total of subcoverages calculation
                                            $carSection1Items = is_array($coverages->carCoverage->section1_items) 
                                                ? $coverages->carCoverage->section1_items 
                                                : (json_decode($coverages->carCoverage->section1_items ?? '[]', true) ?? []);
                                            $carSection2Items = is_array($coverages->carCoverage->section2_items) 
                                                ? $coverages->carCoverage->section2_items 
                                                : (json_decode($coverages->carCoverage->section2_items ?? '[]', true) ?? []);
                                            $carSection3Items = is_array($coverages->carCoverage->section3_items) 
                                                ? $coverages->carCoverage->section3_items 
                                                : (json_decode($coverages->carCoverage->section3_items ?? '[]', true) ?? []);
                                            $carPlantListItems = is_array($coverages->carCoverage->plant_list_items) 
                                                ? $coverages->carCoverage->plant_list_items 
                                                : (json_decode($coverages->carCoverage->plant_list_items ?? '[]', true) ?? []);
                                            
                                            $sum = 0;
                                            $sum_insured = 0;
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
                                            
                                            
                                            // Add risk premiums and sum insured (limit of indemnity)
                                            
                                            $sumIndexInsuredCalculated = round($sum_insured, 2);
                                            $SumIndexExtCalculated = 0;
                                            $sum = round($sum, 2);
                                        } elseif($allcoverage->s_ScreenName == 'Erection All Risk' && $coverages->earCoverage) {
                                            // Calculate EAR premium and sum insured - matching Total of subcoverages calculation
                                            $earSection1Items = is_array($coverages->earCoverage->section1_items) 
                                                ? $coverages->earCoverage->section1_items 
                                                : (json_decode($coverages->earCoverage->section1_items ?? '[]', true) ?? []);
                                            $earSection3Items = is_array($coverages->earCoverage->section3_items) 
                                                ? $coverages->earCoverage->section3_items 
                                                : (json_decode($coverages->earCoverage->section3_items ?? '[]', true) ?? []);
                                            
                                            $sum = 0;
                                            $sum_insured = 0;
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
                                        } elseif($allcoverage->s_ScreenName == 'Plant All Risk'  && $coverages->parCoverage) {
                                            // Calculate PAR premium and sum insured - matching Total of subcoverages calculation
                                            $parInsuredItems = is_array($coverages->parCoverage->insured_items) 
                                                ? $coverages->parCoverage->insured_items 
                                                : (json_decode($coverages->parCoverage->insured_items ?? '[]', true) ?? []);
                                            $parSection2Items = is_array($coverages->parCoverage->section2_items) 
                                                ? $coverages->parCoverage->section2_items 
                                                : (json_decode($coverages->parCoverage->section2_items ?? '[]', true) ?? []);
                                            
                                            $sum = 0;
                                            $sum_insured = 0;
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
                                    @endphp
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;border-right:1px solid #2e77c3!important;">
                                            <td width="20%" style="border: 1px solid #2e77c3!important;border-right:1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $allcoverage->s_ScreenName??""}}
                                                </p>
                                            </td>
                                            <td width="5%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    @php
                                                        $x = 0 ;
                                                        $y = 0 ;
                                                        $z = 0 ;
                                                    @endphp
                                                    @foreach($policy_coverages as $index => $coverages)
                                                        @if ($allcoverage->s_ScreenName == $coverages->coverage->s_ScreenName)
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
                                                    @endif
                                                </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" class="endendorsement-1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                            
                                                </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                
                                                </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                            @php $sumSubCarCoverage = $sumSubCOverages+$SumIndexExtCalculated+$sum;
                                                            $totalOfflineCoverageCalculatedValue += $sumSubCarCoverage;
                                                            session(['totalOfflineCoverageCalculatedValue' => $totalOfflineCoverageCalculatedValue]);
                                                            @endphp
                                                            P {{ number_format((($sumSubCarCoverage ?? 0) == 0 ? 0 : $sumSubCarCoverage), 2, '.', ',') }}
                                                            
                                            
                                                </p>
                                            </td>
                                        </tr>
                                        <!-- Total of subcoverages code starts here with totalOfflineCoverageCalculatedValue -->
                                    @php 
                                    
                                    if($allcoverage->s_ScreenName == 'Contractors All Risk' && $coverages->carCoverage) {
                                        // Calculate CAR sum insured, deductible and premium
                                        $carSection1Items = is_array($coverages->carCoverage->section1_items) 
                                            ? $coverages->carCoverage->section1_items 
                                            : (json_decode($coverages->carCoverage->section1_items ?? '[]', true) ?? []);
                                        $carSection2Items = is_array($coverages->carCoverage->section2_items) 
                                            ? $coverages->carCoverage->section2_items 
                                            : (json_decode($coverages->carCoverage->section2_items ?? '[]', true) ?? []);
                                        $carSection3Items = is_array($coverages->carCoverage->section3_items) 
                                            ? $coverages->carCoverage->section3_items 
                                            : (json_decode($coverages->carCoverage->section3_items ?? '[]', true) ?? []);
                                        $carPlantListItems = is_array($coverages->carCoverage->plant_list_items) 
                                            ? $coverages->carCoverage->plant_list_items 
                                            : (json_decode($coverages->carCoverage->plant_list_items ?? '[]', true) ?? []);
                                        
                                        foreach($carSection1Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['sum_insured'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        foreach($carSection2Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        // Add Section 3 main coverage - Gross Profit
                                        if (!empty($coverages->carCoverage->section3_gross_profit_annual_sum_insured)) {
                                            $carEarParSumInsured += parseNumericValue($coverages->carCoverage->section3_gross_profit_annual_sum_insured);
                                        }
                                        if (!empty($coverages->carCoverage->section3_gross_profit_premium)) {
                                            $carEarParPremium += parseNumericValue($coverages->carCoverage->section3_gross_profit_premium);
                                        }
                                        // Add Section 3 main coverage - Increased Cost of Working
                                        if (!empty($coverages->carCoverage->section3_increased_cost_sum_insured)) {
                                            $carEarParSumInsured += parseNumericValue($coverages->carCoverage->section3_increased_cost_sum_insured);
                                        }
                                        if (!empty($coverages->carCoverage->section3_increased_cost_premium)) {
                                            $carEarParPremium += parseNumericValue($coverages->carCoverage->section3_increased_cost_premium);
                                        }
                                        foreach($carSection3Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['annual_sum_insured'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        
                                        // Add risk sum insured (limit of indemnity), deductibles and premiums
                                        $carEarParSumInsured += parseNumericValue($coverages->carCoverage->section1_earthquake_limit_indemnity ?? '');
                                        $carEarParDeductible += parseNumericValue($coverages->carCoverage->section1_earthquake_deductible ?? '');
                                        // $carEarParPremium += parseNumericValue($coverages->carCoverage->section1_earthquake_premium ?? '');
                                        $carEarParSumInsured += parseNumericValue($coverages->carCoverage->section1_storm_limit_indemnity ?? '');
                                        $carEarParDeductible += parseNumericValue($coverages->carCoverage->section1_storm_deductible ?? '');
                                        // $carEarParPremium += parseNumericValue($coverages->carCoverage->section1_storm_premium ?? '');
                                        // Add specified items (miscellaneous items) for CAR
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                                                $carEarParSumInsured += parseNumericValue($specifed_items->sum_insured ?? 0);
                                                $carEarParPremium += parseNumericValue($specifed_items->calculated_value ?? 0);
                                            }
                                        }
                                    } elseif($allcoverage->s_ScreenName == 'Erection All Risk' && $coverages->earCoverage) {
                                        // Calculate EAR sum insured, deductible and premium
                                        $earSection1Items = is_array($coverages->earCoverage->section1_items) 
                                            ? $coverages->earCoverage->section1_items 
                                            : (json_decode($coverages->earCoverage->section1_items ?? '[]', true) ?? []);
                                        $earSection3Items = is_array($coverages->earCoverage->section3_items) 
                                            ? $coverages->earCoverage->section3_items 
                                            : (json_decode($coverages->earCoverage->section3_items ?? '[]', true) ?? []);
                                        
                                        foreach($earSection1Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['sum_insured'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        foreach($earSection3Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        // Add risk sum insured (limit of indemnity), deductibles and premiums
                                        $carEarParSumInsured += parseNumericValue($coverages->earCoverage->risk_earthquake_limit_indemnity ?? '');
                                        $carEarParDeductible += parseNumericValue($coverages->earCoverage->risk_earthquake_deductible ?? '');
                                        $carEarParPremium += parseNumericValue($coverages->earCoverage->risk_earthquake_premium ?? '');
                                        $carEarParSumInsured += parseNumericValue($coverages->earCoverage->risk_storm_limit_indemnity ?? '');
                                        $carEarParDeductible += parseNumericValue($coverages->earCoverage->risk_storm_deductible ?? '');
                                        $carEarParPremium += parseNumericValue($coverages->earCoverage->risk_storm_premium ?? '');
                                        // Add specified items (miscellaneous items) for EAR
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                                                $carEarParSumInsured += parseNumericValue($specifed_items->sum_insured ?? 0);
                                                $carEarParPremium += parseNumericValue($specifed_items->calculated_value ?? 0);
                                            }
                                        }
                                        // Use calculated sum instead of overwriting with total_premium field
                                        // The calculated sum includes all Section 1, Section 2 (risk), and Section 3 premiums
                                    } elseif($allcoverage->s_ScreenName == 'Plant All Risk'  && $coverages->parCoverage) {
                                        // Calculate PAR sum insured, deductible and premium
                                        $parInsuredItems = is_array($coverages->parCoverage->insured_items) 
                                            ? $coverages->parCoverage->insured_items 
                                            : (json_decode($coverages->parCoverage->insured_items ?? '[]', true) ?? []);
                                        $parSection2Items = is_array($coverages->parCoverage->section2_items) 
                                            ? $coverages->parCoverage->section2_items 
                                            : (json_decode($coverages->parCoverage->section2_items ?? '[]', true) ?? []);
                                        
                                        foreach($parInsuredItems as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['sum_insured'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        foreach($parSection2Items as $item) {
                                            $carEarParSumInsured += parseNumericValue($item['limit_of_indemnity'] ?? '');
                                            $carEarParDeductible += parseNumericValue($item['deductible'] ?? '');
                                            $carEarParPremium += parseNumericValue($item['premium'] ?? '');
                                        }
                                        // Add specified items (miscellaneous items) for PAR
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            if($coverages->status != 1 && $specifed_items->deleted_at == null) {
                                                $carEarParSumInsured += parseNumericValue($specifed_items->sum_insured ?? 0);
                                                $carEarParPremium += parseNumericValue($specifed_items->calculated_value ?? 0);
                                            }
                                        }
                                    }
                                    
                                    // Round to 2 decimal places
                                    $carEarParSumInsured = round($carEarParSumInsured, 2);
                                    $carEarParDeductible = round($carEarParDeductible, 2);
                                    $carEarParPremium = round($carEarParPremium, 2);
                                    session(['carEarParSumInsured' => $carEarParSumInsured]);
                                    session(['carEarParDeductible' => $carEarParDeductible]);
                                    session(['carEarParPremium' => $carEarParPremium]);
                                    @endphp
                                       
                                        