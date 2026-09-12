
<!Doctype html >
<html>
<head>
    <TITLE>Expired card response for today</TITLE>
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
    <span style="float:right;font-size: large">Date : {{ \Carbon\Carbon::parse('today')->format('d-m-Y') }}</span>
    @foreach($count_status as $count)
    <p style="font-size: large"> Total {{ $count->statusRef }} : {{ $count->countStatusRef }}<br></p>
    @endforeach

    @if(count($data) > 0)
        <p style="font-size: large"> Total {{ count($data) }} transactions with expired cards today</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Policy Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Customer Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Product Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Cellphone</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Payment Descripton</b>
                    </p>
                </td>
            </tr>
            @foreach($data as $p)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $p->policyNumber }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords($p->firstName.' '.$p->lastName) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $p->product_name }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->cellphone){{ $p->cellphone }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $p->status.':- '.$p->statusRef }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p>No transaction with card expired today.</p>
    @endif
<br>
    @if(count($data_2) > 0)
    <p style="font-size: large"> Total {{ count($data_2) }} transactions with insufficient funds</p>

    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Number</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Customer Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Product Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Cellphone</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Payment Descripton</b>
                </p>
            </td>
        </tr>
        @foreach($data_2 as $p)
            <tr>
                <td>
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $p->policyNumber }}</b>
                    </p>
                </td>
                <td>
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ ucwords($p->firstName.' '.$p->lastName) }}</b>
                    </p>
                </td>
                <td>
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $p->product_name }}</b>
                    </p>
                </td>
                <td>
                    <p style="color: #000000; margin: 0px;">
                        <b>@if($p->cellphone){{ $p->cellphone }}@else - @endif</b>
                    </p>
                </td>
                <td>
                    <p style="color: #000000; margin: 0px;">
                        <b>{{ $p->status.':- '.$p->statusRef }}</b>
                    </p>
                </td>
            </tr>
        @endforeach
    </table>
@else
    <p>No transaction.</p>
@endif

</div>
</body>

</html>
