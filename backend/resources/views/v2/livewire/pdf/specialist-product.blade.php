<!Doctype html>
<html>
<head>
    <title>{{ ($policy->policyNumber ?? $policy->policy_number ?? 'Quote Sheet') }} - Specialist Product Quote Sheet</title>
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
    // Miscellaneous Items (policy_specified_items) subtotal, bucketed by
    // specialist FAMILY — s_CoverageCode first, s_ScreenName only as a
    // fallback. Same match rule as the Index of Sections Gross column below,
    // so the two can no longer disagree.
    //
    // This fold used the raw s_ScreenName string alone. Two consequences:
    //   1. Any coverage whose screen name differs from the literal here kept
    //      its specialist premium in Gross (that column matches name OR code)
    //      while its misc premium silently vanished from Gross, Total Premium
    //      and VAT. Screen names have drifted before - see the Marine D&O
    //      note on the Gross column.
    //   2. 'Directors and Officers Liability' - the LIVE screen name for the
    //      D&O coverage on Commercial Liabilities (20) / Marine (22) - folded
    //      into $getDirectorsOfficersTotal, which NOTHING reads (it is not in
    //      $grandTotalPremium below), so that misc premium was dropped
    //      outright. It now folds into the Marine D&O bucket the Gross column
    //      actually reads.
    // Each coverage is classified into exactly ONE family, so no bucket can
    // double-count a misc item. Families / aliases mirror the Gross dispatch.
    $specialistMiscFamilies = [
        'medical'       => [['MEDICAMALPRACTICEINSURANCE'], ['Medical Malpractice']],
        'pi'            => [['PROFESSIONALINDEMNITY'], ['Professional Indemnity']],
        'travel'        => [['TRAVELINSURANCE', 'TRAVEL'], ['Travel Insurance']],
        'marineOnceOff' => [['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF'], ['Marine Once-Off Cover', 'Marine Cargo Once-Off', 'Marine Cargo Once Off']],
        'marineOpen'    => [['MARINEOPENCOVER', 'MARINECARGOOPEN'], ['Marine Open Cover', 'Marine Cargo Open']],
        'marineDO'      => [['MARINEDIRECTORSOFFICERS', 'DIRECTORSOFFICERSLIABILITY'], ['Marine Directors & Officers', 'Directors & Officers', 'Directors and Officers Liability']],
        'machinery'     => [['MACHINERYBREAKDOWN'], ['Machinery Breakdown']],
        'medicalEvac'   => [['MEDICALEVACUATION'], ['Medical Evacuation']],
        'crime'         => [['COMMERCIALCRIME'], ['Commercial Crime']],
        'environmental' => [['ENVIRONMENTALLIABILITY'], ['Environmental Liability']],
        'bonds'         => [['BONDSANDGUARANTEES'], ['Bonds and Guarantees']],
    ];
    $miscByFamily = [];
    foreach ($policy_coverages as $coverages) {
        $coverageName = (string) ($coverages->coverage->s_ScreenName ?? '');
        $coverageCode = strtoupper((string) ($coverages->coverage->s_CoverageCode ?? ''));
        $specSum = 0;
        foreach ($coverages->specifedItems as $specifed_items) {
            if ($coverages->status != 1 && $specifed_items->deleted_at == null) {
                $specSum += (float) str_replace(',', '', $specifed_items->calculated_value ?? 0);
            }
        }
        if ($specSum == 0) {
            continue;
        }
        // Code wins; the name is consulted only when the code matched no
        // family (blank code on legacy rows, or an alias not listed above).
        $miscFamily = null;
        foreach ($specialistMiscFamilies as $famKey => $famMatch) {
            if ($coverageCode !== '' && in_array($coverageCode, $famMatch[0], true)) {
                $miscFamily = $famKey;
                break;
            }
        }
        if ($miscFamily === null) {
            foreach ($specialistMiscFamilies as $famKey => $famMatch) {
                if ($coverageName !== '' && in_array($coverageName, $famMatch[1], true)) {
                    $miscFamily = $famKey;
                    break;
                }
            }
        }
        if ($miscFamily !== null) {
            $miscByFamily[$miscFamily] = ($miscByFamily[$miscFamily] ?? 0) + $specSum;
        }
    }

    // Add specified items to coverage totals
    $getMedicalTotal = ($getMedicalTotal ?? 0) + ($miscByFamily['medical'] ?? 0);
    $getProfessionalIndemnityTotal = ($getProfessionalIndemnityTotal ?? 0) + ($miscByFamily['pi'] ?? 0);
    $getTravelTotal = ($getTravelTotal ?? 0) + ($miscByFamily['travel'] ?? 0);
    $getMarineCargoOnceOffTotal = ($getMarineCargoOnceOffTotal ?? 0) + ($miscByFamily['marineOnceOff'] ?? 0);
    $getMarineCargoOpenTotal = ($getMarineCargoOpenTotal ?? 0) + ($miscByFamily['marineOpen'] ?? 0);
    $getMarineDirectorsOfficersTotal = ($getMarineDirectorsOfficersTotal ?? 0) + ($miscByFamily['marineDO'] ?? 0);
    $getMachineryBreakdownTotal = ($getMachineryBreakdownTotal ?? 0) + ($miscByFamily['machinery'] ?? 0);
    $getMedicalEvacuationTotal = ($getMedicalEvacuationTotal ?? 0) + ($miscByFamily['medicalEvac'] ?? 0);
    $getCommercialCrimeTotal = ($getCommercialCrimeTotal ?? 0) + ($miscByFamily['crime'] ?? 0);
    $getEnvironmentalLiabilityTotal = ($getEnvironmentalLiabilityTotal ?? 0) + ($miscByFamily['environmental'] ?? 0);
    $getBondsTotal = ($getBondsTotal ?? 0) + ($miscByFamily['bonds'] ?? 0);
    $getMedicalTotal               = $getMedicalTotal               ?? 0;
    $getProfessionalIndemnityTotal = $getProfessionalIndemnityTotal ?? 0;
    $getTravelTotal                = $getTravelTotal                ?? 0;
    $getlimitIndemnity             = $getlimitIndemnity             ?? 0;
    $getMarineCargoOnceOffTotal       = $getMarineCargoOnceOffTotal       ?? 0;
    $getMarineCargoOpenTotal          = $getMarineCargoOpenTotal          ?? 0;
    $getMarineDirectorsOfficersTotal  = $getMarineDirectorsOfficersTotal  ?? 0;
    $getMachineryBreakdownTotal       = $getMachineryBreakdownTotal       ?? 0;
    $getMedicalEvacuationTotal        = $getMedicalEvacuationTotal        ?? 0;
    $getCommercialCrimeTotal          = $getCommercialCrimeTotal          ?? 0;
    $getEnvironmentalLiabilityTotal   = $getEnvironmentalLiabilityTotal   ?? 0;
    $getBondsTotal                    = $getBondsTotal                    ?? 0;
    $grandTotalPremium = $getMedicalTotal + $getProfessionalIndemnityTotal + $getTravelTotal
        + $getMarineCargoOnceOffTotal + $getMarineCargoOpenTotal + $getMarineDirectorsOfficersTotal
        + $getMachineryBreakdownTotal + $getMedicalEvacuationTotal + $getCommercialCrimeTotal
        + $getEnvironmentalLiabilityTotal + $getBondsTotal;

        $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
        
        $premiumExcludingVAT =  ($grandTotalPremium) / (1 + ($regionVat/100));
        
        if(($policy->premium_freq)==1){
            $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
        }

        $vat = $premiumExcludingVAT * ($regionVat/100);
        $ServiceCharge8 = $premiumExcludingVAT*(8/100);
        $vatServiceCharge = $ServiceCharge8*($regionVat/100);
        
        $vat_pro_data = $vat;
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
                    @if($flag == 'v2_quotationPdfSpecialistProduct')
                       <span class="brand-blue">www.alphadirect.co.bw</span>
                    @else
                        debtors@alphadirect.co.bw | <span class="brand-blue">www.alphadirect.co.bw</span>
                    @endif
                </div>
            </div>
            </div>
        </td>
        <td class="no-border text-right" style="width: 50%; vertical-align: middle;">
            <div class="brand-blue" style="font-size: 18px; font-weight: 700;">
                @if($flag == 'v2_quotationPdfSpecialistProduct')
                    INSURANCE QUOTATION/PROPOSAL
                @else
                    POLICY DOCUMENT
                @endif</div>
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
        <td>{{ data_get($policy, 'agency.address', '') }}</td>
    </tr>
    <tr>
        <td class="brand-blue">Agent Name:</td>
        {{-- The agent is policies.agent_id -> users, exposed on the model as
             the `user` relation (Policy::user()). There is no `agent` relation
             and no users.name column, so `agent.name` silently resolved to ''
             and EVERY specialist-product quote (17, 18, 19, 20, 22, 23, 24)
             printed a blank Agent Name even with an agent selected. Reads the
             same way as v2-quote-sheet-engineering:187 / v2-quote-sheet:431. --}}
        <td>{{ trim(data_get($policy, 'user.firstName', '') . ' ' . data_get($policy, 'user.lastName', '')) }}</td>
        
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
    <tr>
       
        <td class="brand-blue">Period of Insurance:</td>
        <td>{{ Carbon\Carbon::parse($policyTerm->term_start_date)->format('d/m/Y') }} to {{ Carbon\Carbon::parse($policyTerm->term_end_date)->format('d/m/Y') }}</td>        
    </tr>
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
            {{ Carbon\Carbon::parse($policy->profile->date)->format('d/m/Y') }}
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
    
    @foreach($all_coverages as $coverage)
    <tr>
        <td>{{ $coverage->s_ScreenName ?? '' }}</td>
        <td> @php
                $x = 0 ;
                $y = 0 ;
                $z = 0 ;
            @endphp
            @foreach($policy_coverages as $index => $coverages)
                @if ($coverage->s_ScreenName == $coverages->coverage->s_ScreenName)
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
        </td>
        {{-- Pro-rata computed in PHP (SpecialistEndorseCalculator::proRataInclVatByScreenName),
             passed via $specialistProRata keyed by screen name — no calculation here. --}}
        <td class="text-center">@if(($specialistProRata[$coverage->s_ScreenName]['refundInclVat'] ?? 0) > 0)P - {{ number_format($specialistProRata[$coverage->s_ScreenName]['refundInclVat'], 2, '.', ',') }}@else P 0.00 @endif</td>
        <td class="text-center">P {{ number_format($specialistProRata[$coverage->s_ScreenName]['premiumInclVat'] ?? 0, 2, '.', ',') }}</td>
        <td class="text-center">

            @php
                $covCode = strtoupper((string) ($coverage->s_CoverageCode ?? ''));
            @endphp
            @if($coverage->s_ScreenName == 'Medical Malpractice')
                P {{ number_format($getMedicalTotal  ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Professional Indemnity')
                P {{ number_format($getProfessionalIndemnityTotal  ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Travel Insurance')
                P {{ number_format($getTravelTotal  ?? 0, 2) }}
            @elseif(in_array($coverage->s_ScreenName, ['Marine Cargo Once-Off', 'Marine Cargo Once Off']) || in_array($covCode, ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF']))
                P {{ number_format($getMarineCargoOnceOffTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Marine Cargo Open' || in_array($covCode, ['MARINEOPENCOVER', 'MARINECARGOOPEN']))
                P {{ number_format($getMarineCargoOpenTotal ?? 0, 2) }}
            {{-- 'Directors and Officers Liability' is the live s_ScreenName for the
                 Marine D&O coverage on Marine (product 22) policies; the schedule
                 dispatch below sends it to marine_directors_officers_pdf, so the
                 Index Gross must read from the same Marine D&O bucket (Premium +
                 MISC ITEM rebuilt in GenerateQuotationPdfJob) — not from
                 $getDirectorsOfficersTotal, which only picked up stray
                 policy_specified_items and made the row disagree with the Total
                 Premium / VAT below. --}}
            @elseif(in_array($coverage->s_ScreenName, ['Marine Directors & Officers', 'Directors & Officers', 'Directors and Officers Liability']) || $covCode === 'MARINEDIRECTORSOFFICERS')
                P {{ number_format($getMarineDirectorsOfficersTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Machinery Breakdown' || $covCode === 'MACHINERYBREAKDOWN')
                P {{ number_format($getMachineryBreakdownTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Medical Evacuation' || $covCode === 'MEDICALEVACUATION')
                P {{ number_format($getMedicalEvacuationTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Commercial Crime' || $covCode === 'COMMERCIALCRIME')
                P {{ number_format($getCommercialCrimeTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Environmental Liability' || $covCode === 'ENVIRONMENTALLIABILITY')
                P {{ number_format($getEnvironmentalLiabilityTotal ?? 0, 2) }}
            @elseif($coverage->s_ScreenName == 'Bonds and Guarantees' || $covCode === 'BONDSANDGUARANTEES')
                P {{ number_format($getBondsTotal ?? 0, 2) }}
            @else
                P 0.00
            @endif
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
    @php
        // Risk Address: prefer the free-text address_name label, but fall back
        // to the structured captured address (physical_address + city + state)
        // when the label is blank. Previously this rendered only address_name,
        // so the Quote Sheet showed an empty "Risk Address:" line whenever the
        // label field was left empty even though the address was captured.
        // Strictly additive — identical output when address_name is present.
        // (Quote Sheet fix #1, 2026-06-15.)
        $riskAddr = $coverages->risk_address_id
            ? AlphaDirect\Models\RiskAddress::find($coverages->risk_address_id)
            : null;
        $addressName = $riskAddr?->address_name ?: '';
        if ($addressName === '' && $riskAddr) {
            $addressName = trim(implode(', ', array_filter([
                $riskAddr->physical_address ?: null,
                $riskAddr->city->name ?? null,
                $riskAddr->state->name ?? null,
            ])));
        }
        $covCode = strtoupper((string) ($coverages->coverage->s_CoverageCode ?? ''));
        $covScreenName = (string) ($coverages->coverage->s_ScreenName ?? '');

        // Fallback for a historical batch of policies (product_id 19) whose
        // coverage_id points at a Medical Malpractice/Professional Indemnity/
        // Travel Insurance coverage master row (5016/5014/5015) that was later
        // retired in favour of new rows (1714/1715/1716) without backfilling
        // existing policy_coverages — so $coverages->coverage is null and
        // $covScreenName is blank, even though the specialist row (and its
        // Notes) are saved correctly. Detect the type directly from which
        // dedicated table actually holds a row for this policy_coverage_id.
        if ($covScreenName === '') {
            if (\AlphaDirect\Models\MedicalMalpracticeCoverage::where('policy_coverage_id', $coverages->id)->exists()) {
                $covScreenName = 'Medical Malpractice';
            } elseif (\AlphaDirect\Models\ProfessionalIndemnityCoverage::where('policy_coverage_id', $coverages->id)->exists()) {
                $covScreenName = 'Professional Indemnity';
            } elseif (\AlphaDirect\Models\TravelCoverage::where('policy_coverage_id', $coverages->id)->exists()) {
                $covScreenName = 'Travel Insurance';
            }
        }
    @endphp
    @if($covScreenName == 'Medical Malpractice')
        @include('v2/livewire/pdf/medical_malpractice_pdf')
    @elseif($covScreenName == 'Professional Indemnity')
        @include('v2/livewire/pdf/professional_indemnity_pdf')
    @elseif($covScreenName == 'Travel Insurance')
        @include('v2/livewire/pdf/travel_insurance_pdf')
    @elseif(in_array($covScreenName, ['Marine Cargo Once-Off', 'Marine Cargo Once Off']) || in_array($covCode, ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF']))
        @include('v2/livewire/pdf/marine_cargo_once_off_pdf')
    @elseif($covScreenName == 'Marine Cargo Open' || in_array($covCode, ['MARINEOPENCOVER', 'MARINECARGOOPEN']))
        @include('v2/livewire/pdf/marine_cargo_open_pdf')
    @elseif(in_array($covScreenName, ['Marine Directors & Officers', 'Directors & Officers', 'Directors and Officers Liability']) || $covCode === 'MARINEDIRECTORSOFFICERS')
        @include('v2/livewire/pdf/marine_directors_officers_pdf')
    @elseif($covScreenName == 'Machinery Breakdown' || $covCode === 'MACHINERYBREAKDOWN')
        @include('v2/livewire/pdf/machinery_breakdown_pdf')
    @elseif($covScreenName == 'Medical Evacuation' || $covCode === 'MEDICALEVACUATION')
        @include('v2/livewire/pdf/medical_evacuation_pdf')
    @elseif($covScreenName == 'Commercial Crime' || $covCode === 'COMMERCIALCRIME')
        @include('v2/livewire/pdf/commercial_crime_pdf')
    @elseif($covScreenName == 'Environmental Liability' || $covCode === 'ENVIRONMENTALLIABILITY')
        @include('v2/livewire/pdf/environmental_liability_pdf')
    @elseif($covScreenName == 'Bonds and Guarantees' || $covCode === 'BONDSANDGUARANTEES')
        @include('v2/livewire/pdf/bonds_pdf')
    @endif
@endforeach
<table style="margin-top: 6px;">
    <tr>
    </tr>
</table>
<table>
    <tr>
        <td colspan="4" class="text-left">Final Premium Excluding VAT</td>
        <td class="text-center">P {{ number_format(($policyAction->transaction_type ?? '') === 'ENDORSE' ? ($specialistProRataFinalExclVat ?? 0) : $premiumExcludingVAT, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left">VAT</td>
        <td class="text-center">P {{ number_format(($policyAction->transaction_type ?? '') === 'ENDORSE' ? ($specialistProRataFinalVat ?? 0) : $vat_pro_data, 2) }}</td>
    </tr>
    @if(($policyAction->transaction_type ?? '') !== 'ENDORSE' && ($policy->premium_freq)==1)
    <tr>
        <td colspan="4" class="text-left">8% Service Charge On Monthly Payment</td>
        <td class="text-center">P {{ number_format($ServiceCharge8, 2) }}</td>
    </tr>
    <tr>
        <td colspan="4" class="text-left">14% VAT On Service Charge</td>
        <td class="text-center">P {{ number_format($vatServiceCharge, 2) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="4" class="text-left">Final Premium Including VAT</td>
        <td class="text-center">P {{ number_format(($policyAction->transaction_type ?? '') === 'ENDORSE' ? ($specialistProRataFinalInclVat ?? 0) : $grandTotalPremium, 2) }}</td>
    </tr>
</table>
</body>
</html>