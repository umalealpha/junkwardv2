
<!Doctype html >
<html>
<head>
    <TITLE>{{ $policy->policyNumber }}_Rate </TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/arial" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/montserrat" rel="stylesheet">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet"> --}}
    <style>
                            @import url('https://fonts.cdnfonts.com/css/arial');
    </style>
    <style>
        @import url('https://fonts.cdnfonts.com/css/montserrat');
        body {
            font-family: 'Montserrat','Arial', 'sans-serif' !important;
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
            border-collapse: collapse !important;
        }

        /* table,
        th,
        td {
            border: 1px solid #000000;
        } */

        td,
        th {
            padding: 3px 3px;
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
    <p style="margin: 0px;" align="right" >As On: {{ $today }}</p>
    <header>
        <div>
            <table style="width:100%;">
                <tr valign="middle">
                    <td width="50%">
                        <div>
                            <img style="width: 320px;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
                        </div>
                        <br>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Alpha Direct Insurance Co. (Pty) Ltd.</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Office: Floor 2, Bar 2,<br>Botswana Innovation Hub Icon Building,</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Plot 69184 Block 8 Industrial</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Postal Address: P.O. Box 26ADC<br>Gaborone, Botswana</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Phone +267 392 8264 | Fax +267 392 8265</p>
                        <p style="margin: 0px!important;">debtors@alphadirect.co.bw | <a href="mailto: www.alphadirect.co.bw" style="color: #2e77c3!important;">www.alphadirect.co.bw</a></p>
                    </td>
                    <td width="50%">
                        <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">RATE SHEET</p>
                        <p style="text-align: right; color: #2e77c3!important;margin:0px;">TRANSACTION NO :<span style="color: black!important;"> 54646 </span></p>
                        <p style="text-align: right; color: #2e77c3!important;margin-bottom:15px;">POLICY NO :<span style="color: black!important;"> {{ $policy->policyNumber }} </span></p>
                        <p style="text-align: right; font-weight: 600!important;margin:0px;"> TRANSACTION : {{ $policyAction->transaction_type }}</p>
                        <p style="text-align: right; font-weight: 600!important;"> SUB TYPE : {{ $policyAction->transaction_reason }}</p>
                    </td>
                </tr>
            </table>
        </div>
    </header>
    <div style="margin-top:10px;">
        <table border="0" width="100%" cellpadding="0" cellspacing="0" id="commaSeperateValue">
            <thead>
                <tr>
                    <td width="15%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">RISK COVERAGE</p>
                    </td>
                    <td width="18%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">ITEM DESCRIPTION</p>
                    </td>
                    <td width="6%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">RATE</p>
                    </td>
                    <td width="12%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">CALC RATE</p>
                    </td>
                    <td width="14%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">ANN PREMIUM</p>
                    </td>
                    <td width="12%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 12px;margin: 0px; font-weight: 600;">PRO RATED</p>
                    </td>
                    <td width="10%">
                        <p style="text-align: right; color: #2e77c3!important;font-size: 12px; margin: 0px;font-weight: 600;">COMMENT</p>
                    </td>
                </tr>
            </thead>
            <tbody>
            <!-- start - Coverage Details-->
            @if(count($policy_coverages)>0)
            @foreach($policy_coverages as $index => $coverages)
            <!-- risk address-->
            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;">
                <td width="100%" colspan="7">
                    <p style="margin: 0px; padding: 0px;font-size: 10px!important;color:black;">Risk Address: {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->state->name??""}} {{$coverages->riskAddress->city->name??""}}</p>
                </td>
            </tr>

            <tr style="">
                <td width="10%" >
                    <!-- coverage -->
                    <p style="font-size: 10px;text-align:;margin: 0px;padding:0px!important;">
                        <span style="color: #2e77c3!important;">
                            {{$coverages->coverage->s_ScreenName??""}}
                        </span>
                    </p>
                </td>

                <td width="10%" >
                    <!-- sub coverages -->
                    @if(count($coverages->coverageDetail)>0)
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                        <p style="font-size: 10px;">{{ $sub_coverages->coverage->s_ScreenName??"" }}<br>
                        @endforeach
                    @endif
                </td>
                <td width="10%" >
                    <!-- sub coverages rates-->
                    @if(count($coverages->coverageDetail)>0)
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                        <p style="font-size: 10px;">{{ $sub_coverages->rate??"-" }}<br>
                        @endforeach
                    @endif
                </td>
                <td width="30%" >
                    <!-- sub coverages coverage value-->
                    @if(count($coverages->coverageDetail)>0)
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                        @php
                        if(isset($sub_coverages->limit_id)){
                          $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                        }
                       @endphp
                       <p style="font-size: 10px;">
                            @if (isset($tbCvgpclimits))
                            {{ $tbCvgpclimits['s_LimitScreenName'] ?? 'P 0.00'}}<br>
                            @else
                            P {{number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',')}}

                            @if($sub_coverages->coverage->s_CoverageCode == 'BUILDING')

                                @if($sub_coverages->ratefactor_type != null)
                                <span style="margin-left: 12%"> No of month - {{ $sub_coverages->ratefactor_type ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode == 'MONEYCAPITALSUM')

                                @if($sub_coverages->ratefactor_value != null)
                                <span style="margin-left: 12%"> No of Employees - {{ $sub_coverages->ratefactor_value ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode=='PUB_LEGALDEFENCE' || $sub_coverages->coverage->s_CoverageCode=='PUB_WRONGARREST')

                                @if($sub_coverages->ratefactor_value != null)
                                <span style="margin-left: 12%"> No of Persons - {{ $sub_coverages->ratefactor_value ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode=='FIRESTOCK')

                                @if($sub_coverages->ratefactor_type != null)
                                <span style="margin-left: 12%"> Decl M/Q/A - {{ $sub_coverages->ratefactor_type ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode=='BUSI_WAGES')

                                @if($sub_coverages->ratefactor_value != null)
                                <span style="margin-left: 12%"> No of Weeks - {{ $sub_coverages->ratefactor_value ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode=='MONEYSEASONALINC1' || $sub_coverages->coverage->s_CoverageCode=='MONEYSEASONALINC2')

                                @if($sub_coverages->ratefactor_value != null)
                                <span style="margin-left: 12%"> Date - {{ $sub_coverages->ratefactor_value ?? ''}}</span>
                                @endif

                            @elseif($sub_coverages->coverage->s_CoverageCode=='THEFTSUMINS')

                                @if ($sub_coverages->ratefactor_type != null)
                                <span style="margin-left: 12%">Basis Of Cover - {{ $sub_coverages->ratefactor_type ?? ''}}</span>
                                @endif

                            @endif

                            <br>
                            @endif
                        @endforeach
                    @endif
                </td>
                <td width="10%" >
                    <!-- sub coverages coverage value-->
                    @if(count($coverages->coverageDetail)>0)
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                        <p style="font-size: 10px;">P {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} <br>
                        @endforeach
                    @endif
                </td>
                <td width="10%" >
                    <!-- sub coverages coverage value-->
                    @if(count($coverages->coverageDetail)>0)
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                            <p style="font-size: 10px;">P {{number_format((float)$sub_coverages->proRataPremium ?? "", 2, '.', ',')}}<br>
                        @endforeach
                    @endif
                </td>
                <td width="10%" >
                    @if(count($coverages->entities)>0)
                        @foreach($coverages->entities as $newIndex => $entity)
                            @if($entity->entity_type == 'Vehicle')
                                @php
                                    $entitiesData = \AlphaDirect\Vehicle::select('vehiclePlate','id')->where('id',$entity->entity_id)->first();
                                    $entitiesName = $entitiesData->vehiclePlate ?? null;
                                @endphp
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;">
                                ({{$entitiesName??"-"}})
                                </p>
                            @endif
                        @endforeach
                    @endif
                </td>
            </tr>

            <tr style="border-bottom: 0.5px solid #73c9ed!important;border-top: 2px solid #73c9ed!important">
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    Total Of {{$coverages->coverage->s_ScreenName??""}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"> </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"> </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$coverages->coverage_calculated_value ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$coverages->total_calculated_prorated_premium ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>
            @endforeach
            @endif

            {{-- @php
                $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                $premiumExcludingVAT = $policyAction->premium / (1 + ($regionVat/100));
                if(($policy->premium_freq)==1){
                    $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                }
                $vat = $premiumExcludingVAT * ($regionVat/100);
                $ServiceCharge8 = $premiumExcludingVAT*(8/100);
                $vatServiceCharge = $ServiceCharge8*($regionVat/100);
            @endphp --}}

            <tr style="border-bottom: 0.5px solid #73c9ed!important;border-top: 2px solid #2e77c3!important">
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">Total Premium (Before Fees)</p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"> </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"> </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                         P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$premiumProratedExcludingVAT ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>
            <tr style="border-bottom: 0.5px solid #73c9ed!important;">
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">Value added tax</p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%">
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                          P {{number_format((float)$vat ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                         P {{number_format((float)$vatProrated ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>
            @if(($policy->premium_freq)==1)
            <tr style="border-bottom: 0.5px solid #73c9ed!important;">
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">8% Service Charge On Monthly Payment</p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%">
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$ServiceCharge8 ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$ServiceProratedCharge8 ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>
            <tr style="border-bottom: 0.5px solid #73c9ed!important;">
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">14% VAT On Service Charge</p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                    </p>
                </td>
                <td width="10%">
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                       P {{number_format((float)$vatServiceCharge ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">
                        P {{number_format((float)$vatProratedServiceCharge ?? "", 2, '.', ',')}}
                    </p>
                </td>
                <td width="10%" >
                    <p style="font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>
            @endif
            <tr style="border-top: 0.5px solid #73c9ed!important;border-bottom: 2px solid #2e77c3!important">
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">Total Premium</p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"> P {{number_format((float)$policyAction->premium ?? "", 2, '.', ',')}}</p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;">P {{number_format((float)$total_coverage_prorated_premium ?? "", 2, '.', ',')}}</p>
                </td>
                <td width="10%" >
                    <p style="font-weight: 600!important;font-size: 10px;text-align: left;margin: 0px;padding:0px!important;"></p>
                </td>
            </tr>

            </tbody>
        </table>
    </div>
</body>
</html>

