
<!Doctype html >
<html>
<head>
    <TITLE>{{ $policy->policyNumber }} - Quote Sheet</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 500;
            font-size: 12px;
            color: #000000 !important;
        }
        * { color: #000000; }

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
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Alpha Direct Insurance Co. (Pty) Ltd.</p>
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Office: Floor 2, Bar 2, Botswana Innovation Hub,</p>
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Icon Building, Plot 69184 Block 8 Industrial</p>
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Postal Address: P.O. Box 26ADC,</p>
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Gaborone, Botswana</p>
                        <p style="font-size: 12px; margin: 0px;color: black!important;">Phone +267 392 8264 | Fax +267 392 8265</p>
                        <p style="margin: 0px!important;">debtors@alphadirect.co.bw | <a href="mailto: www.alphadirect.co.bw" style="color: #2e77c3!important;">www.alphadirect.co.bw</a></p>
                    </td>
                    <td width="50%">
                        <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">INSURANCE QUOTATION/PROPOSAL</p>
                    </td>
                </tr>
            </table>
        </div>
    </header>

    <div style="margin-top: 1px;">
        <table style="width:100%;">
            <tr valign="middle">
                <td width="50%">
                    <p style="color: #2e77c3!important; font-size: 12px!important;line-height:20px;font-weight: 600!important;margin-bottom: 4px;">To,</p>
                    <p style="margin: 0px;font-size: 12px!important;">
                        @if($policy->profile->entity_type=='Organisation')
                            {!! $policy->profile->company->name??"" !!}
                        @else
                            {!! $policy->customer->firstName??"" !!} {!! $policy->customer->lastName??"" !!}
                        @endif
                    </p>
                    <p style="margin: 0px;font-size: 12px!important;">
                        @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address.",") : "" }} {{ $policy->profile->cities->name ? ($policy->profile->cities->name.",") : "" }} {{ $policy->profile->states->name??"" }}
                        @else
                        {{ $policy->profile->post_address ? ($policy->profile->post_address.",") : "" }} {{ $policy->profile->city ? ($policy->profile->city.",") : "" }} {{ $policy->profile->states->name??"" }}
                        @endif
                    </p><br>
                </td>

                <td width="50%">
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Line of Business : <span style="color: black!important;">

                     @if ($policy->product_id == 7)
                    Commercial All Risk
                    @elseif ($policy->product_id == 8)
                    Domestic All Risk
                    @endif
                    </span></p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Policy No. : <span style="color: black!important;">{!! $policy->policyNumber??"" !!}
                    </span></p>
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                </td>
            </tr>

        </table>
    </div>

    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;margin: 0px;">
                <tr >
                    <td width="30%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 12px;">Agency Name:</p>
                    </td>
                    <td width="70%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"> {!! $policy->agency->name??"" !!}  </p>
                    </td>
                </tr>
                <tr>
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Agency Address:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">  </p>
                    </td>
                </tr>
            </tbody>
        </table >
    </div>


    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr >
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Agent Name:</p>
                    </td>
                    <td width="70%" class="allign">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">{!! $policy->user->firstName??"" !!} {!! $policy->user->lastName??"" !!}  </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr >
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Policy Number : </p>
                    </td>
                    <td width="70%" class="allign">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;"> {!! $policy->policyNumber??"" !!}</p>
                    </td>
                </tr>
                <tr>
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 13px;">Insured Name :</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            @if($policy->profile->entity_type=='Organisation')
                                {!! $policy->profile->company->name??"" !!}
                            @else
                                {!! $policy->customer->firstName??"" !!} {!! $policy->customer->lastName??"" !!}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Insured Mailing Address:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address.",") : "" }} {{ $policy->profile->cities->name ? ($policy->profile->cities->name.",") : "" }} {{ $policy->profile->states->name??"" }}
                            @else
                            {{ $policy->profile->post_address ? ($policy->profile->post_address.",") : "" }} {{ $policy->profile->city ? ($policy->profile->city.",") : "" }} {{ $policy->profile->states->name??"" }}
                            @endif
                        </p>
                    </td>
                </tr>
            </tbody>
        </table >
    </div>

    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr >
                    <td width="100%" class="allign" colspan="2">
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">The provision of this quotation is subject to </p>
                    </td>
                </tr>
                <tr>
                    <td width="25%">
                        <p style="text-align: left;margin: 0px; padding: 0px;"></p>
                    </td>
                    <td width="60%" class="allign" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            a) An acceptable survey and a satisfactory claims history</p>
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            b) Acceptance within 30 days from the date hereof </p>
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            c) F & I's standard endorsements applicable to various policy sections </p>
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            d) The Motor excess conditions, where applicable </p>
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            c) FAIS statutory requirements</p>
                    </td>
                </tr>
            </tbody>
        </table >
    </div>

    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <thead style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr>
                    <td width="65%">
                        <p style="text-align: left; color: #2e77c3!important; font-size: 12px; margin: 0px; font-weight: 600;">General Questions</p>
                    </td>
                    <td width="35%" class="allign" colspan="2">
                        <p style="text-align: center;color: #2e77c3!important;font-size: 12px;  margin: 0px;font-weight: 600;">Answers</p>
                    </td>
                </tr>
            </thead>
            @if($policy->product_id == 7)
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; Date Business Established?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            @if(isset($policy->profile->date))
                            {{ fmtDate($policy->profile->date ?? null, 'd/m/Y', '') }}
                            @endif
                        </p>
                    </td>
                </tr>
            </tbody>
            @endif

            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; Are you currently insured, if so who is your insurer?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        @php $currentlyInsured = AlphaDirect\Lookup::where('id',$policy->profile?->insure)->where('key','are_you_currently_insured')->first()?->value; @endphp
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">{{$currentlyInsured ?? "" }}</p>
                    </td>
                </tr>
            </tbody>
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; Has any insurer ever?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td>
                </tr>
            </tbody>
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">(a) declined any proposal?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">@if(($policy->profile?->decline_proposal)==1)Yes @else No @endif</p>
                    </td>
                </tr>
            </tbody>
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">(b) refused to renew any policy?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">@if(($policy->profile?->refused_policy)==1)Yes @else No @endif</p>
                    </td>
                </tr>
            </tbody>
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;">(c) cancelled any policy?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"> </p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">@if(($policy->profile?->cancel_policy)==1)Yes @else No @endif</p>
                    </td>
                </tr>
            </tbody>
            @if($policy->product_id == 7)
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; Have you or any member of your firm ever made a compromise with creditors or been declared insolvent? </p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">  @if(($policy?->profile->firm_member)==1)Yes @else No @endif</p>
                    </td>
                </tr>
            </tbody>
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; Do you keep a complete set of books showing a true and accurate record of business transacted?</p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"> @if(($policy->profile?->books)==1)Yes @else No @endif</p>
                    </td>
                </tr>
            </tbody>
            @endif
            <tbody style="color: #2e77c3!important;">
                <tr >
                    <td width="65%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;font-size: 10px;">&#8226; How did you hear about Alpha Direct? </p>
                    </td>
                    <!-- <td width="5%" class="allign">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;"></p>
                    </td> -->
                    <td width="35%" class="allign">
                        @php $aboutAlpha = AlphaDirect\Lookup::where('id',$policy->profile->about_alpha)->where('key','hear_about_alphadirect')->first()?->value; @endphp
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size: 10px;">{{ $aboutAlpha ?? "" }}</p>
                    </td>
                </tr>
            </tbody>
        </table >
    </div>
    <br>

    <!-- @if(count($policy_coverages) > 0)
    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
            <tbody style="border-top: 2px solid #2e77c3!important; border-bottom: 2px solid #2e77c3!important;border-left: 2px solid #2e77c3!important;border-right: 2px solid #2e77c3!important;color: #2e77c3!important;">
                <tr>
                    <td width="100%" colspan="2">
                        <p style="text-align: center; margin: 0px; padding: 0px;font-size: 12px;">Premium Summary</p>
                    </td>
                </tr>
                @php
                    $previousRiskName = null;
                    $riskAddressCoverages = [];
                @endphp
                @foreach($policy_coverages as $index => $coverages)
                    @if($coverages->risk_address_id != $previousRiskName)
                        @if(!empty($riskAddressCoverages))
                            @foreach($riskAddressCoverages as $riskCoverage)
                                <tr style="border-top: 0.5px solid #73c9ed!important;font-size:10px;">
                                    <td width="50%">
                                        <p style="text-align: left; margin: 0px; padding: 0px;padding-left: 10px;color:black;">
                                            {{ $riskCoverage['coverage']??"" }}
                                        </p>
                                    </td>
                                    <td width="50%">
                                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">
                                            P {{ number_format($riskCoverage['total']??'', 2, '.', ',')}}
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                            @php $riskAddressCoverages = []; @endphp
                        @endif
                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#D5C8F0;">
                            <td width="100%" colspan="2">
                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Risk Address: {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->state->name??""}} {{$coverages->riskAddress->city->name??""}}</p>
                            </td>
                        </tr>
                        @php
                            $previousRiskName = $coverages->risk_address_id;
                        @endphp
                    @endif

                    @php
                        $coverage = $coverages->coverage->s_ScreenName ?? '';
                        $calculatedValue = $coverages->coverage_calculated_value ?? 0;

                        $found = false;
                        foreach($riskAddressCoverages as &$riskCoverage) {
                            if ($riskCoverage['coverage'] == $coverage) {
                                $riskCoverage['total'] += $calculatedValue;
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            $riskAddressCoverages[] = ['coverage' => $coverage, 'total' => $calculatedValue];
                        }
                    @endphp
                @endforeach

                @if(!empty($riskAddressCoverages))
                    @foreach($riskAddressCoverages as $riskCoverage)
                        <tr style="border-top: 0.5px solid #73c9ed!important;font-size:10px;">
                            <td width="50%">
                                <p style="text-align: left; margin: 0px; padding: 0px;padding-left: 10px;color:black;">
                                    {{ $riskCoverage['coverage']??"" }}
                                </p>
                            </td>
                            <td width="50%">
                                <p style="text-align: center; margin: 0px; padding: 0px;color:black;">
                                    P {{ number_format($riskCoverage['total']??'', 2, '.', ',')}}
                                </p>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    <br>
    <br>
    @endif -->


        <!-- start - Coverage Details-->
        @if(count($policy_coverages)>0)
        <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                <tr>
                    <td width="100%" colspan="2">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;">Coverage Details</p>
                    </td>
                </tr>
            </tbody>
        </table >
        <!-- start -->
        @php
            $previousRisk = null;
        @endphp
        @foreach($policy_coverages as $index => $coverages)
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                @if($coverages->riskAddress->address_name != $previousRisk)
                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#D5C8F0;">
                    <td width="100%" colspan="4">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Risk Address: {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->state->name??""}} {{$coverages->riskAddress->city->name??""}}</p>
                    </td>
                </tr>
                    @php
                        $previousRisk = $coverages->riskAddress->address_name;
                    @endphp
                @endif
                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                    <td width="50%"  colspan="2">
                        <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">&nbsp;&nbsp;  {{$coverages->coverage->s_ScreenName??""}} </p>
                    </td>
                    <td width="25%"  colspan="1">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Sum Insured</p>
                    </td>
                    <td width="25%"  colspan="1">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Premium</p>
                    </td>
                </tr>

                <!-- start vehicle -->
                @if(count($coverages->entities)>0)
                    <tr style="border-top:  2px solid #2e77c3!important;font-size:10px;">
                        <td width="50%"  colspan="2">
                            @foreach($coverages->entities as $newIndex => $entity)
                            <p style="text-align: left;margin: 0px; padding: 0px;padding-left: 10px;color:black;">
                                {{$entity->entity_type}}:
                            </p>
                            @endforeach
                        </td>
                        <td width="50%" colspan="2" >
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
                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">
                                <b>{{$entitiesName??"-"}}</b>
                            </p>
                            @endforeach
                        </td>
                    </tr>
                @endif
                <!-- end vehicle -->

                <!-- start all sub_coverages -->
                @php
                    $totalSumOfCoveragesValues = 0;
                    $totalSumOfCalculatedValues = 0;
                    $sum_insured = 0;
                    $sum = 0;
                @endphp
                @if(count($coverages->all_sub_coverages) > 0 || count($coverages->coverageDetail) > 0)
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
                                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%"  colspan="2">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; {{ $sub_coverages->coverage->s_ScreenName??"" }} </p>
                                    </td>
                                    <td width="25%"  style="border: 0.5px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp;
                                            @if (isset($tbCvgpclimits))
                                               {{ $tbCvgpclimits['s_LimitScreenName'] ?? 'P 0.00' }}
                                            @else
                                            P  {{number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',')}}

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
                                        </p>
                                    </td>
                                    <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}}</p>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        <!-- end present sub_coverages -->

                        @if(!$sub_coverage_found)
                            <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                                <td width="50%"  colspan="2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; {{ $all_sub_coverages->s_ScreenName??"" }} </p>
                                </td>
                                <td width="25%"  style="border: 0.5px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; P  {{number_format(0, 2, '.', ',')}} </p>
                                </td>
                                <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; P  {{number_format(0, 2, '.', ',')}} </p>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @endif

                @php
                     $sum = 0;
                     $sum_insured = 0;
                    foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                        $sum_insured+= $specifed_items->sum_insured;
                        $sum+= $specifed_items->calculated_value;
                    }
                @endphp
                @if(isset($totalSumOfCoveragesValues) || isset($totalSumOfCalculatedValues) || isset($sum_insured) || isset($sum))
                    <!-- end sum of specified items -->
                    @if(count($coverages->specifedItems)>0)
                        <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                            <td width="50%" colspan="2">
                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp;  {{$coverages->coverage->s_ScreenName??""}} Specified Items</p>
                            </td>
                            <td width="25%"  style="border: 0.5px solid #2e77c3!important;"colspan="1" >
                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp;
                                @if($sum_insured)
                                P {{number_format((float)$sum_insured ?? "", 2, '.', ',')}}
                                @else
                                P {{number_format(0, 2, '.', ',')}}
                                @endif
                                </p>
                            </td>
                            <td width="25%"  style="border: 0.5px solid #2e77c3!important;"colspan="1" >
                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp;
                                @if($sum)
                                P {{number_format((float)$sum ?? "", 2, '.', ',')}}
                                @else
                                P {{number_format(0, 2, '.', ',')}}
                                @endif
                                </p>
                            </td>
                        </tr>
                    @endif
                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                    <td width="50%" colspan="2">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp; Sum of Coverages</p>
                    </td>
                    <td width="25%"  style="border: 0.5px solid #2e77c3!important;"colspan="1" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp;
                         @if($totalSumOfCoveragesValues != null || $sum != null)
                         P {{number_format((float)$totalSumOfCoveragesValues+$sum_insured ?? "", 2, '.', ',')}}
                         @else
                         P {{number_format(0, 2, '.', ',')}}
                         @endif
                        </p>
                    </td>
                    <td width="25%"  style="border: 0.5px solid #2e77c3!important;"colspan="1" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp;
                        @if($totalSumOfCalculatedValues != null || $sum != null)
                         P {{number_format((float)$totalSumOfCalculatedValues+$sum ?? "", 2, '.', ',')}}
                         @else
                         P {{number_format(0, 2, '.', ',')}}
                         @endif
                        </p>
                    </td>
                </tr>
                @endif
                <!-- end sub_coverages -->

                <!-- start Specified Items -->
                @if(count($coverages->specifedItems)>0)
                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;">
                        <td width="100%" colspan="4">
                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Specified Items of {{$coverages->coverage->s_ScreenName??""}} Coverage</p>
                        </td>
                    </tr>
                    @php
                      $sumInsuredTotal = 0;
                      $sumInsuredCalculated = 0;
                    @endphp
                    @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                    @php
                        $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                        $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                    @endphp
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="50%" colspan="2">
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                        </td>
                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">

                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}}</p>
                        </td>
                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">

                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">&nbsp;&nbsp; P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}}</p>
                        </td>
                    </tr>

                    @endforeach

                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="50%" colspan="2">
                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp; Total Of Specifed Items</p>
                        </td>

                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                            @if(!empty($sumInsuredTotal))
                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp; P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                            @endif
                        </td>

                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                            @if(!empty($sumInsuredCalculated))
                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">&nbsp;&nbsp; P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                            @endif
                        </td>
                    </tr>

                @endif
                <!-- End Specified Items -->
            </tbody>
        </table >
        @endforeach
        <!-- end -->

        <!-- start -->
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

        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <tbody style="border: 2px solid #2e77c3!important;border-top: 0px solid #2e77c3!important;color: #2e77c3!important;">
                <tr style="font-size:10px;">
                    <td width="40%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;">Annual Premium</p>
                    </td>
                </tr>
                <tr style="font-size:10px;border-top: 0.5px solid #2e77c3!important;">
                    <td width="40%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size:10px;">&nbsp;&nbsp;Final Premium Excluding VAT</p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}</p>
                    </td>
                </tr>
                <tr style="font-size:10px;border-top: 0.5px solid #2e77c3!important;">
                    <td width="40%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;">&nbsp;&nbsp; 14% VAT</p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$vat ?? "", 2, '.', ',')}}</p>
                    </td>
                </tr>
                @if(($policy->premium_freq)==1)
                <tr  style="font-size:10px;border-top: 0.5px solid #2e77c3!important;">
                    <td width="40%">
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;">&nbsp;&nbsp;8% Service Charge On Monthly Payment</p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{number_format((float)$ServiceCharge8 ?? "", 2, '.', ',')}}</p>
                    </td>
                </tr>
                <tr style="font-size:10px;border-top: 0.5px solid #2e77c3!important;">
                    <td width="40%" >
                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size:10px;">&nbsp;&nbsp; 14% VAT On Service Charge</p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$vatServiceCharge ?? "", 2, '.', ',')}}</p>
                    </td>
                </tr>
                @endif
                <tr style="font-size:10px;border-top: 0.5px solid #2e77c3!important;">
                    <td width="40%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;">&nbsp;&nbsp;Final Premium including VAT</p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                    </td>
                    <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{number_format((float)$policyAction->premium ?? "", 2, '.', ',')}}</p>
                    </td>
                </tr>
            </tbody>
        </table >
        <!-- end -->
        <br>
    </div>
    @endif
    <!-- end - Coverage Details -->


    <div>

    <!-- start note -->
    @if(count($policy_coverages)>0)
        @foreach($policy_coverages as $index => $coverages)
            @if(isset($coverages->note->note))
                <div class="container" >
                    <p style="font-size:10px;"> {{$coverages->coverage->s_ScreenName??""}}:</p>
                    <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;
                        font-style:normal;
                        font-size:10px;
                        overflow-y:scroll; border:0.5px solid lightgray; padding:5px; white-space:pre-wrap;
                        ">{{$coverages->note->note}}
                    </pre>

                </div>
            @endif
        @endforeach
    @endif
    <!-- end note -->
