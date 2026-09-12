
<!Doctype html >
<html>
<head>
    <TITLE>Agent Collection Rate</TITLE>
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
    @if(count($agentData) > 0)
    <p style="font-size: large"> Monthly Report </p>
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Agent Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Policy</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Total Policy Cancelled</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Premium</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Collection</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Collection %</b>
                </p>
            </td>
        </tr>
        @foreach($agentData as $p)
        <tr>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['name'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['policies'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['policies_cancelled'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['premium'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['sum'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @php
                        //  if($p['premium']!= 0 && $p['sum'] != 0)
                        //  {
                        //    $percentage=$p['premium']/$p['sum']*100;
                        //    $percentage=round($percentage, 2);
                        //  }else{
                        //      $percentage= 0;
                        //  }
                    @endphp

                    <b>{{ $p['percentage'] }}</b>
                </p>
            </td>
        </tr>
        @endforeach
    </table>
    @else
    <p>No  policy has been processed today.</p>
    @endif
     <!-- Since Begining -->

  {{-- @if(count($complete_agentData) > 0)
    <p style="font-size: large"> Since Begining</p>
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Agent Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Policy</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Policy Cancelled</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Premium</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Total Collection</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Collection %</b>
                </p>
            </td>
        </tr>
        @foreach($complete_agentData as $p)
        <tr>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['name'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['policies'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['agentCancelledpolicies'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['premium'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p['sum'] }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @php
                        //  if($p['premium']!= 0 && $p['sum'] != 0)
                        //  {
                        //    $percentage=$p['premium']/$p['sum']*100;
                        //    $percentage=round($percentage, 2);
                        //  }else{
                        //      $percentage= 0;
                        //  }
                    @endphp

                    <b>{{ $p['percentage'] }}</b>
                </p>
            </td>
        </tr>
        @endforeach
    </table>
    @else
    <p>No  policy has been processed today.</p>
    @endif --}}
</div>
</body>

</html>
