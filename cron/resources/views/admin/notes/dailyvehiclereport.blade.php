
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
        .fonts{
            weight:bold;
        }
    </style>
</head>

<body>
<div><p style="font-size: large;text-align:center"><u>Vehicle Preinspection Report</u></p></div>
<div id="content" style="margin-bottom: 0px;">
    @if(($v_total['Unchecked']+$v_total['Unapprove']+$v_total['Approve']) > 0)
        <p style="font-size: large">Total documents submitted today: {{ $v_total['Unchecked']+$v_total['Unapprove']+$v_total['Approve'] }}</p>
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
            @foreach($v_all as $v_key=>$v_doc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_doc->firstName.' '.$v_doc->lastName)) }} </b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_doc->agency_name){{ $v_doc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $v_doc->count }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No  documents submitted today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if($v_total['Approve'] > 0)
        <p style="font-size: large">Total documents approved today: {{ $v_total['Approve'] }}</p>
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
            @foreach($v_approve as $v_key=>$v_adoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_adoc->firstName.' '.$v_adoc->lastName)) }} </b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_adoc->agency_name){{ $v_adoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $v_adoc->count }}</b>
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
    @if($v_total['Unapprove'] > 0)
        <p style="font-size: large">Total documents rejected today: {{ $v_total['Unapprove'] }}</p>
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
            @foreach($v_unapprove as $v_key=>$v_un)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_un->firstName.' '.$v_un->lastName)) }} </b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_un->agency_name){{ $v_un->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $v_un->count }}</b>
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
    @if($v_total['Unchecked'] > 0)
        <p style="font-size: large">Total documents with pending verification : {{ $v_total['Unchecked'] }}</p>
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
            @foreach($v_unchecked as $v_key=>$v_udoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_udoc->firstName.' '.$v_udoc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_udoc->agency_name){{ $v_udoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $v_udoc->count }}</b>
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
    @if(($v_total['Unapprove']+$v_total['Approve']) > 0)
        <p style="font-size: large">ID Document Action Rate : {{ number_format((($v_total['Action']/($v_total['Unchecked']+$v_total['Unapprove']+$v_total['Approve']))*100),2).'%' }}</p>
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
            @foreach($v_action as $v_key=>$v_act)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_act->firstName.' '.$v_act->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_act->agency_name){{ $v_act->agency_name }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ number_format((($v_act->count/($v_total['Unchecked']+$v_total['Unapprove']+$v_total['Approve']))*100),2).'%' }}</b>
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
    @if($v_total['Action'] > 0 && (number_format((( $v_total['Approve']/($v_total['Action']))*100),2)) > 0)
        <p style="font-size: large">ID Document Approval Rate : {{ number_format((( $v_total['Approve']/($v_total['Action']))*100),2).'%' }}</p>
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
            @foreach($v_approve as $v_key=>$v_adoc)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ ucwords(strtolower($v_adoc->firstName.' '.$v_adoc->lastName)) }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($v_adoc->agency_name){{ $v_adoc->agency_name }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ number_format((( $v_adoc->count/($v_total['Action']))*100),2).'%' }}</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No approvals for today</p>
    @endif

</div>


<div id="content" style="margin-bottom: 0px;">
    @if(count($v_unapproved_all) > 0)
        <p style="font-size: large">Total pre-inspection rejected for today</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Policy Number</b>
                    </p>
                </td>

                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Vehicle Plate</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Status</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Remark</b>
                    </p>
                </td>
            </tr>
            @foreach($v_unapproved_all as $v_key=>$data)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($data->policyNumber){{ $data->policyNumber }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($data->vehiclePlate){{ $data->vehiclePlate }}@else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($data->status == 2) Rejected @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($data->remark){{ $data->remark }}@else - @endif</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts" style="font-size:large;">No pre-inspection rejected for today</p>
    @endif

</div>

</body>

</html>
