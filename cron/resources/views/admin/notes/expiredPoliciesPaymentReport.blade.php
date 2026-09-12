
<!Doctype html >
<html>
<head>
    <TITLE>{{ $title }}</TITLE>
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
    <div style="text-align: left">
        <h4>{{ Carbon::now()->format('d/m/Y H:i:s') }}</h4>
    </div>
    <div style="text-align: center">
        <h3>{{ $title }}</h3>
    </div>
    
    @if(count($policies) > 0)
        <p style="font-size: large">Total number Expired/Cancelled policies Payments Received Today: {{ count($policies) }} </p>
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
                            <b>Policy Price</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Product</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Payment Method</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Contract Cancelled Status</b>
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
                  {{--  <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Email</b>
                        </p>
                    </th>--}}
                     <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Activated Date</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Expired/Cancelled Date</b>
                        </p>
                    </th>
                </tr>
            </thead>
            @foreach($policies as $key=> $q)
                <tbody>
                    <tr>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $key+1 }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $q->policyNumber }}</b>
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                @if ($q->status == 0 )
                                    <span style="font-color:#5bc0de">Deactivated</span>
                                @elseif ($q->status == 1)
                                    <span style="font-color:#428bca">Policy Activated</span>
                                @elseif ($q->status == 2 )
                                    <span style="font-color:#d9534f">Cancelled</span>
                                @elseif ($q->status == 3 )
                                    <span style="font-color:#d9534f">Expired</span>
                                @else
                                    <span style="font-color:#292b2c ">-</span>
                                @endif
                            </p>
                        </td>
                        <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>P {{ $q->premium }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                            
                                <b>{{ \AlphaDirect\Product::where('id',$q->product_id)->first()->name }}</b>
                           
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @php $bankname = \AlphaDirect\CustomerBanking::where('policy_id',$q->id)->orderBy('id','desc')->first(['billing']); @endphp
                                    @if($bankname) {{ $bankname->billing }} @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">

                            <b> @if(isset($bankname) && $bankname->billing == 'VCS')
                                {{ "Need To Cancel Contract" }} 
                                @else
                                        @if($q->isPaymentCancel == 1) 
                                        {{ "Yes" }} 
                                        @else 
                                        {{ "No" }} 
                                        @endif
                                @endif
                            </b>
                            
                            </p>
                        </td>
                         @php $policysi =  \AlphaDirect\Policy::where('policyNumber',$q->policyNumber)->first(['id','customer_id']); @endphp
                         <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $policysi->customer->firstName.' '.$policysi->customer->lastName }}</b>
                            </p>
                        </td>
                         <td>
                            <p style="color: #000000; margin: 0px;">
                                 <b>{{ $policysi->customer->cellphone }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{   \Carbon\Carbon::parse($q->policyActivatedDate)->format('Y-m-d') }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                            @php 
                                $ExpireDateTerm = \AlphaDirect\PolicyTerm::where('policy_id',$q->id)->orderBy('id','desc')->first(['term_end_date'])
                            @endphp
                            @if(isset($q->status) && $q->status == 2)
                            @php $cancel = \AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$q->policyNumber)->first(); @endphp
                              @if($cancel != null) 
                              <b>{{   \Carbon\Carbon::parse($cancel->cancelled_date)->format('Y-m-d') }} {{ ' Cancelled' }}</b>
                              @endif
                            @else
                                    @if($ExpireDateTerm)
                                        <b>{{ $ExpireDateTerm->term_end_date }} {{ ' Expired' }}</b>
                                    @endif
                            @endif
                           
                            </p>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @else
        <p>Total number Expired policies Payments Recieved Today: 0</p>
    @endif
 

</div>
</body>

</html>
