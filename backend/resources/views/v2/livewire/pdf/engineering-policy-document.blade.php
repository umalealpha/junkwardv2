<!Doctype html>
<html>
<head>
    <title>{{ $policy->policyNumber ?? 'Policy Document' }} - Engineering Policy Schedule</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        table { border-spacing: 0; border-collapse: collapse !important; width: 100%; }
        th, td { border: 1px solid #2e77c3; padding: 4px 6px; vertical-align: top; }
        .no-border { border: none !important; }
        .brand-blue { color: #2e77c3 !important; }
        .section-title { background: #d9f2d9; font-weight: 700; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
@php
    if (!function_exists('safeFormatDate')) {
        function safeFormatDate($dateValue, $format = 'd/m/Y') {
            if (empty($dateValue)) return '';
            $dateValue = trim((string) $dateValue);
            if (empty($dateValue)) return '';
            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) return $dateValue;
                if (strpos($dateValue, '/') !== false && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateValue)) {
                    try { return \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue)->format($format); } catch (\Exception $e) {}
                }
                if (strpos($dateValue, '-') !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $dateValue)) {
                    try { return \Carbon\Carbon::createFromFormat('Y-m-d', substr($dateValue, 0, 10))->format($format); } catch (\Exception $e) {}
                }
                try { return \Carbon\Carbon::parse($dateValue)->format($format); } catch (\Exception $e) {}
                return $dateValue;
            } catch (\Exception $e) {
                return $dateValue;
            }
        }
    }
    $today       = $today ?? now()->format('d/m/Y');
    $policyNumber = $policy->policyNumber ?? '';
    $lineOfBusiness = data_get($policy, 'product.name', 'Engineering Insurance');
    $insuredName = '';
    if (data_get($policy, 'profile.entity_type') === 'Organisation') {
        $insuredName = data_get($policy, 'profile.company.name', '');
    } else {
        $insuredName = trim(
            data_get($policy, 'customer.firstName', '') . ' ' .
            data_get($policy, 'customer.middleName', '') . ' ' .
            data_get($policy, 'customer.lastName', '')
        );
    }
    $insuredAddress = '';
    if (data_get($policy, 'profile.entity_type') === 'Organisation') {
        $insuredAddress = trim(data_get($policy, 'profile.company.postal_address', '') . ', ' . data_get($policy, 'profile.company.cities.name', ''));
    } else {
        $insuredAddress = trim(data_get($policy, 'profile.post_address', '') . ', ' . data_get($policy, 'profile.cities.name', ''));
    }
    // Total premium: prefer an explicit policy.premium when present, otherwise
    // sum the schedule totals from the engineering coverage tables. EAR/CAR/
    // PAR each have their own total_premium column populated from the
    // SpecialistCoverage form (Total Premium auto-cascade for EAR).
    // MM/PI store their premium on annual_premium (not total_premium).
    $totalPremium = (float)($policy->premium ?? 0);
    if ($totalPremium <= 0) {
        $sum = 0.0;
        foreach ($engineeringCoverages as $covRow) {
            $sum += (float)(data_get($covRow, 'earCoverage.total_premium', 0));
            $sum += (float)(data_get($covRow, 'carCoverage.total_premium', 0));
            $sum += (float)(data_get($covRow, 'parCoverage.total_premium', 0));
            $sum += (float)(data_get($covRow, 'medicalMalpracticeCoverage.annual_premium', 0));
            $sum += (float)(data_get($covRow, 'professionalIndemnityCoverage.annual_premium', 0));
            // Travel premium = total (policy_amount + vat), the VAT-inclusive
            // figure actually charged — policy_amount alone is pre-VAT.
            $sum += (float)(data_get($covRow, 'travelCoverage.total', 0));
            // Marine / Machinery Breakdown — premium captured on the
            // dedicated `premium` column of each specialist table.
            $sum += (float)(data_get($covRow, 'machineryBreakdownCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'marineCargoOnceOffCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'marineCargoOpenCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'marineDirectorsOfficersCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'medicalEvacuationCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'commercialCrimeCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'environmentalLiabilityCoverage.premium', 0));
            $sum += (float)(data_get($covRow, 'bondsCoverage.premium', 0));
        }
        // Final fallback to the precomputed totals from the controller.
        if ($sum <= 0) {
            $sum = (float)($getMedicalTotal ?? 0)
                 + (float)($getProfessionalIndemnityTotal ?? 0)
                 + (float)($getTravelTotal ?? 0)
                 + (float)($getMachineryBreakdownTotal ?? 0)
                 + (float)($getMarineCargoOnceOffTotal ?? 0)
                 + (float)($getMarineCargoOpenTotal ?? 0)
                 + (float)($getMarineDirectorsOfficersTotal ?? 0)
                 + (float)($getMedicalEvacuationTotal ?? 0)
                 + (float)($getCommercialCrimeTotal ?? 0)
                 + (float)($getEnvironmentalLiabilityTotal ?? 0)
                 + (float)($getBondsTotal ?? 0);
        }
        $totalPremium = $sum;
    }
    $regionVat    = \AlphaDirect\Region::where('id', 7)->value('vat') ?? 14;
    $premExcVAT   = $totalPremium / (1 + $regionVat / 100);
    $vatAmt       = $premExcVAT * ($regionVat / 100);
@endphp

<p class="text-right" style="margin: 0 0 6px 0;">As On: {{ $today }}</p>

