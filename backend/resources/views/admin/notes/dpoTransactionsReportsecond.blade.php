
<!Doctype html >
<html>
<head>
    <TITLE>DPO Payment Policy Tranactions</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
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
        <h3>DPO Transactions Report @if(env('APP_STATUS') == 'Development') {{  '-DEVELOPMENT' }}@endif</h3>
    </div>
    @if(count($policies) > 0)
        <p style="font-size: large">Total Policies Processed today: {{ count($policies) }} </p>
        <p style="font-size: large">Total Success transactions today: {{ $successTransactions }} ({{ number_format((((float)$successTransactions/(float)count($policies)) * 100), 2) }}%)</p>
        <p style="font-size: large">Total Failed transactions today: {{ $failedTransactions }} </p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <thead>
                <tr>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>No.</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Number</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Customer Name</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Cellphone</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Status</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Premium</b>
                        </p>
                    </th>
                    {{-- <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Product</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Customer Name</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Cellphone</b>
                        </p>
                    </th> --}}
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Email</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Retry Count</b>
                        </p>
                    </th>
                    {{-- <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Create Date</b>
                        </p>
                    </th> --}}
                    {{-- <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Agent Name</b>
                        </p>
                    </th> --}}
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Reason</b>
                        </p>
                    </th>
                </tr>
            </thead>
            {{-- @foreach($policies as $key=> $p) --}}
            @foreach($transactions as $key=> $p)
                <tbody>
                    <tr class="table-@if ($p->status == 2 ) {{ 'success' }} @else {{ 'danger' }}  @endif">
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $key+1 }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->policy_number))
                                        {{ $p->policy_number }}
                                    @else
                                        {{ $p->policyNumber }}
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->customer_name))
                                        {{ $p->customer_name }}
                                    @else
                                        N/A
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->cellphone))
                                        {{ $p->cellphone }}
                                    @else
                                        N/A
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                {{ $p->status }}
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>
                                    @if (isset($p->premium))
                                        P {{ $p->premium }}
                                    @else
                                        P {{ $p->amount }}
                                    @endif
                                </b>
                            </p>
                        </td>
                       
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ ucwords($p->email) }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $p->retry_count }}</b>
                            </p>
                        </td>
                      
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                @if (isset($p->reason))
                                    {{ $p->reason }}
                                
                                @endif
                                </b>
                            </p>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @else
        <p  style="font-size: 15px;">No transaction are there for today.</p>
    @endif

    {{-- == --}}

    @if(count($missingTokenTransactions) > 0)
        <p style="font-size: large"> Email is not present policies proceed today: {{ count($missingTokenTransactions) }} </p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <thead>
                <tr>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>No.</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Number</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Status</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Premium</b>
                        </p>
                    </th>
                  
                  
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Reason</b>
                        </p>
                    </th>
                </tr>
            </thead>
            @foreach($missingTokenTransactions as $key=> $p)
                <tbody>
                    <tr>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $key+1 }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $p->policy_number }}</b>
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                               {{ $p->status }}
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>P {{ $p->premium }} </b>
                            </p>
                        </td>
                       
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>Email is not present</b>
                            </p>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @endif

    @if(count($dpoFailedTransactions) > 0)
        <p style="font-size: large"> Failed transactions processed today: {{ count($dpoFailedTransactions) }} </p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
            <thead>
                <tr>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>No.</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Number</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Customer Name</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Cellphone</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Status</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Premium</b>
                        </p>
                    </th>
                  
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Email</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Retry Count</b>
                        </p>
                    </th>
                   
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Reason</b>
                        </p>
                    </th>
                </tr>
            </thead>
           
            @foreach($dpoFailedTransactions as $key=> $p)
                <tbody>
                    <tr class="table-@if ($p->status == 2 ) {{ 'success' }} @else {{ 'danger' }}  @endif">
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $key+1 }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->policy_number))
                                        {{ $p->policy_number }}
                                    @else
                                        {{ $p->policyNumber }}
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->customer_name))
                                        {{ $p->customer_name }}
                                    @else
                                        N/A
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if (isset($p->cellphone))
                                        {{ $p->cellphone }}
                                    @else
                                        N/A
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                               {{ $p->status }}
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>
                                    @if (isset($p->premium))
                                        P {{ $p->premium }}
                                    @else
                                        P {{ $p->amount }}
                                    @endif
                                </b>
                            </p>
                        </td>
                    
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ ucwords($p->email) }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $p->retry_count }}</b>
                            </p>
                        </td>
                        
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                @if (isset($p->reason))
                                    {{ $p->reason }}
                                @else
                                    {{ $p->note }}
                                @endif
                                </b>
                            </p>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @else
        <p style="font-size: 15px;">No failed transaction are there for today.</p>
    @endif

</div>
</body>

</html>
