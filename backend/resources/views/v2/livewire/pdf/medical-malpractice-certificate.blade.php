<!doctype html>
<html>
<head>
    <title>{{ data_get($policy, 'policyNumber', data_get($policy, 'policy_number', 'Medical Malpractice Certificate')) }} - Medical Malpractice Certificate</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 12px;
            color: #000;
        }
        .certificate {
            border: 2px solid #000;
            padding: 24px 28px;
            min-height: 980px;
            position: relative;
        }
        .logo {
            width: 230px;
        }
        .title {
            text-align: center;
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 20px;
        }
        .subtitle {
            text-align: center;
            font-size: 12px;
            margin-top: 2px;
            font-size: 20px;
        }
        .label {
            width: 160px;
            
            vertical-align: top;
            white-space: nowrap;
            font-size: 18px;
        }
        .value {
            width: 320px;
            vertical-align: top;
            font-size: 18px;
        }
        .row {
            margin-top: 8px;
        }
        .signature {
            margin-top: 18px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 300px;
            margin-top: 8px;
        }
        .signature-line img {
            width: 100%;
            height: 100%;
        }
        .seal {
            width: 120px;
        }
        .muted {
            color: #333;
        }
    </style>
</head>
<body>
@php
    $medicalCoverage = $medicalCoverage ?? \AlphaDirect\Models\MedicalMalpracticeCoverage::where(
        'policy_id',
        data_get($policy ?? null, 'id')
    )->whereNotNull('policy_coverage_id')->latest()->first();
    
    $policyNumber = data_get($medicalCoverage, 'policy_number')
        ?: data_get($policy, 'policyNumber', data_get($policy, 'policy_number', ''));
    $agencyName = data_get($policy, 'agency.name', '');
    $insuredName = data_get($medicalCoverage, 'insured');
    if (!$insuredName) {
        if (data_get($policy, 'profile.entity_type') === 'Organisation') {
            $insuredName = data_get($policy, 'profile.company.name', '');
        } else {
            $insuredName = trim(
                (data_get($policy, 'customer.firstName', '') . ' ' .
                data_get($policy, 'customer.middleName', '') . ' ' .
                data_get($policy, 'customer.lastName', ''))
            );
        }
    }

    $insuredAddress = data_get($medicalCoverage, 'insured_postal_address');
    if (!$insuredAddress) {
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
    }

    $basisOfCover = data_get($medicalCoverage, 'basis_of_cover', 'Professional Indemnity/Medmal');

    $riskDetails = is_array(data_get($medicalCoverage, 'risk_details')) ? data_get($medicalCoverage, 'risk_details') : [];
    $limitOfIndemnity = data_get($medicalCoverage, 'limit_of_indemnity');
    if (!$limitOfIndemnity && !empty($riskDetails)) {
        $limitOfIndemnity = data_get($riskDetails, '0.value');
    }

    $periodStart = data_get($medicalCoverage, 'policy_inception_date')
        ?: data_get($policy, 'term_start_date', data_get($policyTerm ?? null, 'term_start_date'));
    $periodEnd = data_get($medicalCoverage, 'policy_expiry_date')
        ?: data_get($policy, 'term_end_date', data_get($policyTerm ?? null, 'term_end_date'));
    $todayDate = data_get($medicalCoverage, 'today_date') ?: now();

 

  
  $logoUrl =  URL::to('/motor/img/2.png'); 
  $sealUrl = URL::to('/motor/img/3.jpg');
  $signatureUrl = URL::to('/motor/img/1.jpg');
  $getlimitIndemnity = $getlimitIndemnity ?? 0;
  $getlimitIndemnityCer = $getlimitIndemnityCer ?? 0;
@endphp

<div class="certificate">
    <table style="width: 100%;">
        <tr>
            <td style="width: 45%;">
                <img class="logo" src="{{ $logoUrl }}" alt="Alpha Direct">
            </td>
           
        </tr>
        <tr>
            <td style="width: 55%;">
                <div class="title">PROFESSIONAL INDEMNITY INSURANCE</div>
                <div class="subtitle">CERTIFICATE OF INSURANCE</div>
            </td>
        </tr>
    </table>

    <table class="row" style="width: 100%; margin-top: 14px;">
        <tr>
            <td class="label">Policy Number:</td>
            <td class="value">{{ $policyNumber ?: '__________________' }}</td>
        </tr>
        <tr>
            <td class="label">Agency:</td>
            <td class="value">{{ $agencyName ?: '__________________' }}</td>
        </tr>
        <tr>
            <td class="label">This is to certify that</td>
            <td class="value">{{ $insuredName ?: '__________________' }}</td>
        </tr>
        <tr>
            <td class="label">Residential Address:</td>
            <td class="value">{{ $insuredAddress ?: '__________________' }}</td>
        </tr>
        <tr>
            <td class="label">Basis of Cover:</td>
            <td class="value">{{ $basisOfCover }}</td>
        </tr>
        <tr>
            <td class="label">Limit of Indemnity:</td>
            <td class="value">
                @if(is_numeric($getlimitIndemnityCer))
                    P {{ number_format((float) $getlimitIndemnityCer, 2) }}
                @else
                    {{ $getlimitIndemnityCer ?: '__________________' }}
                @endif
            </td>
        </tr>
        <tr>
            
            <td class="label" colspan="2">is insured with for Professional Indemnity Insurance as per the details above.</td>
        </tr>
    </table>

    <table class="row" style="width: 100%; margin-top: 16px;">
        <tr>
            <td class="label">Period of Insurance:</td>
            <td class="value">
                From
                {{ $periodStart ? \Carbon\Carbon::parse($periodStart)->format('d/m/Y') : '____/____/____' }}
                to
                {{ $periodEnd ? \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') : '____/____/____' }}
            </td>
            <td style="width: 40%; text-align: center;">
                <div class="signature">
                    <div class="signature-line"><img src="{{ $signatureUrl }}" alt="Signature"></div>
                   
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 16px;">
        <tr>
            <td class="label" colspan="2">Date: 
                {{ $todayDate ? \Carbon\Carbon::parse($todayDate)->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y') }}   &nbsp;  Signature: Authorized Signatory
                
            </td>
           
            </div>
        </tr>
        <tr>
            <td class="label" style="float: right;margin: 30px 0 0 0;width: 40%; text-align: center;">
                Company Seal:
            </td>
            <td style="width: 40%; text-align: center;">
                <img class="seal" src="{{ $sealUrl }}" alt="Company Seal">
            </td> 
        </tr>
    </table>
</div>
</body>
</html>
