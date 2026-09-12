
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
                        <p style="text-align: right; color: #2e77c3!important;font-size:20px!important;">TAX CREDIT NOTE</p>
                        <p style="text-align: right; color: #2e77c3!important;margin:0px;">CREDIT # :<span style="color: black!important;"> {!! $credit_note_no_view !!} </span></p>
                        <p style="text-align: right; color: #2e77c3!important;margin-bottom:15px;">CREDIT DATE :<span style="color: black!important;"> {{  Carbon::now()->format('d/m/Y') }}</span></p>
                        <p style="text-align: right; font-weight: 600!important;margin:0px;"> Co. Regn. No. : BW00000123907</p>
                        <p style="text-align: right; font-weight: 600!important;"> VAT No. : C30499101112</p>
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
                        {!! $customer->firstName !!} {!! $customer->lastName !!},
                    </p>
                    <p style="margin: 0px;">
                        {!! $customerProfile->address !!}
                    </p><br>
                    <p style="font-weight: 600!important; margin: 0px;">VAT No. : TBA</p>
                </td>

                <td width="50%">
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Line of Business : <span style="color: black!important;"> {!! $product !!} </span></p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Period of Insurance : <span style="color: black!important;">
                        {!! $start_date !!}
                        <span style="color: #2e77c3!important;">TO</span>
                        {!! $end_date !!}
                    </span> </p>
                    <p style="color: #2e77c3!important;text-align: left; margin: 0px;">Policy No. : <span style="color: black!important;">
                        {!! $policyNumber !!}
                    </span></p>
                    <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                </td>
            </tr>
        </table>
    </div>

    <hr style="border-bottom:0.5px solid #73c9ed!important;">
    <div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <thead style="border-bottom: 2px solid #2e77c3!important; color: #2e77c3!important;">
                <tr>
                    <td width="50%">
                        <p style="text-align: left;  color: #2e77c3!important; font-size: 14px;margin: 0px; font-weight: 600;">Description</p>
                    </td>
                    <td width="50%">
                        <p style="text-align: right; color: #2e77c3!important;font-size: 14px; margin: 0px;font-weight: 600;">Amount</p>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td width="50%" style="border-bottom: 0.5px solid #73c9ed!important;">
                        <p style="text-align: left;margin: 0px;padding:0px!important;">
                        Endorsement to cancelled
                        @if ($vehicleNumber != null)
                        {!! $vehicleNumber->vehiclePlate !!}.
                        @else
                        N/A
                        @endif
                        </p>
                    </td>
                    <td width="50%" style="border-bottom: 0.5px solid #73c9ed!important;">
                        <p style="font-weight:600!important;font-size:14px!important;text-align: right; margin: 0px;padding:0px!important;">
                             P {!! $total_credited ?? $earned_premium !!}
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td width="100%" style="border-bottom: 0.5px solid #73c9ed!important;">
                        <p style="visibility: hidden; margin: 0px; padding:0px!important;">Emty text</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td width="100%" style="border-bottom: 0.5px solid #73c9ed!important;">
                        <p style="visibility: hidden;margin: 0px; padding:0px!important;">Emty text</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td width="100%" style="border-bottom: 0.5px solid #73c9ed!important;">
                        <p style="visibility: hidden;margin:0px; padding:0px!important;">Emty text</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td width="100%" style="border-bottom: 2px solid #2e77c3!important; line-height: 1.7em;">
                        <p style="visibility: hidden;margin:0px; padding:0px!important;">Emty text</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
                <td style="border-bottom:0.5px solid #73c9ed!important;">
                    <p style="font-weight:600;font-size:14px;margin:0px; padding:0px!important;">Total Amount Due Before VAT :</p>
                </td>
                <td style="border-bottom: 0.5px solid #73c9ed!important;">
                    <p style="font-weight:600!important;font-size:14px!important;margin:0px; padding:0px!important;text-align:right">
                        P {!! $before_vat !!}
                    </p>
                </td>
            </tr>

            <tr style="width: 100%;">
                <td style="border-bottom:0.5px solid #73c9ed!important;">
                    <p style="font-weight:600;font-size:14px; margin:0px; padding:0px!important; color: black;">Total VAT :</p>
                </td>
                <td style="border-bottom:0.5px solid #73c9ed!important;">
                    <p style="font-weight:600!important;font-size:14px!important;margin:0px; padding:0px!important;text-align:right">
                        P {!! $vat !!}
                    </p>
                </td>
            </tr>
            <tr style="width: 100%;">
                <td style="border-bottom:0.5px solid #73c9ed!important;">
                    <p style="font-weight:600;margin:0px; padding:0px!important;font-size:16px; color: #2e77c3 !important;">Total Amount Due From Us :</p>
                </td>
                <td style="border-bottom:0.5px solid #73c9ed!important;">
                    <p style="font-weight:600!important;font-size:14px!important;margin:0px; padding:0px!important;text-align:right">
                        P {!! $total_credited ?? $earned_premium !!}
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    <br>
    <p style="font-size: 14px;font-weight:bold;text-decoration: underline;">PAYMENTS TERMS:</p>
    <p style="text-align: justify; font-size: 11px;line-height:1.2; margin:0px; padding:0px;">
        Under current legislation and in terms of your policy, premiums must be paid before the inception date of the cover. The above premium is therefore payable on
        presentation of this debit note. Failure to do so could result in cover being withdrawn by Alpha Direct Insurance Co. Please note that our specified method of
        payment is by bank transfer. Alpha Direct Insurance Company will only accept payment by cheque if the cheques are received and honoured by the due date for
        payment. Make all checks payable to Alpha Direct Insurance Company (Pty) Ltd. All risks associated with the delivery of cheques by post will be for your account.
        Please quote your policy number on all payments whether by bank transfer or cheque. Our banking details are as follows:
    </p><br>
    <p style="text-align: justify; font-size: 11px;line-height:1.2; margin:0px; padding:0px;">Bank : First National Bank (Botswana) Limited | Branch : Corporate | Branch Code : 282267 | SWIFT Code : FIRNBWGX Account Number : 62403392335</p><br>
    <p style="text-align: justify; font-size: 11px;margin:0px; padding:0px;">If you have any questions concerning this invoice, contact the finance department | + 267 392 8264 | businessdev@alphadirect.co.bw</p><br>
    <p style="text-align: center; color: #2e77c3!important;font-size:17px; margin:0px; padding:0px;">THANK YOU FOR YOUR BUSINESS!</p>
    <p style="text-align: center;font-size: 12px;margin: 0px; padding:0px; ">You just saved a lot of money by choosing Alpha Direct!</p>
</body>
</html>

