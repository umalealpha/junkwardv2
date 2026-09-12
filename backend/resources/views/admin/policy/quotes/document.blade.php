<!doctype html >
<html>

<head>
    <TITLE>Customer Information Verification</TITLE>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <style>
        html {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            -webkit-text-size-adjust: 100%!important;
            -ms-text-size-adjust: 100%!important;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            -webkit-print-color-adjust: exact;
        }

        table,
        th,
        td {
            border: 1px solid #c1c6d1;
            border-collapse: collapse;
            padding: 4px 4px;
        }

        th,
        td {
            padding: 6px 10px;
            text-align: left;
        }
        /* ============================= */

        @media print {
            body {
                font-size: 14px;
                -webkit-text-size-adjust: 100%!important;
                -ms-text-size-adjust: 100%!important;
                -webkit-print-color-adjust: exact !important;
                color: #000 !important;
            }
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
            table,
            th,
            td {
                border: 1px solid #c1c6d1;
                border-collapse: collapse;
            }
            th,
            td {
                padding: 10px;
                text-align: left;
            }
            .table-responsive {
                width: auto;
            }
            p {
                margin: 0px;
                color: #000 !important;
            }
            h3 {
                color: #383884 !important;
                color: #000 !important;
            }
            a {
                color: #383884 !important;
            }
        }

        @page {
            margin: 0px 0px;
            padding: 0px 0px;
        }
    </style>
</head>

<body>
<div style="margin-bottom: 0px;max-width: 900px; margin: auto; position: relative;">
    <div style="position: absolute; top:0;left:0;">
        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/tcorner/top-corner.png" alt="" style="z-index: -1;">
    </div>

    <div style="position: relative; padding: 40px 0px;">
        <div class="inner-container clearfix" style="z-index: 11; max-width:650px;margin: auto; ">
            <div style="margin-bottom: 0px;text-align: center;">
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-logo/new_logo.png" alt="logo" width="230px">
            </div>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Quote Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Quote Number : </span> {{ ucwords($data->quoteCode) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Status : </span>@if($data->quote_status == 1) Active @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Store : </span>@if($storeName) {{ ucwords(strtolower($storeName)) }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Agent : </span>@if($agent) {{ ucwords(strtolower($agent->firstName.' '.$agent->lastName)) }} @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Expiry Date : </span> @if($expiryDate != null) {{ $expiryDate }} @else - @endif</p>
                                </td>
                                <td style="width: 0%;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Customer Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">First Name : </span> {{ ucwords(strtolower($data->firstName)) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Last Name : </span> {{ ucwords(strtolower($data->lastName)) }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang : </span>@if($data->omang) {{ $data->omang }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport : </span> @if($data->passport) {{ $data->passport }} @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Gender : </span> @if($data->gender == 1) Male @else Female @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Date Of Birth : </span> {{ \Carbon\Carbon::parse($data->dob)->format('d-m-Y') }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Email : </span> {{ $data->email }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Mobile Number : </span> {{ $data->cellphone }}</p>
                                </td>

                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Marital Status : </span>
                                        @if($data->maritalstatus == 1)
                                            Single
                                        @elseif($data->maritalstatus == 2)
                                            Married
                                        @elseif($data->maritalstatus == 3)
                                            Divorced
                                        @elseif($data->maritalstatus == 4)
                                            Widowed
                                        @elseif($data->maritalstatus == 5)
                                            Living Together(Not Married)
                                        @elseif($data->maritalstatus == 6)
                                            Living Seperately(Married)
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </td>
                                <td style="width: 50%;">

                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Vehicle Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Make : </span>@if($data->make) {{ ucwords(strtolower($data->make)) }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Model : </span>@if($data->model) {{ ucwords(strtolower($data->model)) }} @else - @endif </p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Manufacturing Year : </span>@if($data->manufacturingYear) {{ $data->manufacturingYear }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Estimated Value : </span> @if($data->estimatedValue) P{{ number_format($data->estimatedValue, 2) }} @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Number of prior accidents : </span>@if($data->priorAccidents) {{ $data->priorAccidents }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Purpose : </span> @if($data->purpose == 27) Personal @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Japanese Import : </span>{{ $data->is_imported }}</p>
                                </td>
                                <td style="width: 0%;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Premium Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Monthly Premium </span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;"> @if($data->premiumMonthly) P{{ $data->premiumMonthly }} @else - @endif </span></p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Three Installments </span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;"> @if($data->premium3Inst) P{{ $data->premium3Inst }} @else - @endif </span></p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Yearly Premium </span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;"> @if($data->premiumAnnually) P{{ $data->premiumAnnually }} @else - @endif </span></p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="text-align: center;">
                                    <img src="{{ url($QRCode) }}" alt="QR Code " style="max-height: 150px; margin-bottom: 5px;">
                                    <div>
                                        <p style="font-size: 8px; border: 1px solid #000000;padding: 4px 10px;max-width: 220px;margin: auto;">Scan this QR code to process policy with this quote</p>
                                    </div>
                                </td>
                                <td style="width: 50%;">

                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div style="position: absolute; bottom:0;right:0;z-index: -1;">
            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-bottom-corner/bottom-corner.png" alt="" style="z-index: -1;">
        </div>
    </div>
</div>


</body>

</html>
