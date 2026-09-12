
<!Doctype html >
<html>
<head>
    <TITLE>Customer KYC Report</TITLE>
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
        .fonts{
            weight:bold;
        }
    </style>
</head>

<body>
<div><p style="font-size: large;text-align:center"><u>KYC Report</u></p></div>
<div id="content" style="margin-bottom: 0px;">
    @if(($total['Unchecked']+$total['Unapprove']+$total['Approve']) > 0)
        <p style="font-size: large">Total documents submitted today: {{ $total['Unchecked']+$total['Unapprove']+$total['Approve'] }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
            </tr>
            @foreach($all as $key=>$doc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($doc->firstName.' '.$doc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($doc->agency_name){{ $doc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $doc->count }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No  documents submitted today </p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if($total['Approve'] > 0)
        <p style="font-size: large">Total documents approved today: {{ $total['Approve'] }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
            </tr>
            @foreach($approve as $key=>$adoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($adoc->firstName.' '.$adoc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($adoc->agency_name){{ $adoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $adoc->count }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No  documents approved today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if($total['Unapprove'] > 0)
        <p style="font-size: large">Total documents rejected today: {{ $total['Unapprove'] }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Reason</b>
                    </p>
                </td>
            </tr>
            @foreach($unapprove as $key=>$un)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($un->firstName.' '.$un->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($un->agency_name){{ $un->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $un->count }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{!! $un->reason !!}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No  documents rejected today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if($total['Unchecked'] > 0)
        <p style="font-size: large">Total documents with pending verification : {{ $total['Unchecked'] }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>

                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
            </tr>
            @foreach($unchecked as $key=>$udoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($udoc->firstName.' '.$udoc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($udoc->agency_name){{ $udoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $udoc->count }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No  documents are left to check for today</p>
    @endif

</div>

<div id="content" style="margin-bottom: 0px;">
    @if(($total['Unapprove']+$total['Approve']) > 0)
        <p style="font-size: large">ID Document Action Rate : {{ number_format((($total['Action']/($total['Unchecked']+$total['Unapprove']+$total['Approve']))*100),2).'%' }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>

                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
            </tr>
            @foreach($action as $key=>$act)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($act->firstName.' '.$act->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($act->agency_name){{ $act->agency_name }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ number_format((($act->count/($total['Unchecked']+$total['Unapprove']+$total['Approve']))*100),2).'%' }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No actions for today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if(($total['Action'] > 0 && number_format((( $total['Approve']/($total['Action']))*100),2)) > 0)
        <p style="font-size: large">ID Document Approval Rate : {{ number_format((( $total['Approve']/($total['Action']))*100),2).'%' }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>

                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agency</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Number of documents</b>
                    </p>
                </td>
            </tr>
            @foreach($approve as $key=>$adoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($adoc->firstName.' '.$adoc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($adoc->agency_name){{ $adoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ number_format((( $adoc->count/($total['Action']))*100),2).'%' }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No approvals for today</p>
    @endif

</div>

</body>

</html>
