@php
ini_set('memory_limit', '128M');
@endphp

<!Doctype html >
<html>
<head>
    <TITLE>Policy Schedule</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet"> --}}
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            font-size: 12px;
        }

        .container {
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        @media (min-width: 768px) {
            .container {
                width: 750px;
            }
        }

        @media (min-width: 992px) {
            .container {
                width: 970px;
            }
        }

        @media (min-width: 1200px) {
            .container {
                width: 1170px;
            }
        }

        .clearfix:after,
        .clearfix:before {
            display: table;
            content: " ";
        }

        .clearfix:after {
            clear: both;
        }
        /* ============================= */

        @media print {
            .table-responsive {
                width: auto;
            }
        }

        b,
        strong {
            font-weight: 700;
        }

        small {
            font-size: 80%;
        }

        hr {
            height: 0;
            border-top: 1px solid #eee;
        }

        .table-responsive {
            min-height: 0.01%;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        table,
        th,
        td {
            border: 1px solid #000000;
        }

        td,
        th {
            padding: 10px 10px;
        }

        .collapse {
            display: none;
        }

        @media print {
            *,
             :after,
             :before {
                color: #000 !important;
                background: 0 0 !important;
            }
            tr {
                page-break-inside: avoid;
            }
            img {
                max-width: 100% !important;
            }
            .table {
                border-collapse: collapse !important;
            }
            .table-bordered td,
            .table-bordered th {
                border: 1px solid #ddd !important;
            }
        }
        .column {
  float: left;
  width: 50%;
}

/* Clear floats after the columns */
.row:after {
  content: "";
  display: table;
  clear: both;
}
    </style>
</head>

<body>
    <div id="content" style="margin-bottom: 0px;">
        <div class="container clearfix">
            <div style="margin-bottom: 0px;">
                <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo">
            </div>
            <div>
                <p style="margin-bottom: 0px; text-align: right;"><span>{{ $today }}</span></p>
                <p style="margin-bottom: 20px"><br>Dear<span> {{ $cust_name }}</span></p>
                <p style="margin-bottom:30px;"><span><U><b>Alpha Direct Domestic Motor Policy</b> </U></span></p>
                <p style="margin: 0px">
                    <b>Insured:</b><span> {{ $cust_name }}</span><br>
                    <b>Policy Number: </b><span> {{ $policy->policyNumber }}</span>
                </p>
                <p style="margin-bottom: 0px">Thank you for activating a
                    <b>digital insurance policy</b> {{ $policy->policyNumber }} with Alpha Direct Insurance Company
                </p>
                <p style="margin-bottom: 0px">Should you require any changes to your policy, or other information or assistance, you may reach our contact centre toll free at (0800) 601 029, or by email at:
                    <a href="mailto:businessdev@alphadirect.co.bw">
                        <U style="color: #0563c1;">businessdev@alphadirect.co.bw</U>
                    </a>.
                </p>
                <p style="margin-bottom: 0px;margin-top: 0px">Most policy changes can be effected through our customer service portal at:
                    <a href="http://www.alphadirect.co.bw">
                        <U style="color: #0563c1;">www.alphadirect.co.bw</U>
                    </a>
                </p>
                <p style="margin-bottom: 0px;margin-top: 0px">Please feel free to contact us should you require any details or information on the information contained in this document.We are delighted to extend our incredible customer experience to you, and trust you have found the process of working
                    with us to be effortless, simple and affordable.
                </p>
                <p style="margin-bottom: 0px">Thank you for being a part of the Alpha Direct family.</p>
            </div>

            <div class="row">
                <div class="column"><img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/sign2/sign.png" alt="Sign" width="180px"></div>
                <div class="column left"><img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Stamp/stamp.png" align="right" alt="stamp" width="110px"></div>
              </div>
            <div style="margin-bottom: 0px;">
                <p style="margin-bottom: 0px;margin-top: 0px">Toll Free: 0800 601 029 | Tel: (+267) 392 8264 | Fax: (+267) 392 8265 |</p>
                <p style="margin-bottom: 0px;margin-top: 0px">Office: Floor 2, Bar 2, Botswana Innovation Hub Icon Building, Plot 69184 Block 8 Industrial</p>
                <p style="margin-bottom: 0px;margin-top: 0px">Postal Address: P.O. Box 26ADC Gaborone, Botswana</p>
                <hr>
                <p style="color: #1f497D;margin-bottom: 0px;margin-top: 0px;max-width: 850px;">Kiosks Located Nationwide: Choppies Lobatse Barclays Mall | Choppies Mahalapye Watershed Mall Choppies Francistown Loja Mall | Choppies Letlhakane | Choppies Phakalane Mowana </p>
                <p style="color: #1f497D;margin-bottom: 0px;margin-top: 0px">Choppies Game City | Choppies Maun Boseja | Choppies Supa Save Palapye</p>
            </div>
            <p style="margin-bottom: 0px; page-break-before: always">
                <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo">
            </p>
            <p style="margin-top: 0px;color: #1f497d;font-size: 18px;">
                <b>This Policy should be read in conjunction with the Policy wording</b>
            </p>
            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000; margin: 0px;">
                            <b>POLICY NUMBER</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ $policy->policyNumber }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>TRANSACTION TYPE </b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ $policyAction->transaction_type  }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>VERSION NUMBER</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                           -
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>INSURED / YOU</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                           {{$cust_name}}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>YOUR TAX NUMBER</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ $company->VAT_registration_number?? ""}}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>INSURED  BUSINESS DESCRIPTION</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">-</p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>YOUR POSTAL ADDRESS</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            @if($policy->profile->entity_type=='Organisation')
                                {!!$policy->profile->company->postal_address??"" !!}{!! $policy->profile->city??"" !!} {!! $policy->profile->state??"" !!}
                            @else
                                {!! $policy->profile->address??"" !!} {!! $policy->profile->city??"" !!} {!! $policy->profile->state??"" !!}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>INTERMEDIARY</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ $agentName }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>INSURER</b>
                        </p>
                    </td>
                    <td style="margin: 0px;">
                        <p>
                            <b> Alpha Direct Insurance Company</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>TERRITORIAL LIMITS </b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            Botswana
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>PERIOD OF INSURANCE</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin-left: 0.12in; margin-top: 0.17in; margin-bottom: 0.18in">
                            <b>From: {{ Carbon::parse(str_replace("/", "-", $fromDate))->format('M d, Y') }}</b>
                        </p>
                        <p style="margin-left: 0.12in; margin-top: 0.17in; margin-bottom: 0.18in">
                            <b>To: {{ Carbon::parse(str_replace("/", "-", $toDate))->format('M d, Y') }}</b>
                            <font color="#1f497d">:
                            </font><br><span><br>Including both dates.</span>
                        </p>
                        <p style="margin-bottom: 0px;">Including both dates and any subsequent period the Company agrees to renew this Policy or any section thereof subject to any revised terms required by the Company
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>RENEWAL DATE</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            @if($policy->renewals_date != null)
                            {{ Carbon::parse(str_replace("/", "-", $policy->renewals_date))->format('M d, Y') }}
                            @else
                            -
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>ANNIVERSARY</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ Carbon::parse(str_replace("/", "-", $anniversaryDate))->format('M d, Y') }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>TYPE OF CONTRACT </b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            @if(($policy->premium_freq)!= null)
                                @if($policy->premium_freq==1)
                                    MONTHLY
                                @elseif($policy->premium_freq==2)
                                    3 INSTALLMENTS
                                @elseif($policy->premium_freq==3)
                                    ANNUAL
                                @elseif($policy->premium_freq==4)
                                    SEMIANNUAL
                                @elseif($policy->premium_freq==5)
                                    QUARTERLY
                                @endif
                            @endif                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>EFFECTIVE DATE</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ Carbon::parse(str_replace("/", "-", $fromDate))->format('M d, Y') }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;margin: 0px;">
                            <b>ISSUED BY</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;">
                            {{ $agentName }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;">
                        <p style="color: #000000;">
                            <b>PAYMENT TYPE</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;
                        ">
                           @if($bankingDetail != null)
                                @if($bankingDetail->billing == "RealPay") @if($policy->premium_freq == null) Monthly through Bank Direct Debit Authorisation @elseif($policy->premium_freq == 1) Monthly through Bank Direct Debit Authorisation @elseif($policy->premium_freq
                            == 2) 3 Installments through Bank Direct Debit Authorisation @elseif($policy->premium_freq == 3) Yearly through Bank Direct Debit Authorisation @endif @else {{ $bankingDetail->billing }} @endif
                            @else
                                -
                            @endif
                        </p>
                    </td>
                </tr>
            </table>

            <div class="row" style="margin-top: 20px;margin-bottom: 20px;">
                <div class="column" style="width:60%;">
                    <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/sign2/sign.png" style="margin-top: 20px;" alt="Sign" width="180px">
                    <p>
                        <span>SIGNED ON BEHALF OF ALPHA DIRECT INSURANCE CO. </span>
                    </p>
                    <p style="margin-bottom: 20px;"><span>ON: {{ $today }}</span></p>
                </div>
                <div class="column left" style="width:40%;"><img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Stamp/stamp.png" align="right" alt="stamp" width="110px"></div>
              </div>

            <div style="page-break-after:always;"></div>
            <!-- ========  PREMIUM SCHEDULE AND INDEX OF SECTIONS =================== -->
            <p style="margin-top: 0px;color: #1f497d; font-size: 18px">
                <b>PREMIUM SCHEDULE AND INDEX OF SECTIONS</b>
            </p>
            <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 30px;margin-top: 30px;">
                <tr>
                    <td style="background-color:#002060;">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Policy Sections Available</b>
                        </p>
                    </td>
                    <td style="background-color:#002060;">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Section Taken</b>
                        </p>
                    </td>
                    <td style="background-color:#002060;">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Pro Rata Premium</b>
                            <b style="color:#f79646;">(Incl.VAT)</b>
                        </p>
                    </td>
                    <td style="background-color: #002060;">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Monthly Gross</b>
                            <b style="color:#f79646;">(Incl.VAT)</b>
                        </p>
                    </td>
                </tr>
                @php
                $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                $cal = ($regionVat/100)+($policy->premium_freq);
                $calculated_rate =  ($policyAction->premium)/$cal;
                $totalPolicyCoverage = AlphaDirect\Models\PolicyCoverage::ForPolicy($policy->id)->TermId($termId)->ActionId($actionId)->get(['coverage_value','calculated_value']);
                $totalCoverageValue = $totalPolicyCoverage->sum('coverage_value');
                $totalCalculatedValue = $totalPolicyCoverage->sum('calculated_value');
                @endphp


                @foreach ($getAllCoverage as $value)
                    @php
                        if (in_array($value->s_CoverageCode, $seletedCoverages))
                        {
                            // @dd($value->s_CoverageCode, $seletedCoverages);
                            $getPolicyCoverage = AlphaDirect\Models\PolicyCoverage::ForPolicy($policy->id)->TermId($termId)->ActionId($actionId)
                                                 ->where('main',$value->s_CoverageCode)
                                                 ->get(['coverage_value','calculated_value']);
                            $totalSumOfCoverages =  $getPolicyCoverage->sum('coverage_value');
                            $totalSumOfCalculated = $getPolicyCoverage->sum('calculated_value');
                        }
                    @endphp
                    <tr>
                        <td>
                            <p style="color: #002060;margin: 0px;">
                                <b>
                                {{ $value->s_CoverageName }}
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="margin: 0px;">
                                @if (in_array($value->s_CoverageCode, $seletedCoverages))
                                   Yes
                                @else
                                   No
                                @endif
                            </p>
                        </td>
                        <td>
                            <p style="margin: 0px;">
                            @if (in_array($value->s_CoverageCode, $seletedCoverages))
                             P {{ number_format($totalSumOfCalculated ?? 0.00, 2, '.', ',')}}
                                {{-- P {{ number_format($totalSumOfCoverages ?? 0.00, 2, '.', ',') }} --}}
                                <br>
                            @else
                               P 0.00
                            @endif
                            </p>
                        </td>
                        <td>
                            <p style="margin: 0px;">
                            @if (in_array($value->s_CoverageCode, $seletedCoverages))
                               P {{ number_format(($totalSumOfCalculated/12) ?? 0.00, 2, '.', ',')}}
                               <br>
                            @else
                               P 0.00
                            @endif
                            </p>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan=2>
                        <p style="color: #002060;margin: 0px;">
                            <b>TOTAL PREMIUM:</b>
                        </p>
                    </td>
                    <td style="">
                        @if ($totalCalculatedValue != null)
                        <p style="margin: 0px;">P {{ number_format((float)$totalCalculatedValue, 2, '.', ',') }}</p>
                        @else
                        <p style="margin: 0px;">P 0.00</p>
                        @endif
                    </td>
                    <td style="">
                        @if ($totalCalculatedValue != null)
                        <p style="margin: 0px;">P {{ number_format((float)($totalCalculatedValue/12), 2, '.', ',') }}</p>
                        @else
                        <p style="margin: 0px;">P 0.00</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td colspan=2>
                        <p style="color: #002060;margin: 0px;">
                            <b>VAT</b>
                        </p>
                    </td>
                    <td style="">
                        <p style="margin: 0px;">P {{ number_format((float)($totalCalculatedValue * $regionVat/100), 2, '.', ',') }} </p>
                    </td>
                    <td style="">
                        <p style="margin: 0px;">P {{ number_format((float)(($totalCalculatedValue/12) * $regionVat/100), 2, '.', ',') }} </p>
                    </td>
                </tr>
                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                    <td width="25%" colspan="4">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-weight: bold;">This schedule becomes a tax invoice when the total amount requested has been paid. The premium reflected is inclusive of VAT
                            at a rate of 12,00%. (Stamp Duty not applicable). VAT registration number C07098401113</p>
                    </td>
                </tr>
            </table>

            <!-- Effective Date -->
            @foreach($seletedCoverages as $coverageId => $name)
                @php
                    // $getHaveEntityOnly = AlphaDirect\Models\PolicyCoverage::ForPolicy($policy->id)->TermId($termId)->ActionId($actionId)->HaveEntityOnly()->where('main',$name)->get();
                    $getPolicyCoverage = AlphaDirect\Models\PolicyCoverage::ForPolicy($policy->id)->TermId($termId)->ActionId($actionId)->where('main',$name)->get();
                    $totalSumOfRate =  $getPolicyCoverage->sum('rate');
                    $totalSumOfCoverages =  $getPolicyCoverage->sum('coverage_value');
                    $totalSumOfCalculated = $getPolicyCoverage->sum('calculated_value');
                @endphp
                {{-- Specified Items --}}
                @php
                $getSpecifiedItems = AlphaDirect\Models\PolicySpecifiedItem::Policy($policy->id)->Term($termId)->Action($actionId)->Coverage($coverageId)->get();
                @endphp
                <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 0px;margin-top: 30px;">
                    <tbody>
                        <tr>
                            <td style="background-color: #002060;">
                                <p style="color: #ffffff;margin: 0px;">
                                    <b>Specified Items</b>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 30px;margin-top: 0px;">
                    <tbody>
                        <tr>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Name</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Sum Insured</b></p>
                            </td>
                        </tr>
                        @foreach($getSpecifiedItems as $id => $specified)
                        <tr>
                            <td style ="padding:1px;">{{ $specified->specifiedCoveragesItems->specified_name }}</td>
                            <td style ="padding:1px;">
                                {{-- {{ $specified->sum_insured }} --}}
                                {{ number_format((float)($specified->sum_insured), 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{-- End Specified Items --}}

                <hr>
                <p style="color: #002060;margin: 0px;font-size: 18px">{{ $name }}</p>

                <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 30px;margin-top: 30px;">
                    <tbody>
                        <tr>
                            <td style="background-color:#002060;">
                                <p style="color: #ffffff;">
                                    <b>EFFECTIVE DATE</b>
                                </p>
                            </td>
                            <td>
                                <p style="margin-bottom: 0px;">
                                    {{ Carbon::parse(str_replace("/", "-", $fromDate))->format('M d, Y') }}
                                </p>
                            </td>
                            <td style="background-color:#002060;">
                                <p style="color: #ffffff;">
                                    <b>TOTAL PREMIUM</b>
                                </p>
                            </td>
                            <td>
                                <p style="margin: 0px;">P {{ number_format((float)($totalSumOfCalculated), 2, '.', ',') }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td rowspan="2" style="background-color: #ffffff;width: auto;">
                                <p style="color: #1f497d;margin: 0px;">
                                    <b>PHYSICAL LOCATION</b>
                                </p>
                            </td>
                            <td colspan="3" style="background-color: #ffffff;width: auto;  border: 1px solid #ffffff;">
                                @foreach($seletedMainCoverages as $riskAddressId => $selectedList)
                                    @php $riskAddress = AlphaDirect\Models\RiskAddress::where('id',$riskAddressId)->first()?->address_name;@endphp
                                    <p style="margin: 0px;">
                                        {{ $riskAddress??"" }}

                                        {{-- @if(count($getHaveEntityOnly)>0)
                                            @foreach($getHaveEntityOnly as $entityData)
                                                @php
                                                    if($entityData->entity_type=='Risk Address'){
                                                        $entity = AlphaDirect\Models\RiskAddress::where('id',$entityData->entity_id)->first()?->address_name;
                                                    }elseif($entityData->entity_type=='Vehicle'){
                                                        $entity = AlphaDirect\Vehicle::where('id',$entityData->entity_id)->first()?->vehiclePlate;
                                                    }elseif($entityData->entity_type=='Members'){
                                                        $benf = AlphaDirect\PolicyBeneficiary::select('first_name','last_name')->where('id',$entityData->entity_id)->first();
                                                        $entity = $benf->first_name.' '.$benf->last_name;
                                                    }elseif($entityData->entity_type=='Devices'){
                                                        $device = AlphaDirect\PolicyCellPhone::select('cell_phone_model','cell_phone_make','device_type')->where('id',$entityData->entity_id)->first();
                                                        $entity = $device->cell_phone_model.' '.$device->cell_phone_make.' '.$device->device_type;
                                                    }
                                                @endphp
                                                &nbsp;&nbsp; {{$entityData->entity_type}} : {{$entity}} <br>
                                            @endforeach
                                        @else
                                        -
                                        @endif --}}
                                    </p>
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style="background-color: #ffffff;width: auto;">
                                <p style="text-align: center;margin: 0px;">
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                {{-- <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 0px;margin-top: 30px;">
                    <tbody>
                        <tr>
                            <td style="background-color: #002060;">
                                <p style="color: #ffffff;margin: 0px;">Type Of Construction</p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="margin: 0px;">Description </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="margin: 0px;">
                                    -
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table> --}}
                <!-- DETAILS OF COVER -->
                <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 0px;margin-top: 30px;">
                    <tbody>
                        <tr>
                            <td style="background-color: #002060;">
                                <p style="color: #ffffff;margin: 0px;">
                                    <b>Details of Cover</b>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="table-responsive" style="border-collapse: collapse;width:100%;margin-bottom: 30px;margin-top: 0px;">
                    <tbody>
                        <tr>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Column Reference</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Description</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Sum Insured</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;">
                                <p style="margin: 0px;"><b>Premium</b></p>
                            </td>
                        </tr>

                        <tr>
                            @if(count($getPolicyCoverage)>0)
                            <td>
                                @php $i=1;@endphp
                                <p style="margin: 0px;">
                                        @foreach ($getPolicyCoverage as $row)
                                        {{$i++}}<br>
                                        @endforeach
                                </p>
                            </td>
                            <td>
                                <p style="margin: 0px;">
                                        @foreach ($getPolicyCoverage as $row)
                                            @if($row->coverage->s_ScreenName != null)
                                                {!!$row->coverage->s_ScreenName ?? ''!!}   <br>
                                            @endif
                                        @endforeach
                                </p>
                            </td>
                            <td>
                                <p style="margin: 0px;">
                                    @foreach ($getPolicyCoverage as $row)
                                        @if($row->coverage_value!=null)
                                            @if($row->coverage_value!=null)
                                            {{-- P {!!$row->coverage_value ?? ''!!} --}}
                                           P {!! number_format($row->coverage_value ?? '', 2, '.', ',')!!}
                                           <br>
                                            @else
                                            -  <br>
                                            @endif
                                        @endif
                                    @endforeach
                                </p>
                            </td>
                            <td>
                                <p style="margin: 0px;">
                                        @foreach ($getPolicyCoverage as $row)
                                            @if($row->coverage_value!=null)
                                                @if($row->calculated_value!=null)
                                                {{-- P {!!$row->calculated_value ?? ''!!} --}}
                                                P {{ number_format((float)$row->calculated_value ?? '', 2, '.', ',')}}
                                                <br>
                                                @else
                                                -  <br>
                                                @endif
                                            @endif
                                        @endforeach
                                </p>
                            </td>
                            @endif
                        </tr>

                        <tr>
                            <td colspan="2">
                                <p style="margin: 0px;">Total Sum Insured  </p>
                            </td>
                            <td>
                                <p style="margin: 0px;">P {{ number_format((float)$totalSumOfCoverages ?? '', 2, '.', ',')}}</p>
                            </td>
                            <td>
                                <p style="margin: 0px;"> P {{ number_format((float)$totalSumOfCalculated ?? '', 2, '.', ',')}} </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endforeach
            <br>
            <p>DEFINITIONS</p>
            <p>The column references refer to the undermentioned</p>
            <p>Column No 1            Buildings (constructed of brick, stone, concrete or metal frame with brick in-fill, metal frame
                with wall brick in-fill with metal or asbestos cladding above and roof with slate, tiles, metal,
                concrete or asbestos) including landlord’s fixtures and fittings therein and thereon, walls
                (except dam walls), gates, posts and fences.</p>
            <p>Column No 2        The number of months rent/rental value stated in the schedule</p>
            <p>Column No 3        Plant, machinery, landlord’s fixtures and fittings for which the insured is responsible and all
                other contents excluding property more specifically insured.</p>
            <p>Column No 4        Stock and materials in trade</p>
            <p>Column No 5        Miscellaneous as described and tenants improvements.</p>
            <br>
            <div style="page-break-before: always">
                <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo">
            </div>

            <p style="margin-bottom: 0px; color: #002060;">
                <U><B>Important points:</B></U>
            </p>

            <p style="margin-bottom: 0px; color: #002060;">
                <B>Your Alpha Direct Policy</B>
            </p>

            <p style="margin: 0px;">Your contract with us (Alpha Direct Insurance Company) consists of this Policy schedule, your Policy wordings, all written and digital correspondence and declarations and verbal agreements. You need to ensure that all the information is correct.
                Incorrect information may influence the validity of the contract and/or the outcome of your claim.
            </p>
            <p style="margin: 0px;">If anything (at all) is not correct, please contact us immediately to have it updated.
            </p>

            <div style="margin-top: 20px;">
                <p style="margin-top: 0px; margin-bottom: 0px;">Toll Free: 0800 601 029 | Tel: (+267) 392 8264 | Fax: (+267) 392 8265 |</p>
                <p style="margin-top: 0px; margin-bottom: 0px;">Office: Floor 2, Bar 2, Botswana Innovation Hub Icon Building, Plot 69184 Block 8 Industrial</p>
                <p style="margin-top: 0px; margin-bottom: 20px;">Postal Address: P.O. Box 26ADC Gaborone, Botswana</p>
                <hr>
                <p style="color: #1f497D;margin-bottom: 0px;margin-top: 20px;max-width: 850px;">Kiosks Located Nationwide: Choppies Lobatse Barclays Mall | Choppies Mahalapye Watershed Mall Choppies Francistown Loja Mall | Choppies Letlhakane | Choppies Phakalane Mowana </p>
                <p style="color: #1f497D;margin-bottom: 0px;margin-top: 0px;max-width: 850px;">Choppies Game City | Choppies Maun Boseja | Choppies Supa Save Palapye</p>
            </div>
            <p style="margin-bottom: 50px"></p>
            <!-- container End -->
        </div>
    </div>
</body>

</html>
