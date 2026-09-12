@phpr
ini_set('memory_limit', '128M');
@endphp

<!Doctype html >
<html>
<head>
    <TITLE>Policy Schedule</TITLE>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
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
        <div class="container clearfix"></div>
        <p style="margin: 0px;" align="right" >As On: {{ $today }}</p>

            <div style="margin-top:100px;">
                <p style="font-size:15px;"><b>Mail to:</b></p>
                <p style="margin: 0px;font-size:20px;"><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    @if($policy->profile->entity_type=='Organisation')
                        {!! $policy->profile->company->name??"" !!}
                    @else
                        {!! $policy->customer->firstName??"" !!} {!! $policy->customer->lastName??"" !!}
                    @endif
               </b></p>
                <p style="margin: 0px;font-size:20px;"><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address.",") : "" }} {{ $policy->profile->cities->name ? ($policy->profile->cities->name.",") : "" }} {{ $policy->profile->states->name??"" }}
                    @else
                    {{ $policy->profile->post_address ? ($policy->profile->post_address.",") : "" }} {{ $policy->profile->city ? ($policy->profile->city.",") : "" }} {{ $policy->profile->states->name??"" }}
                    @endif
                </b></p>
            </div>
            <div style="margin-top:610px;">
                <p style="font-size:15px;"><b>Mail to:</b></p>
                <p style="margin: 0px;font-size:20px;"><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    @if($policy->profile->entity_type=='Organisation')
                        {!! $policy->profile->company->name??"" !!}
                    @else
                        {!! $policy->customer->firstName??"" !!} {!! $policy->customer->lastName??"" !!}
                    @endif
               </b></p>
                <p style="margin: 0px;font-size:20px;"><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                    @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address.",") : "" }} {{ $policy->profile->cities->name ? ($policy->profile->cities->name.",") : "" }} {{ $policy->profile->states->name??"" }}
                    @else
                    {{ $policy->profile->post_address ? ($policy->profile->post_address.",") : "" }} {{ $policy->profile->city ? ($policy->profile->city.",") : "" }} {{ $policy->profile->states->name??"" }}
                    @endif

                    {{-- @if($policy->profile->entity_type=='Organisation')
                        {!!$policy->profile->company->postal_address??"" !!} {!! $policy->profile->city??"" !!} {!! $policy->profile->state??"" !!}
                    @else
                        {!! $policy->profile->address??"" !!} {!! $policy->profile->city??"" !!} {!! $policy->profile->state??"" !!}
                    @endif --}}
                </b></p>
                <p align="right" style="font-size:10px;margin-top:10px;">Insured Copy</p>
            </div>

            <div style="page-break-after:always;"></div>

            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                <tr width="100%">
                    <td style="margin: 0px;padding: 0px;" width="30%">
                        <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo" width="100%;" >
                    </td>
                    <td width="35%">
                        <p style="margin: 0px;">
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Office: Floor 2, Bar 2,</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Botswana Innovation Hub Icon Building,</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Plot 69184 Block 8 Industrial</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Postal Address: P.O. Box 26 ADC</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Gaborone, Botswana</p>
                            </br>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Tel : +267 392 8264</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Fax : +267 392 8265</p>
                        </p>
                    </td>
                    <td width="35%">
                        <p style="margin: 0px;">
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Postal Address: P.O. Box 26 ADC,</p>
                            <p style="font-size: 12px; margin: 0px;color: black!important;">Gaborone, Botswana</p>
                            </br>
                            <p style="margin: 0px!important;">E-Mail :policies@alphadirect.co.bw </p>
                            <p style="margin: 0px!important;"><a href="mailto: www.alphadirect.co.bw" style="color: #2e77c3!important;">www.alphadirect.co.bw</a></p>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="text-align:center;color: #000000;margin: 0px;font-size:17px;padding-top:10px;padding-bottom:10px;">
                            <b>This policy should be read in conjunction with the policy wording.</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Policy Number</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                           {!! $policy->policyNumber??"-" !!}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000; margin: 0px;">
                            <b>Agency</b>
                        </p></br>
                        <p style="color: #000000; margin: 0px;">
                            <b>Agent</b>
                        </p>
                    </td>
                    <td colspan="1">
                        <p style="margin: 0px;">
                           {!! $policy->agency->name??"-" !!}
                        </p></br>
                        <p style="margin: 0px;">
                            {!! $policy->user->firstName??"-" !!} {!! $policy->user->lastName??"" !!}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="text-align:center;color: #000000;margin: 0px;font-size:13px;">
                            <b>Policy Schedule</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Insured Name </b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            @if($policy->profile->entity_type=='Organisation')
                                {!! $policy->profile->company->name??"-" !!}
                            @else
                                {!! $policy->customer->firstName??"-" !!} {!! $policy->customer->lastName??"" !!}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Nature of Business</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            {{ $policy_coverages[0]->riskAddress->address_name??"" }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Postal Address</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address.",") : "" }} {{ $policy->profile->cities->name ? ($policy->profile->cities->name.",") : "" }} {{ $policy->profile->states->name??"" }}
                            @else
                            {{ $policy->profile->post_address ? ($policy->profile->post_address.",") : "" }} {{ $policy->profile->city ? ($policy->profile->city.",") : "" }} {{ $policy->profile->states->name??"" }}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Physical Address</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            @if($policy->profile->entity_type=='Organisation')
                                {!!$policy->profile->company->head_office_physical_address??"" !!}
                            @else
                                {!! $policy->profile->address??"" !!}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Period of Insurance</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            a) From {{ $invoice_start_date??"" }} To {{ $endDate??"" }} (both dates inclusive) <br>
                            {{-- a) From {{ $fromDate??"" }} To {{ $toDate??"" }} (both dates inclusive) <br> --}}
                            b) Any subsequent period for which the Company agrees to renew this policy or any section thereof.
                        </p>
                        <p style="margin: 0px;" colspan="1"></p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Annual Premium</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                          @if(isset($policyAction->premium))
                              P {{number_format((float)$policyAction->premium ?? "", 2, '.', ',')}}
                          @else
                              P {{number_format(0, 2, '.', ',')}}
                          @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Renewal Frequency</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
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
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Anniversary / Renewal Date</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                            {{ $anniversaryDate??"-" }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="color: #000000;margin: 0px;">
                            <b>Signed at Gaborone on the</b>
                        </p>
                    </td>
                    <td>
                        <p style="margin: 0px;" colspan="1">
                           {{ $today }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="color: #000000;margin: 0px;">
                            <b>This is an annual policy paid by monthly installments thereby calling for recovery of outstanding premiums in the event of a total loss.</b>
                        </p>
                    </td>
                </tr>
            </table>


            <div class="row"  style="margin-top: 10px;margin-bottom: 15px;width:100%;">
                <div class="column" style="width:30%;">
                    <p>On behalf of the Company</p>
                </div>
                <div class="column" style="width:30%;">
                    <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/sign2/sign.png" align="center" alt="Sign" width="100px">
                </div>
                <div class="column" style="width:30%;">
                    <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Stamp/stamp.png" align="left" alt="stamp" width="36%"></div>
                </div>
                <div class="column" style="width:10%;">
                </div>
            </div>

            <div style="page-break-after:always;"></div>
             <!-- Start Section -->
                <table class="table-responsive" style="border-collapse: collapse;width:100%">

                        <tr>
                            <td style="background-color: #002060;text-align:center" colspan="4">
                                <p style="color: #ffffff;margin: 0px;">
                                    <b>Coverages</b>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="3">
                                <p style="margin: 0px;"><b>Coverage Name</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="margin: 0px;"><b>Status</b></p>
                            </td>
                        </tr>
                        @foreach($all_coverages as $index => $allcoverage)
                            <tr>
                                <td style="" colspan="3">
                                    <p style="color:#002060;margin: 0px;"><b>
                                        {{$allcoverage->s_ScreenName??""}}
                                    </b></p>
                                </td>
                                <td style="" colspan="1">
                                    <p style="color:#002060;margin: 0px;"><b>
                                    @php
                                        $coverage_s_name = null;
                                        foreach($policy_coverages as $key => $coverages){
                                            $coverage_s_name = $coverages->coverage->s_ScreenName;
                                        }
                                         if($allcoverage->s_ScreenName != $coverage_s_name){
                                           $status = "No";
                                         }else{
                                           $status = "Yes";
                                         }
                                    @endphp

                                    {{ $status }}
                                    </b></p>
                                </td>
                            </tr>
                        @endforeach
                </table>
            <!-- End Section -->
            <br>
            @if(count($policy_coverages)>0)
           <!-- ======== Start Details =================== -->
            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                @php $previousScreenName = null; @endphp
             @foreach($policy_coverages as $index => $coverages)
                    @if($coverages->coverage->s_ScreenName != $previousScreenName)
                    <tr>
                        <td style="background-color: #002060;text-align:center" colspan="4">
                            <p style="margin: 0px;color:#ffffff;"><b>  {{$coverages->coverage->s_ScreenName??""}}</b></p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Risk Address: -->
                        <td style="background-color:#dbe5f1;" colspan="4">
                            <p style="color: #002060;margin: 0px;">
                                <b> Risk Address :  {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->state->name??""}} {{$coverages->riskAddress->city->name??""}} </b>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #dbe5f1;" colspan="2">
                            <p style="margin: 0px;"><b>Description</b></p>
                        </td>
                        <td style="background-color: #dbe5f1;" colspan="1">
                            <p style="margin: 0px;"><b>Sum Insured</b></p>
                        </td>
                        <td style="background-color: #dbe5f1;" colspan="1">
                            <p style="margin: 0px;"><b>Premium</b></p>
                        </td>
                    </tr>
                    @php $previousScreenName = $coverages->coverage->s_ScreenName; @endphp
                    @endif
                    @if(count($coverages->entities)>0)
                    <tr>
                        <td style="" colspan="2">
                            @foreach($coverages->entities as $newIndex => $entity)
                            <p style="color:#002060;margin: 0px;"><b> {{$entity->entity_type}}</b></p><br>
                            @endforeach
                        </td>
                        <td style="" colspan="2">
                            @foreach($coverages->entities as $newIndex => $entity)
                                @if($entity->entity_type == 'Vehicle')
                                @php
                                    $entitiesData = \AlphaDirect\Vehicle::select('vehiclePlate','id')->where('id',$entity->entity_id)->first();
                                    $entitiesName = $entitiesData->vehiclePlate ?? null;
                                @endphp
                                @elseif($entity->entity_type == 'Device')
                                @php
                                    $entitiesData = \AlphaDirect\PolicyCellPhone::selectRaw("id,concat(cell_phone_model,' ',cell_phone_make,' ',device_type) as name")->where('id',$entity->entity_id)->first();
                                    $entitiesName = $entitiesData->name ?? null;
                                    @endphp
                                @elseif($entity->entity_type == 'Member')
                                @php
                                    $entitiesData = \AlphaDirect\PolicyBeneficiary::selectRaw("id,concat(first_name,' ',middle_name,' ',last_name) as name")->where('id',$entity->entity_id)->first();
                                    $entitiesName = $entitiesData->name ?? null;
                                    @endphp
                                @endif
                            <p style="margin: 0px;"><b>{{$entitiesName??"-"}}</b></p><br>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                    @php
                        $sum = 0;
                        $sum_insured = 0;
                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                            $sum_insured+= $specifed_items->sum_insured;
                            $sum+= $specifed_items->calculated_value;
                        }
                    @endphp
                 <!-- start all sub_coverages -->
                  {{-- @if(count($coverages->all_sub_coverages) > 0 || count($coverages->coverageDetail) > 0) --}}
                    @php
                        $totalSumOfCoveragesValues = 0;
                        $totalSumOfCalculatedValues = 0;
                    @endphp

                    @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages)
                        @php
                            $sub_coverage_found = false;
                        @endphp
                        <!-- start present sub_coverages -->
                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                            @if($all_sub_coverages->id == $sub_coverages->coverage->id)
                                @php
                                    $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + $sub_coverages->coverage_value;
                                    $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + $sub_coverages->calculated_value;
                                    $sub_coverage_found = true;
                                    if(isset($sub_coverages->limit_id)){
                                    $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                    }
                                @endphp
                                <tr>
                                    <td style="" colspan="2">
                                        @if (isset($sub_coverages->coverage_value))
                                        <p style="color:#002060;margin: 0px;"><b> {{ $sub_coverages->coverage->s_ScreenName??"" }}</b></p>
                                        @endif
                                    </td>
                                    <td style="" colspan="1">
                                    <p style="color:#002060;margin: 0px;"><b>
                                            @if (isset($tbCvgpclimits))
                                               {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                            @else
                                                  @if (isset($sub_coverages->coverage_value))
                                                  P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                  @endif

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

                                            @endif
                                        </b></p>
                                    </td>
                                    <td style="" colspan="1">
                                        @if (isset($sub_coverages->calculated_value))
                                        <p style="color:#002060;margin: 0px;"><b> P {{ $sub_coverages->calculated_value??"" }}</b></p>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        <!-- end present sub_coverages -->

                        @if(!$sub_coverage_found)
                            <tr>
                                <td style="" colspan="2">
                                    <p style="color:#002060;margin: 0px;"><b> {{ $all_sub_coverages->s_ScreenName??"" }}</b></p>
                                </td>
                                <td style="" colspan="1">
                                    <p style="margin: 0px;"><b>P {{number_format(0, 2, '.', ',')}}   </b></p>
                                </td>
                                <td style="" colspan="1">
                                    <p style="margin: 0px;"><b>P {{number_format(0, 2, '.', ',')}}   </b></p>
                                </td>
                            </tr>
                        @endif
                    @endforeach

                    @if(isset($sum_insured) || isset($sum))
                         <!-- end sum of specified items -->
                        @if(count($coverages->specifedItems)>0)
                            <tr>
                                <td style="background-color: #dbe5f1;"  colspan="2">
                                    <p style="margin: 0px;"><b>{{$coverages->coverage->s_ScreenName??""}} Miscellaneous Items</b></p>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                        @if($sum_insured)
                                        P {{number_format((float)$sum_insured ?? "", 2, '.', ',')}}
                                        @else
                                        P {{number_format(0, 2, '.', ',')}}
                                        @endif
                                    </b>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                        @if($sum)
                                        P {{number_format((float)$sum ?? "", 2, '.', ',')}}
                                        @else
                                        P {{number_format(0, 2, '.', ',')}}
                                        @endif
                                    </b>
                                </td>
                            </tr>
                        @endif
                    @endif

                    @if ($coverages->coverage->s_CoverageCode == "MONEY")
                        @if(isset($totalSumOfCalculatedValues) || isset($sum))
                            <tr>
                                <td style="background-color: #dbe5f1;"  colspan="2">
                                    <p style="margin: 0px;"><b>Total (for premium only)</b></p>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                    </b>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                    @if($totalSumOfCalculatedValues != null || $sum != null)
                                    P {{number_format((float)$totalSumOfCalculatedValues+$sum ?? "", 2, '.', ',')}}
                                    @else
                                    P {{number_format(0, 2, '.', ',')}}
                                    @endif
                                    </b>
                                </td>
                            </tr>
                        @endif
                    @else
                        @if(isset($totalSumOfCoveragesValues) || isset($totalSumOfCalculatedValues) || isset($sum_insured) || isset($sum))
                            <tr>
                                <td style="background-color: #dbe5f1;"  colspan="2">
                                    <p style="margin: 0px;"><b>Sum of Coverages</b></p>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                    @if($totalSumOfCoveragesValues != null || $sum != null)
                                    P {{number_format((float)$totalSumOfCoveragesValues+$sum_insured ?? "", 2, '.', ',')}}
                                    @else
                                    P {{number_format(0, 2, '.', ',')}}
                                    @endif
                                    </b>
                                </td>
                                <td style="background-color: #dbe5f1;"  colspan="1">
                                    <b>
                                    @if($totalSumOfCalculatedValues != null || $sum != null)
                                    P {{number_format((float)$totalSumOfCalculatedValues+$sum ?? "", 2, '.', ',')}}
                                    @else
                                    P {{number_format(0, 2, '.', ',')}}
                                    @endif
                                    </b>
                                </td>
                            </tr>
                        @endif
                    @endif
                {{-- @endif --}}
                <!-- end sub_coverages -->

                {{-- specified items --}}
                @if(count($coverages->specifedItems)>0)
                  <tr>
                      <td style="background-color: #002060;text-align:center" colspan="4">
                          <p style="color: #ffffff;margin: 0px;">
                              <b>Miscellaneous Items of {{$coverages->coverage->s_ScreenName??""}} Coverage</b>
                          </p>
                      </td>
                  </tr>
                  <tr>
                      <td style="background-color: #dbe5f1;" colspan="2">
                          <p style="margin: 0px;"><b>Name</b></p>
                      </td>
                      <td style="background-color: #dbe5f1;" colspan="1">
                          <p style="margin: 0px;"><b>Sum Insured</b></p>
                      </td>
                      <td style="background-color: #dbe5f1;" colspan="1">
                          <p style="margin: 0px;"><b>Premium</b></p>
                      </td>
                  </tr>
                  @php $sumInsuredTotal = 0;  @endphp
                  @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                  @php
                      $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                  @endphp

                  <tr>
                      <td style ="margin: 0px;" colspan="2">{{$specifed_items->specifiedCoveragesItems->specified_name}}</td>
                      <td style ="margin: 0px;" colspan="1">
                          P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}}
                      </td>
                      <td style ="margin: 0px;" colspan="1">
                          P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}}
                      </td>
                  </tr>
                  @endforeach
                  @if(!empty($sumInsuredTotal))
                  <tr>
                      <td style="background-color: #dbe5f1;"  colspan="2">
                          <p style="margin: 0px;"><b>Total of sum insured</b></p>
                      </td>
                      <td style ="margin: 0px;" colspan="2">P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</td>
                  </tr>
                  @endif
                @endif
             {{-- end specified items --}}

                <!-- Definitions  -->
                @foreach($policy_coverages as $index => $coverages)
                    @if($coverages->s_CoverageCode == 'FIRE')
                        @if(isset($coverages->coverage->s_CoverageDesc))
                            <tr>
                                <td style="background-color: #002060;text-align:center" colspan="4">
                                    <p style="color: #ffffff;margin: 0px;">
                                        <b>Definitions</b>
                                    </p>
                                </td>
                            </tr>
                            @if(count($coverages->all_sub_coverages) > 0 || count($coverages->coverageDetail) > 0)
                                @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages)
                                    @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                                        @if($all_sub_coverages->id == $sub_coverages->coverage->id)
                                            <tr>
                                                <td colspan="2">
                                                    <p style="color:#002060;margin: 0px;"><b>&nbsp;&nbsp;{{ $sub_coverages->coverage->s_ScreenName??"" }}</b></p>
                                                </td>
                                                <td colspan="2">
                                                    <p style="color:#002060;margin: 0px;width:350px"><b>&nbsp;&nbsp;{{ $sub_coverages->coverage->s_CoverageDesc??"" }}</b></p>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @endforeach
                            @endif
                        @endif
                    @endif
                @endforeach
                <!-- End Definitions -->

                <!-- Extentions Section-->
                @if(count($coverages->extentionDetail)>0)
                @php
                $totalSumOfExtentionCoveragesValues = 0;
                $totalSumOfExtentionCalculatedValues = 0;

                $totalSumOfPerilsCoveragesValues = 0;
                $totalSumOfPerilsCalculatedValues = 0;
                @endphp
                <tr>
                    <td style="background-color: #002060;text-align:center" colspan="4">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Extentions</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #dbe5f1;" colspan="2">
                        <p style="margin: 0px;"><b>Extention Name</b></p>
                    </td>
                    <td style="background-color: #dbe5f1;" colspan="1">
                        <p style="margin: 0px;"><b>Sum Insured</b></p>
                    </td>
                    <td style="background-color: #dbe5f1;" colspan="1">
                        <p style="margin: 0px;"><b>Premium</b></p>
                    </td>
                </tr>
                @foreach($coverages->all_extentions as $newSubIndex => $all_extentions)
                        <!-- start present sub_coverages -->
                        @foreach($coverages->extentionDetail as $newIndex => $extention_items)

                            @if($all_extentions->id == $extention_items->extentions_id)

                                 @php
                                 $extTypes = AlphaDirect\Models\Extention::where('id',$extention_items->extentions_id)->get();
                                 foreach($extTypes as $extType){
                                     $type = $extType->type;
                                 }
                                 if ($type == 'Extention') {
                                     $totalSumOfExtentionCoveragesValues =  $totalSumOfExtentionCoveragesValues + $extention_items->extention_coverage_value;
                                     $totalSumOfExtentionCalculatedValues =  $totalSumOfExtentionCalculatedValues + $extention_items->extention_calculated_value;

                                     if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                         $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                     }else {
                                         $tbCvgpcextentionlimits = null;
                                     }
                                 }else {
                                     // $totalSumOfExtentionCoveragesValues = 0;
                                     // $totalSumOfExtentionCalculatedValues = 0;
                                     $tbCvgpcextentionlimits = null;
                                 }
                                @endphp

                                @if (isset($extention_items->extention->s_CoverageName) && $extention_items->extention->s_CoverageName != null)
                                <tr>
                                    <td style="" colspan="2">
                                        <p style="color:#002060;margin: 0px;"><b>  {{$extention_items->extention->s_CoverageName ?? ''}} </b></p>
                                    </td>
                                    <td style="" colspan="1">
                                    <p style="color:#002060;margin: 0px;">
                                        <b>
                                            @if (isset($tbCvgpcextentionlimits)&& $tbCvgpcextentionlimits != null)
                                                {{ $tbCvgpcextentionlimits->s_LimitScreenName ?? ''}}
                                            @elseif (isset($extention_items->extention_text_value))
                                                {{ $extention_items->extention_text_value  ?? ''}}
                                            @else
                                                @if (isset($extention_items->extention_coverage_value) && $extention_items->extention_coverage_value != 0.00000000)
                                                P {{ number_format($extention_items->extention_coverage_value  ?? "", 2, '.', ',') }}
                                                @endif
                                            @endif
                                        </b></p>
                                    </td>
                                    <td style="" colspan="1">
                                        <p style="color:#002060;margin: 0px;">
                                        <b>
                                            @if (isset($extention_items->extention_calculated_value) && $extention_items->extention_calculated_value != 0.00000000)
                                            P {{$extention_items->extention_calculated_value}}
                                            @endif
                                        </b>
                                        </p>
                                    </td>
                                </tr>
                                @endif
                            @endif
                        @endforeach
                        <!-- end present ext -->
                @endforeach
                <tr>
                    <td style="background-color: #dbe5f1;"  colspan="2">
                        <p style="margin: 0px;"><b>Total Of Extention</b></p>
                    </td>
                    <td style="background-color: #dbe5f1;"  colspan="1">
                        @if(!empty($totalSumOfExtentionCoveragesValues))
                        <b> P {{number_format((float)$totalSumOfExtentionCoveragesValues ?? "", 2, '.', ',')}}</b>
                        @endif
                    </td>
                    <td style="background-color: #dbe5f1;"  colspan="1">
                       @if(!empty($totalSumOfExtentionCalculatedValues))
                       <b> P {{number_format((float)$totalSumOfExtentionCalculatedValues ?? "", 2, '.', ',')}}</b>
                       @endif
                    </td>
                </tr>
                @endif

                {{-- @if(count($coverages->extentionDetail)>0)
                        @php
                        $totalSumOfExtentionCoveragesValues = 0;
                        $totalSumOfExtentionCalculatedValues = 0;

                        $totalSumOfPerilsCoveragesValues = 0;
                        $totalSumOfPerilsCalculatedValues = 0;
                        @endphp

                        <!-- Start Extention -->
                        <tr>
                            <td style="background-color: #002060;text-align:center" colspan="4">
                                <p style="color: #ffffff;margin: 0px;">
                                    <b>Extentions</b>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="2">
                                <p style="margin: 0px;"><b>Extention Name</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="margin: 0px;"><b>Sum Insured</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="margin: 0px;"><b>Premium</b></p>
                            </td>
                        </tr>

                        @foreach($coverages->extentionDetail as $newExtentionIndex => $extention_items)
                            @php
                             $extTypes = AlphaDirect\Models\Extention::where('id',$extention_items->extentions_id)->get();
                             foreach($extTypes as $extType){
                                 $type = $extType->type;
                             }
                             if ($type == 'Extention') {
                                 $totalSumOfExtentionCoveragesValues =  $totalSumOfExtentionCoveragesValues + $extention_items->extention_coverage_value;
                                 $totalSumOfExtentionCalculatedValues =  $totalSumOfExtentionCalculatedValues + $extention_items->extention_calculated_value;

                                 if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                     $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                 }else {
                                     $tbCvgpcextentionlimits = null;
                                 }
                             }else {
                                 // $totalSumOfExtentionCoveragesValues = 0;
                                 // $totalSumOfExtentionCalculatedValues = 0;
                                 $tbCvgpcextentionlimits = null;
                             }
                            @endphp

                            @if (isset($extention_items->extention->s_CoverageName) && $extention_items->extention->s_CoverageName != null)
                            <tr>
                                <td style="" colspan="2">
                                    <p style="color:#002060;margin: 0px;"><b>  {{$extention_items->extention->s_CoverageName ?? ''}} </b></p>
                                </td>
                                <td style="" colspan="1">
                                   <p style="color:#002060;margin: 0px;">
                                    <b>
                                        @if (isset($tbCvgpcextentionlimits)&& $tbCvgpcextentionlimits != null)
                                            {{ $tbCvgpcextentionlimits->s_LimitScreenName ?? ''}}
                                        @elseif (isset($extention_items->extention_text_value))
                                            {{ $extention_items->extention_text_value  ?? ''}}
                                        @else
                                            @if (isset($extention_items->extention_coverage_value) && $extention_items->extention_coverage_value != 0.00000000)
                                            P {{ number_format($extention_items->extention_coverage_value  ?? "", 2, '.', ',') }}
                                            @endif
                                        @endif
                                    </b></p>
                                </td>
                                <td style="" colspan="1">
                                    <p style="color:#002060;margin: 0px;">
                                      <b>
                                        @if (isset($extention_items->extention_calculated_value) && $extention_items->extention_calculated_value != 0.00000000)
                                          P {{$extention_items->extention_calculated_value}}
                                        @endif
                                      </b>
                                    </p>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        <tr>
                            <td style="background-color: #dbe5f1;"  colspan="2">
                                <p style="margin: 0px;"><b>Total Of Extention</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;"  colspan="1">
                                @if(!empty($totalSumOfExtentionCoveragesValues))
                                <b> P {{number_format((float)$totalSumOfExtentionCoveragesValues ?? "", 2, '.', ',')}}</b>
                                @endif
                            </td>
                            <td style="background-color: #dbe5f1;"  colspan="1">
                               @if(!empty($totalSumOfExtentionCalculatedValues))
                               <b> P {{number_format((float)$totalSumOfExtentionCalculatedValues ?? "", 2, '.', ',')}}</b>
                               @endif
                            </td>
                        </tr>
                        <!-- End Extention -->

                        <!-- Start Perils -->
                        <tr>
                            <td style="background-color: #002060;text-align:center" colspan="4">
                                <p style="color: #ffffff;margin: 0px;">
                                    <b>Perils</b>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="2">
                                <p style="margin: 0px;"><b>Perils Name</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="margin: 0px;"><b>Sum Insured</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="margin: 0px;"><b>Premium</b></p>
                            </td>
                        </tr>

                        @foreach($coverages->extentionDetail as $newExtentionIndex => $extention_items)
                            @php
                            $perTypes = AlphaDirect\Models\Extention::where('id',$extention_items->extentions_id)->get();
                            foreach($perTypes as $perType){
                                $ptype = $perType->type;
                            }

                            if ($ptype == 'Perils') {
                                $totalSumOfPerilsCoveragesValues =  $totalSumOfPerilsCoveragesValues + $extention_items->extention_coverage_value;
                                $totalSumOfPerilsCalculatedValues =  $totalSumOfPerilsCalculatedValues + $extention_items->extention_calculated_value;

                                if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                    $tbCvgpcperilslimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                }else {
                                    $tbCvgpcperilslimits = null;
                                }
                            }else {
                                $tbCvgpcperilslimits = null;
                            }
                            @endphp
                            @if (isset($extention_items->perils->s_CoverageName) && $extention_items->perils->s_CoverageName != null)
                            <tr>
                                <td style="" colspan="2">
                                    <p style="color:#002060;margin: 0px;"><b>  {{$extention_items->perils->s_CoverageName ?? ''}} </b></p>
                                </td>
                                <td style="" colspan="1">
                                <p style="color:#002060;margin: 0px;"><b>
                                        @if (isset($tbCvgpcperilslimits)&& $tbCvgpcperilslimits != null)
                                            {{ $tbCvgpcperilslimits->s_LimitScreenName ?? ''}}
                                        @elseif (isset($extention_items->extention_text_value))
                                            {{ $extention_items->extention_text_value  ?? ''}}
                                        @else
                                            @if (isset($extention_items->extention_coverage_value) && $extention_items->extention_coverage_value != 0.00000000)
                                            P {{ number_format($extention_items->extention_coverage_value  ?? "", 2, '.', ',') }}
                                            @endif
                                        @endif
                                    </b></p>
                                </td>
                                <td style="" colspan="1">
                                   <p style="color:#002060;margin: 0px;"><b>
                                        @if (isset($extention_items->extention_calculated_value) && $extention_items->extention_calculated_value != 0.00000000)
                                        P {{ $extention_items->extention_calculated_value }}
                                        @endif
                                    </b></p>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        <tr>
                            <td style="background-color: #dbe5f1;"  colspan="2">
                                <p style="margin: 0px;"><b>Total Of Perils</b></p>
                            </td>
                            <td style="background-color: #dbe5f1;"  colspan="1">
                               @if(!empty($totalSumOfPerilsCoveragesValues))
                               <b> P {{number_format((float)$totalSumOfPerilsCoveragesValues ?? "", 2, '.', ',')}}</b>
                               @endif
                            </td>
                            <td style="background-color: #dbe5f1;"  colspan="1">
                               @if(!empty($totalSumOfPerilsCalculatedValues))
                               <b> P {{number_format((float)$totalSumOfPerilsCalculatedValues ?? "", 2, '.', ',')}}</b>
                               @endif
                            </td>
                        </tr>
                       <!-- End Perils -->
                 @endif --}}


                <!-- Start Memoranda and Warranties -->
                {{-- <tr>
                    <td style="background-color: #002060;text-align:center" colspan="4">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Memoranda and Warranties</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="text-align:center" colspan="4">
                        <p style="margin: 0px;">
                            Specified Limitations/Details
                        </p>
                        <p style="margin: 0px;">
                            Money contained in a locked safe or strongroom situated in a building at the insured premises outside the hours during which the commercial operations of the insured are conducted.
                        </p>
                    </td>
                </tr> --}}
                <!-- End Memoranda and Warranties -->

                {{-- notes --}}
                @if(isset($coverages->note->note))
                <tr>
                    <td style="background-color: #002060;text-align:center" colspan="4">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Notes</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="4">
                        <p style="margin: 0px;">
                            <pre style="font-family:'Montserrat','Arial', sans-serif !important;font-weight: 500;
                            font-style:normal;
                            font-size:10px;
                            overflow-y:scroll; border:0.5px solid lightgray; padding:5px; white-space:pre-wrap;
                            ">{{$coverages->note->note}}
                            </pre>
                        </p>
                    </td>
                </tr>
                @endif


                @endforeach
            </table>
            <div style="page-break-after:always;"></div>
            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                 <!-- final premium vat -->
                 @php
                 $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                 $premiumExcludingVAT = $policyAction->premium / (1 + ($regionVat/100));
                 if(($policy->premium_freq)==1){
                     $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                 }
                 $vat = $premiumExcludingVAT * ($regionVat/100);
                 $ServiceCharge8 = $premiumExcludingVAT*(8/100);
                 $vatServiceCharge = $ServiceCharge8*($regionVat/100);
                @endphp

                <tr>
                    <td style="background-color: #002060;text-align:center" colspan="4">
                        <p style="color: #ffffff;margin: 0px;">
                            <b>Annual Premium</b>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="color:#002060;margin: 0px;"><b>  Final Premium Excluding VAT </b></p>
                    </td>
                    <td style="" colspan="1">
                        <p style="color:#002060;margin: 0px;"><b>P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}</b></p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="color:#002060;margin: 0px;"><b>14% VAT</b></p>
                    </td>
                    <td style="" colspan="1">
                        <p style="color:#002060;margin: 0px;"><b>P {{number_format((float)$vat ?? "", 2, '.', ',')}}</b></p>
                    </td>
                </tr>
                @if(($policy->premium_freq)==1)
                <tr>
                    <td style="" colspan="3">
                        <p style="color:#002060;margin: 0px;"><b>8% Service Charge On Monthly Payment </b></p>
                    </td>
                    <td style="" colspan="1">
                        <p style="color:#002060;margin: 0px;"><b>P {{number_format((float)$ServiceCharge8 ?? "", 2, '.', ',')}}<b></p>
                    </td>
                </tr>
                <tr>
                    <td style="" colspan="3">
                        <p style="color:#002060;margin: 0px;"><b>14% VAT On Service Charge</b></p>
                    </td>
                    <td style="" colspan="1">
                    <p style="color:#002060;margin: 0px;"><b>P {{number_format((float)$vatServiceCharge ?? "", 2, '.', ',')}}</b></p>
                    </td>
                </tr>
                @endif
                <tr>
                    <td style="" colspan="3">
                        <p style="color:#002060;margin: 0px;"><b>Final Premium including VAT</b></p>
                    </td>
                    <td style="" colspan="1">
                        <p style="color:#002060;margin: 0px;"><b>P {{number_format((float)$policyAction->premium ?? "", 2, '.', ',')}}</b></p>
                    </td>
                </tr>
                <!-- End final premium vat -->
            </table>
            @endif
            <br>
            <!-- ======== End Details =================== -->
           <!-- start note -->
            {{-- @if(count($policy_coverages)>0)
                @foreach($policy_coverages as $index => $coverages)
                    @if(isset($coverages->note->note))
                        <div class="container" >
                            <p style="font-size:10px;"> {{$coverages->coverage->s_ScreenName??""}}:</p>
                            <pre style="font-family:'Montserrat','Arial', sans-serif !important;font-weight: 500;
                                font-style:normal;
                                font-size:10px;
                                overflow-y:scroll; border:0.5px solid lightgray; padding:5px; white-space:pre-wrap;
                                ">{{$coverages->note->note}}
                            </pre>
                        </div>
                    @endif
                @endforeach
            @endif --}}
            <!-- end note -->

            <div style="page-break-after:always;"></div>
            <div class="row">
                <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" width="30%" align="right" alt="logo">
            </div>
            <br>
            <div style="text-align:center;">
                <p style="font-size:12;font-weight:500;">THIS ENDORSEMENT CHANGES THE POLICY, PLEASE READ IT CAREFULLY.</p>
                <p style="font-size:14;"><b>PREFERRED CONTRACTOR OR SERVICE PROVIDER</b></p>
                <p style="font-size:11;font-weight:normal;">THIS ENDORSEMENT CHANGES THE POLICY, PLEASE READ IT CAREFULLY.
                    PREFERRED CONTRACTOR OR SERVICE PROVIDER
                    THIS ENDORSEMENT ALLOWS “US” AT “OUR” OPTION TO SELECT A CONTRACTOR TO MAKE
                    COVERED REPAIRS TO “YOUR” DWELLING, VEHICLE, OR OTHER ASSET INSURED THROUGH THIS
                    POLICY BY ALPHA DIRECT INSURANCE COMPANY (PTY) LTD.</p>
            </div>
            <br>
            <br>
            <p style="font-size:11;font-weight:normal;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; In the event of a covered loss to “your” covered dwelling, vehicle, or any other asset
                insured on this policy“we” at “our” option may select an appropriately licensed or qualified
                contractor, panel beater or other service provider to repair “your” damaged property as
                provided by this policy.</p>
            <p style="font-size:11;font-weight:normal;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; By signing the required Quotation/Proposal form and by paying the requisite insurance
                premium as stated on that form you hereby accept the terms and conditions of this
                endorsement and hereby grant Alpha Direct Insurance Company (Pty) Ltd. an unequivocal right
                and option to select an appropriately licensed or qualified contractor, panel beater or other
                service provider to repair “your” damaged property as provided by this policy.</p>

            <p style="font-size:12;font-weight:500;margin-top:180px;text-align:center;">THIS ENDORSEMENT CHANGES THE POLICY. PLEASE READ IT CAREFULLY.<p>
            @if($policy_coverages)
                @foreach($policy_coverages as $index => $coverages)
                @if($coverages->coverage_id == 44)

                    <div style="page-break-after:always;"></div>

                    <table class="table-responsive" style="border-collapse: collapse;width:100%">
                        <tr width="100%">
                            <td style="margin: 0px;padding: 0px;" width="60%">
                                <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo" width="50%;" >
                            </td>
                            <td width="40%" >
                                <p style="margin: 0px;">
                                    <p style="font-size: 12px; margin: 0px;color: black!important;">Alpha Direct Insurance Company</p>
                                    <p style="font-size: 12px; margin: 0px;color: black!important;">(Proprietary) Limited</p>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:center;color: #000000;font-size:14px;" colspan="2">
                                <p style="margin: 0px;"><b>WORKER’S COMPENSATION ACT, 1998</b></p></br>
                                <p style="margin: 0px;"><b>(23 OF 1998)</b></p></br>
                                <p style="margin: 0px;"><b>(CAP. 47:03)</b></p></br>
                                <p style="margin: 0px;"><b>(SECTION 32)</b></p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="color: #000000;margin: 0px;">
                                    <b>Policy Number</b>
                                </p>
                            </td>
                            <td>
                                <p style="margin: 0px;" colspan="1">
                                {!! $policy->policyNumber??"-" !!}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="color: #000000; margin: 0px;">
                                    <b>Agency</b>
                                </p>
                            </td>
                            <td colspan="1">
                                <p style="margin: 0px;">
                                {!! $policy->agency->name??"-" !!}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="color: #000000; margin: 0px;">
                                    <b>This is to certify that</b>
                                </p>
                            </td>
                            <td colspan="1">
                                <p style="margin: 0px;">
                                    @if($policy->profile->entity_type=='Organisation')
                                    {!! strtoupper($policy->profile->company->name??"") !!}
                                    @else
                                        {!! strtoupper($policy->customer->firstName??"") !!} {!! strtoupper($policy->customer->lastName??"") !!}
                                    @endif
                                </p></br>
                                    @if($policy->profile->entity_type=='Organisation')
                                        <p style="margin: 0px;">{!! strtoupper($policy->profile->company->postal_address??"") !!} {!! strtoupper($policy->profile->city??"") !!} {!! strtoupper($policy->profile->state??"") !!} AND OTHER OFFICES</p></br>
                                        <p style="margin: 0px;">SITUATED IN BOTSWANA</p></br>
                                        <p style="margin: 0px;">GABORONE </p></br>
                                    @else
                                    <p style="margin: 0px;"> {!! strtoupper($policy->profile->address??"") !!} {!! strtoupper($policy->profile->city??"") !!} {!! strtoupper($policy->profile->state??"") !!}</p>
                                    @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin: 0px;font-size:12px;"><b>Is fully insured with this company against liability under the Worker’s Compensation Act, 1998.</b></p></br>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="color: #000000; margin: 0px;">
                                    <b>Period of Insurance</b>
                                </p>
                            </td>
                            <td colspan="1">
                                <p style="margin: 0px;">
                                    From  {{ $invoice_start_date??"" }} To {{ $endDate??"" }}  <br>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin: 0px;font-size:12px;"><b>The Schedule</b></p></br>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #dbe5f1;" colspan="1">
                                <p style="color: #000000; margin: 0px;">
                                    <b>No. of Employees</b>
                                </p><br>
                                <p style="color: #000000; margin: 0px;">
                                    <b>All Employees</b>
                                </p>
                            </td>
                            <td colspan="1">
                                <p style="margin: 0px;">
                                 Estimated Annual Earnings
                                </p> <br>
                                <p style="margin: 0px;">
                                @if(count($coverages->coverageDetail)>0)
                                    @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                                        @if($sub_coverages->coverage_id == 127)
                                        P {{number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',')}}
                                        @endif
                                    @endforeach
                                @endif
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="row" style="margin-top: 20px;margin-bottom: 20px;">
                        <div class="column" style="width:50%;">
                            <p style="margin-bottom: 20px;"><span>Date: {{ $today }}</span></p>
                        </div>
                        <div class="column left" style="width:50%;">
                        <p style="margin-bottom: 20px;"><span>Signature : </span></p>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 20px;margin-bottom: 20px;">
                        <div class="column" style="width:50%;">
                        </div>
                        <div class="column" style="width:20%;">
                            Authorized Signatory:
                        </div>
                        <div class="column" style="width:30%;">
                        <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/sign2/sign.png" align-"left" alt="Sign" width="110px">
                        </div>
                    </div>

                    <div class="row" style="margin-top: 20px;margin-bottom: 20px;">
                        <div class="column" style="width:50%;">
                        </div>
                        <div class="column" style="width:20%;">
                            Company Seal:
                        </div>
                        <div class="column" style="width:30%;">
                            <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Stamp/stamp.png" align="left" alt="stamp" width="110px"></div>
                        </div>
                    </div>
                @endif
                @endforeach
            @endif
            <!-- container End -->

</body>

</html>
