<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
 
<head>
    <title>@if($invoice->invoice_amount < 0)Credit Note@else Tax Invoice @endif</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="license" href="https://www.opensource.org/licenses/mit-license/"> --}}
 
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
            line-height: 1 !important;
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
            line-height: 1 !important;
            /* border: 1px solid #000000; */
        }
 
        td,
        th {
            padding: 5px 5px;
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
                line-height: 1 !important;
                /* border: 1px solid #ddd !important; */
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

        .allign {
            text-align: right;
        }

    </style>
 
    {{-- <style>
        @import url('https://fonts.cdnfonts.com/css/montserrat');
        .allign {
            text-align: right;
        }
 
        body {
            color: black;
            -webkit-print-color-adjust: economy;
            -webkit-print-color-adjust: exact;
            -webkit-print-color-adjust: inherit;
            -webkit-print-color-adjust: initial;
            -webkit-print-color-adjust: unset;
        }
 
        @media print {
            *,
             :after,
             :before {
                color: #000 !important;
                background: 0 0 !important;
            }
            /* body {
                font-size: 14px !important;
                color: black !important;
                font-weight: normal !important;
                font-family: 'Poppins'!important;
                line-height: 0.5 !important;
                -webkit-print-color-adjust: economy;
                -webkit-print-color-adjust: exact;
                -webkit-print-color-adjust: inherit;
                -webkit-print-color-adjust: initial;
                -webkit-print-color-adjust: unset;
                 height: 80%;
            } */
 
            body {
                font-family: 'Montserrat','Arial', 'sans-serif' !important;
                font-weight: 500;
                font-size: 12px;
            }
            table tr td {
                vertical-align: top;
            }
            table,
            table tr {
                page-break-after: auto;
            }
            img {
                max-width: 100% !important;
            }
            p {
                padding: 0px;
            }
        }
    </style> --}}
</head>

<header>
    <div>
        <!-- Wrapper -->
<div style="width:100%;">

    <!-- Logo Row -->
    <!-- Wrapper -->
<div style="width:100%;">

    <!-- Logo -->
    <!-- Wrapper -->
<div style="width:100%;">

    <!-- Logo -->
    <div style="width:100%; margin-bottom:12px;">
        <img
            src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png"
            style="width:20%;"
        >
    </div>

    <!-- Two Column Row -->
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <!-- Left Column -->
            <td width="65%" style="vertical-align: top;">
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Alpha Direct Insurance Co. (Pty) Ltd.</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Office: Floor 2, Bar 2, Botswana Innovation Hub,</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Icon Building, Plot 69184 Block 8 Industrial</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Postal Address: P.O. Box 26ADC,</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Gaborone, Botswana</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">Phone +267 392 8264 | Fax +267 393 8265</div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">
                    Email Address: info@alphadirect.co.bw
                </div>
                <div style="font-size:11px; color:black; margin-bottom:3px; line-height:1.45;">
                    Website: <a href="http://www.alphadirect.co.bw" style="color:#2e77c3;">
                        www.alphadirect.co.bw
                    </a>
                </div>
            </td>

            <!-- Right Column -->
            <td width="35%" style="vertical-align: top; text-align: left;">
                <!-- Header Box -->
                <div style="font-weight:600; text-align:left; margin-bottom:6px; color: #2e77c3!important; font-size: 14px;">
                    @if($invoice->invoice_amount < 0)
                        Credit Note
                    @else
                        Tax Invoice ("Inv")
                    @endif
                </div>

                <!-- Row -->
                <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size:11px; line-height:1.55;">
                    <tr>
                        <td style="width:75px; font-weight:600; padding-bottom:2px;">Inv No</td>
                        <td style="width:10px; padding-bottom:2px;">:</td>
                        <td style="padding-bottom:2px;">{!! $invoice->invoice_no !!}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; padding-bottom:2px;">Inv Date</td>
                        <td style="padding-bottom:2px;">:</td>
                        <td style="padding-bottom:2px;">
                            {{ \Carbon\Carbon::createFromFormat('d/m/Y', $invoiceStartDate)->format('d F Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">VAT No</td>
                        <td>:</td>
                        <td>BW00000123907</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>


</header>
<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">

<div style="margin-top:2px; width:100%;">

    <!-- Two Column Row -->
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-top:10px;">
        <tr>
            <!-- LEFT : Bill To -->
            <td width="65%" style="vertical-align: top; padding-right:20px;">
                <!-- Header -->
                <div style="font-weight:600; text-align:left; margin-bottom:6px; color: #2e77c3!important; font-size: 14px;">
                    Bill to :
                </div>

                <div style="line-height:1.55; font-size:11px;">
                    <div style="font-weight:600;">
                        @if($customerProfile->entity_type == 'Organisation')
                            {!! $company->name ?? "" !!}
                        @else
                            {!! $customer->firstName !!} {!! $customer->lastName !!}
                        @endif
                    </div>

                    <div>{!! $risk_address->address_name ?? "" !!}</div>
                    <div>{!! $getCity->name ?? "" !!}</div>

                    <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size:11px;">
                        <tr>
                            <td style="width:60px;">VAT No</td>
                            <td style="width:10px;">:</td>
                            <td>{!! $company->VAT_registration_number ?? 'NOT AVAILABLE' !!}</td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- RIGHT : Policy Details -->
            <td width="35%" style="vertical-align: top;">
                <!-- Header -->
                <div style="font-weight:600; text-align:left; margin-bottom:6px; color: #2e77c3!important; font-size: 14px;">
                    Policy Details
                </div>

                <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size:11px; line-height:1.55;">
                    <tr>
                        <td style="width:100px; font-weight:600;">Policy Type</td>
                        <td style="width:10px;">:</td>
                        <td>
                            @if ($policy->product_id == 7)
                                Commercial All Risk
                            @elseif ($policy->product_id == 8)
                                Domestic All Risk
                            @elseif ($policy->product_id == 16)
                                Commercial Engineering Product 
                            @elseif ($policy->product_id == 17)
                                Commercial Specialist Product
                            @elseif ($policy->product_id == 18)
                                Domestic Engineering Product 
                            @elseif ($policy->product_id == 19)
                                Domestic Specialist Product
                            @endif
                        </td>
                    </tr>

                    @if(isset($policyAction))
                    <tr>
                        <td style="font-weight:600;">Cover period</td>
                        <td>:</td>
                        <td>
                            {{ \Carbon\Carbon::parse($policyAction->effective_from)->format('d M Y') }}
                            to
                            {{ \Carbon\Carbon::parse($policyAction->effective_to)->format('d M Y') }}
                        </td>
                    </tr>
                    @endif
                    {{-- commnted on 12-02-26 coz manually created invoice was giving issue
                    <tr>
                        <td style="font-weight:600;">Cover period</td>
                        <td>:</td>
                        <td>
                            {!! \Carbon\Carbon::parse($policyAction->effective_from)->format('d M Y') !!}
                            to
                            {!! \Carbon\Carbon::parse($policyAction->effective_to)->format('d M Y') !!}
                        </td>
                    </tr> --}}
                    <tr>
                        <td style="font-weight:600;">Policy Number</td>
                        <td>:</td>
                        <td>{!! $policy->policyNumber !!}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Converge</td>
                        <td>:</td>
                        <td>Quarterly</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>


<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<div>
   
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
        <thead style="border-bottom: 2px solid #2e77c3!important; color: #2e77c3!important;">
            <tr>
                <td width="50%">
                    <p style="text-align: left; color: #2e77c3!important; font-size: 13px; margin: 0px; font-weight: 600;">Description of the Invoice</p>
                </td>
                <td width="50%" class="allign">
                    <p style="text-align: right; color: #2e77c3!important; font-size: 13px; margin: 0px; font-weight: 600;">Amount (P)</p>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td width="100%" colspan="2" style="border-bottom: 0.5px solid #73c9ed; font-size: 11px; padding: 6px 0;">
                    @if($productId == 7)
                        Commercial
                    @elseif($productId == 8)
                        Domestic
                    @endif
                    Insurance Cover for Insured Risks and Perils
                </td>
            </tr>
        </tbody>
    </table>

   <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="50%" style="border-bottom: 0.5px solid #73c9ed; font-size: 11px;">
                    <p style="margin: 0px; padding: 0px;">{{ $note }}</p>
                </td>
                 <td width="50%" class="allign" style="border-bottom: 0.5px solid #73c9ed; font-size: 11px;">
                    <p style="text-align: right; margin: 0px; padding: 5px 0;"> P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}</p>
                </td>
            </tr>
        </tbody>
    </table>
 {{-- 
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="font-family: ;border-bottom: 0.5px solid #73c9ed;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="font-family: ;border-bottom: 0.5px solid #73c9ed;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table> --}}

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="border-bottom: 2px solid #2e77c3; line-height: 1.7em;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<br>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
    @if(($policy->premium_freq)==1)

        <tr>
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                   @if($invoice->invoice_amount < 0)
                      Refund Amount Before VAT:
                    @else
                        Total amount due for the current invoice:
                    @endif
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                 @if($invoice->invoice_amount > 0)
                    Total Instalment Fee:
                 @endif
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    @if($invoice->invoice_amount > 0)
                        P {{number_format((float)$ServiceCharge8 ?? "", 2, '.', ',')}}
                    @else
                        -
                    @endif
                </p>
            </td>
        </tr>
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;font-weight: 600;">
                    Total Excluding VAT:
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;font-weight: 600;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                    VAT at 14%:
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">                
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P {{number_format((float)($vat + $vatServiceCharge) ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0; font-weight: 600;">
                     @if($invoice->invoice_amount < 0)
                        Total Amount Due From Us:
                     @else
                        Total Amount due to Alpha Direct Insurance Company:
                     @endif
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0; font-weight: 600;">
                    P {{number_format((float)$invoice->invoice_amount ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
    @else
        <tr>
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                    @if($invoice->invoice_amount <= 0)
                        Credit Amount Before VAT:
                    @else
                        Total amount due for the current invoice:
                    @endif
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
        @if($invoice->invoice_amount >  0)
        <tr>
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                        Total Instalment Fee:
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P 0.00
                </p>
            </td>
        </tr>
        @endif
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0;">
                @if($invoice->invoice_amount <= 0)
                    VAT on Credit:
                @else
                    VAT at 14%:
                @endif    
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0;">
                    P {{number_format((float)($vat) ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 5px 0; font-weight: 600;">
                    @if($invoice->invoice_amount <= 0)
                       Total Credit Amount (Incl. VAT):
                     @else
                        Total Amount due to Alpha Direct Insurance Company:
                     @endif
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 5px 0; font-weight: 600;">
                    P {{number_format((float)$invoice->invoice_amount ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
    
    @endif
    </tbody>
</table>
<br>
@if($invoice->invoice_amount == 0)
<p style="text-align: justify; font-size: 11px; line-height: 1.5; margin: 0px; padding: 0px;">
This credit note confirms the cancellation of the policy referenced above. A refund is due to you as detailed herein. No further action or payment is required on your part. Should you have any questions, please contact our Finance Department at +267 392 8264 or accountsdept@alphadirect.co.bw.</p><br>
@elseif($invoice->invoice_amount > 0)

<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<p style="font-size:14px; font-weight:600;color:#2e77c3!important; margin-bottom:8px; margin-top:6px;">
    PAYMENT TERMS:</p>
<p style="text-align: justify; font-size: 11px; line-height: 1.5; margin: 0px; padding: 0px;">
    Premiums are payable in accordance with applicable legislation and the terms of the policy, and must be settled before the inception date of cover.
    This invoice is payable upon presentation. Failure to effect payment may result in suspension or withdrawal of cover by Alpha Direct Insurance Co.
    Payments may be made via bank transfer, cash deposit, or point of sale over the counter.
    Please quote the
    <strong style="text-decoration: underline;">policy number</strong>
    on all payments made by bank transfer or cash deposit.
</p>
<br>
@elseif($invoice->invoice_amount < 0)
<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<p style="font-size:14px; font-weight:600;color:#2e77c3!important; margin-bottom:8px; margin-top:6px;">
    REFUND TERMS:</p>
<p style="text-align: justify; font-size: 11px; line-height: 1.5; margin: 0px; padding: 0px;">
This notice confirms the cancellation of the referenced insurance policy with effect from the date stated. A refund of the unused
premium amounting to P {{number_format((float)$invoice->invoice_amount ?? "", 2, '.', ',')}} is due to you and will be processed within Thirty (30) days from
the date of cancellation. No payment is required from your side. If you have any questions or
require assistance regarding this credit, please contact the Alpha Direct Finance Department
at +267 392 8264 or email accountsdept@alphadirect.co.bw.</p><br>
@endif
@if($invoice->invoice_amount > 0)
<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<!-- Banking Details -->
<table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-top:10px;">
    <tr>
        <td>
            <!-- Heading -->
            <div style="font-size:14px; font-weight:600;color:#2e77c3!important; margin-bottom:8px; margin-top:6px;">
                BANKING DETAILS
            </div>

            <!-- Banking Details Table -->
            <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                <tr>
                    <td style="width:140px; font-weight:600; font-size:11px; line-height:1.55; padding-bottom:2px;">Bank</td>
                    <td style="width:10px; padding-bottom:2px;">:</td>
                    <td style="font-size:11px; line-height:1.55; padding-bottom:2px;">First National Bank (Botswana) Limited</td>
                </tr>
                <tr>
                    <td style="font-weight:600; font-size:11px; line-height:1.55; padding-bottom:2px;">Branch</td>
                    <td style="padding-bottom:2px;">:</td>
                    <td style="font-size:11px; line-height:1.55; padding-bottom:2px;">Corporate (282267)</td>
                </tr>
                <tr>
                    <td style="font-weight:600; font-size:11px; line-height:1.55; padding-bottom:2px;">SWIFT Code</td>
                    <td style="padding-bottom:2px;">:</td>
                    <td style="font-size:11px; line-height:1.55; padding-bottom:2px;">FIRNBWGX</td>
                </tr>
                <tr>
                    <td style="font-weight:600; font-size:11px; line-height:1.55; padding-bottom:2px;">Account Number</td>
                    <td style="padding-bottom:2px;">:</td>
                    <td style="font-size:11px; line-height:1.55; padding-bottom:2px;">62403392335</td>
                </tr>
                <tr>
                    <td style="font-weight:700; font-style: italic; font-size:11px; line-height:1.55;">Payment Reference</td>
                    <td>:</td>
                    <td style="font-style: italic; font-weight:700; font-size:11px; line-height:1.55;">{!! $policy->policyNumber !!}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
{{-- <p style="text-align: justify; font-size: 10px;line-height: 1.4; margin: 0px; padding: 0px;">
    <strong>Contact for Questions:</strong><br/>
    Finance department<br/>
    Phone: +267 392 8264<br/>
    Email: accountsdept@alphadirect.co.bw
</p> --}}
@endif
<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<p style="text-align: center; font-size: 12px; line-height:1.5; margin: 0px; padding: 0px;">If you have any questions concerning this invoice, contact the finance department | +267 392 8264 |</p>
<p style="text-align: center; font-size: 12px; line-height:1.5; margin: 0px; padding: 0px;">accountsdept@alphadirect.co.bw</p>
</body>

</html>
