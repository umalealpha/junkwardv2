
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
    <span style="float:right;font-size: large">Date : {{ \Carbon\Carbon::parse('today')->format('d-m-Y') }}</span>
    <h2>Policies expired yesterday</h2>
    <br>
    @if(count($policies) > 0)
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>ID</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Number</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Customer Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Cellphone</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Expiry Date</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Status</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Last Payment Date</b>
                </p>
            </td>
        </tr>
        @foreach($policies as $key => $p)
        <tr>
            <td>
                <p style="color: #000000; margin: 0px;">
                    {{ $key+1 }}
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    {{ $p->policyNumber }}
                </p>
            </td>
            @php
                $policyData =  \AlphaDirect\Policy::where('policyNumber',$p->policyNumber)->first();
                $customer =  \AlphaDirect\Customer::where('id',$policyData->customer_id)->first();
            @endphp
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if ($customer !=null && $customer->firstName != null && $customer->lastName != null)
                        {{ $customer->firstName.' '.$customer->lastName }}
                    @else
                            -
                    @endif
                    {{-- @if (isset($p->customer_name))
                        <b>{{ $p->customer_name }}</b>
                    @else
                        <b>N/A</b>
                    @endif --}}
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($customer->cellphone))
                        {{ $customer->cellphone }}
                    @else
                        N/A
                    @endif
                    {{-- @if (isset($p->cellphone))
                    <b>{{ $p->cellphone }}</b>
                    @else
                        <b>N/A</b>
                    @endif --}}
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($p->expiry_date))
                        {{ Carbon::parse($p->expiry_date)->format('d-m-Y') }}
                    @else
                        N/A
                    @endif
                    {{-- @if (isset($p->cellphone))
                    <b>{{ $p->cellphone }}</b>
                    @else
                        <b>N/A</b>
                    @endif --}}
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($policyData->status))
                        @if ($policyData->status == 0)
                            Deactived
                        @elseif ($policyData->status == 1)
                            Activated
                        @elseif ($policyData->status == 2)
                            Cancel
                        @elseif ($policyData->status == 3)
                            Expired
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                    {{-- @if (isset($p->cellphone))
                    <b>{{ $p->cellphone }}</b>
                    @else
                        <b>N/A</b>
                    @endif --}}
                </p>
            </td>
            @php
                $paymentTx = \AlphaDirect\PaymentTransaction::where('policyNumber',$p->policyNumber)->orderBy('id','desc')->first(); // ->whereIn('status',['Paid','Success','SUCCESSFUL','SUCCESS'])
                if (!isset($paymentTx)) {
                    $paymentTx = \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber',$p->policyNumber)->orderBy('id','desc')->first(); // ->whereIn('status',['Paid','Success','SUCCESSFUL','SUCCESS'])
                }
            @endphp
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($paymentTx->paymentDate))
                     {{ Carbon::parse($paymentTx->paymentDate)->format('d-m-Y') }}
                    @else
                        N/A
                    @endif
                    {{-- @if (isset($p->cellphone))
                    <b>{{ $p->cellphone }}</b>
                    @else
                        <b>N/A</b>
                    @endif --}}
                </p>
            </td>
        </tr>
        @endforeach
    </table>
    @else
    <p>No  policy has been expired.</p>
    @endif
</div>

{{-- <br><br> --}}

{{-- <div id="content" style="margin-bottom: 0px;">

    <span style="float:right;font-size: large">Date : {{ \Carbon\Carbon::parse('today')->addDays(8)->format('d-m-Y') }}</span>
    <h2>Weekly Policies</h2>
    <br>
    @if(count($weeklyPolicy) > 0)
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Policy ID</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Number</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Customer Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Cellphone</b>
                </p>
            </td>
        </tr>
        @foreach($weeklyPolicy as $wp)
        <tr>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $wp->id }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $wp->policyNumber }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($wp->customer_name))
                        <b>{{ $wp->customer_name }}</b>
                    @else
                        <b>N/A</b>
                    @endif
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if (isset($wp->cellphone))
                    <b>{{ $wp->cellphone }}</b>
                    @else
                        <b>N/A</b>
                    @endif
                </p>
            </td>
        </tr>
        @endforeach
    </table>
    @else
    <p>No  policy has been expired.</p>
    @endif

</div> --}}
</body>

</html>
