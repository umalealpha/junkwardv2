
<!Doctype html >
<html>
<head>
    <title>Policy Schedule</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    {{-- <link rel="preconnect" href="https://fonts.gstatic.com"> --}}
    {{-- <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet"> --}}
    <style>
        body {
            /* font-family: 'Montserrat', sans-serif; */
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
<div id="content" style="margin-bottom: 0px;">
    <div style="text-align: left">
        <h4>{{ Carbon::now()->format('d/m/Y H:i:s') }}</h4>
    </div>
    <div style="text-align: center">
        <h3>Sms Email Logs @if(env('APP_STATUS') == 'Development') {{  '-DEVELOPMENT' }}@endif</h3>
    </div>

    @if(count($smsEmailLogs) > 0)
        {{-- <p style="font-size: large"> {{ count($smsEmailLogs) }} are policies activated today.</p> --}}
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <thead>
                <tr>
                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Type</b>
                        </span>
                    </td>
                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Message Id</b>
                        </span>
                    </td>
                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Message</b>
                        </span>
                    </td>
                    {{-- <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Content</b>
                        </span>
                    </td> --}}
                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Hook</b>
                        </span>
                    </td>

                    {{-- <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Attachments</b>
                        </span>
                    </td> --}}

                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Send To</b>
                        </span>
                    </td>

                    <td style="background-color: #dbe5f1; padding: 0.01in">
                        <span style="text-align: center;margin-top: 0.18in;">
                            <b>Created At</b>
                        </span>
                    </td>
                </tr>
            </thead>
            @foreach($smsEmailLogs as $s)
                <tr>
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ $s->type }}</b>
                        </span>
                    </td>
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ strip_tags($s->message_id) }}</b>
                        </span>
                    </td>
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ strip_tags($s->message) }}</b>
                        </span>
                    </td>
                    {{-- <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ strip_tags($s->content) }}</b>
                        </span>
                    </td> --}}
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ $s->hook }}</b>
                        </span>
                    </td>
                    {{-- <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ strip_tags($s->attachments) }}</b>
                        </span>
                    </td> --}}
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ $s->send_to }}</b>
                        </span>
                    </td>
                    <td>
                        <span style="color: #000000; margin: 0px; padding: 0.01in;">
                            <b style="margin-top: 0.18in">{{ $s->created_at }}</b>
                        </span>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p>No sms/email logs found.</p>
    @endif

</div>
</body>

</html>