</div>
<p style="font-size:10px;">DECLARATION</p>
<p style="font-size:10px;">I warrant that the answers given are true, and I do not know of any material facts, even though specific questions about them
have not been asked, that should be communicated to the Insurer. I have never been refused insurance for the risks I now wish
to insure nor have I had any policy in which I have or had an interest, cancelled or restricted.</p>
<p style="font-size:10px;">Information Sharing</p>

<p style="font-size:10px;">On my behalf and on the behalf of any person I represent herein, I hereby waive my right to privacy with regard to underwriting or
claims information (including credit information) that I provide or that is provided by another person on my behalf in respect of any
insurance policy or claim made or ledged by me.</p>

<p style="font-size:10px;">I acknowledge that the insurance information provided by me may be stored in any number of shared databses and used as set
out above as well as for any decision pertaining to the continuance of my policy or the meeting of any claim I may submit.</p>

<p style="font-size:10px;">I consent that the information may be verified against legally recognised sources or databases.</p>

<p style="font-size:10px;">I AGREE THAT this quotation/proposal shall be the basis of the contract between the insurer and myself.</p>

<p style="font-size:10px;">I WILL ACCEPT the insurer’s standard Commercial Policy.</p>

<p style="font-size:10px;">I UNDERSTAND THAT this insurance will not start until this proposal/quotation has been accepted by the insurer.
</p>

