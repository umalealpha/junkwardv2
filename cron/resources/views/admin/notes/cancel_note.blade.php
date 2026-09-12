<!doctype html >
<html>

<head>
    <TITLE>Policy Cancel Note</TITLE>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <style>
        html {
            font-size: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 400;
            -webkit-text-size-adjust: 100%!important;
            -ms-text-size-adjust: 100%!important;
        }

        body {
            font-size: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 400;
            -webkit-print-color-adjust: exact !important;
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

        @media (min-width: 900px) {
            .container {
                width: 870px;
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

        b,
        strong {
            font-weight: 700;
        }

        small {
            font-size: 80%;
        }

        hr {
            margin: 20px 0px;
            border: 0;
            height: 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .table-responsive {
            min-height: 0.01%;
            overflow-x: auto;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        table,
        th,
        td {
            border: 0px solid #000000;
        }

        td,
        th {
            padding: 6px 10px;
        }

        .collapse {
            display: none;
        }

        p {
            margin: 0px;
        }
        /* ============================= */
        /* ============================= */

        @media print {
            body {
                font-size: 12px;
                -webkit-text-size-adjust: 100%!important;
                -ms-text-size-adjust: 100%!important;
                -webkit-print-color-adjust: exact !important;
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
            .table-bordered td,
            .table-bordered th {
                border: 1px solid #ddd !important;
            }
            table,
            th,
            td {
                border: 0px solid #000000;
            }
            td,
            th {
                padding: 6px 10px;
            }
            .table-responsive {
                width: auto;
            }
            p {
                margin: 0px;
            }
        }
    </style>
</head>

<body>
<div style="margin-bottom: 0px;max-width: 900px; margin: auto; position: relative;">
    <div style="position: absolute; top:0;left:0;">
        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/tcorner/top-corner.png" alt="" style="z-index: -1;">
    </div>
    <div class="container clearfix" style="position: relative; padding: 40px 0px;">
        <div class="inner-container clearfix" style="z-index: 11; max-width:650px;margin: auto; ">
            <div style="margin-bottom: 0px;text-align: center;">
            <!-- {{-- <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/Header/logo_1.png" alt="logo" width="230px">--}} -->
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-logo/new_logo.png" alt="logo" width="228px">
            </div>
            <div>
                <p style="margin-bottom:30px;text-align: center;"> Digital Policy Cancellation Notice </p>
            </div>
            @if($policy->billing == "RealPay")
                <table class="table-responsive" style="border-collapse: collapse;width:100%">
                    <tr>
                        <td>
                            <p style="margin: 0px;margin-bottom:0px;">{{ $policy->curr_date }}</p>
                            <h4 style="margin: 0px;margin-bottom:0px;">{{ $policy->bankName }}</h4>
                            <h4 style="margin: 0px;margin-bottom:0px;">{{ $policy->branchCode }}</h4>
                            {{--
                            <p style="margin: 0px;margin-bottom:0px;">[BANK CITY], [BANK COUNTRY]</p>--}}
                        </td>
                    </tr>
                </table>
            @endif
            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                <tr>
                    <td>
                        <p style="margin: 0px;margin-bottom:0px;"> Dear Sir/Madam </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h4 style="margin: 0px; font-weight: 600;">Cancellation Notice : <span style="text-transform: uppercase">{{ $policy->firstName.' '.$policy->lastName }}</span></h4>
                        <h4 style="margin: 0px; font-weight: 600;">Policy Number : {{ $policy->policyNumber }}</h4>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p>This serves to inform you that <span style="font-weight: 600;text-transform: uppercase">{{ $policy->firstName.' '.$policy->lastName }} ,</span> is <span style="font-weight: 600;">no longer under insurance cover </span> by
                            <span style="font-weight: 600;"> Alpha Direct Insurance Company(Pty) Ltd.</span>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h4 style="margin:0px; font-weight: 600;">Terminated Motor Comprehensive Policy:</h4>
                        <p style="margin-top: 0px;text-transform: uppercase">{{ $policy->year.' '.$policy->make.' '.$policy->model }} , {{ $policy->vehiclePlate }} - <span style="font-weight: 600;">P{{ $policy->estimated_value }}</span></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p>The above vehicle no longer has insurance cover with us. </p>
                        <p>Please do the needful.</p>
                    </td>
                </tr>
            </table>

            <table class="table-responsive" style="border-collapse: collapse;width:100%">
                <tr>
                    <td>
                        <p style="margin-bottom: 0px; margin-top: 0px;">Yours Sincerely,</p>
                        <p style="margin-bottom: 6px; margin-top: 0px;">For <span style="font-weight: 600;">Alpha Direct Insurance Company</span></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div style="margin-bottom: 10px;">
                            <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/sign2/sign.png" alt="" style="border-bottom: 1px dotted #000000; max-height: 80px;">
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/update_stamp/updated_stamp.png" alt="" style="max-height: 80px;">
                        </div>
                        <div class="clearfix">
                            <p style="font-size: 14px;">System Generated(+267 392-2718)</p>
                            <h5 style="margin: 0px;">Digital Policy UnderwritingUnit - Alpha Direct</h5>
                            <a href="#" style="font-style: italic; font-size: 14px;font-weight: 500;color: #024acf; -webkit-print-color-adjust: exact!important;">underwriting@alphadirect.co.bw</a>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <img src="{{ url('/images/qrcode'.$policy->policy_id.'.png') }}" alt="QR Code " style="max-height: 146px;">
                        <div>
                            <p style="font-size: 8px; border: 1px solid #000000;padding: 4px 10px;max-width: 220px;margin: auto;">Scan this QR code for current policy status& authenticity</p>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 20px;">
                <p style="margin-bottom: 4px;margin-bottom: 0px;margin-top: 20px;max-width: 850px;">N.B: This email must be received from <span style="font-weight: 600;">insurance@alphadirect.co.bw</span></p>
                <p style="margin-bottom: 60px"></p>
            </div>
        </div>
        <div style="position: absolute; bottom:0;right:0;z-index: -1;">
            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/bcorner/bottom-corner.png" alt="" style="z-index: -1;">
        </div>
    </div>
</div>
</body>
</html>
