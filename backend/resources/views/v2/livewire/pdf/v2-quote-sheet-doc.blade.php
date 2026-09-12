
<!Doctype html >
<html>
<head>
    <TITLE>{{ $policy->policyNumber }} - Quote Sheet</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/arial" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/montserrat" rel="stylesheet">
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

        td p {
            padding: 0px 4px !important;
        }
             .greybg {
            background-color: #bcbabaff; /* light grey */
        }
        .greybg,
        .greybg td,
        .greybg th,
        .greybg tr {
            background-color: #bcbaba !important;
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
                        <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">POLICY DOCUMENT</p>
                    </td>
                </tr>
            </table>
            <table style="width:100%;">
                <tr>
                <td>
                <p style="color: black!important;font-size: 16px!important;text-align: center; margin: 0px;font-weight: 600!important;">This policy should be read in conjunction with the policy wording.</p></td>
                </tr>
            </table>
        </div>
    </header>
@php
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
            @endphp

    <div style="margin-top: 1px;">
        <table style="width:100%;">
            <tr valign="middle">
                <td width="50%">
                    <p style="color: #2e77c3!important; font-size: 12px!important;line-height:20px;font-weight: 600!important;margin-bottom: 4px;">To,</p>
                    <p style="margin: 0px;font-size: 12px!important;">
                        @if($policy->profile->entity_type=='Organisation')
                            {!! $policy->profile->company->name??"" !!}
                        @else
                        {!! $policy->customer->firstName??"" !!} {!! $policy->customer->middleName??"" !!} {!! $policy->customer->lastName??"" !!}
                        @endif
                    </p>
                    <p style="margin: 0px;font-size: 12px!important;">
                        @if($policy->profile->entity_type=='Organisation')
                            {{ $policy->profile?->company?->postal_address ? ($policy->profile->company->postal_address.',') : '' }}
                           {{ $policy->profile?->company?->cities?->name ?? '' }}
                        @else
                            {{ $policy->profile?->post_address ? ($policy->profile->post_address . ',') : '' }}
                            {{ $policy->profile?->cities?->name ?? '' }}
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
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
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
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
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
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Insured Mailing Address:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            @if($policy->profile->entity_type=='Organisation')
                                {{ $policy->profile?->company?->postal_address ? ($policy->profile->company->postal_address.',') : '' }}
                                {{ $policy->profile?->company?->cities?->name ?? '' }}
                            @else
                                {{ $policy->profile?->post_address ? ($policy->profile->post_address . ',') : '' }}
                                {{ $policy->profile?->cities?->name ?? '' }}
                            @endif
                        </p>
                    </td>
                </tr>
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Renewal frequency:</p>
                    </td>
                    <td width="70%" class="allign" >
                         @if(($policyAction->current_frequency_id)!= null)
                                @if($policyAction->current_frequency_id==1)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Monthly Premium</p>
                                @elseif($policyAction->current_frequency_id==2)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">3 Installments Premium</p>
                                @elseif($policyAction->current_frequency_id==3)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Annual Premium</p>
                                @elseif($policyAction->current_frequency_id==4)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Semiannual Premium</p>
                                @elseif($policyAction->current_frequency_id==5)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Quarterly Premium</p>
                                @endif
                        @elseif(($policy->premium_freq)!= null)
                                @if($policy->premium_freq==1)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Monthly Premium</p>
                                @elseif($policy->premium_freq==2)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">3 Installments Premium</p>
                                @elseif($policy->premium_freq==3)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Annual Premium</p>
                                @elseif($policy->premium_freq==4)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Semiannual Premium</p>
                                @elseif($policy->premium_freq==5)
                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">Quarterly Premium</p>
                                @endif
                            @endif
                    </td>
                </tr>
               
                        
              @if($policyAction->transaction_type=='RENEW' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE')

                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >

                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Period of Renewal:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            {{ $renewStart }} - {{ $renewEnd }}

                        </p>
                    </td>
                </tr>
                @if(!empty($annualPeriodStart) && !empty($annualPeriodEnd))
                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Annual Period:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            {{ $annualPeriodStart }} - {{ $annualPeriodEnd }}
                        </p>
                    </td>
                </tr>
                @endif
                @elseif($policyAction->transaction_type=='ANNIVERSARY-RENEW')
               
                <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                      
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Period of Insurance:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            {{ $annRenewStart }} - {{ $annRenewEnd }}

                        </p>
                    </td>
                </tr>
                @elseif($policyAction->transaction_type!='NEWBUSINESS' )
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Period of Endorsement:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            {{ $endorStart }} - {{ $endorENd }}

                        </p>
                    </td>
                </tr>
                @else
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                    <td width="30%" >
                        <p style="text-align: left;margin: 0px; padding: 0px;font-size: 12px;">Period of Insurance:</p>
                    </td>
                    <td width="70%" class="allign" >
                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size: 10px;">
                            {{ $fromDateStart }} - {{ $fromDateEnd }}

                        </p>
                    </td>
                </tr>
                @endif
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
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
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
                            e) FAIS statutory requirements</p>
                    </td>
                </tr>
            </tbody>
        </table >
    </div>

    <div>
        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
            <thead style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
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
             <!-- start - Coverage Details-->

        @if(count($policy_coverages ?? [])>0)
        <div>
            <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
                <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                        <td width="100%" colspan="2">
                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;">Index of Sections</p>
                        </td>
                    </tr>
                </tbody>
            </table >
            <!-- Start Section -->
            @php   $finalMotorSumProRata=0; $finalMotorSumCancel=0; $fidelityGurntee=0;  $finalMotorSumPrev=0; $finalMotorSum = 0; $finalMotorSumPrevPersonal=0;  $finalMotorexternalSum = 0; $finalMotorexternalProRata = 0; $finalMotorSumInternal=0;$finalMotorSumInternalProRata=0; $finalMotorPersonalSum=0;$finalMotorPersonaProrata=0;
             $finalMotorexternalSumCancel=0; $finalMotorSumInternalCancel=0; $finalMotorPersonalSumCancel = 0; @endphp
            <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
                {{-- Pro Rata Refund + Pro Rata Premium incl. VAT columns are
                     ALWAYS rendered (header + body cells) so the Policy Doc
                     Index of Sections has the same column layout as the V2
                     Quote Sheet across every transaction type. For non-ENDORSE
                     transactions (NEWBUSINESS/RENEW/REINSTATE/REISSUE) the
                     per-section value-guard @ifs inside each cell keep the
                     content blank — the cell shell stays for alignment.
                     Operations requirement (213504): NEWBUSINESS PDF must
                     look identical in layout to the corresponding ENDORSE
                     PDF; only the values differ. --}}
                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                    <td width="20%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Policy Section Available</p>
                    </td>
                    <td width="5%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Section Taken</p>
                    </td>
                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Pro Rata Refund</p>
                    </td>
                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Pro Rata Premium incl. VAT</p>
                    </td>
                    
                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        {{-- DOMG (product 8) and product 7: premiums are stored per billing
                             period (freq 1/2/5), so the header carries the
                             frequency label — e.g. "MONTHLY Gross incl. VAT"
                             (reference: DOMG2025150196 policy document).
                             Other products keep the fixed ANNUAL label. --}}
                        @if(($policy->product_id == 8 || $policy->product_id == 7) && ($policy->premium_freq) != null)
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">
                            @if($policy->premium_freq==1)
                                Monthly
                            @elseif($policy->premium_freq==2)
                                3 Installments
                            @elseif($policy->premium_freq==3)
                                Annual
                            @elseif($policy->premium_freq==4)
                                Semiannual
                            @elseif($policy->premium_freq==5)
                                Quarterly
                            @endif Gross incl. VAT</p>
                        @else
                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">ANNUAL Gross incl. VAT</p>
                        @endif
                    </td>
                </tr>
                @php
                    $totalCoverageCalculatedValue = 0;
                    $totalProRataPremium = 0;
                    $totalProRataPremiumVatFreq = 0;
                    $totalProRataPremiumVatFreqCancel = 0;
                    $SumMotorCom = 0;
                    $totalProRata = 0;
                    $totalExtensionsOfCommericialMotorPremuim = 0;
                    $prorataCoverage=0; $covragesIds=[];

                @endphp
                 @foreach($all_coverages as $index => $allcoverage)
                @if ($allcoverage->s_ScreenName != 'Commercial Motor'&& $allcoverage->s_ScreenName != 'Personal Motor' && $allcoverage->s_ScreenName != 'Motor Traders External' && $allcoverage->s_ScreenName !='Motor Traders Internal')
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

                            @php
                                $sumIndexInsuredTotal = 0;
                                $sumInsuredTotalNew = 0;
                                $sumInsuredCalculatedNew = 0;
                                $totalIndexSumInsuredCalculated = 0;
                                $totalIndexSumExtCalculated = 0;
                                $matchFound = false;
                                $sumSubCOverages1=0;  $sumSubCOverages=0; $flag=0; @endphp
                            @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                             @foreach($policy_coverages as $index => $coverages)
                             @if($coverages->status == 1)
                               @php

                                        $SumIndexExtCalculated = 0;
                                        $sumIndexInsuredCalculated = 0;
                                        $sumIndex = 0;
                                        $sumInsuredIndex = 0;
                                        
                                @endphp

                                    @foreach($coverages->extension_with_type_extention as $newIndex => $extention_items)
                                        @php
                                            if ($extention_items->type == 'Extention') {
                                                //echo "SONALI".$extention_items->extention_calculated_value.'E';

                                                $SumIndexExtCalculated = $SumIndexExtCalculated + $extention_items->extention_calculated_value;
                                                $totalIndexSumExtCalculated += $extention_items->extention_calculated_value;

                                                if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                    $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                                }else {
                                                    $tbCvgpcextentionlimits = null;
                                                }
                                            }else {
                                                $tbCvgpcextentionlimits = null;
                                            }
                                        @endphp
                                    @endforeach
                                    @if ($allcoverage->s_ScreenName == $coverages->coverage->s_ScreenName)
                                    @php  $matchFound = true;
                                        $getSubCoverPresent = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->where('policy_coverage_id',$coverages->id)
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->sum('calculated_value');
                                        $sumIndexInsuredCalculated = $getSubCoverPresent;
                                        $sum_insured = 0;
                                        $sum = 0;

                                        $getSubCoverPresent1 = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->where('policy_coverage_id',$coverages->id)

                                        ->first();
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                // Skip soft-deleted specified items (relation is
                                                // withTrashed()) so the section premium excludes
                                                // cancelled items — matches the third accumulator's
                                                // existing deleted_at guard.
                                                if($specifed_items->deleted_at==null){
                                                $sum_insured+= $specifed_items->sum_insured;
                                                $sum+= $specifed_items->calculated_value;
                                                }
                                        }
                                
                                            if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW'  || $policyAction->transaction_type=='ENDORSE-RENEW' || ( isset( $getSubCoverPresent1) && $policyAction->transaction_type=='ENDORSE')){
                                            if(isset( $getSubCoverPresent1) && $getSubCoverPresent1->s_ParentCoverageCode == $coverages->coverage->s_CoverageCode){
                                                $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                            
                                            }else{
                                                $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                            }
                                            }
                                    @endphp
                                    @if ($allcoverage->s_ScreenName == 'Fidelity Guarantee')
                                    @php      
                                    $fidelityGurntee=$sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum + $policyCoveragesDataSum;@endphp
                                    @else                                   
                                 
                                    @endif
                                @endif    
                              
                                @endif
                            @endforeach

                            @if ($allcoverage->s_ScreenName != 'Fidelity Guarantee' )
                            @php  $sumSubCOverages= ($diff_in_days_new_coverage/$diff_in_days_main)*$sumSubCOverages;@endphp
                                P @if($sumSubCOverages > 0) - @endif {{number_format((float)$sumSubCOverages, 2, '.', ',')}}
                                  @php
                                    $totalProRataPremiumVatFreqCancel = $totalProRataPremiumVatFreqCancel + $sumSubCOverages ; 
                                    $totalProRataPremiumVatFreqCancel= $totalProRataPremiumVatFreqCancel;
                                    @endphp
                                @php  $sumSubCOverages=0;@endphp
                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee != 0 )
                            @php $fidelityGurntee= ($diff_in_days_new_coverage/$diff_in_days_main)*$fidelityGurntee; @endphp
                            P {{number_format((float)$fidelityGurntee, 2, '.', ',')}}
                            @php
                            $totalProRataPremiumVatFreqCancel = $totalProRataPremiumVatFreqCancel + $fidelityGurntee;
                            $totalProRataPremiumVatFreqCancel= $totalProRataPremiumVatFreqCancel;
                            @endphp

                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee == 0 )
                             P  0.00

                            @endif
                            @endif
                            </p>
                        </td>
                        <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" >
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                            @php
                                $sumIndexInsuredTotal = 0;
                                $sumInsuredTotalNew = 0;
                                $sumInsuredCalculatedNew = 0;
                                $totalIndexSumInsuredCalculated = 0;
                                $totalIndexSumExtCalculated = 0;
                                $matchFound = false;
                                $sumSubCOverages1=0;  $sumSubCOverages=0; $flag=0; @endphp
                            @if($policyAction->transaction_type=='ENDORSE')
                            @foreach($policy_coverages as $index => $coverages)
                            @if($coverages->status == 0)

                                @php
                                        $SumIndexExtCalculated = 0;
                                        $sumIndexInsuredCalculated = 0;
                                        $sumIndex = 0;
                                        $sumInsuredIndex = 0;
                                        $totalIndexSumExtCalculated = 0;
                                @endphp

                                
                                @if ($allcoverage->s_ScreenName == $coverages->coverage->s_ScreenName)
                                    @php  $matchFound = true;
                                        $getSubCoverPresent = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->where('policy_coverage_id',$coverages->id)
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->where('policy_coverage_detail.endors_flag',1)
                                        ->where('policy_coverage_detail.previousActionIdCov',$policyAction->id)
                                        ->sum('policy_coverage_detail.pro_rate_premium');
                                        $sumIndexInsuredCalculated = $getSubCoverPresent;
                                        $sum_insured = 0;
                                        $sum = 0;

                                        $flagC=0;
                                        if($sumIndexInsuredCalculated < 0){
                                            $sumIndexInsuredCalculated= $sumIndexInsuredCalculated *(-1);
                                            $flagC=1;
                                        }else{
                                            $sumIndexInsuredCalculated = $sumIndexInsuredCalculated;
                                            $flagC=0;
                                        }
                                        if($sumIndexInsuredCalculated==0){
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            if($specifed_items->endors_flag==1 && $policyAction->id==$specifed_items->action_id && $specifed_items->deleted_at==null){
                                                $sum+= $specifed_items->calculated_value;
                                            }
                                            elseif($policyAction->transaction_reason=='SUBCOVCANCEL' && $specifed_items->endors_flag==1 &&  $policyAction->id==$specifed_items->action_id && $specifed_items->deleted_at!=null){
                                                $sum+= $specifed_items->calculated_value;

                                            }
                                        }
                                           foreach($coverages->extension_with_type_extention as $newIndex => $extention_items){
                                            if($extention_items->policy_coverage_id==$coverages->id){
                                                    if ($extention_items->type == 'Extention') {
                                                        if($extention_items->endors_flag==1 && $policyAction->id==$extention_items->previousActionIdCov && $extention_items->deleted_at==null){
                                                        $SumIndexExtCalculated = $SumIndexExtCalculated + $extention_items->extention_calculated_value;
                                                        $totalIndexSumExtCalculated += $extention_items->extention_calculated_value;
                                                        }    
                                                       
                                                    }
                                                }
                                            }
                                        }

                                        $getSubCoverPresent1 = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->where('policy_coverage_id',$coverages->id)
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->first('tb_cvgpccoverages.s_ParentCoverageCode');

                                        $getActionId = \AlphaDirect\Models\PolicyCoverageDetail::
                                                        where('policy_coverage_id',$coverages->id)
                                                        ->where('endors_flag',1)
                                                        ->where('previousActionIdCov',$policyAction->id)
                                                        ->first();
                                        if(isset($getActionId) && $getActionId->endors_flag == 1 ){
                                            $sum = 0; $SumIndexExtCalculated=0;$totalIndexSumExtCalculated=0;
                                            $actionDates=AlphaDirect\Models\PolicyAction::where('id',$getActionId->previousActionIdCov)->first();
                                            foreach($coverages->extension_with_type_extention as $newIndex => $extention_items){
                                            if($extention_items->policy_coverage_id==$coverages->id){
                                                    if ($extention_items->type == 'Extention') {
                                                        if($extention_items->endors_flag==1 && $policyAction->id==$extention_items->previousActionIdCov && $extention_items->deleted_at==null){
                                                        $SumIndexExtCalculated = $SumIndexExtCalculated + $extention_items->extention_calculated_value;
                                                        $totalIndexSumExtCalculated += $extention_items->extention_calculated_value;
                                                        }    
                                                        if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                            $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                                        }else {
                                                            $tbCvgpcextentionlimits = null;
                                                        }
                                                    }else {
                                                        $tbCvgpcextentionlimits = null;
                                                    }
                                                }
                                            }
                                            foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                if($policyAction->id==$specifed_items->action_id && $specifed_items->endors_flag==1 && $specifed_items->deleted_at==null){
                                                    $sum+= $specifed_items->calculated_value;
                                                }
                                            }
                                        }
                                        if(isset($getActionId)){
                                        if(isset( $getSubCoverPresent1) && $getSubCoverPresent1->s_ParentCoverageCode == $coverages->coverage->s_CoverageCode &&  $getActionId->previousActionIdCov ==$policyAction->id){
                                            $sumSubCOverages1=$sumSubCOverages1+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                        }else{
                                            $sumSubCOverages1=$sumSubCOverages1+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                        }
                                        }else if(isset($sum) && $sum != 0 && $sumIndexInsuredCalculated==0){
                                            $sumSubCOverages1=$sum;
                                            if($policyAction->transaction_reason=='SUBCOVCANCEL'){
                                                $sumSubCOverages1=$sumSubCOverages1 *(-1);
                                            }
                                        }else if(isset($SumIndexExtCalculated) && $SumIndexExtCalculated != 0 && $sumIndexInsuredCalculated==0){
                                            $sumSubCOverages1=$SumIndexExtCalculated;
                                        }
                                        if(isset($getActionId) && $getActionId->previousActionIdCov ==$policyAction->id && $getActionId->endors_flag==1){
                                        $prorataCoverage= ($diff_in_days_new_coverage/$diff_in_days_main)*$sumSubCOverages1;
                                        if($flagC==1){
                                            $prorataCoverage=  $prorataCoverage *(-1);
                                        }
                                       // array_push($covragesIds,$coverages->id);
                                        }else if(isset($sum) && $sum != 0 ){
                                            $prorataCoverage= ($diff_in_days_new_coverage/$diff_in_days_main)*$sumSubCOverages1;
                                            if($flagC==1){
                                                $prorataCoverage=  $prorataCoverage *(-1);
                                            }
                                        }else if(isset($SumIndexExtCalculated) && $SumIndexExtCalculated != 0 ){
                                            $prorataCoverage= ($diff_in_days_new_coverage/$diff_in_days_main)*$sumSubCOverages1;
                                            if($flagC==1){
                                                $prorataCoverage=  $prorataCoverage *(-1);
                                            }
                                        }
                                    @endphp

                                    @if ($allcoverage->s_ScreenName == 'Fidelity Guarantee')
                                    @php                                    
                                    $fidelityGurntee=($diff_in_days_new_coverage/$diff_in_days_main)*($sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum + $policyCoveragesDataEndorseSum);@endphp
                                    @elseif(isset($getActionId) && $getActionId->previousActionIdCov ==$policyAction->id && $getActionId->endors_flag==1)
                                    @php  @endphp
                                    @endif
                                @endif
                            @endif    
                            @endforeach
                            @if ($allcoverage->s_ScreenName != 'Fidelity Guarantee' )
                                P   {{number_format((float)$prorataCoverage, 2, '.', ',')}}
                                @php  $totalProRata +=  $prorataCoverage;   $prorataCoverage=0;@endphp
                                @if(isset($sum) && $sum != 0 && $totalProRata==0)
                                    @php  $totalProRata +=  $prorataCoverage;  @endphp
                                @endif
                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee != 0 )
                            P {{number_format((float)$fidelityGurntee, 2, '.', ',')}}
                            @php
                            $totalProRata = $totalProRata + $fidelityGurntee;
                            @endphp

                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee == 0 )
                             P  0.00

                            @endif
                            @endif
                            @if($policyAction->transaction_type=='ENDORSE-RENEW')
                            @foreach($policy_coverages as $index => $coverages)
                                @php

                                        $SumIndexExtCalculated = 0;
                                        $sumIndexInsuredCalculated = 0;
                                        $sumIndex = 0;
                                        $sumInsuredIndex = 0;
                                        
                                @endphp

                                    @foreach($coverages->extension_with_type_extention as $newIndex => $extention_items)
                                        @php
                                            if ($extention_items->type == 'Extention') {
                                                //echo "SONALI".$extention_items->extention_calculated_value.'E';

                                                $SumIndexExtCalculated = $SumIndexExtCalculated + $extention_items->extention_calculated_value;
                                                $totalIndexSumExtCalculated += $extention_items->extention_calculated_value;

                                                if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                    $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                                }else {
                                                    $tbCvgpcextentionlimits = null;
                                                }
                                            }else {
                                                $tbCvgpcextentionlimits = null;
                                            }
                                        @endphp
                                    @endforeach
                                           
                                @if ($allcoverage->s_ScreenName == $coverages->coverage->s_ScreenName)
                                    @php  $matchFound = true;
                                        $getSubCoverPresent = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->where('policy_coverage_id',$coverages->id)
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->where('endors_flag',1)
                                        ->sum('calculated_value');
                                        $sumIndexInsuredCalculated = $getSubCoverPresent;
                                        $sum_insured = 0;
                                        $sum = 0;
                                        

                                        $getSubCoverPresent1 = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->where('policy_coverage_id',$coverages->id)

                                        ->first();
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            // Skip soft-deleted specified items — the specifedItems
                                            // relation is withTrashed(), so without this the section
                                            // premium counted cancelled items (matches the guard the
                                            // third accumulator below already has).
                                            if($specifed_items->deleted_at==null){
                                            $sum_insured+= $specifed_items->sum_insured;
                                            $sum+= $specifed_items->calculated_value;
                                            }
                                        }
                                            if($policyAction->transaction_type=='NEWBUSINESS'|| $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE'  || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW'  || $policyAction->transaction_type=='ENDORSE-RENEW' || ( isset( $getSubCoverPresent1) && $policyAction->transaction_type=='ENDORSE' && $policyAction->id==$getSubCoverPresent1->previousActionIdCov)){
                                            if(isset( $getSubCoverPresent1) && $getSubCoverPresent1->s_ParentCoverageCode == $coverages->coverage->s_CoverageCode){
                                                $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;

                                            }else{
                                                $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;

                                            }
                                            }
                                    @endphp
                                    @if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW'  || $policyAction->transaction_type=='ENDORSE-RENEW'  || (isset($getSubCoverPresent1) && $policyAction->transaction_type=='ENDORSE' && $policyAction->id==$getSubCoverPresent1->previousActionIdCov))
                                    @if ($allcoverage->s_ScreenName == 'Fidelity Guarantee')
                                    @php                                    
                                    $totalProRata += $sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum ;  @endphp
                                    @else                                   
                                    @php $totalProRata += $sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum ;  @endphp
                                    @endif
                                    @endif
                                @endif
                            @endforeach

                            @if ($allcoverage->s_ScreenName != 'Fidelity Guarantee' )
                                P   {{number_format((float)$sumSubCOverages, 2, '.', ',')}}
                                @php  $sumSubCOverages=0;@endphp
                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee != 0 )
                            P {{number_format((float)$fidelityGurntee, 2, '.', ',')}}
                            @php
                            $totalProRata = $totalProRata + $fidelityGurntee;
                            @endphp

                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee == 0 )
                             P  0.00

                            @endif
                            @endif
                            </p>
                        </td>
                        <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                            @foreach($policy_coverages as $index => $coverages)
                            @if($coverages->status == 0)
                                @php

                                        $SumIndexExtCalculated = 0;
                                        $sumIndexInsuredCalculated = 0;
                                        $sumIndex = 0;
                                        $sumInsuredIndex = 0;
                                        
                                @endphp

                                    @foreach($coverages->extension_with_type_extention as $newIndex => $extention_items)
                                        @php
                                            if ($extention_items->type == 'Extention') {
                                                //echo "SONALI".$extention_items->extention_calculated_value.'E';

                                                $SumIndexExtCalculated = $SumIndexExtCalculated + $extention_items->extention_calculated_value;
                                                $totalIndexSumExtCalculated += $extention_items->extention_calculated_value;

                                                if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                    $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                                }else {
                                                    $tbCvgpcextentionlimits = null;
                                                }
                                            }else {
                                                $tbCvgpcextentionlimits = null;
                                            }
                                        @endphp
                                    @endforeach
                                           
                                @if ($allcoverage->s_ScreenName == $coverages->coverage->s_ScreenName)
                                    @php  $matchFound = true;
                                        $getSubCoverPresent = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->where('policy_coverage_id',$coverages->id)
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                       // ->where('endors_flag',0)
                                        ->sum('calculated_value');
                                        $sumIndexInsuredCalculated = $getSubCoverPresent;
                                        $sum_insured = 0;
                                        $sum = 0;
                                        

                                        $getSubCoverPresent1 = \AlphaDirect\Models\PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                                        ->whereNull('tb_cvgpccoverages.policy_id')
                                        ->where('policy_coverage_id',$coverages->id)

                                        ->first();
                                        foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                            if($specifed_items->deleted_at==null){
                                            $sum_insured+= $specifed_items->sum_insured;
                                            $sum+= $specifed_items->calculated_value;
                                            }
                                        }
                                            if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW'  || $policyAction->transaction_type=='ENDORSE-RENEW' || $policyAction->transaction_type=='ENDORSE'){
                                                if(isset( $getSubCoverPresent1) && $getSubCoverPresent1->s_ParentCoverageCode == $coverages->coverage->s_CoverageCode){
                                                    $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                                
                                                }else{
                                                    $sumSubCOverages=$sumSubCOverages+$sumIndexInsuredCalculated+$SumIndexExtCalculated+$sum;
                                                }
                                            }
                                        if( $sumSubCOverages==0 && $sum != 0){
                                            $sumSubCOverages=$sum;
                                        }
                                    @endphp
                                    @if ($allcoverage->s_ScreenName == 'Fidelity Guarantee')
                                    @php      
                                    $fidelityGurntee=$sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum + $policyCoveragesDataSum;@endphp
                                    @else                                   
                                    @php $totalProRataPremiumVatFreq += $sumIndexInsuredCalculated + $SumIndexExtCalculated + $sum ;  @endphp
                                    @endif
                                @endif
                                @endif
                            @endforeach

                            @if ($allcoverage->s_ScreenName != 'Fidelity Guarantee' )
                                P   {{number_format((float)$sumSubCOverages, 2, '.', ',')}}
                                @php  $sumSubCOverages=0;@endphp
                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee != 0 )
                            P {{number_format((float)$fidelityGurntee, 2, '.', ',')}}
                            @php
                            $totalProRataPremiumVatFreq = $totalProRataPremiumVatFreq + $fidelityGurntee;
                            @endphp

                            @elseif( $allcoverage->s_ScreenName == 'Fidelity Guarantee' && $fidelityGurntee == 0 )
                             P  0.00

                            @endif
                            </p>
                        </td>
                    </tr>
                @elseif ($allcoverage->s_ScreenName == 'Commercial Motor' )
                        @if(isset($policyComCover))
                            @php
                            $sum_insuredMotor = 0;
                            $sumMotor = 0;
                            $finalMotorSum = 0;
                            $flagCom=0;
                            @endphp

                            @foreach($policyComCover as $index => $motorData)

                                @if($motorData['type_of_cover_main'] != "third_party_only")
                                        @php
                                        $totalExtensionsOfCommericialMotorPremuim =
                                        ($motorData['premium_passenger_liability'] ??0) +
                                        ($motorData['premium_unorthorised_passanger_liability'] ??0) +
                                        ($motorData['premium_parking_facilities'] ??0) +
                                        ($motorData['premium_com_windscreen'] ??0) +
                                        ($motorData['premium_riot_strike'] ??0) +
                                        ($motorData['premium_locks_keys'] ??0) +
                                        ($motorData['premium_wreckage_removal'] ??0) +
                                        ($motorData['premium_credit_shortfall'] ??0) +
                                        ($motorData['premium_third_party_liability'] ??0);


                                        @endphp
                                @elseif($motorData['type_of_cover_main'] == "third_party_only")
                                    @php $totalExtensionsOfPersonallMotorPremuim =0; @endphp

                                @endif
                                    @php
                                                $x = 0 ;
                                                $y = 0 ;
                                                $z = 0 ;
                                            @endphp
                                            @foreach($policy_coverages as $index => $coverages)
                                            @php
                                            if($policyAction->transaction_type=='ENDORSE'){
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                if(isset($specifed_items->motor_id) && $specifed_items->motor_id==$motorData['id'] && $specifed_items->policy_coverage_id==$motorData['policy_coverage_id'] && $specifed_items->endors_flag==1 &&  $specifed_items->action_id==$policyAction->id){
                                                    $sum_insuredMotor+= $specifed_items->sum_insured;
                                                    $sumMotor+= $specifed_items->calculated_value;
                                                    }
                                                }
                                            }else{
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                    if($specifed_items->deleted_at==null ){
                                                       if(isset($specifed_items->motor_id) && $specifed_items->motor_id==$motorData['id'] ){                                                      
                                                            $sum_insuredMotor+= $specifed_items->sum_insured;
                                                            $sumMotor+= $specifed_items->calculated_value;
                                                        }
                                                    }
                                                }
                                            }
                                            @endphp
                                                @if ($allcoverage->s_ScreenName == 'Commercial Motor')
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
                                    @php
                                
                                    if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW' || $policyAction->transaction_type=='ENDORSE-RENEW' || ($policyAction->transaction_type=='ENDORSE')){
                                        if( $motorData['cancelStatus']==0){    
                                            if($motorData['deleted_at']==null) {   
                                            $finalMotorSum = $finalMotorSum + $motorData['finalSum'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotor;
                                            }
                                        }
                                    }
                                    if (
                                        $policyAction->transaction_type == 'ENDORSE' &&
                                        $policyAction->transaction_reason == 'COVERAGECANCEL' &&
                                        (
                                            $motorData['cancelStatus'] == 1 ||
                                            (
                                                isset($motorData['previousActionIdCov']) &&
                                                $motorData['previousActionIdCov'] == $policyAction->id
                                            )
                                        ) &&
                                        ($motorData['cancelStatus'] == 1 || $motorData['deleted_at'] != null)
                                    ) {                                    
                                    $finalMotorSumCancel = $finalMotorSumCancel + $motorData['finalSum'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotor;
                                    }
                                    if( $policyAction->transaction_type=='ENDORSE' &&$motorData['endors_flag']==1 &&  $motorData['previousActionIdCov']==$policyAction->id && ($motorData['cancelStatus']==0)){
                                        $flagCom=0;
                                        
                                        if( $motorData['deleted_at']==null){
                                        if((float)$motorData['pro_rate_premium'] < 0){
                                            $motorData['pro_rate_premium'] =  (float)$motorData['pro_rate_premium'] *(-1);
                                            $flagCom=1;
                                        }else{
                                            $motorData['pro_rate_premium'] =  $motorData['pro_rate_premium'];
                                            $flagCom=0;
                                        }
                                        $finalMotorSumProRata = $finalMotorSumProRata + $motorData['pro_rate_premium'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotor;
                                       
                                    }
                                    }
                                    if($sumMotor!=0 && $policyAction->transaction_type=='ENDORSE' && $finalMotorSumProRata==0)
                                    {
                                        $finalMotorSumProRata = $finalMotorSumProRata + $sumMotor;
                                        $finalMotorSumProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumProRata;
                                        
                                    }
                                    // if($policyAction->transaction_type=='ENDORSE-RENEW' && $motorData['endors_flag']==1){
                                    //     $finalMotorSumProRata = $finalMotorSumProRata + $motorData['finalSum'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotor;
                                    // }
                                    $sumMotor=0;
                                    @endphp

                            @endforeach
                            @php 
                             
                            if($flagCom==1){
                            $finalMotorSumProRata=  $finalMotorSumProRata *(-1);
                            }
                            if($diff_in_days_new_coverage > 0 && $diff_in_days_main > 0 && $finalMotorSumProRata!=0){
                               
                                $finalMotorSumProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumProRata;
                               
                            }
                               if($finalMotorSumCancel !=0){
                                $finalMotorSumCancel= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumCancel;
                               }
                               if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'WRITEOFFCVG'){
                                $finalMotorSumProRata= 0;
                               }
                            @endphp
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="20%" style="border: 1px solid #2e77c3!important;border-right:1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;text-transform: capitalize;">
                                        {{ 'Commercial Motor' }}
                                        </p>
                                    </td>
                                    <td width="5%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                       @if($allcoverage->s_ScreenName == 'Commercial Motor' && $finalMotorSum >0 )
                                                Yes
                                            @else
                                                No
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" class="endendorsement-2">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                    <!-- @if($finalMotorSumCancel != 0)P - {{ number_format((float)$finalMotorSumCancel,2,'.',',') }} @endif -->
                                    @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                                    P - {{ number_format((($finalMotorSumCancel ?? 0) == 0 ? 0 : $finalMotorSumCancel), 2, '.', ',') }}
                                    @endif
                                    </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     <!-- @if($finalMotorSumProRata != 0)P {{ number_format((float)$finalMotorSumProRata,2,'.',',') }} @endif -->
                                    @if($policyAction->transaction_type=='ENDORSE')
                                     P {{ number_format((float) ($finalMotorSumProRata != 0 ? $finalMotorSumProRata : 0), 2, '.', ',') }}
                                    @endif
                                    </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">

                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     P {{number_format((float)($finalMotorSum), 2, '.', ',') }}
                                    </p>
                                    </td>
                                </tr>
                                @endif
                @elseif ($allcoverage->s_ScreenName == 'Personal Motor' )
                        @if(isset($policyComCoverDom))
                            @php
                            $sum_insuredMotor = 0;
                            $sumMotorPersonal = 0;$flagCom=0;
                            $finalMotorPersonalSum = 0;$finalMotorPersonaProrata=0;
                            $totalExtensionsOfPersonallMotorPremuim=0;

                            @endphp
                            @foreach($policyComCoverDom as $index => $motorDataDom)
                                @if($motorDataDom['type_of_cover_main'] != "third_party_only")
                                        @php
                                        $totalExtensionsOfPersonallMotorPremuim =
                                        ($motorDataDom['premium_passenger_liability'] ??0) +
                                          ($motorDataDom['premium_unorthorised_passanger_liability'] ??0) +
                                          ($motorDataDom['premium_parking_facilities'] ??0) +
                                          ($motorDataDom['premium_com_windscreen'] ??0) +
                                          ($motorDataDom['premium_riot_strike'] ??0) +
                                          ($motorDataDom['premium_locks_keys'] ??0) +
                                          ($motorDataDom['premium_wreckage_removal'] ??0) +
                                          ($motorDataDom['premium_window_glass'] ??0) +
                                          ($motorDataDom['premium_parts_accessories'] ??0) +
                                          ($motorDataDom['premium_audio_accessories'] ??0) +
                                          ($motorDataDom['premium_credit_shortfall'] ??0) +
                                          ($motorDataDom['premium_car_hire_theft'] ??0) +
                                          ($motorDataDom['premium_insured_driver'] ??0) +
                                          ($motorDataDom['premium_insured_family'] ??0) +
                                          ($motorDataDom['premium_medical_expenses'] ??0) +
                                          ($motorDataDom['premium_specified_accessories'] ??0) +
                                          ($motorDataDom['premium_third_party_liability'] ??0);


                                        @endphp
                                    @elseif($motorDataDom['type_of_cover_main'] == "third_party_only")
                                    @php $totalExtensionsOfPersonallMotorPremuim =0; @endphp

                                    @endif
                                        @php
                                                $x = 0 ;
                                                $y = 0 ;
                                                $z = 0 ;
                                            @endphp
                                            @foreach($policy_coverages as $index => $coverages)
                                            @php
                                            if($policyAction->transaction_type=='ENDORSE'){
                                            foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                 if(isset($specifed_items->motor_id) && $specifed_items->motor_id==$motorDataDom['id'] && $specifed_items->motor_id==$motorDataDom['id'] && $specifed_items->endors_flag==1){
                                                    $sum_insuredMotor+= $specifed_items->sum_insured;
                                                    $sumMotorPersonal+= $specifed_items->calculated_value;
                                                 }
                                            }
                                            }else{
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                    if($specifed_items->deleted_at==null){
                                                       if(isset($specifed_items->motor_id) && $specifed_items->motor_id==$motorDataDom['id'] ){                                                      
                                                            $sum_insuredMotor+= $specifed_items->sum_insured;
                                                            $sumMotorPersonal+= $specifed_items->calculated_value;
                                                        }
                                                    }
                                                }
                                            }
                                            @endphp
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
                                    @php
                                    $diff_in_days_old_coverage = 0;
                                    if(($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='ENDORSE' || $policyAction->transaction_type=='RENEW' || $policyAction->transaction_type=='ENDORSE-RENEW') &&  ($motorDataDom['cancelStatus']==0 || $motorDataDom['deleted_at']==null)){
                                        if( $motorDataDom['cancelStatus']==0){    
                                        if($motorDataDom['deleted_at']==null) { 
                                        $finalMotorPersonalSum = $finalMotorPersonalSum + $motorDataDom['finalSum'] + $totalExtensionsOfPersonallMotorPremuim + $sumMotorPersonal;
                                        }
                                        }
                                    }
                                    $flagCom=0;
                                      if (
                                        $policyAction->transaction_type == 'ENDORSE' &&
                                        $policyAction->transaction_reason == 'COVERAGECANCEL' &&
                                        (
                                            $motorDataDom['cancelStatus'] == 1 ||
                                            (
                                                isset($motorDataDom['previousActionIdCov']) &&
                                                $motorDataDom['previousActionIdCov'] == $policyAction->id
                                            )
                                        ) &&
                                        ($motorDataDom['cancelStatus'] == 1 || $motorDataDom['deleted_at'] != null)
                                    ) {
                                    $finalMotorPersonalSumCancel = $finalMotorPersonalSumCancel + $motorDataDom['finalSum'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotorPersonal;
                                    }

                                    if($policyAction->transaction_type=='ENDORSE'  && $motorDataDom['endors_flag']==1 &&  $motorDataDom['previousActionIdCov']==$policyAction->id && $motorDataDom['cancelStatus']==0){
                                        $flagCom=0;
                                        if($motorDataDom['deleted_at']==null){
                                        if((float)$motorDataDom['pro_rate_premium'] < 0){
                                            $motorDataDom['pro_rate_premium'] =  $motorDataDom['pro_rate_premium'] *(-1);
                                            $flagCom=1;
                                        }else{
                                            $motorDataDom['pro_rate_premium'] =  $motorDataDom['pro_rate_premium'];
                                            $flagCom=0;
                                        }
                                        $finalMotorSumProRata = $finalMotorSumProRata + $motorDataDom['pro_rate_premium'] + $totalExtensionsOfCommericialMotorPremuim + $sumMotorPersonal;
                                      
                                    }

                                    }
                                    if($sumMotorPersonal!=0 && $policyAction->transaction_type=='ENDORSE' && $finalMotorSumProRata==0)
                                    {
                                        $finalMotorSumProRata = $finalMotorSumProRata + $sumMotorPersonal;
                                        $finalMotorSumProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumProRata;
                                        
                                    }
                                    // if($policyAction->transaction_type=='ENDORSE-RENEW' && $motorDataDom['endors_flag']==1){
                                    //     $finalMotorPersonaProrata = $finalMotorPersonaProrata + $motorDataDom['finalSum'] + $totalExtensionsOfPersonallMotorPremuim + $sumMotorPersonal;
                                    // }
                                    $sumMotorPersonal = 0;

                                    @endphp
                            @endforeach
                            @php
                                if($flagCom==1){
                                    $finalMotorSumProRata=  $finalMotorSumProRata *(-1);
                                }
                                if($diff_in_days_new_coverage > 0 && $diff_in_days_main > 0 && $finalMotorSumProRata !=0){
                                            $finalMotorSumProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumProRata;
                                }      
                                if($finalMotorPersonalSumCancel !=0 && $diff_in_days_new_coverage > 0 && $diff_in_days_main > 0){
                                $finalMotorPersonalSumCancel= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorPersonalSumCancel;
                                }
                                // Section Taken: recompute from the policy's coverages directly.
                                // $x is otherwise only set inside the vehicle loop above, so when
                                // the policy has no Personal Motor vehicles ($policyComCoverDom
                                // empty) it leaks the previous index row's value — wrong Yes/No
                                // on the "No" rows the DOMG index always renders.
                                $x = 0;
                                foreach ($policy_coverages as $_pmRow) {
                                    if (($allcoverage->s_ScreenName ?? null) == ($_pmRow->coverage->s_ScreenName ?? null)) { $x = 1; break; }
                                }
                                @endphp
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="20%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;text-transform: capitalize;">
                                         {{ 'Personal Motor' }}
                                        </p>
                                    </td>
                                    <td width="5%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if ($x > 0)
                                                Yes
                                            @else
                                                No
                                            @endif
                                        </p>
                                    </td>
                                    <td width="20%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <!-- @if($finalMotorPersonalSumCancel != 0) P - {{ number_format((float)($finalMotorPersonalSumCancel), 2, '.', ',') }} @endif  -->
                                    @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                                    P - {{ number_format((($finalMotorPersonalSumCancel ?? 0) == 0 ? 0 : $finalMotorPersonalSumCancel), 2, '.', ',') }}
                                    @endif
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <!-- @if($finalMotorSumProRata != 0) P {{ number_format((float)($finalMotorSumProRata), 2, '.', ',') }} @endif -->
                                    @if($policyAction->transaction_type=='ENDORSE')
                                     P {{ number_format((float) ($finalMotorSumProRata != 0 ? $finalMotorSumProRata : 0), 2, '.', ',') }}
                                    @endif
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     P {{ number_format((float)($finalMotorPersonalSum), 2, '.', ',') }}
                                    </p>
                                    </td>
                                </tr>
                @endif
                @elseif ($allcoverage->s_ScreenName == 'Motor Traders External')
                        @if(isset($policyMotorIndex))
                            @php
                            $sumInsuredTotalExt = 0;
                            $sumInsuredCalculatedExt = 0;
                            $finalMotorexternalSum = 0;$flagCom=0;
                            $specified_items=0;
                            @endphp

                            @foreach($policyMotorIndex as $index => $motorDatas)
                                    @php
                                    $specified_items=$motorDatas['specified_items'] ??0;
                                        $totalExtensionsOfMotorPremuim =
                                        ($motorDatas['vehicle_lent_hire_calculated_value'] ??0) +
                                        ($motorDatas['social_domestic_pleasure_calculated_value'] ??0) +
                                        ($motorDatas['unauthoried_use_calculated_value'] ??0) +
                                        ($motorDatas['windscreen_calculated_value'] ??0) +
                                        ($motorDatas['contigent_liability_calculated_value'] ??0) +
                                        ($motorDatas['wreckage_removal_calculated_value'] ??0) +
                                        ($motorDatas['Loss_of_use_of_customer_calculated_value'] ??0) +
                                        ($motorDatas['special_type_vehicle_calculated_value'] ??0) +
                                        ($motorDatas['loss_of_key_calculated_value'] ??0) +
                                        ($motorDatas['passanger_liability_respect_of_motor_calculated_value'] ??0) +
                                        ($motorDatas['motor_cycle_motor_tricycle_calculated_value'] ??0)
                                        @endphp

                                            @php
                                                $x = 0 ;
                                                $y = 0 ;
                                                $z = 0 ;
                                            @endphp
                                            @foreach($policy_coverages as $index => $coverages)
                                            @php
                                            if($policyAction->transaction_type=='ENDORSE'){
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                    if($specifed_items->policy_coverage_id==$motorDatas['policy_coverage_id'] && $specifed_items->endors_flag==1 && $specifed_items->deleted_at==null ){
                                                    $sumInsuredTotalExt = $sumInsuredTotalExt +  $specifed_items->sum_insured;
                                                    $sumInsuredCalculatedExt = $sumInsuredCalculatedExt +  $specifed_items->calculated_value;
                                                    }
                                                }
                                            }else{
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                    if($specifed_items->policy_coverage_id==$motorDatas['policy_coverage_id'] && $specifed_items->deleted_at==null){
                                                    $sumInsuredTotalExt = $sumInsuredTotalExt +  $specifed_items->sum_insured;
                                                    $sumInsuredCalculatedExt = $sumInsuredCalculatedExt +  $specifed_items->calculated_value;
                                                    }
                                                }
                                            }
                                            @endphp
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

                                    @php
                                    $finalMotorexternalSum = $finalMotorexternalSum + $motorDatas['finalSumMotorExternal'] + $totalExtensionsOfMotorPremuim +$specified_items;
                                    
                                    if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL' && $motorDatas['cancelStatus']==1){
                                        $finalMotorexternalSumCancel = $finalMotorexternalSumCancel + $motorDatas['finalSumMotorExternal'] + $totalExtensionsOfMotorPremuim +$specified_items;
                                        $finalMotorexternalSumCancel= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorexternalSumCancel;
                                    }
                                   
                                    if($policyAction->transaction_type=='ENDORSE' && $motorDatas['endors_flag']==1 &&  $motorDatas['previousActionIdCov']==$policyAction->id && $motorDatas['cancelStatus']==0){
                                        $flagCom=0;
                                        if($motorDatas['pro_rate_premium'] < 0){
                                            $motorDatas['pro_rate_premium'] =  $motorDatas['pro_rate_premium'] *(-1);
                                            $flagCom=1;
                                        }else{
                                            $motorDatas['pro_rate_premium'] =  $motorDatas['pro_rate_premium'];
                                            $flagCom=0;
                                        }
                                        $finalMotorexternalProRata = $finalMotorexternalProRata + $motorDatas['pro_rate_premium'] + $totalExtensionsOfMotorPremuim +$specified_items;
                                        $finalMotorexternalProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorexternalProRata;
                                        if($flagCom==1){
                                            $finalMotorexternalProRata=  $finalMotorexternalProRata *(-1);
                                        }
                                       
                                        array_push($covragesIds,$coverages->id);

                                    }
                                    $specified_items=0;
                                    @endphp

                            @endforeach
                            @php
                                // Motor Traders External pro-rata also lives in
                                // motor_traders.pro_rate_premium (separate from
                                // motor.pro_rate_premium that the loop above
                                // walks). Add the wizard-stamped delta sum here
                                // so the per-section Pro Rata cell shows the MT
                                // contribution alongside any motor-table delta.
                                if (\Schema::hasColumn('motor_traders', 'pro_rate_premium')
                                    && \Schema::hasColumn('motor_traders', 'previousActionIdCov')
                                    && $policyAction->transaction_type === 'ENDORSE') {
                                    $_mtExtPcIds = \DB::table('policy_coverages as pc')
                                        ->join('tb_cvgpccoverages as cv', 'cv.id', '=', 'pc.coverage_id')
                                        ->where('pc.policy_id', $policy->id)
                                        ->where('pc.action_id', $policyAction->id)
                                        ->where('cv.s_CoverageCode', 'MOTORTRADERSEXTERNAL')
                                        ->pluck('pc.id');
                                    if ($_mtExtPcIds->isNotEmpty()) {
                                        $_mtExtPr = (float) \DB::table('motor_traders')
                                            ->whereIn('policy_coverage_id', $_mtExtPcIds)
                                            ->where('previousActionIdCov', $policyAction->id)
                                            ->sum('pro_rate_premium');
                                        $_factor = ($diff_in_days_main > 0)
                                            ? ($diff_in_days_new_coverage / $diff_in_days_main) : 0;
                                        $finalMotorexternalProRata += $_mtExtPr * $_factor;
                                    }
                                }
                            @endphp
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="20%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;text-transform: capitalize;">
                                         {{ 'Motor Traders External' }}
                                        </p>
                                    </td>
                                    <td width="5%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if ($x > 0)
                                                Yes
                                            @else
                                                No
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" class="endendorsement-4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                    <!-- @if($finalMotorexternalSumCancel!=0)P -{{ number_format((float)$finalMotorexternalSumCancel, 2, '.', ',') }}@endif -->
                                    @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                                    P -{{ number_format((float) ($finalMotorexternalSumCancel != 0 ? $finalMotorexternalSumCancel : 0), 2, '.', ',') }}
                                    @endif
                                    </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     <!-- @if($finalMotorexternalProRata!=0)P {{ number_format((float)$finalMotorexternalProRata, 2, '.', ',') }}@endif -->
                                    @if($policyAction->transaction_type=='ENDORSE')
                                     P {{ number_format((float) ($finalMotorexternalProRata != 0 ? $finalMotorexternalProRata : 0), 2, '.', ',') }}                                    </td>
                                    @endif
                                    </p>
                                    </td>
                                     <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     P {{ number_format((float)$finalMotorexternalSum, 2, '.', ',') }}
                                    </p>
                                    </td>
                                </tr>
                        @endif
                @elseif ($allcoverage->s_ScreenName == 'Motor Traders Internal')
                        @if(isset($policyMotorIndexInternal))
                            @php
                            $sum_insuredMotorInternal = 0;
                            $sumMotorInternal = 0;
                            $finalMotorSumInternal = 0;
                            $sum_insured=0;$flagCom=0;
                            @endphp

                            @foreach($policyMotorIndexInternal as $index => $motorDataInternal)

                                        @php
                                        $totalExtensionsOfCommericialMotorPremuimInternal =
                                        ($motorDataInternal['vehicle_lent_hire_calculated_value'] ??0) +
                                        ($motorDataInternal['social_domestic_pleasure_calculated_value'] ??0) +
                                        ($motorDataInternal['unauthoried_use_calculated_value'] ??0) +
                                        ($motorDataInternal['windscreen_calculated_value'] ??0) +
                                        ($motorDataInternal['contigent_liability_calculated_value'] ??0) +
                                        ($motorDataInternal['wreckage_removal_calculated_value'] ??0) +
                                        ($motorDataInternal['loss_of_key_calculated_value'] ??0) +
                                        ($motorDataInternal['Loss_of_use_of_customer_calculated_value'] ??0) +
                                        ($motorDataInternal['motor_cycle_motor_tricycle_calculated_value'] ??0) +
                                        ($motorDataInternal['special_type_vehicle_calculated_value'] ??0) +
                                        ($motorDataInternal['passanger_liability_respect_of_motor_calculated_value'] ??0)
                                        @endphp

                                            @php
                                                $x = 0 ;
                                                $y = 0 ;
                                                $z = 0 ;
                                            @endphp
                                            @foreach($policy_coverages as $index => $coverages)
                                            @php
                                            if($policyAction->transaction_type=='ENDORSE'){
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                if($specifed_items->policy_coverage_id==$motorDataInternal['policy_coverage_id'] && $specifed_items->endors_flag==1 && $specifed_items->deleted_at==null ){
                                                    $sum_insured+= $specifed_items->sum_insured;
                                                    $sumMotorInternal+= $specifed_items->calculated_value;
                                                    }
                                                }
                                            }
                                            else{
                                                foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                                if($specifed_items->policy_coverage_id==$motorDataInternal['policy_coverage_id'] && $specifed_items->deleted_at==null){
                                                    $sum_insured+= $specifed_items->sum_insured;
                                                    $sumMotorInternal+= $specifed_items->calculated_value;
                                                    }
                                                }
                                            }    
                                            @endphp
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
                                    @php
                                    $finalMotorSumInternal = $finalMotorSumInternal + $motorDataInternal['finalSumMotorInternal'] + $totalExtensionsOfCommericialMotorPremuimInternal + $sumMotorInternal;
                                    
                                    if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL' && $motorDataInternal['cancelStatus']==1){
                                        $finalMotorSumInternalCancel = $finalMotorSumInternalCancel + $motorDataInternal['finalSumMotorInternal'] + $totalExtensionsOfCommericialMotorPremuimInternal + $sumMotorInternal;
                                        $finalMotorSumInternalCancel= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumInternalCancel;
                                    }

                                    if($policyAction->transaction_type=='ENDORSE' && $motorDataInternal['endors_flag']==1 &&  $motorDataInternal['previousActionIdCov']==$policyAction->id && $motorDataInternal['cancelStatus']==0){
                                        $flagCom=0;
                                        if($motorDataInternal['pro_rate_premium'] < 0){
                                            $motorDataInternal['pro_rate_premium'] =  $motorDataInternal['pro_rate_premium'] *(-1);
                                            $flagCom=1;
                                        }else{
                                            $motorDataInternal['pro_rate_premium'] =  $motorDataInternal['pro_rate_premium'];
                                            $flagCom=0;
                                        }
                                        $finalMotorSumInternalProRata = $finalMotorSumInternalProRata + $motorDataInternal['pro_rate_premium'] + $totalExtensionsOfCommericialMotorPremuimInternal +$sumMotorInternal;
                                        $finalMotorSumInternalProRata= ($diff_in_days_new_coverage/$diff_in_days_main)*$finalMotorSumInternalProRata;
                                        if($flagCom==1){
                                            $finalMotorSumInternalProRata=  $finalMotorSumInternalProRata *(-1);
                                        }
                                       
                                    }
                                    $sumMotorInternal=0;
                                    @endphp

                            @endforeach
                            @php
                                // Motor Traders Internal pro-rata also lives in
                                // motor_traders_internal.pro_rate_premium. Add
                                // wizard-stamped delta sum × factor here so the
                                // per-section Pro Rata cell shows the contribution.
                                if (\Schema::hasColumn('motor_traders_internal', 'pro_rate_premium')
                                    && \Schema::hasColumn('motor_traders_internal', 'previousActionIdCov')
                                    && $policyAction->transaction_type === 'ENDORSE') {
                                    $_mtIntPcIds = \DB::table('policy_coverages as pc')
                                        ->join('tb_cvgpccoverages as cv', 'cv.id', '=', 'pc.coverage_id')
                                        ->where('pc.policy_id', $policy->id)
                                        ->where('pc.action_id', $policyAction->id)
                                        ->where('cv.s_CoverageCode', 'MOTORTRADERSINTERNAL')
                                        ->pluck('pc.id');
                                    if ($_mtIntPcIds->isNotEmpty()) {
                                        $_mtIntPr = (float) \DB::table('motor_traders_internal')
                                            ->whereIn('policy_coverage_id', $_mtIntPcIds)
                                            ->where('previousActionIdCov', $policyAction->id)
                                            ->sum('pro_rate_premium');
                                        $_factorInt = ($diff_in_days_main > 0)
                                            ? ($diff_in_days_new_coverage / $diff_in_days_main) : 0;
                                        $finalMotorSumInternalProRata += $_mtIntPr * $_factorInt;
                                    }
                                }
                            @endphp
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="20%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;text-transform: capitalize;">
                                         {{'Motor Traders Internal'}}
                                        </p>
                                    </td>
                                    <td width="5%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                            @if ($x > 0)
                                                Yes
                                            @else
                                                No
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1" class="endendorsement-5">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                    <!-- @if($finalMotorSumInternalCancel!=0)P -{{ number_format((float)$finalMotorSumInternalCancel, 2, '.', ',') }}@endif     -->
                                    @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                                    P -{{ number_format((float) ($finalMotorSumInternalCancel != 0 ? $finalMotorSumInternalCancel : 0), 2, '.', ',') }}
                                    @endif
                                    </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     <!-- @if($finalMotorSumInternalProRata!=0)P {{ number_format((float)$finalMotorSumInternalProRata, 2, '.', ',') }}@endif -->
                                    @if($policyAction->transaction_type=='ENDORSE')
                                     P {{ number_format((float) ($finalMotorSumInternalProRata != 0 ? $finalMotorSumInternalProRata : 0), 2, '.', ',') }}
                                    @endif
                                    </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">

                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     P {{ number_format((float)$finalMotorSumInternal, 2, '.', ',') }}

                                    </p>
                                    </td>
                                </tr>
                        @endif
                @endif
                @endforeach
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="AO">
                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Premium</p>
                    </td>
                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1" class="endendorsement-total">
                    @if($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                        P -{{ number_format((float)($totalProRataPremiumVatFreqCancel + $finalMotorSumCancel + $finalMotorexternalSumCancel + $finalMotorSumInternalCancel + $finalMotorPersonalSumCancel ) , 2, '.', ',') }}
                        </p>
                    @endif
                    </td>

                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                    @php
                        // Pro-Rata Total = canonical $rateProRataPremium (= Rate
                        // banner / policy_actions.premium). Operations rule:
                        // Rate banner, V2 Quote sheet, Policy Doc Pro-Rata Total
                        // must all match exactly. Same logic as v2-quote-sheet
                        // L2158 — use canonical first, fall back to per-section
                        // sum only when canonical is missing.
                        $proRataDisplayTotalDoc = isset($rateProRataPremium) && abs((float)$rateProRataPremium) > 0.001
                            ? (float) $rateProRataPremium
                            : ($totalProRata + $finalMotorPersonaProrata + $finalMotorSumInternalProRata + $finalMotorexternalProRata + $finalMotorSumProRata);
                    @endphp
                    @if($policyAction->transaction_type!='NEWBUSINESS')
                        @if($proRataDisplayTotalDoc > 0)
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                        P {{ number_format((float)$proRataDisplayTotalDoc, 2, '.', ',') }}
                        </p>
                        @elseif($proRataDisplayTotalDoc < 0)
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#CC0000;">
                        P {{ number_format((float)$proRataDisplayTotalDoc, 2, '.', ',') }}
                        </p>
                        @endif
                    @endif
                    </td>
                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        @php
                            // Annual Total = canonical $rateAnnualPremium (= Rate
                            // banner). Policy Doc Total must match Rate banner +
                            // V2 Quote Total exactly. Per-section sum kept as
                            // fallback when canonical is missing/zero.
                            $annualDisplayTotalDoc = isset($rateAnnualPremium) && (float)$rateAnnualPremium > 0
                                ? (float) $rateAnnualPremium
                                : ($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal + $finalMotorPersonalSum);
                        @endphp
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                        P {{ number_format((float)$annualDisplayTotalDoc, 2, '.', ',') }}
                        </p>
                    </td>
                </tr>
                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="CO">
                    <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> VAT</p>
                    </td>

                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">

                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                            @php
                            $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                          
                            $premiumExcludingVAT =  ($totalProRataPremiumVatFreqCancel + $finalMotorSumCancel + $finalMotorexternalSumCancel + $finalMotorSumInternalCancel + $finalMotorPersonalSumCancel) / (1 + ($regionVat/100));
                            
                            if(($policy->premium_freq)==1){
                                $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                            }

                            $vat = $premiumExcludingVAT * ($regionVat/100);
                            $ServiceCharge8 = $premiumExcludingVAT*(8/100);
                            $vatServiceCharge = $ServiceCharge8*($regionVat/100);
                            $vat_pro_data=0;
                            $vat_pro_data = $vat;
                            @endphp
                            @if(!empty($vat_pro_data))
                            P {{ number_format((float)$vat_pro_data ?? "", 2, '.', ',') }}
                            @endif
                        </p>

                    </td>

                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">

                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                            {{-- Pro-rata VAT only renders on ENDORSE — keeps the
                                 Policy Doc VAT row blank on NEWBUSINESS /
                                 RENEW / REINSTATE / REISSUE / CANCEL, matching
                                 the Total Pro-Rata row above which is also blank
                                 for those transaction types. --}}
                            @if($policyAction->transaction_type === 'ENDORSE')
                            @php
                             $premiumExcludingVAT =0;
                            $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                            // VAT derived from canonical $proRataDisplayTotalDoc
                            // (gross incl-VAT) so Policy Doc VAT row matches Rate
                            // banner + V2 Quote VAT. Strip VAT via /(1+vat/100),
                            // then VAT = base × vat%. Monthly (freq=1) also strips
                            // the 8% service charge via another /1.08 first.
                            $premiumExcludingVAT = (float)($proRataDisplayTotalDoc ?? 0) / (1 + ($regionVat/100));
                            if(($policy->premium_freq)==1){
                                $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                            }

                            $vat = $premiumExcludingVAT * ($regionVat/100);
                            $ServiceCharge8 = $premiumExcludingVAT*(8/100);
                            $vatServiceCharge = $ServiceCharge8*($regionVat/100);
                            $vat_pro_data=0;
                            $vat_pro_data = $vat;
                            @endphp
                            @if(!empty($vat_pro_data))
                            P {{ number_format((float)$vat_pro_data ?? "", 2, '.', ',') }}
                            @endif
                            @endif
                        </p>

                    </td>

                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                        @php

                            $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                            $premiumExcludingVAT = ($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal + $finalMotorPersonalSum) / (1 + ($regionVat/100));
                            if(($policy->premium_freq)==1){
                                $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                            }

                            $vat = $premiumExcludingVAT * ($regionVat/100);
                            $ServiceCharge8 = $premiumExcludingVAT*(8/100);
                            $vatServiceCharge = $ServiceCharge8*($regionVat/100);
                            $vat_month=0;
                            //dd($vat,$ServiceCharge8,$vatServiceCharge);
                            //$vat_month = ($vat + $vatServiceCharge);
                            $vat_month = $vat;
                        //$vat_month = ($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal + $finalMotorPersonalSum) * ($regionVat/100);

                        @endphp
                        @if(!empty($totalProRataPremiumVatFreq))
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @elseif(!empty($finalMotorSumInternal))
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @elseif(!empty($finalMotorSum))
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @elseif(!empty($finalMotorexternalSum))
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @elseif(!empty($finalMotorPersonalSum))
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @elseif(isset($vat_month) && $vat_month > 0)
                                P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }}
                        @endif
                        </p>
                    </td>
                </tr>
            </table>
            <!-- End index Section -->

           <!-- start -->
            @php
                $previousRisk = null;
            @endphp

            @foreach($policy_coverages as $index => $coverages)

            {{-- cancel risk address --}}
 