<p style="font-size:10px;">If you are unable to sign this declaration without qualification, please give your reasons here:</p>
<p style="font-size:10px;">________________________________________________________________________________________</p>
<p style="font-size:10px;">________________________________________________________________________________________</p>
<p style="font-size:10px;">________________________________________________________________________________________</p>

<p style="font-size:10px;">ALL WRITTEN STATEMENT AND MATERIALS FURNISHED TO THE INSURANCE COMPANY WHICH THIS APPLICATION
IS SUBMITTED (HEREIN CALLED THE COMPANY) IN CONJUNCTION WITH THIS APPLICATION ARE HEREBY
INCORPORATED BY REFERENCE INTO THIS APPLICATION AND MADE A PART THEREOF.</p>

<p style="font-size:10px;">THIS APPLICATION DOES NOT BIND THE APPLICANT TO BUY, OR THE COMPANY TO ISSUE THE INSURANCE, BUT IT
IS AGREED THAT THIS FORM SHALL BE THE BASIS OF THE CONTRACT SHOULD A POLICY BE ISSUED, AND IT WILL
BE ATTACHED TO AND MADE A PART OF THE POLICY. THE UNDERSIGNED APPLICANT DECLARES THAT THE
STATEMENTS SET FORTH IN THIS APPLICATION ARE TRUE. THE APPLICANT FURTHER DECLARES THAT IF THE
INFORMATION SUPPLIED ON THIS APPLICATION CHANGES BETWEEN THE DATE OF THIS APPLICATION AND THE
TIME WHEN THE POLICY IS ISSUED \, THE APPLICANT WILL IMMEDIATELY NOTIFY THE COMPANY OF SUCH
CHANGES, AND THE COMPANY MAY WITHDRAW OR MODIFY ANY OUTSTANDING QUOTATIONS AND/OR
AUTHORISATION OR AGREEMENT TO FIND THE INSURANCE.
</p>

<p style="font-size:10px;">APPLICANT’S SIGNATURE: _______________________________________</p>

<p style="font-size:10px;">TITLE: __________________________________________</p>

<p style="font-size:10px;">DATE:  __________/___________/________________</p>

<p style="font-size:10px;">IN TERMS OF POLICYHOLDER PROTECTION LEGISLATION, IT IS AN OFFENCE FOR ANYBODY OTHER THAN THE
PROPOSER TO SIGN THIS QUOTATION/PROPOSAL FORM.</p>

<p style="font-size:10px;">WE REMIND YOU NOT TO SIGN ANY BLANK OR PARTIALLY COMPLETED FORMS.</p>

<p style="font-size:10px;">REMEMBER, NO LIABILITY WILL ATTACH TO THE INSURER UNTIL THIS QUOTATION/PROPOSAL HAS BEEN ACCEPTED
BY THE INSURER.
</p>


    <!-- <p style="text-align: justify; font-size: 11px;line-height:1.2; margin:0px; padding:0px;"></p> -->

</body>
</html>

