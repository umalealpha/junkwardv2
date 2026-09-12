
<!Doctype html >
<html>
<head>
    <TITLE>Policy Cancellation Note</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet"> --}}
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            font-size: 14px;
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
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Floor 2, Bar 2, Botswana Innovation Hub</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;"> Plot 69184, Block 8, P.O. Box 26 ADC</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Gaborone, Botswana</p>
                        <p style="font-size: 13px; margin: 0px;color: black!important;">Phone +267 392 8264 | Fax +267 392 8265</p>
                        <p style="margin: 0px!important;">debtors@alphadirect.co.bw | <a href="mailto: www.alphadirect.co.bw" style="color: #2e77c3!important;">www.alphadirect.co.bw</a></p>
                    </td>
                    <td width="50%">
                        <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">NOTICE OF CANCELLATION OF INSURANCE</p>
                        <!-- <p style="text-align: right; font-weight: 600!important;padding:0px!important;"> Co. Regn. No. : 2012/13738</p> -->
                        <p style="text-align: right; font-weight: 600!important;padding:0px;!important;"> VAT No. : BW00000123907</p>
                        <p style="text-align: right; font-weight: 600!important;padding:0px;!important;"> DATE : {{  Carbon::now()->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </table>
        </div>
    </header>
    <div style="margin-top: 1px;">
        <table style="width:100%;">
            <tr valign="middle">
                <td width="50%">
                    <p style="color: #2e77c3!important; font-size: 18px!important;line-height:20px;font-weight: 600!important;margin-bottom: 4px;">To,</p>
                    <p style="margin: 0px;">
                         @if($customerProfile->entity_type=='Organisation')
                            {!! $customerProfile->company->name??"" !!}
                        @else
                        {!! $customer->firstName??"" !!} {!! $customer->middleName??"" !!} {!! $customer->lastName??"" !!}
                        @endif
                    </p>
                    <p style="margin: 0px;">
                         @if($policy->profile->entity_type=='Organisation')
                        {{ $policy->profile->company->postal_address ? ($policy->profile->company->postal_address) : "" }}
                        @else
                        {{ $policy->profile->post_address ? ($policy->profile->post_address) : "" }} 
                        @endif
                    </p><br>
                    <p style="font-weight: 600!important; margin: 0px;">VAT No. : @if(isset($company_vat)){!! $company_vat !!}@else N/A @endif</p>
                </td>

                <td width="50%">
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Line of Business : <span style="color: black!important;">@if ($policy->product_id == 7)
                    Commercial All Risk
                    @elseif ($policy->product_id == 8)
                    Domestic All Risk
                    @endif </span></p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Period of Insurance : <span style="color: black!important;">
                        {!! $term_start_date !!}
                        <span style="color: #2e77c3!important;">TO</span>
                        {!! $term_end_date !!}
                    </span> </p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Policy No. : <span style="color: black!important;">
                        {!! $policyNumber !!}
                    </span></p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Credit Amount : <span style="color: black!important;">
                       P {{number_format($premium_due,2)}}
                    </span></p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">
                        We are cancelling this policy. Your insurance coverage will cease on
                        the date above.
                    </p>
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                </td>
            </tr>
        </table>
    </div>

    <hr style="border-bottom:0.5px solid #73c9ed!important;">
    <div>
        <p>Dear   @if($policy->profile->entity_type=='Organisation')
                            {!! $policy->profile->company->name??"" !!}
                        @else
                        {!! $policy->customer->firstName??"" !!} {!! $policy->customer->middleName??"" !!} {!! $policy->customer->lastName??"" !!}
                        @endif,</p>
        <p>Please note that as of 12:01 A.M. on <strong>{!! $cancel_effective_from !!}</strong>, your insurance coverage will be cancelled. Any unused portion of the premium already paid by you will be refunded within thirty (30) days of the date of cancellation. 
        </p>
        <p>The reason for cancellation of this policy is: {!! $cancellation_reason !!}</p>
        <!-- <p>If your premium due as per this notice is not paid within ten (10) days of the date of this notice, your coverage will lapse.
            If you would like additional information about this decision, you may contact our office within a period of ninety (90) days of the
            date of this notice. This notice does not waive our right to terminate this policy at an earlier date for any valid reason.
        </p> -->
        <p style="font-weight:bold;color: #2e77c3!important;text-align: left; margin: 0px;">Total Credit Amount (Incl. VAT): P {{number_format($premium_due,2)}}</p>
    </div>
    <br>
    @if($premium_due == 0)
    <p style="text-align: justify; font-size: 10px;line-height: 1.2; margin: 0px; padding: 0px;">
    This credit note confirms the cancellation of the policy referenced above. A refund is due to you as detailed herein. No further action or payment is required on your part. Should you have any questions, please contact our Finance Department at +267 392 8264 or accountsdept@alphadirect.co.bw.</p><br>
    @elseif($premium_due > 0)
    <p style="text-align: justify; font-size: 13px;font-weight:;text-decoration: underline;color:#000">PAYMENTS TERMS:</p>
    <p style="text-align: justify; font-size: 10px;line-height: 1.2; margin: 0px; padding: 0px;">
    Under current legislation and in terms of your policy, premiums must be paid before the inception date of the cover. 
    The above premium is therefore payable on presentation of this debit note. Failure to do so could result in cover being withdrawn by Alpha 
    Direct Insurance Co.  Please note that our specified method of payment is by bank transfer, cash deposit or point of sale over the counter. 
    Please quote your policy number on all payments made through bank transfer or cash deposit. Our banking details are as follows: &nbsp;
    </p><br>
    @elseif($premium_due < 0)
    <p style="text-align: justify; font-size: 13px;font-weight:;text-decoration: underline;color:#000">REFUND TERMS:</p>
    <p style="text-align: justify; font-size: 10px;line-height: 1.2; margin: 0px; padding: 0px;">
    This notice confirms the cancellation of the referenced insurance policy with effect from the date stated. A refund of the unused 
    premium amounting to P {{number_format((float)$premium_due ?? "", 2, '.', ',')}} is due to you and will be processed within Thiry (30) days from 
    the date of cancellation. No payment is required from your side. If you have any questions or 
    require assistance regarding this credit, please contact the Alpha Direct Finance Department 
    at +267 392 8264 or email accountsdept@alphadirect.co.bw.</p><br>
    @endif
    @if($premium_due > 0)
    <p style="text-align: justify; font-size: 11px;line-height:1.2; margin:0px; padding:0px;font-weight:bold">Bank : First National Bank (Botswana) Limited | Branch : Corporate | Branch Code : 282267 | SWIFT Code : FIRNBWGX Account Number : 62403392335</p><br>
    <p style="text-align: justify; font-size: 11px;margin:0px; padding:0px;">If you have any questions concerning this invoice, contact the finance department | + 267 392 8264 | accountsdept@alphadirect.co.bw</p><br>
    @endif
    <p style="text-align: center; color: #2e77c3!important;font-size:17px; margin:0px; padding:0px;">THANK YOU FOR YOUR BUSINESS!</p>
    <p style="text-align: center;font-size: 12px;margin: 0px; padding:0px; ">You just saved a lot of money by choosing Alpha Direct!</p>
</body>
</html>