@if( $policyAction->transaction_type === 'ENDORSE'
    && (( $policyAction->id == $coverages->riskAddress->action_id
            && $coverages->riskAddress->status == 1 ) ||
        ( $policyAction->id == $coverages['action_id']
            && $coverages['status'] == 1))  )

                <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="risk_table greybg">
                   @else 
                        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="risk_table">
                    @endif
                    <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                        @if($coverages->riskAddress->address_name != $previousRisk)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#D5C8F0;" class="DO">

                                @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE" || $coverages->coverage->s_CoverageCode == "WORKERSCOMPENSATION" || $coverages->coverage->s_CoverageCode =='STATEDBENEFITS')
                                <td width="100%" colspan="5">
                                @else
                                <td width="100%" colspan="4">
                                @endif
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Risk Address : {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->city->name??""}}
                                        <br>Construction type : {{$coverages->riskAddress->const_type??""}}
                                    </p>
                                </td>
                            </tr>
                            @php
                                $previousRisk = $coverages->riskAddress->address_name;
                            @endphp
                        @endif

                        @if ($coverages->coverage->s_CoverageCode == "WORKERSCOMPENSATION" || $coverages->coverage->s_CoverageCode =="STATEDBENEFITS")
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;" class="EO">
                                <td width="100%" colspan="5" class="pl3">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;"> {{$coverages->coverage->s_ScreenName??""}}</p>
                                </td>
                            </tr>
                        @elseif ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;" class="FO">
                                <td width="100%" colspan="5" class="pl2">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;"> {{$coverages->coverage->s_ScreenName??""}}</p>
                                </td>
                            </tr>
                        @else
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;border-right: 2px solid #2e77c3!important;" class="FO1">
                                <td width="100%" colspan="4" class="pl1" style="border-right: 2px solid #2e77c3!important;">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;"> {{$coverages->coverage->s_ScreenName??""}}</p>
                                </td>
                            </tr>
                        @endif
                        @if ($coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                        @if(!empty($coverages->publicliability_date))
                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="ZI">
                        <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                       
                        <p style="text-align: left; margin: 0px; padding: 0px; color:#2e77c3;">
                                Retroactive Date:
                                {{ safeFormatDate($coverages->publicliability_date) }}
                            </p>
                        </td>
                        </tr>
                          @endif
                        @endif

                        @if($coverages->coverage->s_CoverageCode == "BUSINESSINTERUPTION" || $coverages->coverage->s_CoverageCode == "BUSINESSINTERRUPTION" || $coverages->coverage->s_CoverageCode == "GOODSINTRANSIT" || $coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                            @if(count($coverages->coverageDetail ?? []) > 0)
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;" class="GO">
                                    <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                        @if ($coverages->coverage->s_CoverageCode == "GOODSINTRANSIT")
                                            @php
                                                $property_business_being = null;
                                                $propertyBusinessBeing = DB::table('policy_coverages')->where('policy_id',$policy->id)->where('id',$coverages->id)->where('coverage_id','10')->where('row_type','NEW')
                                                ->orderBy('id','desc')->first(['property_business_being']);

                                                if (isset($propertyBusinessBeing)) {
                                                   $property_business_being = $propertyBusinessBeing->property_business_being;
                                                }
                                            @endphp

                                            <p style="color:black;margin: 0px;">All property usual to the Insured's business being: {{ $property_business_being }}</p>
                                            <p style="color:black;margin: 0px;">(including ropes, tarpaulins and packing materials in connection with the transit)</p>
                                            @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                                                @if(isset($sub_coverages->coverage->id))

                                                    @php
                                                        $coverageMaster = AlphaDirect\Models\CoverageMaster::where('id',$sub_coverages->coverage_id)->first(['s_CoverageCode']);

                                                        if(isset($sub_coverages->limit_id)){
                                                            $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                        }
                                                    @endphp

                                                    @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && isset($coverageMaster) && $coverageMaster->s_CoverageCode == 'BASIS OF COVER')
                                                        <p style="color:black;margin: 0px;">
                                                            Basis of cover :
                                                            {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                        </p>
                                                    @endif

                                                    @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && isset($coverageMaster) && $coverageMaster->s_CoverageCode == 'MEANS OF CONVEYANCE')
                                                        <p style="color:black;margin: 0px;">
                                                            Means of Conveyance :
                                                            {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                        </p>
                                                    @endif
                                                    </p>
                                                @endif
                                            @endforeach
                                        @else
                                            <p style="color:black;margin: 0px;">
                                            Basis of cover :
                                            @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)


                                                @if(isset($sub_coverages->coverage->id))
                                                    @php
                                                        if(isset($sub_coverages->limit_id)){
                                                        $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                        }
                                                    @endphp
                                                    @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null)
                                                    {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                    @endif
                                                @endif
                                            @endforeach
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endif
                        <!-- $coverages->coverage->s_CoverageCode == "BUILDINGSCOMBINED" ||  remove by sonali for policy COMG2024103655-->
                        <!-- @if($coverages->coverage->s_CoverageCode == "ACCOUNTSRECEIVABLE" || $coverages->coverage->s_CoverageCode == "HOUSEOWNERS" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS" || $coverages->coverage->s_CoverageCode == "OFFICECONTENTS" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS-CONTENTS")
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;">
                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Address : {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->city->name??""}}
                                        <br>Construction type : {{$coverages->riskAddress->const_type??""}}
                                    </p>
                                </td>
                            </tr>
                        @endif -->
                        @if($coverages->coverage->s_CoverageCode == "THEFT")
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;" class="HO">
                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Address : {{$coverages->riskAddress->address_name??""}} {{$coverages->riskAddress->city->name??""}}
                                    </p>
                                </td>
                            </tr>
                        @endif
                        @if($coverages->coverage->s_CoverageCode != "PERSONALMOTOR" || $coverages->coverage->s_CoverageCode != "MOTORTRADERSEXTERNALCOMPR" || $coverages->coverage->s_CoverageCode != "COMMERCIALMOTOR")

                                @if ($coverages->coverage->s_CoverageCode == "WORKERSCOMPENSATION" || $coverages->coverage->s_CoverageCode == "STATEDBENEFITS")
                                <tr style="border-top: 1px solid #2e77c3!important; border-bottom: 1px solid #2e77c3!important; font-size: 10px; background-color: #C6EED8;" class="IO">

                                <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px; color: black!important;"> Description </p>
                                </td>

                                <td width="15%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px; color: black;">No. of Employee</p>
                                </td>

                                <td width="20%" style="border: 1px solid #2e77c3!important; width: 1rem!important" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px; color: black;">Individual Cover</p>
                                </td>

                                <td width="15%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px; color: black;">Annual Wages/Limits</p>
                                </td>

                                <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    <p style="text-align: left; margin: 0px; padding: 0px; color: black;">Deposit and Min Prem</p>
                                </td>
                                </tr>
                                @elseif ($coverages->coverage->s_CoverageCode == "MOTORTRADERSEXTERNAL")
                                @elseif ($coverages->coverage->s_CoverageCode == "MOTORTRADERSINTERNAL")
                                @elseif ($coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                                @elseif ($coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;" class="JO">
                                    <td style="border: 1px solid #2e77c3!important;" colspan="2">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Limit of Liability</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="344">Premium</p>
                                    </td>
                                    </tr>
                                    @elseif ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                    @if(count($policyCoveragesData ?? []) > 0)
                                     @foreach($policyCoveragesData as $pData)
                                        @if($pData['policyCoverageID'] == $coverages->id)
                                          <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td colspan="5" style="border: 2px solid #2e77c3!important;text-align: center;"> Basis Of Cover  {{ str_replace('_',' ',$pData['cover_type']) }}</td>
                                        </tr>
                                          <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;" class="KO">
                                        @if($pData['cover_type'] == 'Named_Position')
                                            <td  width="30%" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Description</p>
                                            </td>                                    
                                            <td  width="20%" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Designation</p>
                                            </td>
                                            <td  width="10%" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Length Of Service</p>
                                            </td>
                                            <td  width="20%" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Amount to be guaranteed</p>
                                            </td>
                                            <td  width="20%" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;" class="mt1233">Premium</p>
                                            </td>
                                        @else
                                            <td  colspan="2" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Name And Position</p>
                                            </td>
                                            <td   colspan="1" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Amount to be guaranteed</p>
                                            </td>
                                            <td colspan="2" style="border: 2px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;" class="mt677">Premium</p>
                                            </td>

                                        @endif
                                        @endif
                                        @endforeach
                                        </tr>
                                        @foreach($policyCoveragesData as $pData)
                                        @if($pData['policyCoverageID'] == $coverages->id)
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="NO">
                                                @if($pData['cover_type'] == 'Named_Position')
                                                <td style="border: 2px solid #2e77c3!important;width:30%">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                   {{ isset($pData['cover_type']) ? str_replace('_',' ',$pData['cover_type']) : '' }} : {{ $pData['name_and_position'] }}</p>
                                                </td>
                                                <td   style="border: 2px solid #2e77c3!important;width:20%">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pData['designation'] }}</p>
                                                </td>
                                                <td   style="border: 2px solid #2e77c3!important;width:10%">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pData['length_of_service'] }}</p>
                                                </td>
                                                <td style="border: 2px solid #2e77c3!important;width:20%">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pData['amount_to_be_guaranteed'] ?? "", 2, '.', ',')}}</p>
                                                </td>
                                                <td   style="border: 2px solid #2e77c3!important;width:20%">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pData['premium'] ?? "", 2, '.', ',')}}</p>
                                                </td>
                                                @else
                                                <td  colspan="2" style="border: 2px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{
                                                    $pData['cover_type'] ?? $pData['cover_type'] }}</p>
                                                </td>
                                                <td colspan="1" style="border: 2px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pData['amount_to_be_guaranteed'] ?? "", 2, '.', ',')}}</p>
                                                </td>
                                                <td  colspan="2" style="border: 2px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pData['premium'] ?? "", 2, '.', ',')}}</p>
                                                </td>
                                                @endif
                                        </tr>
                                        @endif
                                        @endforeach
                                        {{-- Fidelity subcoverage totals — mirror every other coverage:
                                             an in-line "Miscellaneous Items" row for the specified items,
                                             then "Total of subcoverages" = Basis Of Cover + Miscellaneous. --}}
                                        @php
                                            $fidCoverAmount  = 0; $fidCoverPremium = 0; $fidCoverType = null;
                                            foreach(($policyCoveragesData ?? []) as $fidRow){
                                                if($fidRow['policyCoverageID'] == $coverages->id){
                                                    $fidCoverAmount  += (float)($fidRow['amount_to_be_guaranteed'] ?? 0);
                                                    $fidCoverPremium += (float)($fidRow['premium'] ?? 0);
                                                    $fidCoverType     = $fidRow['cover_type'] ?? $fidCoverType;
                                                }
                                            }
                                            $fidMiscAmount = 0; $fidMiscPremium = 0; $fidMiscCount = 0;
                                            foreach(($coverages->specifedItems ?? []) as $fidSpec){
                                                if($coverages['status'] != 1 && $fidSpec->deleted_at == null){
                                                    $fidMiscAmount  += (float)($fidSpec->sum_insured ?? 0);
                                                    $fidMiscPremium += (float)($fidSpec->calculated_value ?? 0);
                                                    $fidMiscCount++;
                                                }
                                            }
                                            $fidIsNamed = ($fidCoverType == 'Named_Position');
                                        @endphp
                                        @if(!is_null($fidCoverType))
                                            @if($fidMiscCount > 0)
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td colspan="{{ $fidIsNamed ? 3 : 2 }}" style="border:1px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Miscellaneous Items {{ $coverages->coverage->s_ScreenName ?? '' }}</p>
                                                </td>
                                                <td colspan="1" style="border:1px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{ number_format($fidMiscAmount, 2, '.', ',') }}</p>
                                                </td>
                                                <td colspan="{{ $fidIsNamed ? 1 : 2 }}" style="border:1px solid #2e77c3!important;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{ number_format($fidMiscPremium, 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                            @endif
                                            <tr style="border-top:2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                                <td colspan="{{ $fidIsNamed ? 3 : 2 }}" style="border:2px solid #2e77c3!important;text-align:left;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total of subcoverages</p>
                                                </td>
                                                <td colspan="1" style="border:2px solid #2e77c3!important;text-align:left;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{ number_format($fidCoverAmount + $fidMiscAmount, 2, '.', ',') }}</p>
                                                </td>
                                                <td colspan="{{ $fidIsNamed ? 1 : 2 }}" style="border:2px solid #2e77c3!important;text-align:left;">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{ number_format($fidCoverPremium + $fidMiscPremium, 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                        @endif
                                    @endif
                                @elseif ($coverages->coverage->s_ScreenName != "Personal Motor")
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description</p>
                                    </td>
                                    @if ($coverages->coverage->s_ScreenName == "Personal Accident")
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Limit</p>
                                        </td>
                                    @else
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Sum Insured</p>
                                        </td>
                                    @endif
                                    @if($coverages->coverage->s_CoverageCode != "ELECTRONICEQUIPMENT")
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    @else
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                    @endif
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt88">Premium</p>
                                    </td>
                                </tr>
                                @endif
                            </tr>
                        @endif
                        <!-- start vehicle -->

                        @if($coverages->coverage->s_ScreenName != 'Houseowner-Buildings')
                            @if(count($coverages->entities ?? [])>0)
                                <tr style="border-top:  2px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%"  colspan="2" class="sd">
                                        @foreach($coverages->entities as $newIndex => $entity)
                                        <p style="text-align: left;margin: 0px; padding: 0px;padding-left: 10px;color:black;">
                                            {{$entity->entity_type}}:
                                        </p>
                                        @endforeach
                                    </td>
                                    <td width="50%" colspan="2" class="900" >
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
                        @endif
                        <!-- end vehicle -->

                        @if ($coverages->coverage->s_CoverageCode != "COMPUTEREQUIPMENT" || $coverages->coverage->s_CoverageCode !="PERSONALMOTOR" || $coverages->coverage->s_CoverageCode != "MOTORTRADERSEXTERNALCOMPR" || $coverages->coverage->s_CoverageCode !="COMMERCIALMOTOR" )
                            <!-- start all sub_coverages -->
                            @if(count($coverages->all_sub_coverages ?? []) > 0 || count($coverages->coverageDetail ?? []) > 0)
                                @php
                                    $totalSumOfCoveragesValues = 0;
                                    $totalSumOfCalculatedValues = 0;
                                    $header = "";
                                @endphp
                                @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages)
                                    @php
                                        $sub_coverage_found = false;
                                    @endphp
                                    <!-- start present sub_coverages -->
                                    @if(count($coverages->coverageDetail ?? []) > 0)
                                        @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                                            @if(isset($sub_coverages->coverage->id))
                                                @if(isset($all_sub_coverages->id) && $all_sub_coverages->id == $sub_coverages->coverage->id)
                                                    @if($header!=$sub_coverages->coverage->s_CoverageGroupName)
                                                        @if ($sub_coverages->coverage->s_CoverageGroupName != "Description of cover")
                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="OO">
                                                           @if($coverages->coverage->s_CoverageCode == "STATEDBENEFITS")
                                                            <td style="background-color: #e5f4e3;text-align:center" colspan="5">
                                                                <p style="color:black;margin: 0px;"> 
                                                                    {{ $sub_coverages->coverage->s_CoverageGroupName }}
                                                                </p>
                                                            </td>
                                                            @else
                                                             <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                                <p style="color:black;margin: 0px;">
                                                                    {{ $sub_coverages->coverage->s_CoverageGroupName }}
                                                                </p>
                                                            </td>
                                                            @endif
                                                        </tr>
                                                        @endif
                                                    @endif
                                                    @if($sub_coverages->coverage->s_SubCoverageMainName == "Heading")
                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                            <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                                <p style="color:black;margin: 0px;">
                                                                {{ $sub_coverages->coverage->s_CoverageName }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    @endif


                                                    @php
                                                        $header = $sub_coverages->coverage->s_CoverageGroupName;
                                                        if($coverages['status']== 1){
                                                        $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + 0;
                                                        }else{
                                                        $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + $sub_coverages->coverage_value;
                                                        }
                                                        if($coverages['status']== 1)
                                                        {
                                                        $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + 0;
                                                        }else{
                                                         $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + $sub_coverages->calculated_value;
                                                        }    
                                                        $sub_coverage_found = true;
                                                        if(isset($sub_coverages->limit_id)){
                                                        $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                        }
                                                    @endphp
                                                    @if ($coverages->coverage->s_ScreenName != "Computer Equipment")
                                                        @if($sub_coverages->coverage->s_SubCoverageMainName != "Heading")
                                                            {{-- <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO"> --}}
                                                                @if ($coverages->coverage->s_ScreenName == "Workers Compensation" || $coverages->coverage->s_ScreenName == "Stated Benefits")
                                                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO1">
                                                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                                        @if (isset($sub_coverages->coverage_value_string))
                                                                            {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                        @else
                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                        @endif
                                                                        </p>
                                                                    </td>
                                                                    <td width="15%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                        @if(isset($sub_coverages->ratefactor_value) && $sub_coverages->ratefactor_value !=0 && $sub_coverages->coverage->s_ScreenName!='Employers Liablity (Common Law Liability)')
                                                                            {{ $sub_coverages->ratefactor_value ?? "" }}
                                                                        @elseif($sub_coverages->ratefactor_value_check!=0)
                                                                            {{ $sub_coverages->ratefactor_value_check ?? "" }}
                                                                        @endif
                                                                        </p>
                                                                    </td>
                                                                    <td width="20%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                        @if (isset($sub_coverages->ratefactor_type)  && $sub_coverages->ratefactor_type != 0)
                                                                              {{ $sub_coverages->ratefactor_type ?? "" }}
                                                                        @endif
                                                                        </p>
                                                                    </td>
                                                                    <td width="15%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                                        @if (isset($sub_coverages->ratefactor_AnnualWages) && $sub_coverages->coverage->s_ScreenName!='Employers Liablity (Common Law Liability)')
                                                                          @if(  $sub_coverages->ratefactor_AnnualWages!=0 &&   $sub_coverages->ratefactor_AnnualWages!='')
                                                                            P  @endif {{ $sub_coverages->ratefactor_AnnualWages ?? "" }}
                                                                            @php
                                                                            $value = str_replace( ',', '', $sub_coverages->ratefactor_AnnualWages);
                                                                            if($value != 0 && $value != '')
                                                                            {
                                                                                $value = (float)$value;
                                                                            }
                                                                            else
                                                                            {
                                                                                $value = 0;
                                                                            }
                                                                            $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + $value;
                                                                            @endphp

                                                                        @elseif($sub_coverages->coverage->s_ScreenName=='Employers Liablity (Common Law Liability)')
                                                                         P {{number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',')  ?? "" }}
                                                                        @endif

                                                                        </p>
                                                                    </td>
                                                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                            @if (isset($sub_coverages->calculated_value))
                                                                                @if($coverages->status==1) P 0.00 @else P {{ number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')  ?? "" }} @endif
                                                                            @endif
                                                                        </p>
                                                                    </td>
                                                                @else
                                                                @if ($coverages->coverage->s_CoverageCode != "GOODSINTRANSIT" && $coverages->coverage->s_CoverageCode != "PERSONALACCIDENT" && !in_array($coverages->coverage->s_CoverageCode, [
                                                                                        "BUSINESSALLRISKS",
                                                                                        "ELECTRONICEQUIPMENT",
                                                                                        "HOUSEHOLDERS-CONTENTS",
                                                                                        "HOUSEOWNER-BUILDINGS",
                                                                                        "HOUSEOWNERS",
                                                                                        "PERSONALALLRISKS",
                                                                                    ]) )
                                                                    {{-- Basis of cover is itemised here too — see the matching comment
                                                                         in v2-quote-sheet.blade.php. Kept in sync so the Word/doc
                                                                         export and the PDF show the same lines. --}}
                                                                    @php
                                                                        $isBasisOfCoverRow = ($sub_coverages->coverage->s_ScreenName ?? '') === 'Basis of cover';
                                                                        // PUBLIC LIABILITY ONLY (per UW, 2026-08-17) — same rule as
                                                                        // v2-quote-sheet.blade.php, kept in sync so the doc export and
                                                                        // the PDF agree. PL hides the row; every other coverage in this
                                                                        // branch still itemises it.
                                                                        $hideBasisOfCoverRow = $isBasisOfCoverRow
                                                                            && ($coverages->coverage->s_CoverageCode ?? '') === 'PUBLICLIABILITY';
                                                                        $basisOfCoverLabel = null;
                                                                        if ($isBasisOfCoverRow && !empty($sub_coverages->limit_id)) {
                                                                            $bocLimit = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK', $sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                                            $basisOfCoverLabel = $bocLimit['s_LimitScreenName'] ?? null;
                                                                        }
                                                                    @endphp
                                                                    @if ($sub_coverages->coverage->s_ScreenName != 'Means of Conveyance' && !$hideBasisOfCoverRow)
                                                                    @if($sub_coverages->coverage_value!=0 && $sub_coverages->coverage_value)

                                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO2">
                                                                        <td width="50%"  colspan="2" class="LO">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                {{ $sub_coverages->coverage->s_ScreenName ?? "" }}@if($isBasisOfCoverRow && $basisOfCoverLabel) - {{ $basisOfCoverLabel }}@endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if ($isBasisOfCoverRow)
                                                                                    @if ((float)($sub_coverages->coverage_value ?? 0) > 0)
                                                                                    P {{ number_format((float)$sub_coverages->coverage_value, 2, '.', ',') }}
                                                                                    @endif
                                                                                @elseif (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                                @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                    P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                 @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                                @endif
                                                                            </p>            
                                                                        </td>
                                                                    @endif
                                                                    @endif 
                                                                    @elseif ($coverages->coverage->s_CoverageCode == "GOODSINTRANSIT")
                                                                    @if ($sub_coverages->coverage->s_ScreenName != 'Basis of cover' && $sub_coverages->coverage->s_ScreenName != 'Means of Conveyance')
                                                                       @if($sub_coverages->coverage_value!=0 && $sub_coverages->coverage_value)
                                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO2">
                                                                    <td width="50%"  colspan="2" class="LO">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                                @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                    P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                 @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                    @endif
                                                                    @endif
                                                                    @elseif ($coverages->coverage->s_CoverageCode == "ELECTRONICEQUIPMENT")
                                                                    @if ($sub_coverages->coverage->s_ScreenName != 'Basis of cover' && $sub_coverages->coverage->s_ScreenName != 'Means of Conveyance')
                                                                       @if($sub_coverages->coverage_value!=0 && $sub_coverages->coverage_value)
                                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO2">
                                                                    <td width="50%"  colspan="2" class="LO">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                                @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                    P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                 @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                    @endif
                                                                    @endif
                                                                    @elseif ($coverages->coverage->s_CoverageCode != "GOODSINTRANSIT" && $coverages->coverage->s_CoverageCode != "PERSONALACCIDENT")
                                                                    @if ($sub_coverages->coverage->s_ScreenName != 'Basis of cover' && $sub_coverages->coverage->s_ScreenName != 'Means of Conveyance')
                                                                      @if($sub_coverages->coverage_value != null && $sub_coverages->coverage_value!='')
                                                                        @if($sub_coverages->ratefactor_value!="" && $sub_coverages->ratefactor_value !=0 && $sub_coverages->ratefactor_value !=0.00) 
 
                                                                      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="MO3">
                                                                    <td width="50%"  colspan="2" class="LO1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                            {{-- @if ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS" || $coverages->coverage->s_CoverageCode == "ELECTRONICEQUIPMENT" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS-CONTENTS" || $coverages->coverage->s_CoverageCode == "HOUSEOWNER-BUILDINGS" || $coverages->coverage->s_CoverageCode == "HOUSEOWNERS" || $coverages->coverage->s_CoverageCode == "PERSONALALLRISKS" ) --}}
                                                                            @if (
                                                                                    in_array($coverages->coverage->s_CoverageCode, [
                                                                                        "BUSINESSALLRISKS",
                                                                                       // "ELECTRONICEQUIPMENT",
                                                                                        "HOUSEHOLDERS-CONTENTS",
                                                                                        "HOUSEOWNER-BUILDINGS",
                                                                                        "HOUSEOWNERS",
                                                                                        "PERSONALALLRISKS",
                                                                                    ]) 
                                                                                    && $coverages->coverage->s_CoverageCode != "PERSONALACCIDENT"
                                                                                )
                                                                                @if($sub_coverages->ratefactor_value != 0 && trim($sub_coverages->ratefactor_value) != "" )
                                                                                    {{ $sub_coverages->ratefactor_value  ?? '' }}
                                                                                @else
                                                                                    {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                @endif
                                                                            @elseif (!in_array($coverages->coverage->s_CoverageCode, ["PERSONALACCIDENT"]))
                                                                                @if ($coverages->coverage->s_CoverageCode != "THEFT")
                                                                                   {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                @else
                                                                                    @if($sub_coverages->ratefactor_type == 0)
                                                                                    {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                    @else
                                                                                        {{ $sub_coverages->ratefactor_type  ?? '' }}
                                                                                    @endif

                                                                                @endif
                                                                            @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                    @if(isset($tbCvgpclimits['s_LimitScreenName']))
                                                                                    P {{ number_format((float)$sub_coverages->ratefactor_value ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                        {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                    @endif
                                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                                @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                    @if ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                                                                     {{ $sub_coverages->coverage_value  ?? ''}}
                                                                                    @elseif ($coverages->coverage->s_CoverageCode == "PERSONALALLRISKS")
                                                                                     P {{ $sub_coverages->coverage_value  ?? ''}}
                                                                                    @else
                                                                                       {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                    @endif
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                    @if($coverages->status==1) P 0.00 @else P  {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }} @endif
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        </tr>

                                                                        @endif
                                                                    @endif
                                                                     @endif
                                                                @elseif ($coverages->coverage->s_ScreenName == "Computer Equipment")
                                                                    {{ $coverages->coverage->s_ScreenName }} {{ $sub_coverages->coverage->s_ScreenName }}
                                                                       @if($sub_coverages->coverage->s_ScreenName == "Basis of cover")

                                                                        <td width="50%"  colspan="2" class="KM">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if($coverages->coverage->s_CoverageCode == "BUILDINGSCOMBINED")
                                                                             
                                                                                    @if (isset($sub_coverages->coverage_value))
                                                                                            @if (isset($sub_coverages->ratefactor_type) && $sub_coverages->ratefactor_type != 0)
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}  ( No of Month : {{ $sub_coverages->ratefactor_type ?? "" }} )
                                                                                            @else
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @endif
                                                                                    @endif
                                                                                @elseif($coverages->coverage->s_CoverageCode == "THEFT")
                                                                                    @if (isset($sub_coverages->coverage_value) || isset($sub_coverages->coverage_value_string))
                                                                                  
                                                                                        @if (isset($sub_coverages->coverage_value) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->ratefactor_type != 0 && $sub_coverages->ratefactor_type != '')
                                                                                            {{ $sub_coverages->ratefactor_type ?? $sub_coverages->coverage->s_ScreenName }}
                                                                                        @else
                                                                                            @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @else
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @endif
                                                                                        @endif
                                                                                    @endif
                                                                                    @elseif($coverages->coverage->s_CoverageCode == "PERSONALACCIDENT")
                                                                                    @if (isset($sub_coverages->coverage_value) || isset($sub_coverages->coverage_value_string))
                                                                                  
                                                                                        @if (isset($sub_coverages->coverage_value) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->ratefactor_type != 0 && $sub_coverages->ratefactor_type != '')
                                                                                            {{ $sub_coverages->ratefactor_type ?? $sub_coverages->coverage->s_ScreenName }}
                                                                                        @else
                                                                                            @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @else
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @endif
                                                                                        @endif
                                                                                    @endif
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) || isset($sub_coverages->coverage_value_string))
                                                                                        @if($sub_coverages->coverage->s_ScreenName=='Full address of Premises Covered' && $coverages->coverage->s_CoverageCode=='PUBLICLIABILITY' )
                                                                                        {{$coverages->riskAddress->address_name??""}}  {{$coverages->riskAddress->city->name??""}}
                                                                                        @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->ratefactor_value != 0)
                                                                                            {{ $sub_coverages->ratefactor_value ?? "" }}
                                                                                        @elseif (isset($sub_coverages->ratefactor_type)&& $sub_coverages->ratefactor_type != 0 && $coverages->coverage->s_CoverageCode != "FIRE")
                                                                                            {{ $sub_coverages->ratefactor_type ?? "" }}
                                                                                        @elseif (isset($sub_coverages->coverage_value_string) && $coverages->coverage->s_CoverageCode != "PERSONALACCIDENT")
                                                                                            {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                                        @else
                                                                                            @if (isset($sub_coverages->ratefactor_value) && $sub_coverages->ratefactor_value != 0)
                                                                                            {{ $sub_coverages->ratefactor_value ?? "" }}
                                                                                            @else
                                                                                            {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                                            @endif
                                                                                        @endif
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1" class="SO">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                                                @if ($coverages->coverage->s_ScreenName == "Personal Accident")
                                                                                    @if (isset($sub_coverages->coverage_value_string))
                                                                                        {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                                    @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                            {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                    @elseif(isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                        P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                        {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                                    @endif
                                                                                @elseif($coverages->coverage->s_CoverageCode == "THEFT")

                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->ratefactor_value == 0)
                                                                                        P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                        P {{ number_format((float)$sub_coverages->ratefactor_value ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                        {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                    @endif
                                                                                @else
                                                                                    @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ ''}}
                                                                                    @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                                    @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                            {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                    @elseif ($sub_coverages->ratefactor_value == null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                           P {{ number_format((float)0 ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                        @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                        P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                        @endif
                                                                                    @endif
                                                                                @endif

                                                                            </p>
                                                                        </td>
                                                                        @if($coverages->coverage->s_CoverageCode != "ELECTRONICEQUIPMENT")
                                                                        <td width="25%" class="descpre1"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        @else
                                                                        <td width="25%" class="descpre2"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                        @endif

                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                         
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                 @if($coverages->status==1) P 0.00 @else P  {{ number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',') }} @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        @endif
                                                                        @elseif ( $coverages->coverage->s_ScreenName == "Personal Accident")
                                                                     
                                                                          @if (in_array($sub_coverages->coverage->s_ScreenName, [
                                                                            "Name of Insured Person and Designation","Temporary Total Disablement",
                                                                            "Death Benefits","Permanent Disablement Benefits","Medical Expenses Benefits"
                                                                        ]))
                                                                            <td width="50%"  style="border: 0.5px solid #2e77c3!important;" colspan="2" class="personalacc1">
                                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                    @if (isset($sub_coverages->coverage->s_ScreenName ))
                                                                                        {{ $sub_coverages->coverage->s_ScreenName  }}
                                                                                        @if(!empty($sub_coverages->coverage_value_string))
                                                                                            - {{ $sub_coverages->coverage_value_string  }}
                                                                                        @endif
                                                                                    @endif
                                                                                </p>                                          
                                                                            </td>                  
                                                                        @else
                                                                            <td width="50%"  style="border: 0.5px solid #2e77c3!important;" colspan="2" class="personalacc2">
                                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                    @if (isset($sub_coverages->coverage_value_string))
                                                                                        {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                                    @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                                        {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                                    @elseif(isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                        P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                        {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                                    @endif
                                                                                </p>
                                                                            </td>
                                                                        @endif
                                                                        @if ($sub_coverages->coverage->s_ScreenName == "Name of Insured Person and Designation")
                                                                            <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                  P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                                </p>
                                                                            </td>            
                                                                        @else                    
                                                                         <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                                @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                                    @if(isset($tbCvgpclimits['s_LimitScreenName']))
                                                                                    P {{ number_format((float)$sub_coverages->ratefactor_value ?? "", 2, '.', ',') }}
                                                                                    @else
                                                                                      {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                                    @endif
                                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                               
                                                                                @else
                                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                                    @if($coverages->status==1)  P 0.00 @else  P  {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }} @endif
                                                                                    @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                        @endif
                                                                        <td width="25%"  style="border: 0.5px solid #2e77c3!important;" colspan="1">
                                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                               
                                                                                @if (isset($sub_coverages->calculated_value))
                                                                                @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                                @endif
                                                                            </p>
                                                                        </td>
                                                                @endif
                                                            @endif
                                                            </tr>
                                                        @endif
                                                    @endif
                                                @endif
                                            @endif
                                        @endforeach
                                    @endif
                                    <!-- end present sub_coverages -->
                                    @if(!$sub_coverage_found)
                                        @if($coverages->coverage->s_CoverageCode != "PERSONALMOTOR" || $coverages->coverage->s_CoverageCode != "MOTORTRADERSEXTERNALCOMPR" || $coverages->coverage->s_CoverageCode != "COMMERCIALMOTOR" || $coverages->coverage->s_CoverageCode != "COMPUTEREQUIPMENT")
                                            @if ($coverages->coverage->s_ScreenName != "Computer Equipment")
                                                @if($all_sub_coverages->s_SubCoverageMainName == "Heading")
                                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                        <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                            <p style="color:black;font-size: 10px;margin: 0px;">
                                                             {{ $all_sub_coverages->s_ScreenName??"" }}
                                                            </p>
                                                        </td>
                                                    </tr>
                                                 {{-- @else
                                                    @if($coverages->coverage->s_CoverageCode != "BUSINESSALLRISKS")
                                                    @if($coverages->coverage->s_CoverageCode != "ELECTRONICEQUIPMENT")
                                                    @if($coverages->coverage->s_CoverageCode != "OFFICECONTENTS")
                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                            <td width="50%"  colspan="2">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                {{ $all_sub_coverages->s_ScreenName??"" }}
                                                            </p>
                                                            </td>
                                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P  {{number_format(0, 2, '.', ',')}} </p>
                                                            </td>
                                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P  {{number_format(0, 2, '.', ',')}} </p>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    @endif
                                                    @endif--}}
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                @endforeach
                            @endif
                        @elseif ($coverages->coverage->s_CoverageCode == "MOTORTRADERSEXTERNAL")
                        @elseif ($coverages->coverage->s_CoverageCode == "MOTORTRADERSINTERNAL")
                        @elseif ($coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                        @elseif ($coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                        @endif

                        @if ($coverages->coverage->s_CoverageCode == "COMPUTEREQUIPMENT")
                            <!-- start all sub_coverages -->
                            @if(count($coverages->all_sub_coverages ?? []) > 0 || count($coverages->coverageDetail ?? []) > 0)
                                @php
                                    $totalSumOfCoveragesValues = 0;
                                    $totalSumOfCalculatedValues = 0;
                                    $header = "";
                                @endphp
                                @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages)
                                    @php
                                        $sub_coverage_found = false;
                                    @endphp
                                    <!-- start present sub_coverages -->
                                    @foreach($coverages->coverageDetail as $newIndex => $sub_coverages)
                                        @if(isset($sub_coverages->coverage->id))
                                            @if(isset($all_sub_coverages->id) && $all_sub_coverages->id == $sub_coverages->coverage->id)

                                                @if($header!=$sub_coverages->coverage->s_CoverageGroupName)

                                                    @if ($sub_coverages->coverage->s_CoverageGroupName != "Description of cover")
                                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                        <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                            <p style="color:black;margin: 0px;">
                                                                {{ $sub_coverages->coverage->s_CoverageGroupName }}
                                                            </p>
                                                        </td>
                                                    </tr>
                                                    @endif
                                                @endif

                                                @if($sub_coverages->coverage->s_SubCoverageMainName == "Heading")

                                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                        <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                            <p style="color:black;margin: 0px;">
                                                            {{ $sub_coverages->coverage->s_CoverageName }}
                                                            </p>
                                                        </td>
                                                    </tr>
                                                @endif
                                                @php
                                                    $header = $sub_coverages->coverage->s_CoverageGroupName;
                                                    if($coverages['status']==1) {
                                                    $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + 0;
                                                    }else {
                                                     $totalSumOfCoveragesValues = $totalSumOfCoveragesValues + $sub_coverages->coverage_value;
                                                    }
                                                    if($coverages['status']==1) {
                                                    $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + 0;
                                                    }else {
                                                    $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + $sub_coverages->calculated_value;
                                                        
                                                    }
                                                     $sub_coverage_found = true;
                                                    if(isset($sub_coverages->limit_id)){
                                                    $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                    }
                                                @endphp

                                                @if($sub_coverages->coverage->s_SubCoverageMainName != "Heading")

                                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                        <td width="50%"  colspan="2" class="MJ">
                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                                @if (isset($sub_coverages->coverage_value) || isset($sub_coverages->coverage_value_string))
                                                                    @if (isset($sub_coverages->ratefactor_value) && $sub_coverages->ratefactor_value != 0)
                                                                        {{ $sub_coverages->ratefactor_value ?? "" }}
                                                                    @elseif (isset($sub_coverages->coverage_value_string))
                                                                        {{ $sub_coverages->coverage_value_string ?? "" }}
                                                                    @else
                                                                        {{ $sub_coverages->coverage->s_ScreenName ?? "" }}
                                                                    @endif
                                                                @endif
                                                            </p>
                                                        </td>
                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                                @if (isset($tbCvgpclimits)&& $tbCvgpclimits != null && $sub_coverages->coverage_value_string == null && $sub_coverages->coverage_value == 0.00000000)
                                                                    {{ $tbCvgpclimits['s_LimitScreenName'] ?? ''}}
                                                                @elseif (isset($sub_coverages->coverage_value_string) && $sub_coverages->ratefactor_value == 0 && $sub_coverages->coverage_value == 0.00000000)
                                                                    {{ $sub_coverages->coverage_value_string  ?? ''}}
                                                                @elseif (isset($sub_coverages->ratefactor_value) && $sub_coverages->coverage_value == 0.00000000)
                                                                        {{ $sub_coverages->ratefactor_value  ?? ''}}
                                                                @else
                                                                    @if (isset($sub_coverages->coverage_value) && $sub_coverages->coverage_value != 0.00000000)
                                                                    P {{ number_format((float)$sub_coverages->coverage_value ?? "", 2, '.', ',') }}
                                                                    @endif
                                                                @endif
                                                            </p>
                                                        </td>
                                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                        
                                                                @if (isset($sub_coverages->calculated_value))
                                                                 @if($coverages->status==1) P 0.00 @else P  {{number_format((float)$sub_coverages->calculated_value ?? "", 2, '.', ',')}} @endif
                                                                @endif
                                                            </p>
                                                        </td>
                                                    </tr>
                                                    @php
                                                        $policyExtention = AlphaDirect\Models\PolicyExtentionDetails::where('s_ParentCoverageID', $coverages->coverage->id)
                                                                                                        ->where('s_SubCoverageID',$sub_coverages->coverage->id)
                                                                                                        ->where('type','Excess')
                                                                                                        ->orderBy('n_DisplaySequence', 'asc')
                                                                                                        ->get();

                                                    @endphp
                                                    {{-- @if(count($policyExtention ?? [])>0)
                                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                                            <td width="100%" colspan="4">
                                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Excess</p>
                                                            </td>
                                                        </tr>
                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                                            <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                                                <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Excess Name </p>
                                                            </td>
                                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Min %</p>
                                                            </td>
                                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Minimum Amount</p>
                                                            </td>
                                                        </tr>
                                                        @foreach($policyExtention as $newIndex => $extention_items)
                                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                            <td width="50%" colspan="2" class="1">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                    @if(isset($extention_items->extention_type))
                                                                        @if($extention_items->extention_type == 'DROPDOWN' || $extention_items->extention_type == 'RADIO')
                                                                            {{ $extention_items->extentionCvgpclimits->s_LimitScreenName ?? "" }}
                                                                        @elseif($extention_items->extention_type == 'NOEDIT')
                                                                            {{ $extention_items->extention_text_value ?? "" }}
                                                                        @elseif($extention_items->extention_type == 'NUMBER')
                                                                            {{ $extention_items->extention_coverage_value ?? "" }}
                                                                        @else
                                                                            {{ $extention_items->extention_text_value ?? "" }}
                                                                        @endif
                                                                    @endif
                                                                </p>
                                                            </td>
                                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                                {{ $extention_items->extention_excess_min_value ?? "" }}
                                                                </p>
                                                            </td>
                                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                               P {{number_format((float)$extention_items->extention_excess_max_value ?? "", 2, '.', ',')}}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    @endif --}}
                                                    <!-- excess code end -->
                                                @endif
                                            @endif
                                        @endif
                                    @endforeach
                                    <!-- end present sub_coverages -->
                                    @if(!$sub_coverage_found)
                                        @if($all_sub_coverages->s_SubCoverageMainName == "Heading")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                                    <p style="color:black;font-size: 10px;margin: 0px;">
                                                     {{ $all_sub_coverages->s_ScreenName??"" }}
                                                    </p>
                                                </td>
                                            </tr>
                                        {{-- @else
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%"  colspan="2">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                     {{ $all_sub_coverages->s_ScreenName??"" }}
                                                </p>
                                                </td>
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P  {{number_format(0, 2, '.', ',')}} </p>
                                                </td>
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P  {{number_format(0, 2, '.', ',')}} </p>
                                                </td>
                                            </tr> --}}
                                        @endif
                                    @endif
                                @endforeach
                            @endif
                        @endif

                        @php
                            $sum = 0;
                            $sum_insured = 0;
                            foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items){
                                if($coverages['status']!= 1  && $specifed_items->deleted_at==null){
                                $sum_insured+= $specifed_items->sum_insured;
                                $sum+= $specifed_items->calculated_value;
                                }
                            }
                        @endphp
                        @if(isset($sum_insured) || isset($sum))
                            @if ($coverages->coverage->s_CoverageCode != "FIDELITYGUARANTEE" && $coverages->coverage->s_CoverageCode !="MOTORTRADERSEXTERNAL" && $coverages->coverage->s_CoverageCode !="MOTORTRADERSINTERNAL" && $coverages->coverage->s_CoverageCode != "COMMERCIALMOTOR" && $coverages->coverage->s_CoverageCode != "PERSONALMOTOR")
                                <!-- end sum of specified items -->
                                @if(count($coverages->specifedItems ?? [])>0 )
                                  @if($policyAction->transaction_type === 'ENDORSE'
                                    && $policyAction->id == $specifed_items->action_id
                                    && !is_null($specifed_items->deleted_at)
                                 )   

                                   <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;"  class="PO greybg">

                                  @else      
                                     <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;"  class="PO">

                                    @endif       
                          
                                    @if($coverages->coverage->s_ScreenName=='Workers Compensation' || $coverages->coverage->s_ScreenName=='Stated Benefits')
                                    <td width="50%" colspan="3" class="22">
                                             <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Miscellaneous Items {{$coverages->coverage->s_ScreenName??""}} </p>
                                        </td>
                                    @else
                                    <td width="50%" colspan="2" class="2">
                                         <p class='Public Liability' style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Miscellaneous Items {{$coverages->coverage->s_ScreenName??""}} </p>
                                        </td>
                                    @endif
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                            @if($sum_insured)
                                            P {{number_format((float)$sum_insured ?? "", 2, '.', ',')}}
                                            @else
                                            P {{number_format(0, 2, '.', ',')}}
                                            @endif
                                            </p>
                                        </td>
                                        @if($coverages->coverage->s_CoverageCode != "ELECTRONICEQUIPMENT")
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                        @else
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                        @endif
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">                                            @if($sum)
                                            P {{number_format((float)$sum ?? "", 2, '.', ',')}}
                                            @else
                                            P {{number_format(0, 2, '.', ',')}}
                                            @endif
                                            </p>
                                        </td>
                                    </tr>
                                @endif
                            @endif
                        @endif
                         {{-- money setion total part --}}
                        @if ($coverages->coverage->s_CoverageCode == "MONEY") 
                        {{-- // added by snehal on 5-9-25 --}}
                         @php

                            $total_SumOfCoveragesValues1 = 0;
                             $moneyRatefactorValue =0;
                            if(isset($sub_coverages)){
                            // $moneyRatefactorValue = AlphaDirect\Models\PolicyCoverageDetail::where('policy_coverage_id',$sub_coverages->policy_coverage_id)->where('ratefactor_value','!=',0)->sum('ratefactor_value');
                            $moneyRatefactorValue = AlphaDirect\Models\PolicyCoverageDetail::where('policy_coverage_id',$coverages->id)->where('coverage_value','!=',0)->sum('coverage_value');
                            }
                            if(isset($sub_coverages)){
                                // $total_SumOfCoveragesValues1 = $sub_coverages->coverage_value + $moneyRatefactorValue;
                                $total_SumOfCoveragesValues1 =  $moneyRatefactorValue;
                                }
                            @endphp
                            {{-- addition end 5-9-25 --}}
                        
                            @if(isset($totalSumOfCalculatedValues) || isset($sum))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="2" class="3"> 
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                       {{-- // added by snehal on 5-9-25 --}}
                                        @if(isset($total_SumOfCoveragesValues1) && $total_SumOfCoveragesValues1 != null || $sum != null)
                                        P {{number_format((float)$total_SumOfCoveragesValues1+$sum_insured ?? "", 2, '.', ',')}}
                                        @else
                                        P {{number_format(0, 2, '.', ',')}}
                                        @endif
                                          {{-- addition end 5-9-25 --}}
                                    </p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                    @if($totalSumOfCalculatedValues != null || $sum != null)
                                    P {{number_format((float)$totalSumOfCalculatedValues+$sum ?? "", 2, '.', ',')}}
                                    @else
                                    P {{number_format(0, 2, '.', ',')}}
                                    @endif
                                    </p>
                                </td>
                            </tr>
                            @endif
                        @elseif ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                        @else
                            @if(($coverages->coverage->s_CoverageCode != "PERSONALMOTOR") && ($coverages->coverage->s_CoverageCode != "MOTORTRADERSEXTERNALCOMPR") && ($coverages->coverage->s_CoverageCode != "MOTORTRADERSEXTERNAL") && ($coverages->coverage->s_CoverageCode!="MOTORTRADERSINTERNAL")&&($coverages->coverage->s_CoverageCode != "COMMERCIALMOTOR"))
                            {{-- Stated Benefits  total part --}}
                            @if(isset($totalSumOfCoveragesValues) || isset($totalSumOfCalculatedValues) || isset($sum_insured) || isset($sum))
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        @if ($coverages->coverage->s_CoverageCode == "WORKERSCOMPENSATION" || $coverages->coverage->s_CoverageCode == "STATEDBENEFITS")
                                            <td colspan="3" class="4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;font-size:10px;">
                                                   Total of subcoverages
                                                </p>
                                            </td>
                                        @else
                                            <td width="50%" colspan="2" class="4">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;font-size:10px;">
                                                    Total of subcoverages
                                                    </p>
                                            </td>
                                        @endif
                                            @if ($coverages->coverage->s_CoverageCode == 'THEFT')
                                                @php
                                                    $total_SumOfCoveragesValues = 0; $theftRatefactorValue =0;
                                                    if(isset($sub_coverages)){
                                                    $theftRatefactorValue = AlphaDirect\Models\PolicyCoverageDetail::where('policy_coverage_id',$coverages->id)->where('ratefactor_value','!=',0)->sum('ratefactor_value');
                                                    }
                                                    // if (isset($sub_coverages->coverage_value) && isset($theftRatefactorValue)){
                                                        // $header = $sub_coverages->coverage->s_CoverageGroupName;
                                                        if(isset($sub_coverages)){
                                                        $total_SumOfCoveragesValues = $sub_coverages->coverage_value + $theftRatefactorValue;
                                                        }
                                                        // $totalSumOfCalculatedValues = $totalSumOfCalculatedValues + $sub_coverages->calculated_value;
                                                        // $sub_coverage_found = true;
                                                        // if(isset($sub_coverages->limit_id)){
                                                        //     $tbCvgpclimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$sub_coverages->limit_id)->first(['s_LimitScreenName']);
                                                        // }
                                                    // }
                                                @endphp

                                                <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                                    @if(isset($total_SumOfCoveragesValues) && $total_SumOfCoveragesValues != null || $sum != null)
                                                    P {{number_format((float)$total_SumOfCoveragesValues+$sum_insured ?? "", 2, '.', ',')}}
                                                    @else
                                                    P {{number_format(0, 2, '.', ',')}}
                                                    @endif
                                                    </p>
                                                </td>
                                            @else
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                                    @if(isset($totalSumOfCoveragesValues) && $totalSumOfCoveragesValues != null || $sum_insured != null)
                                                    P {{number_format((float)$totalSumOfCoveragesValues+$sum_insured ?? "", 2, '.', ',')}}
                                                    @else
                                                    P {{number_format(0, 2, '.', ',')}}
                                                    @endif
                                                    </p>
                                                </td>
                                            @endif
                                            @if ($coverages->coverage->s_CoverageCode != "ELECTRONICEQUIPMENT")
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1" class="totalsub1">
                                            @else
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" class="totalsub2">
                                            @endif
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">
                                                @if(isset($totalSumOfCalculatedValues) && $totalSumOfCalculatedValues != null || $sum != null)
                                                P {{ number_format((float)$totalSumOfCalculatedValues+$sum ?? "", 2, '.', ',') }}

                                                @else
                                                P {{number_format(0, 2, '.', ',')}}
                                                @endif
                                                </p>
                                            </td>
                                        </tr>
                                @endif
                            @endif
                        @endif
                    </tbody>
                </table>

                {{-- START MOTOR TRADERS EXTERNAL --}}
                @if($coverages->coverage->s_CoverageCode == "MOTORTRADERSEXTERNAL")
                    @php
                        $totalExternalSumInsured = 0.00;
                        $totalExternalPremium = 0.00;

                        $totalExternalExtentionSumInsured = 0.00;
                        $totalExternalExtentionPremium = 0.00;

                        $totalExternalExcessSumInsured = 0.00;
                        $totalExternalExcessPremium = 0.00;
                        $motorTradersData = \AlphaDirect\Models\MotorTraders::where('policy_coverage_id',$coverages->id)->orderBy('id', 'asc')->get();
                    @endphp
                    @if(count($motorTradersData ?? [])>0)
                      @if( $policyAction->transaction_type === 'ENDORSE'
                            && ( ( $policyAction->id == $coverages->riskAddress->action_id 
                            && $coverages->riskAddress->status == 1 ) ||
                            ( $policyAction->id == $coverages['action_id'] && $coverages['status'] == 1))  )

                    <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_ext greybg">
                        @else
                        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_ext">
                            @endif
                    
                          <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ece3db;">
                                    <td  colspan="9">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">
                                            @foreach($motorTradersData as $newIndex => $tradersData)
                                                @if($tradersData->type_of_cover == "ComprehensiveMotorTradersExternal")
                                                Motor Traders External Comprehensive
                                                @elseif($tradersData->type_of_cover == "TPMotorTradersExternal")
                                                Motor Traders External FTP
                                                @elseif($tradersData->type_of_cover == "TPFTMotorTradersExternal")
                                                Motor Traders External FTPFT
                                                @endif
                                            @endforeach
                                        </p>
                                    </td>
                                </tr>
                                @foreach($motorTradersData as $newIndex => $tradersData)
                                    @php
                                        $totalExternalSumInsured = ($tradersData->loss_or_damage_coverage_value ?? 0.00)+($tradersData->third_party_liability_coverage_value ?? 0.00)+($tradersData->medical_benefits_coverage_value ?? 0.00);
                                        $totalExternalPremium = ($tradersData->loss_or_damage_calculated_value ?? 0.00)+($tradersData->third_party_liability_calculated_value ?? 0.00)+($tradersData->medical_benefits_calculated_value ?? 0.00);

                                        $totalExternalExtentionSumInsured = ($tradersData->vehicle_lent_hire_coverage_value ?? 0.00)+($tradersData->social_domestic_pleasure_coverage_value ?? 0.00)+($tradersData->unauthoried_use_coverage_value ?? 0.00)
                                                                            +($tradersData->windscreen_coverage_value ?? 0.00)+($tradersData->contigent_liability_coverage_value ?? 0.00)+($tradersData->wreckage_removal_coverage_value ?? 0.00)
                                                                            +($tradersData->loss_of_key_coverage_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_coverage_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_coverage_value ?? 0.00)
                                                                            +($tradersData->passanger_liability_respect_of_motor_coverage_value ?? 0.00)+($tradersData->special_type_vehicle_coverage_value ?? 0.00);

                                        $totalExternalExtentionPremium = ($tradersData->vehicle_lent_hire_calculated_value ?? 0.00)+($tradersData->social_domestic_pleasure_calculated_value ?? 0.00)+($tradersData->unauthoried_use_calculated_value ?? 0.00)
                                                                         +($tradersData->windscreen_calculated_value ?? 0.00)+($tradersData->contigent_liability_calculated_value ?? 0.00)+($tradersData->wreckage_removal_calculated_value ?? 0.00)
                                                                         +($tradersData->loss_of_key_calculated_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_calculated_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_calculated_value ?? 0.00)
                                                                         +($tradersData->passanger_liability_respect_of_motor_calculated_value ?? 0.00)+($tradersData->special_type_vehicle_calculated_value ?? 0.00);

                                        // $totalExternalExcessSumInsured = ($tradersData->own_damage_minimun_percent ?? 0.00)+($tradersData->windscreen_minimun_percent ?? 0.00);
                                        // $totalExternalExcessPremium = ($tradersData->own_damage_minimum_amount ?? 0.00)+($tradersData->windscreen_minimum_amount ?? 0.00);
                                    @endphp

                                    @if($tradersData->type_of_cover != "TPMotorTradersExternal")
                                        {{-- Start Description --}}

                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt3"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss or damage </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical benefits </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalExternalSumInsured ?? "", 2, '.', ',')}}</p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalExternalPremium ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                            @if(count($coverages->specifedItems ?? [])>0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;">
                                <td width="100%" colspan="9">
                                   <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;" class= "Motor Traders External">Miscellaneous Items</p>
                                </td>
                            </tr>
                            @php
                            $sumInsuredTotal = 0;
                            $sumInsuredCalculated = 0;
                            @endphp

                            @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                           
                            
                                @php
                                    if($coverages['status']!= 1 && $specifed_items->deleted_at==null ){
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="3">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">  @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                            
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                    @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                    @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                            </tr>
                            @endif
                                        {{-- End Description --}}
                                        {{-- Start Extensions --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;"><p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Extensions and Clauses</p></td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extension Name </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt4"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Vehicles lent or hired to Customers </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->vehicle_lent_hire_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->vehicle_lent_hire_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Social, domestic and pleasure use </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->social_domestic_pleasure_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->social_domestic_pleasure_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Unauthoried use by employees </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->unauthoried_use_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->unauthoried_use_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->windscreen_coverage_value ?? "", 2, '.', ',')}}  </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->windscreen_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Contigent liability </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->contigent_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->contigent_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Wreckage removal </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->wreckage_removal_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->wreckage_removal_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of keys </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_of_key_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_of_key_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of use of customer's vehicle </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->Loss_of_use_of_customer_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->Loss_of_use_of_customer_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Motor cycle, motor tricycle or quad bike </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->motor_cycle_motor_tricycle_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->motor_cycle_motor_tricycle_calculated_value ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Passanger liability in respect of motor cycles and motor tricycles </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->passanger_liability_respect_of_motor_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->passanger_liability_respect_of_motor_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                           <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Special type vehicle </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->special_type_vehicle_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->special_type_vehicle_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{number_format((float)$totalExternalExtentionSumInsured ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalExternalExtentionPremium ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        {{-- End Extensions --}}
                                        {{-- Start Excesses --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Excesses</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Excesses Name </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Min %</p>

                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Minimum Amount</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Basic Excess </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$tradersData->own_damage_minimun_percent ?? 0}} % </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->own_damage_minimum_amount ?? "", 2, '.', ',')}}  </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$tradersData->windscreen_minimun_percent ?? 0}} %</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->windscreen_minimum_amount ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        @if(isset($coverages->note->note))
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td width="100%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                                    font-style:normal;
                                                    font-size:10px;
                                                    overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                                    ">{{$coverages->note->note}}
                                                    </pre>
                                                </p>
                                            </td>
                                        </tr>
                                         @endif
                                        {{-- End Excesses --}}
                                    @elseif($tradersData->type_of_cover == "TPMotorTradersExternal")
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt5"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>


                                    @endif
                                @endforeach
                                @if($tradersData->type_of_cover == "TPMotorTradersExternal")
                                @if(count($coverages->specifedItems ?? [])>0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;">
                                <td width="100%" colspan="9">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Miscellaneous Items </p>
                                </td>
                            </tr>
                            @php
                            $sumInsuredTotal = 0;
                            $sumInsuredCalculated = 0;
                            @endphp
                            @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                                @php
                                    if($coverages['status']!= 1  && $specifed_items->deleted_at==null ){
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                    
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="3">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}}@endif</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                     @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                      @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                     @endif
                                </td>
                            </tr>
                            @endif
                            @if(isset($coverages->note->note))
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td width="100%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                                    font-style:normal;
                                                    font-size:10px;
                                                    overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                                    ">{{$coverages->note->note}}
                                                    </pre>
                                                </p>
                                            </td>
                                        </tr>
                            @endif
                            @endif
                            </tbody>
                        </table>
                    @endif



                @endif
                {{-- END MOTOR TRADERS EXTERNAL --}}

                {{-- START MOTORTRADERS INTERNAL --}}
                @if($coverages->coverage->s_CoverageCode == "MOTORTRADERSINTERNAL")
                    @php
                        $totalInternalSumInsured = 0.00;
                        $totalInternalPremium = 0.00;

                        $totalInternalExtentionSumInsured = 0.00;
                        $totalInternalExtentionPremium = 0.00;

                        $motorTradersData = \AlphaDirect\Models\MotorTradersInternal::where('policy_coverage_id',$coverages->id)->orderBy('id', 'asc')->get();
                    @endphp
                   
                     @if(count($motorTradersData ?? [])>0)
                          @if( $policyAction->transaction_type === 'ENDORSE'
                            && ( ( $policyAction->id == $coverages->riskAddress->action_id 
                            && $coverages->riskAddress->status == 1 ) ||
                            ( $policyAction->id == $coverages['action_id'] && $coverages['status'] == 1))  )

                    <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_int greybg">
                        @else
                    <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_int">
                        @endif
                           <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ece3db;">
                                    <td  colspan="9">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">
                                            @foreach($motorTradersData as $newIndex => $tradersData)
                                                @if($tradersData->type_of_cover == "ComprehensiveMotorTradersInternal")
                                                 Motor Traders Internal Comprehensive
                                                @elseif($tradersData->type_of_cover == "TPMotorTradersInternal")
                                                Motor Traders Internal FTP
                                                @elseif($tradersData->type_of_cover == "TPFTMotorTradersInternal")
                                                Motor Traders Internal FTPFT
                                                @endif
                                            @endforeach
                                        </p>
                                    </td>
                                </tr>
                                @foreach($motorTradersData as $newIndex => $tradersData)
                                    @php
                                        $totalInternalSumInsured = ($tradersData->loss_or_damage_coverage_value ?? 0.00)+($tradersData->third_party_liability_coverage_value ?? 0.00)+($tradersData->medical_benefits_coverage_value ?? 0.00);
                                        $totalInternalPremium = ($tradersData->loss_or_damage_calculated_value ?? 0.00)+($tradersData->third_party_liability_calculated_value ?? 0.00)+($tradersData->medical_benefits_calculated_value ?? 0.00);

                                        $totalInternalExtentionSumInsured = ($tradersData->vehicle_lent_hire_coverage_value ?? 0.00)+($tradersData->social_domestic_pleasure_coverage_value ?? 0.00)+($tradersData->unauthoried_use_coverage_value ?? 0.00)
                                                                            +($tradersData->windscreen_coverage_value ?? 0.00)+($tradersData->contigent_liability_coverage_value ?? 0.00)+($tradersData->wreckage_removal_coverage_value ?? 0.00)
                                                                            +($tradersData->loss_of_key_coverage_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_coverage_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_coverage_value ?? 0.00)
                                                                            +($tradersData->passanger_liability_respect_of_motor_coverage_value ?? 0.00)+($tradersData->special_type_vehicle_coverage_value ?? 0.00);

                                        $totalInternalExtentionPremium = ($tradersData->vehicle_lent_hire_calculated_value ?? 0.00)+($tradersData->social_domestic_pleasure_calculated_value ?? 0.00)+($tradersData->unauthoried_use_calculated_value ?? 0.00)
                                                                         +($tradersData->windscreen_calculated_value ?? 0.00)+($tradersData->contigent_liability_calculated_value ?? 0.00)+($tradersData->wreckage_removal_calculated_value ?? 0.00)
                                                                         +($tradersData->loss_of_key_calculated_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_calculated_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_calculated_value ?? 0.00)
                                                                         +($tradersData->passanger_liability_respect_of_motor_calculated_value ?? 0.00)+($tradersData->special_type_vehicle_calculated_value ?? 0.00);

                                    @endphp

                                    @if($tradersData->type_of_cover != "TPMotorTradersInternal")
                                        {{-- Start Description --}}
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt6"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss or damage </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_or_damage_calculated_value ?? "", 2, '.', ',')}}  </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->third_party_liability_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical benefits </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_coverage_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->medical_benefits_calculated_value ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalSumInsured ?? "", 2, '.', ',')}}</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalPremium ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                                        @if(count($coverages->specifedItems ?? [])>0)
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;">
                                            <td width="100%" colspan="9">
                                               <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;" class= "Motor Traders Internal">Miscellaneous Items</p>
                                            </td>
                                        </tr>
                                        @php
                                        $sumInsuredTotal = 0;
                                        $sumInsuredCalculated = 0;
                                        @endphp
                                        @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                                @php
                                     if($coverages['status']!= 1  && $specifed_items->deleted_at==null ){
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="3">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">  @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                    @else 
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00</p>
                                    @endif
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                    @else 
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00</p>
                                    @endif
                                </td>
                            </tr>
                            @endif
                                        {{-- End Description --}}
                                        {{-- Start Extensions --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;"><p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Extensions and Clauses</p></td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                           <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extension Name </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt8"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Vehicles lent or hired to Customers </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->vehicle_lent_hire_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->vehicle_lent_hire_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Social, domestic and pleasure use </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->social_domestic_pleasure_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->social_domestic_pleasure_calculated_value  ?? "", 2, '.', ',')  }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Unauthoried use by employees </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->unauthoried_use_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->unauthoried_use_calculated_value  ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->windscreen_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->windscreen_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Contigent liability </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->contigent_liability_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->contigent_liability_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Wreckage removal </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->wreckage_removal_coverage_value  ?? "", 2, '.', ',')}} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->wreckage_removal_calculated_value  ?? "", 2, '.', ',')  }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of keys </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_of_key_coverage_value  ?? "", 2, '.', ',')  }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->loss_of_key_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of use of customer's vehicle </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->Loss_of_use_of_customer_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->Loss_of_use_of_customer_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Motor cycle, motor tricycle or quad bike </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->motor_cycle_motor_tricycle_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->motor_cycle_motor_tricycle_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Passanger liability in respect of motor cycles and motor tricycles </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->passanger_liability_respect_of_motor_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->passanger_liability_respect_of_motor_calculated_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Special type vehicle </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$tradersData->special_type_vehicle_coverage_value  ?? "", 2, '.', ',') }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$tradersData->special_type_vehicle_calculated_value  ?? "", 2, '.', ',')  }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ number_format((float)$totalInternalExtentionSumInsured  ?? "", 2, '.', ',') }}</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P @if(isset($totalInternalExtentionPremium)){{ number_format((float)$totalInternalExtentionPremium  ?? "", 2, '.', ',') }} @endif</p>
                                            </td>
                                        </tr>
                                        {{-- End Extensions --}}
                                        {{-- Start Excesses --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Excesses</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Excesses Name </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Min %</p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="4">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Minimum Amount</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Basic Excess </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ $tradersData->own_damage_minimun_percent ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->own_damage_minimum_amount ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ $tradersData->windscreen_minimun_percent ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->windscreen_minimum_amount ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                @if(isset($coverages->note->note))
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                    <td width="100%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                            font-style:normal;
                                            font-size:10px;
                                            overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                            ">{{$coverages->note->note}}
                                            </pre>
                                        </p>
                                    </td>
                                </tr>
                                @endif
                                        {{-- End Excesses --}}
                                    @elseif($tradersData->type_of_cover == "TPMotorTradersInternal")
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt9"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="3" class="5">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="3" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }}</p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="3" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }}</p>
                                            </td>
                                        </tr>


                                    @endif
                                @endforeach
                            @if($tradersData->type_of_cover == "TPMotorTradersInternal")
                                @if(count($coverages->specifedItems ?? [])>0)
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;">
                                    <td width="100%" colspan="9">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Miscellaneous Items </p>
                                    </td>
                                </tr>
                            @php
                            $sumInsuredTotal = 0;
                            $sumInsuredCalculated = 0;
                            @endphp
                            @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                                @php
                                     if($coverages['status']!= 1 && $specifed_items->deleted_at==null ) {
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                @endphp
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="3">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="3">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                    @else  
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="4">
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                    @else
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p> 
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @if(isset($coverages->note->note))
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                            <td width="100%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                                    font-style:normal;
                                                    font-size:10px;
                                                    overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                                    ">{{$coverages->note->note}}
                                                    </pre>
                                                </p>
                                            </td>
                                        </tr>
                                        @endif
                            @endif
                            </tbody>
                        </table>
                    @endif



                @endif
                {{-- END MOTOR TRADERS EXTERNAL --}}

                {{-- START MOTORTRADERS INTERNAL --}}
                @if($coverages->coverage->s_CoverageCode == "MOTORTRADERSINTERNALCOMPR")
                    @php
                        $totalInternalSumInsured = 0.00;
                        $totalInternalPremium = 0.00;

                        $totalInternalExtentionSumInsured = 0.00;
                        $totalInternalExtentionPremium = 0.00;

                        $totalInternalExcessSumInsured = 0.00;
                        $totalInternalExcessPremium = 0.00;
                        $motorTradersData = \AlphaDirect\Models\MotorTradersInternal::where('policy_coverage_id',$coverages->id)->orderBy('id', 'asc')->get();
                    @endphp
                    @if(count($motorTradersData ?? [])>0)
                     @if( $policyAction->transaction_type === 'ENDORSE'
                        && ( ( $policyAction->id == $coverages->riskAddress->action_id 
                        && $coverages->riskAddress->status == 1 ) ||
                        ( $policyAction->id == $coverages['action_id'] && $coverages['status'] == 1))  )
                        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_int greybg">
                    @else
                        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor_int">
                    @endif
                           <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ece3db;">
                                    <td  colspan="9" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">
                                            @foreach($motorTradersData as $newIndex => $tradersData)
                                                @if($tradersData->type_of_cover == "ComprehensiveMotorTradersInternal")
                                                Comprehensive
                                                @elseif($tradersData->type_of_cover == "TPMotorTradersInternal")
                                                Third party only
                                                @elseif($tradersData->type_of_cover == "TPFTMotorTradersInternal")
                                                Third party, fire and theft
                                                @endif
                                            @endforeach
                                        </p>
                                    </td>
                                </tr>
                                @foreach($motorTradersData as $newIndex => $tradersData)
                                    @php
                                        $totalInternalSumInsured = ($tradersData->loss_or_damage_coverage_value ?? 0.00)+($tradersData->third_party_liability_coverage_value ?? 0.00)+($tradersData->medical_benefits_coverage_value ?? 0.00);
                                        $totalInternalPremium = ($tradersData->loss_or_damage_calculated_value ?? 0.00)+($tradersData->third_party_liability_calculated_value ?? 0.00)+($tradersData->medical_benefits_calculated_value ?? 0.00);

                                        $totalInternalExtentionSumInsured = ($tradersData->vehicle_lent_hire_coverage_value ?? 0.00)+($tradersData->social_domestic_pleasure_coverage_value ?? 0.00)+($tradersData->unauthoried_use_coverage_value ?? 0.00)
                                                                            +($tradersData->windscreen_coverage_value ?? 0.00)+($tradersData->contigent_liability_coverage_value ?? 0.00)+($tradersData->wreckage_removal_coverage_value ?? 0.00)
                                                                            +($tradersData->loss_of_key_coverage_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_coverage_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_coverage_value ?? 0.00)
                                                                            +($tradersData->passanger_liability_respect_of_motor_coverage_value ?? 0.00)+($tradersData->special_type_vehicle_coverage_value ?? 0.00);

                                        $totalInternalExtentionPremium = ($tradersData->vehicle_lent_hire_calculated_value ?? 0.00)+($tradersData->social_domestic_pleasure_calculated_value ?? 0.00)+($tradersData->unauthoried_use_calculated_value ?? 0.00)
                                                                         +($tradersData->windscreen_calculated_value ?? 0.00)+($tradersData->contigent_liability_calculated_value ?? 0.00)+($tradersData->wreckage_removal_calculated_value ?? 0.00)
                                                                         +($tradersData->loss_of_key_calculated_value ?? 0.00)+($tradersData->Loss_of_use_of_customer_calculated_value ?? 0.00)+($tradersData->motor_cycle_motor_tricycle_calculated_value ?? 0.00)
                                                                         +($tradersData->passanger_liability_respect_of_motor_calculated_value ?? 0.00)+($tradersData->special_type_vehicle_calculated_value ?? 0.00);

                                        $totalInternalExcessSumInsured = ($tradersData->own_damage_minimun_percent ?? 0.00)+($tradersData->windscreen_minimun_percent ?? 0.00);
                                        $totalInternalExcessPremium = ($tradersData->own_damage_minimum_amount ?? 0.00)+($tradersData->windscreen_minimum_amount ?? 0.00);
                                    @endphp

                                    @if($tradersData->type_of_cover != "TPMotorTradersInternal")
                                        {{-- Start Description --}}
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt10"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="2" class="6">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss or damage </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_or_damage_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_or_damage_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="2" class="7">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical benefits </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->medical_benefits_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->medical_benefits_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="2" class="77">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalSumInsured ?? "", 2, '.', ',')}}</p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalPremium ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                                        {{-- End Description --}}
                                        {{-- Start Extensions --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;"><p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Extensions and Clauses</p></td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extension Name </p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt1"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Vehicles lent or hired to Customers </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->vehicle_lent_hire_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->vehicle_lent_hire_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Social, domestic and pleasure use </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->social_domestic_pleasure_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->social_domestic_pleasure_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Unauthoried use by employees </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->unauthoried_use_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->unauthoried_use_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->windscreen_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->windscreen_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Contigent liability </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->contigent_liability_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->contigent_liability_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Wreckage removal </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->wreckage_removal_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->wreckage_removal_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of keys </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_of_key_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->loss_of_key_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of use of customer's vehicle </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->Loss_of_use_of_customer_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->Loss_of_use_of_customer_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Motor cycle, motor tricycle or quad bike </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->motor_cycle_motor_tricycle_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->motor_cycle_motor_tricycle_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Passanger liability in respect of motor cycles and motor tricycles </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->passanger_liability_respect_of_motor_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->passanger_liability_respect_of_motor_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%"  colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Special type vehicle </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"  colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->special_type_vehicle_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->special_type_vehicle_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>

                                        {{-- End Extensions --}}
                                        {{-- Start Excesses --}}
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                            <td colspan="9" style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Excesses</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Excesses Name </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Min %</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Minimum Amount</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Basic Excess </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ $tradersData->own_damage_minimun_percent ?? 0.00 }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->own_damage_minimum_amount ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ $tradersData->windscreen_minimun_percent ?? 0.00 }} %</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P  {{number_format((float)$tradersData->windscreen_minimum_amount ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="2" class="66">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalExcessSumInsured ?? "", 2, '.', ',')}}</p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="1" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalInternalExcessPremium ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                                        {{-- End Excesses --}}
                                    @elseif($tradersData->type_of_cover == "TPMotorTradersInternal")
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                 <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"> Sum Insured</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt2"> Premium</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }} </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }} </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">Total </p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_coverage_value ?? 0.00 }}</p>
                                            </td>
                                            <td colspan="3" style="border: 1px solid #2e77c3!important;">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{ $tradersData->third_party_liability_calculated_value ?? 0.00 }}</p>
                                            </td>
                                        </tr>


                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                @endif
                {{-- END MOTOR TRADERS INTERNAL --}}

                {{-- START PERSONALMOTOR --}}
                @if($coverages->coverage->s_CoverageCode == "PERSONALMOTOR" || $coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                    @php
                   // Policy Document PDF — fetch ALL motor rows including
                   // soft-deleted (per-vehicle Cancel). Cancelled vehicles
                   // remain visible in the Summary of Vehicles table —
                   // greyed, P 0.00, and excluded from $commMotorTotal.
                   // Mirrors v2-quote-sheet.blade.php fix on 2026-05-25.
                   // Motor model does NOT use SoftDeletes, so a plain Eloquent
                   // query already returns soft-deleted rows — `withTrashed()`
                   // is not available and would throw 'undefined method'.
                   // GRA-0122: on any non-ENDORSE action (New Business /
                   // Renewal / Anniversary) a cancelled (soft-deleted) vehicle
                   // must NOT appear at all — not greyed, not counted. Only an
                   // ENDORSE keeps the deleted rows so the cancelling
                   // endorsement can document them (greyed, P 0.00).
                   $personalMotorData = \AlphaDirect\Models\Motor::where('policy_coverage_id', $coverages->id)
                        ->when(($policyAction->transaction_type ?? null) !== 'ENDORSE', fn($q) => $q->whereNull('deleted_at'))
                        ->orderBy('id', 'asc')
                        ->get();
                    @endphp
                    @if(count($personalMotorData ?? [])>0)
                            @if( $policyAction->transaction_type === 'ENDORSE'
                            && ( ( $policyAction->id == $coverages->riskAddress->action_id 
                            && $coverages->riskAddress->status == 1 ) ||
                            ( $policyAction->id == $coverages['action_id'] && $coverages['status'] == 1))  )

                        <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" class="motor greybg">
                            @else
                            <table style="border='0'" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
                                @endif  
                            <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;border-right: 2px solid #2e77c3;">
                                <!-- Summary Of Vehicles Start-->
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#A6A6A6;border-right: 2px solid #2e77c3;">
                                    <td width="100%" colspan="11" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Summary Of Vehicles</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td colspan="4" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">&nbsp; Description </p>
                                    </td>
                                    <td  colspan="1" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Registration No.</p>
                                    </td>
                                    <!-- <td  style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Engine No.</p>
                                    </td>
                                    <td  style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Chassis No.</p>
                                    </td> -->
                                    <td  colspan="1" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Usage</p>
                                    </td>
                                    <td  colspan="2" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Type of Cover </p>
                                    </td>
                                    <td colspan="2" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Sum Insured</p>
                                    </td>
                                    <td  colspan="2" style="border: 1px solid #2e77c3!important;" >
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt123">Premium</p>
                                    </td>
                                </tr>
                                @php
                                $sumInsuredTotal = 0;
                                $sumInsuredCalculated = 0;
                                @endphp

                                @php
                                    $totalCommercial = 0;
                                    $totalCommercialPremium = 0;
                                    $totalExtensionsOfCommericialMotorPremuim=0;
                                    $totalExtensionsOfCommericialMotor=0;
                                    $commMotorTotal=0; $commMotorSumInsuredTotal=0;
                                    $sumInsuredTotal = 0;
                                    $sumInsuredCalculated = 0;
                                    $commMotorTotalEndors=0; $commMotorTotalPre=0;
                                @endphp
                                @foreach($personalMotorData as $newIndex => $motor)
                                {{-- Cancelled (soft-deleted) vehicles render greyed
                                     with P 0.00 ONLY on the action that cancelled
                                     them (deleteMotorVehicle stamps
                                     previousActionIdCov = that action). Any later
                                     transaction — ANNIVERSARY-RENEW / RENEW /
                                     subsequent endorses — replicates the deleted
                                     row forward, and it must not appear at all
                                     (COMG ticket: cancelled vehicle still showing
                                     on anniversary quote). Mirrors
                                     v2-quote-sheet.blade.php. --}}
                                {{-- GRA-0122: on New Business / Renewal (and any non-ENDORSE
                                     action) a soft-deleted vehicle must NOT show at all. Only
                                     an ENDORSE that cancelled the vehicle keeps the greyed row
                                     (to document the cancellation on that endorsement). --}}
                                @continue(!is_null($motor->deleted_at) && ($policyAction->transaction_type !== 'ENDORSE' || (int)($motor->previousActionIdCov ?? 0) !== (int)($policyAction->id ?? 0)))
                                @php  $sumInsuredTotal = 0;
                                $sumInsuredCalculated = 0;  @endphp
                                @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)

                                @if( $specifed_items->motor_id ==$motor->id && $specifed_items->deleted_at==null)
                                @php
                                    if($coverages['status']!= 1){
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                    @endphp
                                @endif
                                @endforeach
                                @php

                                if($coverages->coverage->s_CoverageCode == "PERSONALMOTOR")
                                {
                                    $totalExtensionsOfCommericialMotorPremuim = ($motor->premium_wreckage_removal ??0) + ($motor->premium_window_glass ??0) + ($motor->premium_locks_keys ??0)+ ($motor->premium_parts_accessories ??0)
                                            + ($motor->premium_audio_accessories ??0)+ ($motor->premium_riot_strike ??0)+ ($motor->premium_car_hire_theft ??0)+ ($motor->premium_credit_shortfall ??0)+ ($motor->premium_insured_driver ??0)
                                            + ($motor->premium_insured_family ??0)+ ($motor->premium_medical_expenses ??0)+ ($motor->premium_passenger_liability ??0)+ ($motor->premium_third_party_liability ??0)+ ($motor->premium_specified_accessories ??0);

                                 }else{
                                    $totalExtensionsOfCommericialMotorPremuim = ($motor->premium_contigent_liability ??0) + ($motor->premium_passenger_liability ??0)+ ($motor->premium_unorthorised_passanger_liability ??0)
                                            + ($motor->premium_parking_facilities ??0)+ ($motor->premium_com_windscreen ??0)+ ($motor->premium_riot_strike ??0)+ ($motor->premium_locks_keys ??0)+ ($motor->premium_wreckage_removal ??0)
                                            + ($motor->premium_credit_shortfall ??0)+ ($motor->premium_third_party_liability ??0);

                                }

                                 $totalExtensionsOfCommericialMotor = ((float)$motor->contigent_liability ??0) + ((float)$motor->passenger_liability ??0)+ ((float)$motor->unorthorised_passanger_liability ??0)
                                            + ((float)$motor->parking_facilities ??0)+ ((float)$motor->com_windscreen ??0)+ ((float)$motor->riot_strike ??0)+ ((float)$motor->locks_keys ??0)+ ((float)$motor->wreckage_removal ??0)
                                            + ((float)$motor->credit_shortfall ??0)+ ((float)$motor->third_party_liability ??0);


                                $totalCommercial=$totalCommercial+(float)$motor->coverage_value;
                                $totalCommercialPremium=$totalCommercialPremium+(float)$motor->calculated_value;
                                if($motor->deleted_at==NULL){
                                $commMotorTotal= $commMotorTotal+$motor->calculated_value + $sumInsuredCalculated +$totalExtensionsOfCommericialMotorPremuim;
                                $commMotorSumInsuredTotal= $commMotorSumInsuredTotal+$motor->coverage_value;
                                }
                                @endphp
                                {{-- Grey out ANY cancelled (deleted_at) motor row,
                                     not only those cancelled in the current ENDORSE.
                                     RENEW transactions inheriting a vehicle cancel
                                     also need the greyed row + P 0.00 + total exclusion. --}}
                                @if(!is_null($motor->deleted_at))
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="mot1 greybg" >
                                @else
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="mot1" >
                                @endif
                                    <td colspan="4" style="white-space:pre-line;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{$motor->vehicle_name ?? ""}}</p>
                                    </td>
                                    <td colspan="1" style="border: 1px solid #2e77c3!important;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{$motor->registration_no ?? ""}} </p>
                                    </td>
                                    <!-- <td   style="border: 1px solid #2e77c3!important;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{$motor->engine_number ?? ""}} </p>
                                    </td>
                                    <td   style="border: 1px solid #2e77c3!important;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{$motor->chassis_number ?? ""}}</p>
                                    </td> -->
                                    <td  colspan="1" style="border: 1px solid #2e77c3!important;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{$motor->use_main ?? ""}} </p>
                                    </td>
                                    <td colspan="2" style="border: 1px solid #2e77c3!important;border-right: 2px solid #2e77c3;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if($motor->type_of_cover == "Comprehensive")
                                            Comprehensive
                                            @elseif($motor->type_of_cover == "third_party_only")
                                            Third party only
                                            @elseif($motor->type_of_cover == "Third_fire_and_theft")
                                            Third party, fire and theft
                                            @elseif($motor->type_of_cover == "Third_fire")
                                            Third party and fire
                                            @endif
                                        </p>
                                    </td>
                                    <td colspan="2" style="border: 1px solid #2e77c3!important;word-wrap: break-word;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">  @if($coverages->status==1) P 0.00 @else @if($motor->deleted_at==NULL)P {{ number_format((float)($motor->coverage_value ) ?? "", 2, '.', ',') }}@else P 0.00 @endif @endif </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;word-wrap: break-word;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">  @if($coverages->status==1) P 0.00 @else @if($motor->deleted_at==NULL)P {{ number_format((float)($motor->calculated_value + $sumInsuredCalculated +$totalExtensionsOfCommericialMotorPremuim) ?? "", 2, '.', ',') }}@else P 0.00 @endif @endif</p>
                                    </td>
                                </tr>
                                @endforeach
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="mot2">
                                    <td colspan="9" class="motor122">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">{{$coverages->coverage->s_ScreenName}}</p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;word-wrap: break-word;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{number_format((float)($commMotorSumInsuredTotal)  ?? "", 2, '.', ',')}} </p>
                                    </td>
                                    <td style="border: 1px solid #2e77c3!important;word-wrap: break-word;"  >
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{number_format((float)($commMotorTotal)  ?? "", 2, '.', ',')}}</p>
                                    </td>
                                 </tr>
                                <!-- Summary Of Vehicles End-->
                                @php
                                    $totalExtensionsOfPersonalMotor = 0;
                                    $totalMiniPerExcessesOfPersonalMotor = 0;
                                    $totalMiniAmountExcessesOfPersonalMotor = 0;
                                    $totalExtensionsOfPersonalMotorSumInsured = 0;
                                    // Set true by whichever per-vehicle Notes block below actually
                                    // renders; read after the loop to decide whether the
                                    // coverage-level Notes tab is still needed. Per motor section.
                                    $motorNoteTabRendered = false;
                                @endphp

                                @foreach($personalMotorData as $newIndex => $motor)
                                {{-- GRA-0122: keep the per-vehicle detail block in sync with the
                                     Summary Of Vehicles guard above — a soft-deleted vehicle only
                                     shows on the ENDORSE that cancelled it, never on a non-ENDORSE
                                     (New Business / Renewal / Anniversary). --}}
                                @if($motor->deleted_at==NULL || ($motor->deleted_at!=NULL && $policyAction->transaction_type=='ENDORSE' && $motor->previousActionIdCov == $policyAction->id))

                                     <!-- Vehicle Start-->
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ece3db;">
                                        <td  colspan="11" style="border-right: 2px solid #2e77c3;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">
                                                @if($motor->type_of_cover == "Comprehensive")
                                                Comprehensive
                                                @elseif($motor->type_of_cover == "third_party_only")
                                                Third party only
                                                @elseif($motor->type_of_cover == "Third_fire_and_theft")
                                                Third party, fire and theft
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#A6A6A6;border-right: 2px solid #2e77c3;">
                                        <td colspan="11" style="border-right: 2px solid #2e77c3;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;"> Vehicle details</p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Use
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->use ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Registration number
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->registration_no ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Make
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->make ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Model
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->model ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Engine number
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->engine_number ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Chassis number
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->chassis_number ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Tracking device
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->tracking_device ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Security features
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            {{$motor->security_features ?? ""}}
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Type of cover
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;border-right: 2px solid #2e77c3;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                                @if($motor->type_of_cover == "Comprehensive")
                                                Comprehensive
                                                @elseif($motor->type_of_cover == "third_party_only")
                                                Third party only
                                                @elseif($motor->type_of_cover == "Third_fire_and_theft")
                                                Third party, fire and theft
                                                @elseif($motor->type_of_cover == "Third_fire")
                                                Third party and fire
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Sum Insured
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                              @if($coverages->status==1) P 0.00 @else @if($motor->deleted_at==NULL)P {{ number_format((float)$motor->coverage_value ?? "", 2, '.', ',') }}@else P 0.00 @endif @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="6">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                            Premium
                                            </p>
                                        </td>
                                        <td  style="border: 1px solid #2e77c3!important;" colspan="5">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;">
                                             @if($coverages->status==1) P 0.00 @else @if($motor->deleted_at==NULL)P {{ number_format((float)$motor->calculated_value ?? "", 2, '.', ',') }} @else P 0.00 @endif @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <!-- Vehicle End-->

                            @if(count($coverages->specifedItems ?? [])>0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;" class="mot1_spec"> 
                                <td width="100%" colspan="11">
                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;" class="Comprehensive motor">Miscellaneous Items</p>
                                </td>
                            </tr>
                            @php
                            $sumInsuredTotal = 0;
                            $sumInsuredCalculated = 0;
                            @endphp
                            @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                             {{-- @if (empty($specifed_items['deleted_at']))  --}}
                            @if($specifed_items->motor_id == $motor->id)
                                @php
                                    if($coverages['status']!= 1 && $specifed_items->deleted_at==null ) {
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                @endphp
                                 @if($policyAction->transaction_type === 'ENDORSE'
                                    && $policyAction->id == $specifed_items->action_id 
                                    && !is_null($specifed_items->deleted_at) 
                                    ) 
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="mot2_spec greybg"> 
                                        @else 
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="mot2_spec ff">
                                     @endif
                                    <td width="50%" colspan="6">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1  || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                            @endif
                            
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="6">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                    @else 
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                    @else
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @php $noteMotor=\AlphaDirect\Models\PolicyCoverageNote::where('motor_id',$motor->id)->latest('id')->first(); @endphp
                            @if(isset($noteMotor) && $motor->type_of_cover == "third_party_only")
                                            @php $motorNoteTabRendered = true; @endphp
                                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                                <td width="100%" colspan="11" style="border-right: 2px solid #2e77c3;">
                                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="11" style="white-space:pre-wrap;color:black;font-size:11px;border-right: 2px solid #2e77c3;">{{$noteMotor->note}}</td>
                                            </tr>
                            @endif
                                    @if($motor->type_of_cover != "third_party_only")
                                        @php
                                        $totalExtensionsOfPersonalMotor = ($motor->premium_wreckage_removal ??0) + ($motor->premium_window_glass ??0) + ($motor->premium_locks_keys ??0)+ ($motor->premium_parts_accessories ??0)
                                            + ($motor->premium_audio_accessories ??0)+ ($motor->premium_riot_strike ??0)+ ($motor->premium_car_hire_theft ??0)+ ($motor->premium_credit_shortfall ??0)+ ($motor->premium_insured_driver ??0)
                                            + ($motor->premium_insured_family ??0)+ ($motor->premium_medical_expenses ??0)+ ($motor->premium_passenger_liability ??0)+ ($motor->premium_third_party_liability ??0)+ ($motor->premium_specified_accessories ??0);

                                            $totalExtensionsOfCommericialMotorPremuim = ($motor->premium_contigent_liability ??0) + ($motor->premium_passenger_liability ??0)+ ($motor->premium_unorthorised_passanger_liability ??0)
                                            + ($motor->premium_parking_facilities ??0)+ ($motor->premium_com_windscreen ??0)+ ($motor->premium_riot_strike ??0)+ ($motor->premium_locks_keys ??0)+ ($motor->premium_wreckage_removal ??0)
                                            + ($motor->premium_credit_shortfall ??0)+ ($motor->premium_third_party_liability ??0);

                                            $totalExtensionsOfCommericialMotor = ((float)$motor->contigent_liability ??0) + ((float)$motor->passenger_liability ??0)+ ((float)$motor->unorthorised_passanger_liability ??0)
                                            + ((float)$motor->parking_facilities ??0)+ ((float)$motor->com_windscreen ??0)+ ((float)$motor->riot_strike ??0)+ ((float)$motor->locks_keys ??0)+ ((float)$motor->wreckage_removal ??0)
                                            + ((float)$motor->credit_shortfall ??0)+ ((float)$motor->third_party_liability ??0);


                                            $totalExtensionsOfPersonalMotorSumInsured = ((float)$motor->wreckage_removal ??0) + ((float)$motor->locks_keys ??0)+ ((float)$motor->parts_accessories ??0)
                                            + ((float)$motor->audio_accessories ??0)+ ((float)$motor->car_hire_theft ??0)+ ((float)$motor->riot_strike ??0)+ ((float)$motor->locks_keys ??0)+ ((float)$motor->wreckage_removal ??0)
                                            +((float)$motor->insured_driver ??0)+ ((float)$motor->passenger_liability ??0)+ ((float)$motor->specified_accessories ??0)+((float)$motor->insured_family ??0)+((float)$motor->medical_expenses ??0) + ((float)$motor->credit_shortfall ??0)+ ((float)$motor->third_party_liability ??0);

                                            $totalMiniPerExcessesOfPersonalMotor = ((float)$motor->own_damage_minimun_percent ??0) + ((float)$motor->windscreen_minimun_percent ??0) + ((float)$motor->loss_of_keys_minimun_percent??0);
                                            $totalMiniAmountExcessesOfPersonalMotor = ((float)$motor->own_damage_minimum_amount ??0) + ((float)$motor->windscreen_minimum_amount ??0) + ((float)$motor->loss_of_keys_minimum_amount??0);

                                            $totalMiniPerExcessesOfCommericialMotor = ((float)$motor->own_damage_minimun_percent ??0) + ((float)$motor->windscreen_minimun_percent ??0);
                                            $totalMiniPerExcessesOfCommericialMotor = ((float)$motor->own_damage_minimun_percent ??0) + ((float)$motor->windscreen_minimun_percent ??0);
                                                                         @endphp
                                        <!-- Extensions Start-->
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                        <td colspan="11" style="border-right: 2px solid #2e77c3;"><p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Extensions and Clauses</p></td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td  colspan="6" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;" >&nbsp; Extension Name </p>
                                            </td>
                                            <td  colspan="2" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"  >Sum Insured</p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;"  class="mt123">Premium</p>
                                            </td>
                                        </tr>

                                        @if($coverages->coverage->s_CoverageCode == "PERSONALMOTOR")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Wreckage removal </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->wreckage_removal ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_wreckage_removal ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Window glass </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->window_glass ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_window_glass ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Locks and keys </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->locks_keys ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_locks_keys ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Parts or accessories not readily available </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->parts_accessories ?? "", 2, '.', ',') }}</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_parts_accessories ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Audio accessories </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->audio_accessories ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_audio_accessories ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">Riot and strike </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->riot_strike ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_riot_strike ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Car hire-theft/hijack of the vehicle </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->car_hire_theft ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_car_hire_theft ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Credit Shortfall</p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->credit_shortfall ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_credit_shortfall ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Insured only driver </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->insured_driver ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_insured_driver ?? "", 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Insured and family only drivers</p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->insured_family ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_insured_family ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Medical expenses </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->medical_expenses ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_medical_expenses ?? "", 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Passenger liability excluded</p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->passenger_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_passenger_liability ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->third_party_liability ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{ number_format((float)$motor->premium_third_party_liability ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  > Specified accessories </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  > P {{ number_format((float)$motor->specified_accessories ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  > P {{ number_format((float)$motor->premium_specified_accessories ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>

                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#7daada;"  > Total of Extension</p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > P {{ number_format((float)$totalExtensionsOfPersonalMotorSumInsured ?? "", 2, '.', ',') }}</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > P {{ number_format((float)$totalExtensionsOfPersonalMotor ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                        @endif

                                        @if($coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Contigent Liability </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->contigent_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_contigent_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Passenger liability excluded </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <!-- <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{$motor->passenger_liability}} {{ number_format((float)$motor->passenger_liability ?? "", 2, '.', ',') }}</p> -->
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P  {{ number_format((float)$motor->passenger_liability ?? "", 2, '.', ',') }}</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_passenger_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> unauthorized passanger liability </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->unorthorised_passanger_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_unorthorised_passanger_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Parking facilities and movement of third party vehicles </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->parking_facilities ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_parking_facilities ?? "", 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Windscreen </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->com_windscreen ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_com_windscreen ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">Riot and strike </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->riot_strike ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td   colspan="3" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_riot_strike ?? "", 2, '.', ',') }}   </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Locks and keys </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->locks_keys ?? "", 2, '.', ',') }}</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_locks_keys ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Wreckage removal</p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->wreckage_removal ?? "", 2, '.', ',') }}  </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_wreckage_removal ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Credit Shortfall </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->credit_shortfall ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_credit_shortfall ?? "", 2, '.', ',') }} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Third party liability</p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->third_party_liability ?? "", 2, '.', ',') }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$motor->premium_third_party_liability ?? "", 2, '.', ',') }}</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td width="50%" colspan="6">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total </p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="2" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{number_format((float)$totalExtensionsOfCommericialMotor ?? "", 2, '.', ',')}}</p>
                                            </td>
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;"colspan="3" >
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;">P {{number_format((float)$totalExtensionsOfCommericialMotorPremuim ?? "", 2, '.', ',')}}</p>
                                            </td>
                                        </tr>
                                        @endif
                                        <!-- Extensions End -->
                                        <!-- Excesses Start-->
                                        <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                            <td colspan="11"style="border-right: 2px solid #2e77c3;">
                                                <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Excesses</p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                            <td  colspan="6" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;" >&nbsp; Excesses Name </p>
                                            </td>
                                            <td  colspan="2" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Min %</p>
                                            </td>
                                            <td  colspan="3" style="border: 1px solid #2e77c3!important;" >
                                                <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Minimum Amount</p>
                                            </td>
                                        </tr>

                                        @if($coverages->coverage->s_CoverageCode == "PERSONALMOTOR")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  >  Basic Excess </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > {{$motor->own_damage_minimun_percent ?? 0}}% </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > P {{number_format((float)$motor->own_damage_minimum_amount ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > Windscreen </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$motor->windscreen_minimun_percent ?? 0}}% </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> P {{number_format((float)$motor->windscreen_minimum_amount ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> Loss of Keys </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$motor->loss_of_keys_minimun_percent ?? 0}}% </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$motor->loss_of_keys_minimum_amount ?? "", 2, '.', ',')}}  </p>
                                                </td>
                                            </tr>
                                            <!-- <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="3">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#7daada;" > Total of Excesses</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  >  {{ $totalMiniPerExcessesOfPersonalMotor??"" }}% </p>
                                                </td>
                                                <td  colspan="5"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" >  P {{number_format((float)$totalMiniAmountExcessesOfPersonalMotor ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr> -->
                                            @php $noteMotor=\AlphaDirect\Models\PolicyCoverageNote::where('motor_id',$motor->id)->latest('id')->first();
                                            @endphp
                                            @if(isset($noteMotor))
                                            @php $motorNoteTabRendered = true; @endphp
                                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                                <td width="100%" colspan="11" style="border-right: 2px solid #2e77c3;">
                                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%" colspan="11" style="white-space:pre-wrap;color:black;font-size:11px;border-right: 2px solid #2e77c3;">{{$noteMotor->note}}</td>
                                            </tr>
                                            @endif
                                        @endif
                                        @if($coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  >  Basic Excess </p>
                                                </td>
                                                <td  colspan="2"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > {{$motor->own_damage_minimun_percent ?? 0}}% </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" >P {{number_format((float)$motor->own_damage_minimum_amount ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr>
                                            @if($motor->type_of_cover == "Comprehensive")
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="SO">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > Windscreen </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$motor->windscreen_minimun_percent ?? 0}} %</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$motor->windscreen_minimum_amount ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="SO">
                                                <td  colspan="6" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" > Loss of Keys </p>
                                                </td>
                                                <td   colspan="2" style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{$motor->loss_of_keys_minimun_percent ?? 0}} %</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$motor->loss_of_keys_minimum_amount ?? "", 2, '.', ',')}} </p>
                                                </td>
                                            </tr>
                                            @endif
                                            <!-- {{--<tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td  colspan="3">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#7daada;" > Total of Excesses</p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;"  >  {{ $totalMiniPerExcessesOfCommericialMotor??"" }} </p>
                                                </td>
                                                <td  colspan="3"  style="border: 1px solid #2e77c3!important;"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;" >  P {{$totalMiniAmountExcessesOfCommericialMotor ?? ""}} </p>
                                                </td>
                                            </tr>--}} -->

                                            @php $noteMotor=\AlphaDirect\Models\PolicyCoverageNote::where('motor_id',$motor->id)->latest('id')->first(); @endphp
                                            @if(isset($noteMotor))
                                            @php $motorNoteTabRendered = true; @endphp
                                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;" class="QO">
                                                <td width="100%" colspan="11" style="border-right: 2px solid #2e77c3;">
                                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="RO">
                                            <td width="50%" colspan="11" style="white-space:pre-wrap;color:black;font-size:11px;border-right: 2px solid #2e77c3;">{{$noteMotor->note}}</td>
                                            </tr>
                                            @endif
                                        @endif
                                        <!-- Excesses End -->
                                    @endif
                                    @endif
                                @endforeach
                                {{-- Coverage-level note (motor_id NULL/0 — the wizard's Notes box
                                     for Personal/Commercial Motor). Rendered once after the vehicle
                                     rows; per-vehicle notes above stay as-is. Without this block the
                                     motor sections only ever showed per-vehicle notes, so the
                                     coverage note never reached the Quote/Policy Doc. --}}
                                {{-- Duplicate-Notes-tab guard — same structural rule as
                                     v2-quote-sheet.blade.php. A note captured under a vehicle is
                                     reachable both per-vehicle (loop above, keyed on motor_id) and
                                     coverage-level, so the Notes tab rendered TWICE for one note.
                                     Exactly ONE Notes tab per motor section: if the vehicle loop
                                     already drew one, skip this; if it drew none (no per-vehicle
                                     note, or its vehicle hidden by the GRA-0122 gate), render it.
                                     No text comparison — works for any policy. --}}
                                @if(!($motorNoteTabRendered ?? false)
                                    && isset($coverages->note->note) && !empty($coverages->note->note))
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                        <td width="100%" colspan="11" style="border-right: 2px solid #2e77c3;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="50%" colspan="11" style="white-space:pre-wrap;color:black;font-size:11px;border-right: 2px solid #2e77c3;">{{$coverages->note->note}}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    @endif
                @endif
                {{-- @if($coverages->coverage->s_CoverageCode == "COMMERCIALMOTOR")
                    <div class="container">
                        <h3> ENDORSEMENTS</h3><br>
                        <b>REQUIREMENTS REGARDING ANTI THEFT DEVICES<b><br>
                        This policy is subject to the following provisions in regard to the vehicles insured in terms hereof. The Limits of Indemnity for vehicles referred to in these provisions are<br>
                        those stated in the Policy Schedule.		<br>
                        .1. All vehicles must be fitted with a approved or factory fitted alarm/ immobiliser. If not fitted then an additional first amount payable for theft of 10% of claim minimum<br>
                        P1500 shall apply over and above the existing amount payable by the Insured in terms of this policy for loss or damage caused by or resulting from theft or hijacking.<br>
                        2. Vehicles having a Limit of Indemnity from P500 000 and above (Vat inclusive) must be fitted with a factory fitted alarm / immobiliser plus an approved tracking device<br>
                        and the Insured must be a fully paid up subscriber of the tracking Company<br>

                        Note:
                        1) If a vehicle insured is stolen or hijacked and is then recovered as a result of the approved tracking device then the first amount payable by the Insured in terms of the<br>
                        policy shall be reduced to Nil. Provided that at the time of the theft or hi-jack, the Insured is a fully paid-up subscriber of the tracking Company.<br><br>

                        GREY IMPORTED VEHICLES
                        The cover provided by this policy will not apply to "Grey Imported Vehicles" being those vehicles imported into Botswana by Agents other than the original<br>
                        manufacturers or their authorised representatives, unless the status of such vehicles have been disclosed to Hollard Botswana and the company has been provided<br>
                        with a copy of the Purchase Invoice including Vat, duties and other tax in respect of such vehicles.<br>

                        <b>UNOBTAINABLE PARTS ENDORSEMENT</b><br>
                        It is agreed that in the event of loss or damage to the Vehicle and/or its permanent fittings the liability of the Company under Sub-Section 1 in respect of such new parts as<br>
                        may be required and which are unobtainable in Botswana or are obsolete in pattern shall be limited to the value of such parts at the time of the accident but not exceeding<br>
                        the price of such parts in the manufacturer's last issued catalogue or price list.<br>

                        <b>TABLE OF FIRST AMOUNT PAYABLE APPLICABLE TO SPECIFIED BASIS ONLY</b><br>
                        AMOUNT TO BE BORNE BY THE INSURED<br>
                        In respect of each and every occurrence giving rise to a claim under sub-section A and notwithstanding anything contained in this section to the contrary, the insured shall<br>
                        be responsible for the first amount payable as specified.<br>

                        <b>PRIVATE TYPE MOTOR CARS (DEFINITION (a):</b>
                        i) Claims for theft of car radios, tape decks and resulting body damage caused to the vehicle - The First PXX<br>
                        The amount stated hereunder to be borne by the Insured shall apply independently and shall be cumulative.<br>
                        ii) Where the driver of the vehicle at the time of the occurrence was:<br>
                        a) Aged 25 years and under The First PXX<br>
                        b) Has held a driving license for less than 2 years The First P500<br>
                        If more than one vehicle is insured by this section the above amounts shall apply as though a separate policy had been issued for each vehicle.<br>
                    </div><br>
                @endif --}}

                {{-- END PERSONALMOTOR --}}
                <!-- end sub_coverages -->

                <!-- start Miscellaneous Items -->
                @if($coverages->coverage->s_CoverageCode  != "COMMERCIALMOTOR" && $coverages->coverage->s_CoverageCode  != "MOTORTRADERSEXTERNAL" &&  $coverages->coverage->s_CoverageCode  != "MOTORTRADERSINTERNAL" && $coverages->coverage->s_CoverageCode  != "PERSONALMOTOR")

                  @if( $policyAction->transaction_type === 'ENDORSE'
                            && ( ( $policyAction->id == $coverages->riskAddress->action_id 
                            && $coverages->riskAddress->status == 1 ) ||
                            ( $policyAction->id == $coverages['action_id'] && $coverages['status'] == 1))  )
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; " class="spec_tbl greybg">
                    @else
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; " class="spec_tbl">
                        @endif
                    <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
                        @if(count($coverages->specifedItems ?? [])>0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#F0DBE0;" class="BO">
                            @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                            <td colspan="5">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> Miscellaneous Items</p>
                              </td>
                            @elseif ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                            <td colspan="8">
                                   <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> Miscellaneous Items</p>
                                </td>
                            @else
                                <td colspan="4">
                               <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Miscellaneous Items</p>
                                </td>
                            @endif
                            </tr>
                            @php
                            $sumInsuredTotal = 0;
                            $sumInsuredCalculated = 0;
                            @endphp
                            @foreach($coverages->specifedItems as $newSpecifyIndex => $specifed_items)
                             @if (
                                    // ENDORSEMENT + same action → show all
                                    ($policyAction->transaction_type === 'ENDORSE'
                                        && $policyAction->id == $specifed_items->action_id)
                                    ||
                                    // All other cases → show only non-deleted
                                    is_null($specifed_items->deleted_at)
                                )      
                             {{-- @if (empty($specifed_items['deleted_at'])) --}}
                             
                                @php
                                    if($coverages['status']!= 1  && $specifed_items->deleted_at==null ) { 
                                    $sumInsuredTotal = $sumInsuredTotal +  $specifed_items->sum_insured;
                                    $sumInsuredCalculated = $sumInsuredCalculated +  $specifed_items->calculated_value;
                                    }
                                @endphp
                               @if (

                                    $policyAction->transaction_type === 'ENDORSE'

                                    && $policyAction->id == $specifed_items->action_id

                                    && !is_null($specifed_items->deleted_at)

                                )  
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;" class="BOD greybg">

                                @else    

                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;" class="BOD">
                                    @endif
                                @if ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                    <td width="50%" colspan="4" class="33">
                                @else
                                    <td width="50%" colspan="2" class="33">
                                @endif
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> {{ optional($specifed_items->specifiedCoveragesItems)->specified_name ?? ($specifed_items->custom_name ?? '') }}</p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">

                                         <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null ) P 0.00 @else P {{number_format((float)$specifed_items->sum_insured ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                    @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                    @elseif ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                    @else
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                    @endif

                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;"> @if($coverages->status==1 || $specifed_items->deleted_at!=null ) P 0.00 @else P {{number_format((float)$specifed_items->calculated_value ?? "", 2, '.', ',')}} @endif</p>
                                    </td>
                                </tr>
                                  @endif  
                            @endforeach
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;" class="TO">
                            @if ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                            <td width="50%" colspan="4" class="77">
                            @else
                            <td width="50%" colspan="2" class="77">
                            @endif
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> Total Of Specifed Items</p>
                                </td>
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                    @if(!empty($sumInsuredTotal))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredTotal ?? "", 2, '.', ',')}}</p>
                                    @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                                @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                @elseif ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="3">
                                @else
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                @endif
                                    @if(!empty($sumInsuredCalculated))
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$sumInsuredCalculated ?? "", 2, '.', ',')}}</p>
                                     @else
                                     <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P 0.00 </p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <!-- End Miscellaneous Items -->
                        {{-- Definitions --}}
                        @if ($coverages->coverage->s_CoverageCode == "BUILDINGSCOMBINED" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS" || $coverages->coverage->s_CoverageCode =="HOUSEOWNERS" || $coverages->coverage->s_CoverageCode=="OFFICECONTENTS" || $coverages->coverage->s_CoverageCode == "BUSINESSINTERUPTION" || $coverages->coverage->s_CoverageCode == "BUSINESSINTERRUPTION" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS-CONTENTS")
                        @if(isset($coverages->coverage->s_CoverageDesc))
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;" class="OOO" >
                                        <td width="100%" colspan="4">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Definitions</p>
                                        </td>
                                    </tr>  
                                    {{-- @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages) --}}
                                        @if(isset($coverages->riskAddress->const_type))
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;" class="PPP">
                                                <td width="50%">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                      Buildings
                                                    </p>
                                                </td>
                                                <td width="50%"  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                        {{-- @if($all_sub_coverages->s_ScreenName == 'Buildings' || $all_sub_coverages->s_CoverageDesc == 'Buildings') --}}
                                                            @if($coverages->riskAddress->const_type)
                                                                @php
                                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value',$coverages->riskAddress->const_type)->first(['description']);
                                                                @endphp
                                                                {{ $look_up_non->description ?? ''}}
                                                            @endif
                                                        {{-- @else --}}
                                                            {{-- @if(isset($coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName) && $coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName !='') --}}
                                                                {{-- {{ $all_sub_coverages->s_CoverageDesc ?? '' }} --}}
                                                            {{-- @endif --}}
                                                        {{-- @endif --}}
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                        @if($coverages->coverage->s_CoverageCode =="HOUSEOWNERS"  || $coverages->coverage->s_CoverageCode == "HOUSEOWNER-BUILDINGS")
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    Rent
                                                    </p>
                                                </td>
                                                <td width="50%"  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                    @php
                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value','Rent')->first(['description']);
                                                    @endphp
                                                    {{ $look_up_non->description ?? ''}}

                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                        @if($all_sub_coverages->s_ScreenName == 'Legal liabilty' || $all_sub_coverages->s_ScreenName=='Legal Liability')
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    Legal liabilty
                                                    </p>
                                                </td>
                                                <td width="50%"  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                    @php
                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value','Legal Liability')->first(['description']);
                                                    @endphp
                                                    {{ $look_up_non->description ?? ''}}
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                    {{-- @endforeach --}}
                                @endif
                        @endif
                        {{-- Definitions --}}
                        @if ($coverages->coverage->s_CoverageCode == "HOUSEOWNER-BUILDINGS")
                        @if(isset($coverages->coverage->s_CoverageDesc))
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                        <td width="100%" colspan="4">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Definitions</p>
                                        </td>
                                    </tr>
                                    {{-- @foreach($coverages->all_sub_coverages as $newSubIndex => $all_sub_coverages) --}}
                                        @if(isset($coverages->riskAddress->const_type))
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                        Buildings
                                                    </p>
                                                </td>
                                                <td  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                        {{-- @if($all_sub_coverages->s_ScreenName == 'Buildings' || $all_sub_coverages->s_CoverageDesc == 'Buildings') --}}
                                                            @if($coverages->riskAddress->const_type)
                                                                @php
                                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value',$coverages->riskAddress->const_type)->first(['description']);
                                                                @endphp
                                                                {{ $look_up_non->description ?? ''}}
                                                            @endif
                                                        {{-- @else --}}
                                                            {{-- @if(isset($coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName) && $coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName !='') --}}
                                                                {{-- {{ $all_sub_coverages->s_CoverageDesc ?? '' }} --}}
                                                            {{-- @endif --}}
                                                        {{-- @endif --}}
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                        @if( $coverages->coverage->s_CoverageCode == "HOUSEOWNER-BUILDINGS")
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%"  >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    Rent
                                                    </p>
                                                </td>
                                                <td  style="border: 1px solid #2e77c3!important;" colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                    @php
                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value','Rent')->first(['description']);
                                                    @endphp
                                                    {{ $look_up_non->description ?? ''}}

                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                        @if($all_sub_coverages->s_ScreenName == 'Legal liabilty' || $all_sub_coverages->s_ScreenName=='Legal Liability')
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%" >
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    Legal liabilty
                                                    </p>
                                                </td>
                                                <td  style="border: 1px solid #2e77c3!important;"  colspan="3">
                                                    <p style="text-align: left; padding: 0px;color:black;">
                                                    @php
                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value','Legal Liability')->first(['description']);
                                                    @endphp
                                                    {{ $look_up_non->description ?? ''}}
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                    {{-- @endforeach --}}
                                @endif
                        @endif
                        {{-- Definitions --}}
                        @if ($coverages->coverage->s_CoverageCode == "FIRE")
                                @if(isset($coverages->coverage->s_CoverageDesc))
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                        <td width="100%" colspan="4">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Definitions</p>
                                        </td>
                                    </tr>
                                    @php
                                            // Extract unique rows based on desired keys
                                            $uniqueCoverages = $coverages->all_sub_coverages->unique(function ($item) {
                                                return $item->s_CoverageGroupName . $item->s_ScreenName . $item->s_CoverageDesc;
                                            });
                                        @endphp
                                    @foreach( $uniqueCoverages as $newSubIndex => $all_sub_coverages)
                                        @if($all_sub_coverages->s_CoverageGroupName == 'Description of cover')
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                <td width="50%"  colspan="2">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">

                                                        @if($newSubIndex == 0 && isset($coverages->coverageDetail[$newSubIndex]->ratefactor_value) && $coverages->coverageDetail[$newSubIndex]->ratefactor_value != 0 )
                                                        {{ $coverages->coverageDetail[$newSubIndex]->ratefactor_value??"" }}
                                                        @else
                                                        @php
                                                            $displayValue = $all_sub_coverages->s_ScreenName ?? "";
                                                            if (isset($coverages->coverageDetail[$newSubIndex]->coverage_value_string) && !empty($coverages->coverageDetail[$newSubIndex]->coverage_value_string)) {
                                                                $displayValue = $coverages->coverageDetail[$newSubIndex]->coverage_value_string;
                                                            }
                                                        @endphp
                                                        {{ $displayValue }}
                                                        @endif
                                                    </p>
                                                </td>
                                                <td width="50%"  style="border: 1px solid #2e77c3!important;"  colspan="2">
                                                    <p style="text-align: left; padding: 0px;color:black;">

                                                        @if($all_sub_coverages->s_ScreenName == 'Buildings' || $all_sub_coverages->s_CoverageDesc == 'Buildings')
                                                            @if($coverages->riskAddress->const_type)
                                                                @php
                                                                    $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value',$coverages->riskAddress->const_type)->first(['description']);
                                                                @endphp
                                                                {{ $look_up_non->description ?? ''}}
                                                            @endif
                                                        @else
                                                            {{-- @if(isset($coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName) && $coverages->coverageDetail[$newSubIndex]->coverage->s_ScreenName !='') --}}
                                                                {{ $all_sub_coverages->s_CoverageDesc ?? '' }}
                                                            {{-- @endif --}}
                                                        @endif
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @endif
                        @endif

                        <!-- {{-- @if ($coverages->coverage->s_CoverageCode == "HOUSEOWNER-BUILDINGS" )
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td width="100%" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;">Definitions</p>
                                </td>
                            </tr>
                            @foreach($coverages->all_sub_coverages as $newSubIndex => $sub_coverages)

                                @if($sub_coverages->s_CoverageGroupName == 'Description of cover')
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%"  colspan="2">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if($sub_coverages->s_ScreenName == "Sum Insured")
                                            @php
                                                $policyCoverageDetail = AlphaDirect\Models\PolicyCoverageDetail::where('coverage_id',$sub_coverages->id)
                                                                    ->where('policy_coverage_id',$coverages->id)
                                                                    ->orderBy('id', 'asc')
                                                                    ->first();

                                            @endphp
                                            {{$policyCoverageDetail->ratefactor_value ?? ""}}

                                            @else
                                                {{$sub_coverages->s_ScreenName}}
                                            @endif
                                        </p>
                                    </td>
                                    <td width="50%"  style="border: 1px solid #2e77c3!important;"  colspan="2">
                                        <p style="text-align: left; padding: 0px;color:black;">
                                            @if($sub_coverages->s_ScreenName == "Sum Insured")
                                                @if($coverages->riskAddress->const_type)
                                                    @php
                                                        $look_up_non = \AlphaDirect\Lookup::where('key','risk_construction_type')->where('value',$coverages->riskAddress->const_type)->first(['description']);
                                                    @endphp
                                                    {{ $look_up_non->description ?? ''}}
                                                @endif
                                                {{$policyCoverageDetail->ratefactor_value ?? ""}}
                                            @else
                                                {{ $sub_coverages->s_CoverageDesc ?? '' }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        @endif --}} -->
                        @if ($coverages->coverage->s_CoverageCode == "WORKERSCOMPENSATION" || $coverages->coverage->s_CoverageCode == "STATEDBENEFITS")
                            @php
                                // "Benefits for the circumstances" — V1 parity:
                                // policy_coverage_notes.benefits_note (migration 2026_06_03_000001).
                                // Fall back to the standard clause the edit screen pre-fills
                                // (STATED_BENEFITS_DEFAULT in StepCoverages.tsx) so the Doc
                                // always shows it, matching the edit form.
                                $benefitsDefault = "Death 6x Annual Earnings max P 200,000\n"
                                    . "Permanent Total Disablement 5x Annual Earnings Max P 250,000\n"
                                    . "Temporary Total Disablement 66.66% of weekly earnings up to 26 weeks\n"
                                    . "Medical Expenses P 75,000";
                                $benefitsText = ($coverages->note->benefits_note ?? null) ?: $benefitsDefault;
                            @endphp
                            @if(!empty($benefitsText))
                                <tr style="border: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                    <td width="100%" colspan="4"  style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Benefits for the circumstances</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%" colspan="4" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                            font-style:normal;
                                            font-size:10px;
                                            overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                            ">{{ $benefitsText }}
                                            </pre>
                                        </p>
                                    </td>
                                </tr>
                            @endif
                        @endif
                        <!-- start Section -->
                        @if(count($coverages->extension_with_type_extention ?? [])>0)
                                @php
                                $totalSumOfExtentionCoveragesValues = 0;
                                $totalSumOfExtentionCalculatedValues = 0;
                                $totalSumOfPerilsCoveragesValues = 0;
                                $totalSumOfPerilsCalculatedValues = 0;
                                @endphp
                        <!-- start Extention-->
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;" class="UO">
                                    @if($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                    <td width="100%" colspan="5">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Extentions</p>
                                    </td>
                                    @elseif($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                    <td width="100%" colspan="8">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Extentions</p>
                                    </td>
                                    @else
                                    <td width="100%" colspan="4">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Extentions</p>
                                    </td>
                                    @endif
                                </tr>

                                @if ($coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                        <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extention Name </p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Limit of Liability</p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt12">Premium</p>
                                        </td>
                                    </tr>
                                @elseif($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                        <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extention Name </p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Sum Insured</p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt13">Premium</p>
                                        </td>
                                    </tr>
                                    @elseif($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;" class="EO">
                                        <td  colspan="4" style="border: 2px solid #2e77c3!important;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> Extention Name</p>
                                        </td>
                                        <td colspan="1" style="border: 2px solid #2e77c3!important;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Sum Insured</p>
                                        </td>
                                        <td colspan="2" style="border: 2px solid #2e77c3!important;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Sum Amount</p>
                                        </td>
                                        <td colspan="1" style="border: 2px solid #2e77c3!important;">
                                            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;" class="mt1233">Premium</p>
                                        </td>
                                    </tr>
                                @else
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                        <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Extention Name </p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Sum Insured</p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt1234">Premium</p>
                                        </td>
                                    </tr>
                                @endif
                                @if($coverages->coverage->s_CoverageCode != "BUSINESSALLRISKS")
                                    {{-- start present --}}
                                    @foreach($coverages->extension_with_type_extention as $newIndex => $extention_items)
                                        @php
                                            if ($extention_items->type == 'Extention') {
                                                $totalSumOfExtentionCoveragesValues =  $totalSumOfExtentionCoveragesValues + $extention_items->extention_coverage_value;
                                                $totalSumOfExtentionCalculatedValues =  $totalSumOfExtentionCalculatedValues + $extention_items->extention_calculated_value;

                                                if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                    $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                                }else {
                                                    $tbCvgpcextentionlimits = null;
                                                }
                                            }else {
                                                $tbCvgpcextentionlimits = null;
                                            }
                                        @endphp

                                        @if (isset($extention_items->s_ScreenName) && $extention_items->s_ScreenName != null || isset($extention_items->extention_ratefactor_value) && $extention_items->extention_ratefactor_value != null )
                                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                                @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                                <td  colspan="2" class="fidelity">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;white-space:pre-line">
                                                        @if (isset($extention_items->extention_ratefactor_value) && $extention_items->extention_ratefactor_value != 0)
                                                        {{ $extention_items->extention_ratefactor_value  ?? ''}}
                                                        @else
                                                        {{$extention_items->s_ScreenName ?? ''}}
                                                        @endif
                                                    </p>
                                                </td>
                                                @else
                                                <td width="50%" colspan="2" class="88">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                        @if (isset($extention_items->extention_ratefactor_value) && $extention_items->extention_ratefactor_value != 0)
                                                        {{ $extention_items->extention_ratefactor_value  ?? ''}}
                                                        @else
                                                        {{$extention_items->s_ScreenName ?? ''}}
                                                        @endif
                                                    </p>
                                                </td>
                                                @endif

                                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                        @if (isset($tbCvgpcextentionlimits)&& $tbCvgpcextentionlimits!= null)
                                                            {{ $tbCvgpcextentionlimits->s_LimitScreenName ?? ''}}
                                                        @elseif (isset($extention_items->extention_text_value))
                                                            {{ $extention_items->extention_text_value  ?? ''}}
                                                        @else
                                                            @if (isset($extention_items->extention_coverage_value) && $extention_items->extention_coverage_value != 0.00000000)
                                                            P {{ number_format($extention_items->extention_coverage_value  ?? "", 2, '.', ',') }}
                                                            @endif
                                                        @endif
                                                    </p>
                                                </td>
                                                @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE" || $coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS" )
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                                @else
                                                <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                                @endif

                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                        @if (isset($extention_items->extention_calculated_value) && $extention_items->extention_calculated_value != 0.00000000)
                                                        P {{ number_format($extention_items->extention_calculated_value  ?? "", 2, '.', ',') }}
                                                        @endif
                                                    </p>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @else

                                @foreach($coverages->extension_with_type_extention as $newIndex => $extention_items)
                                    @php
                                        if ($extention_items->type == 'Extention') {
                                            $totalSumOfExtentionCoveragesValues =  $totalSumOfExtentionCoveragesValues + $extention_items->extention_coverage_value;
                                            $totalSumOfExtentionCalculatedValues =  $totalSumOfExtentionCalculatedValues + $extention_items->extention_calculated_value;

                                            if(isset($extention_items->extention_limit_id)&& $extention_items->extention_limit_id != null){
                                                $tbCvgpcextentionlimits = AlphaDirect\Models\TbCvgpcLimits::where('n_PCLimitId_PK',$extention_items->extention_limit_id)->first(['s_LimitScreenName']);
                                            }else {
                                                $tbCvgpcextentionlimits = null;
                                            }
                                        }else {
                                            $tbCvgpcextentionlimits = null;
                                        }
                                    @endphp

                                    @if (isset($extention_items->s_ScreenName) && $extention_items->s_ScreenName != null || isset($extention_items->extention_ratefactor_value) && $extention_items->extention_ratefactor_value != null )
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        @if($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                        <td  colspan="4">
                                            @else
                                        <td  colspan="1">
                                        @endif

                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    @if (isset($extention_items->extention_ratefactor_value) && $extention_items->extention_ratefactor_value != 0)
                                                    {{ $extention_items->extention_ratefactor_value  ?? ''}}
                                                    @else
                                                    {{$extention_items->s_ScreenName ?? ''}}
                                                    @endif
                                                </p>
                                            </td>

                                            @if($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                                            <td  style="border: 1px solid #2e77c3!important;" colspan="1">
                                            @else
                                            <td  style="border: 1px solid #2e77c3!important;" colspan="1">
                                            @endif
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    @if (isset($tbCvgpcextentionlimits)&& $tbCvgpcextentionlimits!= null)
                                                        {{ $tbCvgpcextentionlimits->s_LimitScreenName ?? ''}}
                                                    @elseif (isset($extention_items->extention_text_value))
                                                        {{ $extention_items->extention_text_value  ?? ''}}
                                                    @else
                                                        @if (isset($extention_items->extention_coverage_value) && $extention_items->extention_coverage_value != 0.00000000)
                                                        P {{ number_format($extention_items->extention_coverage_value  ?? "", 2, '.', ',') }}
                                                        @endif
                                                    @endif
                                                </p>
                                            </td>

                                            <td  style="border: 1px solid #2e77c3!important;" colspan="2">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    @if (isset($extention_items->extention_sum_insured) && $extention_items->extention_sum_insured != 0.00000000)
                                                    P {{ number_format($extention_items->extention_sum_insured  ?? "", 2, '.', ',') }}
                                                    @endif
                                                </p>
                                            </td>


                                            @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                            @else
                                            <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                            @endif
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                    @if (isset($extention_items->extention_calculated_value) && $extention_items->extention_calculated_value != 0.00000000)
                                                    P {{ number_format($extention_items->extention_calculated_value  ?? "", 2, '.', ',') }}
                                                    @endif
                                                </p>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @endif


                                {{-- end present ext --}}
                                @if ($coverages->coverage->s_CoverageCode != "BUSINESSALLRISKS")
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                                <td width="50%" colspan="2" class="19">
                                                    <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;font-size:10px;"> Total Of Extention</p>
                                                </td>
                                            @else
                                            <td width="50%" colspan="2" class="3p">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;font-size:10px;"> Total Of Extention</p>
                                            </td>
                                        @endif

                                        <td width="25%"  style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="1">
                                            @if(!empty($totalSumOfExtentionCoveragesValues))
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalSumOfExtentionCoveragesValues ?? "", 2, '.', ',')}}</p>
                                            @endif
                                        </td>
                                        @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="2">
                                        @else
                                        <td width="25%"  style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="1">
                                        @endif

                                            @if(!empty($totalSumOfExtentionCalculatedValues))
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalSumOfExtentionCalculatedValues ?? "", 2, '.', ',')}}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @elseif ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")

                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                            <td  colspan="4">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;font-size:10px;"> Total Of Extention</p>
                                            </td>
                                            <td style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="1">
                                                @if(!empty($totalSumOfExtentionCoveragesValues))
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalSumOfExtentionCoveragesValues ?? "", 2, '.', ',')}}</p>
                                                @endif
                                            </td>
                                            <td style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="2">
                                            </td>
                                            <td  style="border: 1px solid #2e77c3!important;font-size:10px;" colspan="1">
                                                @if(!empty($totalSumOfExtentionCalculatedValues))
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:#2e77c3;"> P {{number_format((float)$totalSumOfExtentionCalculatedValues ?? "", 2, '.', ',')}}</p>
                                                @endif
                                            </td>
                                    </tr>
                                @endif
                            <!-- End Extention-->
                            @endif
                            @if ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")

                                    <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                        <td  width="50%" class="MJ" colspan="4" style="border-right: 2px solid #2e77c3;">
                                            <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Excesses</p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                            <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Min %</p>
                                        </td>
                                        <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                            <p style="text-align: left;margin: 0px; padding: 0px;font-size: 10px;color:black;">Minimum Amount</p>
                                        </td>
                                    </tr>
                                    @foreach($PolicyBusiExcessesData as $pBusiExData)
                                        <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="50%"  class="1" colspan="4">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pBusiExData['excesses'] }}</p>
                                            </td>
                                            <td width="25%"   style="border: 1px solid #2e77c3!important;" colspan="1">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pBusiExData['min_percent'] }} %</p>
                                            </td>
                                            <td  width="25%" style="border: 1px solid #2e77c3!important;" colspan="3">
                                                <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pBusiExData['min_amt'] ?? "", 2, '.', ',')}} </p>
                                            </td>
                                        </tr>
                                    @endforeach
                            @endif


                        <!-- End Section -->

                        <!-- Memoranda Code -->
                        @if(count($coverages->extension_with_type_memoranda ?? []) > 0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td width="100%" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> Memoranda</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                </td>
                                <td width="25%" style="border: 1px solid #2e77c3!important;">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Status</p>
                                </td>
                                <td width="25%" style="border: 1px solid #2e77c3!important;">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Premium</p>
                                </td>
                            </tr>

                            @foreach($coverages->extension_with_type_memoranda as $newIndex => $extention_items)
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="2" class="99">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        {{ $extention_items->s_CoverageName ?? '' }}
                                        </p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(isset($extention_items->extention_type))
                                                @if($extention_items->extention_type == 'DROPDOWN' || $extention_items->extention_type == 'RADIO')
                                                    {{ $extention_items->extentionCvgpclimits->s_LimitScreenName ?? "" }}
                                                @elseif($extention_items->extention_type == 'NOEDIT')
                                                    {{ $extention_items->extention_text_value ?? "" }}
                                                @elseif($extention_items->extention_type == 'NUMBER')
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @else
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @endif
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%" class="99">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                         P {{number_format((float)$extention_items->extention_calculated_value ?? "", 2, '.', ',')}}</p></td>
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                        <!-- First Amount Payable Code -->
                        @if(count($coverages->extension_with_type_firstamount ?? []) > 0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td width="100%" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> First Amount Payable</p>
                                </td>
                            </tr>

                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td width="25%"  colspan="1">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description</p>
                                </td>
                                <td width="25%"  colspan="1">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Minimum %</p>
                                </td>
                                <td width="25%"  colspan="1">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Minimum Amount</p>
                                </td>
                                 <td width="25%"  colspan="1">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Premium</p>
                                </td>
                            </tr>

                            @foreach($coverages->extension_with_type_firstamount as $newIndex => $extention_items)
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="25%" colspan="1" class="55">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(isset($extention_items->extention_type))
                                                @if($extention_items->extention_type == 'DROPDOWN' || $extention_items->extention_type == 'RADIO')
                                                    {{ $extention_items->extentionCvgpclimits->s_LimitScreenName ?? "" }}
                                                @elseif($extention_items->extention_type == 'NOEDIT')
                                                    {{ $extention_items->extention_text_value ?? "" }}
                                                @elseif($extention_items->extention_type == 'NUMBER')
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @else
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @endif
                                            @endif
                                        </p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                         {{$extention_items->extention_excess_min_value ?? ''}}
                                        </p>
                                    </td>
                                    <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                       
                                         P {{number_format((float)$extention_items->extention_excess_max_value ?? "", 2, '.', ',')}}</p></td>
                                        </p>
                                    </td>
                                      <td width="25%"  style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                     P {{number_format((float)$extention_items->extention_calculated_value ?? "", 2, '.', ',')}}</p></td>
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                        <!-- Excess Code -->
                        {{-- @if ($coverages->coverage->s_CoverageCode != "COMPUTEREQUIPMENT") --}}
                            @if(count($coverages->extension_with_type_excess ?? []) > 0)

                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                    <td width="100%" colspan="4">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Excess</p>
                                    </td>
                                </tr>

                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                    <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Excess Name </p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Min %</p>
                                    </td>
                                    <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
                                        <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Minimum Amount</p>
                                    </td>
                                </tr>
                                @foreach($coverages->extension_with_type_excess as $newIndex => $extention_items)
                                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                        <td width="50%" colspan="2" style="border: 1px solid #2e77c3!important;">
                                            @php
                                                // Excess name can come from a free-text custom name, a
                                                // dropdown/radio limit (e.g. Electronic Equipment), NOEDIT/
                                                // NUMBER text, or the master screen name — fall back through
                                                // all so the name shows for every coverage's excess.
                                                $excessName = ($extention_items->custom_name ?? null)
                                                    ?: (optional($extention_items->extentionCvgpclimits)->s_LimitScreenName
                                                    ?: (($extention_items->extention_text_value ?? null)
                                                    ?: (($extention_items->extention_coverage_value ?? null)
                                                    ?: (($extention_items->s_ScreenName ?? null)
                                                    ?: ($extention_items->s_CoverageName ?? '')))));
                                            @endphp
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $excessName }}</p>
                                        </td>
                                        <td width="25%" colspan="1" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                {{ $extention_items->extention_excess_min_value ?? "" }}
                                            </p>
                                        </td>
                                        <td width="25%" colspan="1" style="border: 1px solid #2e77c3!important;">
                                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                                P {{ number_format((float)$extention_items->extention_excess_max_value ?? "", 2, '.', ',') }}
                                            </p>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        {{-- @endif --}}
                        <!-- Burglar alarm warranty -->
                        @if(count($coverages->extension_with_type_burglaralarmwarranty ?? []) > 0)

                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td width="100%" colspan="4">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;"> Burglar alarm warranty</p>
                                </td>
                            </tr>

                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
                                <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description </p>
                                </td>
                                <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
                                    <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Status</p>
                                </td>
                            </tr>

                            @foreach($coverages->extension_with_type_burglaralarmwarranty as $newIndex => $extention_items)

                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="2" class="100">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        {{ $extention_items->s_CoverageName ?? '' }}
                                        </p>
                                    </td>
                                    <td width="50%"  style="border: 1px solid #2e77c3!important;" colspan="2">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            @if(isset($extention_items->extention_type))
                                                @if($extention_items->extention_type == 'DROPDOWN' || $extention_items->extention_type == 'RADIO')
                                                    {{ $extention_items->extentionCvgpclimits->s_LimitScreenName ?? "" }}
                                                @elseif($extention_items->extention_type == 'NOEDIT')
                                                    {{ $extention_items->extention_text_value ?? "" }}
                                                @elseif($extention_items->extention_type == 'NUMBER')
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @else
                                                    {{ $extention_items->extention_coverage_value ?? "" }}
                                                @endif
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                        <!-- End Memoranda and Warranties -->

                        @if ($coverages->coverage->s_CoverageCode == "MOTORCOMPREHESIVE" || $coverages->coverage->s_CoverageCode == "MOTORTHIRDPARTYFIREANDTHE" || $coverages->coverage->s_CoverageCode == "MOTORTHIRDPARTYONLY")
                            @if(isset($coverages->note->endorsements))
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="background-color: #e5f4e3;font-size: 10px;text-align:center" colspan="4">
                                    <p style="color:black;margin: 0px;">
                                        ENDORSEMENTS
                                    </p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border-right: 2px solid #2e77c3;" colspan="4">
                                    <p style="margin: 0px;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important;font-weight: 500;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                        ">{{$coverages->note->endorsements}}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif
                        @endif

                        @if ($coverages->coverage->s_CoverageCode == "MONEY")
                            @if(isset($coverages->note->memoranda_warranty))
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                    <p style="color:black;margin: 0px;">
                                    Coverage Memoranda Warranty
                                    </p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td style="border-right: 2px solid #2e77c3;" width="50%" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;
                                        ">{{$coverages->note->memoranda_warranty}}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif

                            @if(isset($coverages->note->cash_warranty))
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                    <p style="color:black;margin: 0px;">
                                    Coverage Cash Warranty
                                    </p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="4" style="border-right: 2px solid #2e77c3;">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                        ">{{$coverages->note->cash_warranty}}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif
                        @endif

                        {{-- burglar_warranty --}}
                        @if ($coverages->coverage->s_CoverageCode == "HOUSEHOLDERS-CONTENTS" || $coverages->coverage->s_CoverageCode == "OFFICECONTENTS" || $coverages->coverage->s_CoverageCode == "MONEY" || $coverages->coverage->s_CoverageCode == "THEFT" || $coverages->coverage->s_CoverageCode == "HOUSEHOLDERS" || $coverages->coverage->s_CoverageCode == "ELECTRONICEQUIPMENT" )
                            @if(isset($coverages->note->burglar_warranty))
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td style="background-color: #e5f4e3;text-align:center" colspan="4">
                                    <p style="color:black;margin: 0px;">
                                        Burglar Alarm Warranty Note
                                    </p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td width="50%" colspan="4">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px;  white-space: pre-line;
                                        ">{{$coverages->note->burglar_warranty}}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @endif
                        @endif
                        <!-- Notes -->
                        @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                        @if(count($policyExcessesData ?? []) > 0)
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;border-right: 2px solid #2e77c3;">
                                <td  colspan="2" style="border: 2px solid #2e77c3!important;">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Excesses</p>
                                </td>
                                <td  colspan="1" style="border: 2px solid #2e77c3!important;">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Min %</p>
                                </td>
                                <td   colspan="2" style="border: 2px solid #2e77c3!important;">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Minimum Amount</p>
                                </td>
                            </tr>
                            @foreach($policyExcessesData as $pExData)
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;border-right: 1px solid #2e77c3;">
                                    <td  colspan="2" style="border: 2px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pExData['excesses'] }}</p>
                                    </td>
                                    <td   colspan="1" style="border: 2px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">{{ $pExData['min_percent'] }} %</p>
                                    </td>
                                    <td   colspan="2" style="border: 2px solid #2e77c3!important;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$pExData['min_amt'] ?? "", 2, '.', ',')}}</p>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        @endif
                            @if ($coverages->coverage->s_CoverageCode == "PUBLICLIABILITY")
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                            <td style="background-color: #e5f4e3;text-align:left" colspan="4">
                            <p style="color:black;margin: 0px;">Warranty</p>
                            </td>
                            </tr>

                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                            <td style="background-color: #e5f4e3;text-align:left" colspan="4">
                            <p style="color:black;margin: 0px;">
                                Warranted that the insured will display the following message at every entrance to their premises
                            </p>
                            </td>
                            </tr>

                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                            <td style="background-color: #e5f4e3;text-align:left" colspan="4">
                            <p style="color:black;margin: 0px;">EXCLUSION OF LIABILITY</p>
                            </td>
                            </tr>

                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                            <td width="50%" colspan="4">
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                    font-style:normal;
                                    font-size:10px;
                                    overflow-y:hidden; border:0.5px solid lightgray; padding:5px;  white-space: pre-line;">
                            {{ $exclusionText }}
                                </pre>
                            </p>
                            </td>
                            </tr>

                        @endif
                        @if(isset($coverages->note->note))
                            @if ($coverages->coverage->s_CoverageCode == "FIDELITYGUARANTEE")
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                    <td width="100%" colspan="5" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="5" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                            font-style:normal;
                                            font-size:10px;
                                            overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                            ">{{$coverages->note->note}}
                                            </pre>
                                        </p>
                                    </td>
                                </tr>
                            @elseif ($coverages->coverage->s_CoverageCode == "BUSINESSALLRISKS")
                            <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                <td colspan="8" style="border-right: 2px solid #2e77c3;">
                                    <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                <td  colspan="8" style="border-right: 2px solid #2e77c3;">
                                    <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                        <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                        font-style:normal;
                                        font-size:10px;
                                        overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                        ">{{$coverages->note->note}}
                                        </pre>
                                    </p>
                                </td>
                            </tr>
                            @else
                                <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                                    <td width="100%" colspan="4" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: center;margin: 0px; padding: 0px;font-size: 10px;color:black;">Notes</p>
                                    </td>
                                </tr>
                                <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
                                    <td width="50%" colspan="4" style="border-right: 2px solid #2e77c3;">
                                        <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
                                            <pre style="font-family:'Montserrat','Arial', sans-serif !important; font-weight: 500;color:black;
                                            font-style:normal;
                                            font-size:10px;
                                            overflow-y:hidden; border:0.5px solid lightgray; padding:5px; white-space:pre-line;;
                                            ">{{$coverages->note->note}}
                                            </pre>
                                        </p>
                                    </td>
                                </tr>
                            @endif

                        @endif
                    </tbody>
                </table>
                @endif
            @endforeach
            <!-- end -->

            <!-- start -->
            @php
                $regionVat = AlphaDirect\Region::where('id', 7)->first('vat')?->vat;
                if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW' ){
                $premiumExcludingVAT = ($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal +$finalMotorPersonalSum ) / (1 + ($regionVat/100));
                }else{
                $premiumExcludingVAT =    ($totalProRata + $finalMotorPersonaProrata +$finalMotorSumInternalProRata+ $finalMotorexternalProRata+ $finalMotorSumProRata +$totalProRataPremiumVatFreqCancel + $finalMotorSumCancel + $finalMotorexternalSumCancel + $finalMotorSumInternalCancel + $finalMotorPersonalSumCancel) / (1 + ($regionVat/100));
                }
                if(($policy->premium_freq)==1){
                    $premiumExcludingVAT = ($premiumExcludingVAT/1.08);
                }
                //echo 'Exclude'.$premiumExcludingVAT;
                $vat = $premiumExcludingVAT * ($regionVat/100);
               // echo 'VAT'.$vat;
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
                            @if(($policy->premium_freq)!= null)
                            @if($policy->premium_freq==1 && ($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE'  || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW'))
                                <p style="text-align: center; margin: 0px; padding: 0px;">Monthly Premium</p>
                                @elseif($policy->premium_freq==2)
                                <p style="text-align: center; margin: 0px; padding: 0px;">3 Installments Premium</p>
                                @elseif($policy->premium_freq==3 && ($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW' ))
                                <p style="text-align: center; margin: 0px; padding: 0px;">Annual Premium</p>
                                @elseif($policy->premium_freq==4)
                                <p style="text-align: center; margin: 0px; padding: 0px;">Semiannual Premium</p>
                                @elseif($policy->premium_freq==5)
                                <p style="text-align: center; margin: 0px; padding: 0px;">Quarterly Premium</p>
                                @elseif($policyAction->transaction_type!='NEWBUSINESS')
                                @if(($totalProRata + $finalMotorPersonaProrata +$finalMotorSumInternalProRata+ $finalMotorexternalProRata+ $finalMotorSumProRata) > 0)
                                <p style="text-align: center;margin: 0px; padding: 0px;color:#2e77c3;">Additional Premium</p>
                                @else
                                <p style="text-align: center;margin: 0px; padding: 0px;color:#CC0000;">Refund Premium</p>
                                @endif
                                @endif
                            @endif
                        </td>
                    </tr>
                    <tr style="font-size:10px;border-top: 1px solid #2e77c3!important;">
                        <td width="40%" >
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size:10px;">Final Premium Excluding VAT</p>
                        </td>
                        <td width="20%" >
                            <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >
                            <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >
                        @php
                        $vat_month=0;
                        //$vat_month = ($vat + $vatServiceCharge);
                        $vat_month = $vat;
                        //$vat_month = ($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal + $finalMotorPersonalSum ) * ($regionVat/100);
                        @endphp
                            <!-- <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P SONALI {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}</p> -->
                            <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}</p>
                            <!-- <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{ number_format((float)($totalProRataPremiumVatFreq + $finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal +$finalMotorPersonalSum)  - $vat_month, 2, '.', ',') }} </p> -->
                        </td>
                    </tr>
                    <tr style="font-size:10px;border-top: 1px solid #2e77c3!important;">
                        <td width="40%" >
                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;"> 14% VAT</p>
                      </td>
                        <td wi.dth="20%" >
                            <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >
                            <p style="text-align: center; margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >

                        @if(!empty($totalProRataPremiumVatFreq))
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @elseif(!empty($finalMotorSum))
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @elseif(!empty($finalMotorexternalSum))
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @elseif(!empty($finalMotorSumInternal))
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @elseif(!empty($finalMotorPersonalSum))
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @elseif(isset($vat_month) && $vat_month > 0)
                        <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{ number_format((float)$vat_month ?? "", 2, '.', ',') }} </p>
                        @endif
                            <!-- <p style="text-align: center; margin: 0px; padding: 0px;color:black;">P {{number_format((float)$vat ?? "", 2, '.', ',')}}</p> -->
                        </td>
                    </tr>
                    @if(($policy->premium_freq)==1)
                    <tr  style="font-size:10px;border-top: 1px solid #2e77c3!important;">
                        <td width="40%">
                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;">8% Service Charge On Monthly Payment</p>
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
                    <tr style="font-size:10px;border-top: 1px solid #2e77c3!important;">
                        <td width="40%" >
                            <p style="text-align: left; margin: 0px; padding: 0px;color:black;font-size:10px;">14% VAT On Service Charge</p>
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
                    <tr style="font-size:10px;border-top: 1px solid #2e77c3!important;">
                        <td width="40%" >
                            <p style="text-align: left;margin: 0px; padding: 0px;color:black;font-size:10px;">Final Premium including VAT</p>
                        </td>
                        <td width="20%" >
                            <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >
                        <p style="text-align: center;margin: 0px; padding: 0px;color:black;"></p>
                        </td>
                        <td width="20%" >
                            <!-- <p style="text-align: center;margin: 0px; padding: 0px;color:black;">P {{number_format((float)$policyAction->premium ?? "", 2, '.', ',')}}</p> -->
                            <p style="text-align: center;margin: 0px; padding: 0px;color:black;">
                            @if($policyAction->transaction_type=='NEWBUSINESS' || $policyAction->transaction_type=='REINSTATE' || $policyAction->transaction_type=='REISSUE' || $policyAction->transaction_type=='ANNIVERSARY-RENEW' || $policyAction->transaction_type=='RENEW' )
                            P {{number_format((float)($totalProRataPremiumVatFreq  +$finalMotorSum + $finalMotorexternalSum + $finalMotorSumInternal +$finalMotorPersonalSum) ?? "", 2, '.', ',')}}
                            @elseif($policyAction->transaction_type=='ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL')
                            P -{{ number_format((float)($totalProRata + $finalMotorPersonaProrata +$finalMotorSumInternalProRata+ $finalMotorexternalProRata+ $finalMotorSumProRata+ +$totalProRataPremiumVatFreqCancel + $finalMotorSumCancel + $finalMotorexternalSumCancel + $finalMotorSumInternalCancel + $finalMotorPersonalSumCancel), 2, '.', ',') }}
                            @else
                            P {{ number_format((float)($totalProRata + $finalMotorPersonaProrata +$finalMotorSumInternalProRata+ $finalMotorexternalProRata+ $finalMotorSumProRata+ +$totalProRataPremiumVatFreqCancel + $finalMotorSumCancel + $finalMotorexternalSumCancel + $finalMotorSumInternalCancel + $finalMotorPersonalSumCancel), 2, '.', ',') }}

                            @endif
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table >
            <!-- end -->
        </div>
    @endif
    <!-- <table style="border='0'" width="100%" cellpadding="0" cellspacing="0">
   <tbody style="border: 2px solid #2e77c3!important;color: #2e77c3!important;">
      <tr style="border-top: 2px solid #2e77c3!important;font-size:10px;background-color:#ecd5c2;">
         <td width="100%" colspan="4" class="pl1">
            <p style="text-align: center;margin: 0px; padding: 0px;font-size: 12px;color:black;"> Personal Accident</p>
         </td>
      </tr>
      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#C6EED8;">
         <td width="50%" style="border: 1px solid #2e77c3!important;" colspan="2">
            <p style="text-align: left;margin: 0px; padding: 0px;color: black!important;"> Description</p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
            <p style="text-align: left;margin: 0px; padding: 0px;color:black;">Limit</p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">
            <p style="text-align: left;margin: 0px; padding: 0px;color:black;" class="mt88">Premium</p>
         </td>
      </tr>
      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Sharon Boxshall Smith,
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1"></td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1"></td>
      </tr>
      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Death Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">2 400 000.00 </td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">		2 400.00
         </td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Permanent Disablement Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">2 400 000.00 </td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">		2 400.00
         </td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Temporary Total Disablement
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Medical Expenses Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
      </tr>
       <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
       <td  colspan="4" class="KM">
        </tr>
      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Angus Boxshall Smith
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1"></td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1"></td>
      </tr>
      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Death Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">2 400 000.00 </td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">		2 400.00
         </td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Permanent Disablement Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">2 400 000.00 </td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">		2 400.00
         </td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Temporary Total Disablement
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
      </tr>

      <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;">
         <td width="50%" colspan="2" class="KM">
            <p style="text-align: left; margin: 0px; padding: 0px;color:black;">
               Medical Expenses Benefits
            </p>
         </td>
         <td width="25%" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
         <td width="25%" class="descpre1" style="border: 1px solid #2e77c3!important;" colspan="1">0.00</td>
      </tr>

   </tbody>
</table> -->
    <!-- end - Coverage Details -->
    <div>
</div>

<!-- <p style="font-size:10px;">DECLARATION</p>
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
</p> ---->


    <!-- <p style="text-align: justify; font-size: 11px;line-height:1.2; margin:0px; padding:0px;"></p> -->

</body>
</html>

