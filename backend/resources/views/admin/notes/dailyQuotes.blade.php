
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
    @if(count($quotes) > 0)
        <span style="float:right;font-size: large">Date : {{ \Carbon\Carbon::parse('today')->format('d-m-Y') }}</span>
    <p style="font-size: large">Number of quotes processed : {{ count($quotes) }}</p>
        <p style="font-size: large">Existing Customers : {{ $existing }} ({{ $existing_per }}) / New Customers : {{ $new }} ({{ $new_per }})</p>
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Quote Number</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Make</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Model</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Manufacturing Year</b>
                </p>
            </td>

            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Drivers Age</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Drivers Gender</b>
                </p>
            </td>
             <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Premium Rate</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Customer Name</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Cellphone</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Existing Customer</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Agent Name</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Agency Name</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Store Name</b>
                </p>
            </td>
            <td  style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Processed</b>
                </p>
            </td>
        </tr>
        @foreach($quotes as $p)
        <tr>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->quoteCode != null ? $p->quoteCode : "-"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->make != null ? $p->make : "-"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->model != null ? $p->model : "-"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->manufacturingYear != null ? $p->manufacturingYear : "-"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->age != null ? $p->age : "-"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->gender == '1' ? "Male" : "Female"  }}</b>
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if($p->premium_rate != null)
                        <b>{{ number_format($p->premium_rate, 2) }}%</b>
                    @else
                        <b>Rate not found</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                @php  $customer = \AlphaDirect\Customer::where('id', $p->customer_id)->orderBy('id','desc')->first(); 
              
                @endphp
                    @if(isset($customer))
                        <b>{{ $customer->firstName.' '.$customer->lastName }}</b>
                    @else
                        <b>-</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                   @if(isset($customer))
                        <b>{{ $customer->cellphone }}</b>
                    @else
                        <b>-</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                     @if(isset($customer) &&  \Carbon\Carbon::parse($customer->created_at)->format('Y-m-d') == \Carbon\Carbon::parse('today')->format('Y-m-d'))
                        <b>New</b>
                    @else
                        <b>Existing</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if($p->agentFname != null || $p->agentLname != null)
                        <b>{{ $p->agentFname.' '.$p->agentLname }}</b>
                    @else
                        <b>-</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if($p->agency_name != null)
                        <b>{{ $p->agency_name }}</b>
                    @else
                        <b>-</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if($p->store_name != null)
                        <b>{{ $p->store_name }}</b>
                    @else
                        <b>-</b>
                    @endif

                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    @if($p->policy_processed != null)
                        <b>Yes</b>
                    @else
                        <b>No</b>
                    @endif

                </p>
            </td>
        </tr>
        @endforeach
    </table>
    @else
    <p>No  quotes has been processed today.</p>
    @endif

</div>
</body>

</html>
