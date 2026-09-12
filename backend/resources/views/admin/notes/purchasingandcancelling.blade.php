
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
      <div style="text-align: left">
        <h4>{{ Carbon::now()->format('d/m/Y H:i:s') }}</h4>
    </div>
    @if(count($policies) > 0)
        <p style="font-size: large"> {{ count($policies) }} Policies has been cancelled that active less than 6 months</p>
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
                        <b>Product</b>
                    </p>
                </td>
                
               <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Policy Create Date</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Policy Cancel Date</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Policy Active Days count</b>
                    </p>
                </td>

                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Agent Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Store Name</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Total Amout</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Total No. of Success Transaction</b>
                    </p>
                </td>
                <td style="background-color: #dbe5f1;">
                    <p style="text-align: center;margin: 0px;">
                        <b>Total No. of Failed Transaction</b>
                    </p>
                </td>
            </tr>
            @foreach($policies as $p)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ $p->policyNumber }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                        @if(isset($p->customer))
                            <b>{{ $p->customer->firstName }}  {{ $p->customer->lastName }}</b>
                        @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                        @if(isset($p->product))
                            <b>{{ $p->product->name }}</b>
                        @else
                        <b>-</b>
                        @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>{{ \Carbon\Carbon::parse($p->created_at)->format('d-m-Y') }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>
                      @php  $cancel = \AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$p->policyNumber)->first();  @endphp
                       @if($cancel)    
                       {{ \Carbon\Carbon::parse($cancel->created_at)->format('d-m-Y') }}</b>
                      @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            <b>
                          @php  if($cancel != null){
                            $cal = \Carbon\Carbon::parse($cancel->created_at);
                            $cre = \Carbon\Carbon::parse($p->created_at);
                            
                            $interval = $cal->diffInDays($cre);
                           
                          }else{
                            $interval = 1;
                          }
                           @endphp
                            {{ $interval }}
                            </b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            @if(isset($p->user))
                               {{ $p->user->firstName }}  {{ $p->user->lastName }}
                            @endif
                        </p>
                    </td>
                    <td>
                <p style="color: #000000; margin: 0px;">
               
                    <b>@if($p->storeID != null)
                        @php  $store = \AlphaDirect\Stores::where('id',$p->storeID)->where('status',1)->first(); @endphp
                   @if($store != null)
                   {{ $store->name }} 
                    @endif

                    @endif
                    
                    </b>
                    
                </p>
            </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                    @php  $payments = \AlphaDirect\PaymentTransaction::where('policyNumber',$p->policyNumber)
                                ->whereIn('status',['SUCCESS','Success','success','1'])->get(); 
                                $total_amount = 0;
                                if(count($payments) > 0){
                                    foreach($payments as $payment){
                                        $total_amount += $payment->amount;
                                    }
                                    
                                    
                                    }
                    @endphp
                        
                            <b>{{ $total_amount }}</b>
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            
                              {{ count($payments)  }} 
                           
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">

                        @php  $paymentFaileds = \AlphaDirect\PaymentTransaction::where('policyNumber',$p->policyNumber)
                                ->whereNotIn('status',['SUCCESS','Success','success','1'])->get(); 
                        @endphp
                        {{ count($paymentFaileds) }} 
                        </p>
                    </td>
                    
                </tr>
            @endforeach
        </table>
    @else
        <p>No policies has been cancelled today.</p>
    @endif

</div>
</body>

</html>
