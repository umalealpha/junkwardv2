
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
<div id="content" style="margin-bottom: 0px;">
    @if(count($kyc) > 0)
        <p style="font-size: large">Total documents submitted today : {{ count($kyc) }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Customer Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Front</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Back</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of residence</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of income</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Driving License</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Compliance</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Verification Status</b>
                    </p>
                </td>
            </tr>
            @foreach($kyc as $key=>$k)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $k->firstName.' '.$k->lastName }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->omangNumber != null) {{ $k->omangNumber }} @else - @endif</b>
                        </p>
                    </td><td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->passportNumber != null) {{ $k->passportNumber }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->omangFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->omangBack != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->passportFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->pr != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->pi != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->dl != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->compliance == 0) Verification Pending @elseif($k->compliance == 1) Compliant @elseif($k->compliance == 2) Non-compliant @else N/A @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($k->status == 'Unchecked') Verification Pending @elseif($k->status == 'Approve') Approved @elseif($k->status == 'Unapprove') Rejected @else N/A @endif</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts">No  documents uploaded today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if(count($approve) > 0)
        <p style="font-size: large">Total documents approved today : {{ count($approve) }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Customer Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Front</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Back</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of residence</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of income</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Driving License</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Compliance</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Verification Status</b>
                    </p>
                </td>
            </tr>
            @foreach($approve as $key=>$a)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $a->firstName.' '.$a->lastName }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->omangNumber != null) {{ $a->omangNumber }} @else - @endif</b>
                        </p>
                    </td><td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->passportNumber != null) {{ $a->passportNumber }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->omangFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->omangBack != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->passportFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->pr != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->pi != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->dl != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->compliance == 0) Verification Pending @elseif($a->compliance == 1) Compliant @elseif($a->compliance == 2) Non-compliant @else N/A @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($a->status == 'Unchecked') Verification Pending @elseif($a->status == 'Approve') Approved @elseif($a->status == 'Unapprove') Rejected @else N/A @endif</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts">No  documents approved today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if(count($unapprove) > 0)
        <p style="font-size: large">Total documents rejected today : {{ count($unapprove) }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Customer Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Front</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Back</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of residence</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of income</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Driving License</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Compliance</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Verification Status</b>
                    </p>
                </td>
            </tr>
            @foreach($unapprove as $key=>$u)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $u->firstName.' '.$u->lastName }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->omangNumber != null) {{ $u->omangNumber }} @else - @endif</b>
                        </p>
                    </td><td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->passportNumber != null) {{ $u->passportNumber }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->omangFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->omangBack != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->passportFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->pr != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->pi != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->dl != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->compliance == 0) Verification Pending @elseif($u->compliance == 1) Compliant @elseif($u->compliance == 2) Non-compliant @else N/A @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($u->status == 'Unchecked') Verification Pending @elseif($u->status == 'Approve') Approved @elseif($u->status == 'Unapprove') Rejected @else N/A @endif</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts">No  documents rejected today</p>
    @endif

</div>
<div id="content" style="margin-bottom: 0px;">
    @if(count($pending) > 0)
        <p style="font-size: large">Total documents under pending verification : {{ count($pending) }}</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <tr>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Customer Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport Number</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Front</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Omang Back</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Passport</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of residence</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Proof of income</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Driving License</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Compliance</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Verification Status</b>
                    </p>
                </td>
            </tr>
            @foreach($pending as $key=>$p)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $p->firstName.' '.$p->lastName }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->omangNumber != null) {{ $p->omangNumber }} @else - @endif</b>
                        </p>
                    </td><td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->passportNumber != null) {{ $p->passportNumber }} @else - @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->omangFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->omangBack != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->passportFront != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->pr != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->pi != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->dl != null) Uploaded @else Not Uploaded @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->compliance == 0) Verification Pending @elseif($p->compliance == 1) Compliant @elseif($p->compliance == 2) Non-compliant @else N/A @endif</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>@if($p->status == 'Unchecked') Verification Pending @elseif($p->status == 'Approve') Approved @elseif($p->status == 'Unapprove') Rejected @else N/A @endif</b>
                        </p>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="fonts">No  documents under pending verification</p>
    @endif

</div>
</body>

</html>