<table class="no-border">
    <tr>
        <td class="no-border" style="width: 50%;">
            <img style="width: 300px;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
            <div style="margin-top: 6px;">
                <div>Alpha Direct Insurance Co. (Pty) Ltd.</div>
                <div>Floor 2, Bar 2, Botswana Innovation Hub, Icon Building, Plot 69184 Block 8 Industrial</div>
                <div>P.O. Box 26ADC, Gaborone, Botswana</div>
                <div>Phone +267 392 8264 | debtors@alphadirect.co.bw</div>
            </div>
        </td>
        <td class="no-border text-right" style="width: 50%; vertical-align: middle;">
            <div class="brand-blue" style="font-size: 18px; font-weight: 700;">POLICY DOCUMENT</div>
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
                <tr><td class="brand-blue">Line of Business:</td><td>{{ $lineOfBusiness }}</td></tr>
                <tr><td class="brand-blue">Policy No.:</td><td>{{ $policyNumber }}</td></tr>
                <tr><td class="brand-blue">Period From:</td><td>{{ $fromDate ?? '' }}</td></tr>
                <tr><td class="brand-blue">Period To:</td><td>{{ $toDate ?? '' }}</td></tr>
                <tr><td class="brand-blue">Agency:</td><td>{{ data_get($policy, 'agency.name', '') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── Engineering Coverage Sections ─────────────────────────────── --}}
@foreach($engineeringCoverages as $coverages)
    @php
        $coverageCode = strtoupper($coverages->coverage->s_CoverageCode ?? '');
        $screenName   = $coverages->coverage->s_ScreenName ?? $coverageCode;
        // Resolve $addressName once per coverage row. The included PDF
        // partials (travel_insurance_pdf, professional_indemnity_pdf,
        // medical_malpractice_pdf, marine_*) all reference $addressName for
        // their "Risk Address" header; without this, each include throws
        // "Undefined variable $addressName" when rendered from this path.
        // Synthetic-fallback objects (no policy_coverages match) lack
        // risk_address_id — fall through to empty string in that case.
        $addressName = isset($coverages->risk_address_id) && $coverages->risk_address_id
            ? (\AlphaDirect\Models\RiskAddress::where('id', $coverages->risk_address_id)->value('address_name') ?? '')
            : '';
    @endphp

    @if($coverages->carCoverage)
        @include('v2/livewire/pdf/contractors_all_risk')
    @elseif($coverages->earCoverage)
        @include('v2/livewire/pdf/erection_all_risk')
    @elseif($coverages->parCoverage)
        @include('v2/livewire/pdf/plant_all_risk')
    @elseif(!empty($coverages->medicalMalpracticeCoverage))
        @include('v2/livewire/pdf/medical_malpractice_pdf', ['mmCoverage' => $coverages->medicalMalpracticeCoverage])
    @elseif(!empty($coverages->professionalIndemnityCoverage))
        @include('v2/livewire/pdf/professional_indemnity_pdf', ['piCoverage' => $coverages->professionalIndemnityCoverage])
    @elseif(!empty($coverages->travelCoverage))
        @include('v2/livewire/pdf/travel_insurance_pdf')
    @elseif(!empty($coverages->machineryBreakdownCoverage))
        @include('v2/livewire/pdf/machinery_breakdown_pdf')
    @elseif(!empty($coverages->marineCargoOnceOffCoverage))
        @include('v2/livewire/pdf/marine_cargo_once_off_pdf')
    @elseif(!empty($coverages->marineCargoOpenCoverage))
        @include('v2/livewire/pdf/marine_cargo_open_pdf')
    @elseif(!empty($coverages->marineDirectorsOfficersCoverage))
        @include('v2/livewire/pdf/marine_directors_officers_pdf')
    @elseif(!empty($coverages->medicalEvacuationCoverage))
        @include('v2/livewire/pdf/medical_evacuation_pdf')
    @elseif(!empty($coverages->commercialCrimeCoverage))
        @include('v2/livewire/pdf/commercial_crime_pdf')
    @elseif(!empty($coverages->environmentalLiabilityCoverage))
        @include('v2/livewire/pdf/environmental_liability_pdf')
    @elseif(!empty($coverages->bondsCoverage))
        @include('v2/livewire/pdf/bonds_pdf')
    @else
        <div style="margin: 8px 0; padding: 6px; border: 1px solid #ccc;">
            <strong>{{ $screenName }}</strong> — No detailed schedule data available.
        </div>
    @endif
@endforeach

{{-- ── Premium Summary ─────────────────────────────────────────────── --}}
<table style="margin-top: 12px;">
    <tr>
        <td colspan="3" class="text-left section-title">Premium Summary</td>
    </tr>
    <tr>
        <td colspan="2">Total Premium Excluding VAT</td>
        <td class="text-right">P {{ number_format($premExcVAT, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">VAT ({{ $regionVat }}%)</td>
        <td class="text-right">P {{ number_format($vatAmt, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold;">Total Premium Including VAT</td>
        <td class="text-right" style="font-weight: bold;">P {{ number_format($totalPremium, 2) }}</td>
    </tr>
</table>

<table style="margin-top: 8px;">
    <tr>
        <td colspan="2">Authorised Signature: ________________________</td>
        <td colspan="2">Date: ________________________</td>
    </tr>
</table>
</body>
</html>
