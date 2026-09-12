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
    @if(count($contracts) > 0)
    {{-- <p> {{ count($policy) }} payments that got failed on : {{ $policy[0]->created_at }} .</p> --}}
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Policy ID</b>
                </p>
            </td>
            <td>
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Number</b>
                </p>
            </td>
            <td>
                <p style="text-align: center;margin: 0px;">
                    <b>Client Number </b>
                </p>
            </td>
            <td>
                <p style="text-align: center;margin: 0px;">
                    <b>Contract Number</b>
                </p>
            </td>
        </tr>
        @foreach($contracts as $c)
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $c->policy_id }}</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $c->policyNumber }}</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $c->client_number }}</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $c->contract_number }}</b>
                    </p>
                </td>
            </tr>
        @endforeach
    </table>
    @else
    <p>No multiple realpay contract found active today.</p>
    @endif
</div>
</body>

</html>
