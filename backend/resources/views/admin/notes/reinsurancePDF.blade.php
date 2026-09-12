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
    <div style="position: relative; padding: 40px 0px;">
        <div class="inner-container clearfix" style="z-index: 11; max-width:650px;margin: auto; ">
            <h3 style="text-align: center;color: #383884!important;-webkit-print-color-adjust: exact;">Reinsurance Risk Profile Summary Report</h3>
            <p></p>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Accidental Insurance</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                            <tr style="width: 100%;">
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Policy Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Claim Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Payments</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Total Reserve</span></p>
                                </th>
                            </tr>

                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $life['policy_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $life['premium'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $life['claim_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $life['payment'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $life['reserve'] }}</span></p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;">Motor Third-party Insurance</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                            <tr style="width: 100%;">
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Risk Band</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Sum Insured</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Policy Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Claim Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Payments</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Total Reserve</span></p>
                                </th>
                            </tr>

                            @foreach($vehicle as $v)
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['risk_band'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ round($v['sum_assured'], 2) }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['policy_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['premium'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['claim_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['payment'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['reserve'] }}</span></p>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Motor Comprehensive Insurance</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                            <tr style="width: 100%;">
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Risk Band</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Sum Insured</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Policy Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Claim Count</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Payments</span></p>
                                </th>
                                <th style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Total Reserve</span></p>
                                </th>
                            </tr>

                            @foreach($motorComp as $v)
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['risk_band'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ round($v['sum_assured'], 2) }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['policy_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['premium'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['claim_count'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['payment'] }}</span></p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">{{ $v['reserve'] }}</span></p>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>


</body>

</html>