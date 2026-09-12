
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
            font-size: 8px;
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
        <h3 style="font-size: 15px;">{{ $title }}</h3>
    </div>
    @if(isset($policies))
    <p style="font-size: large">Total number of Adi Policy where Customer Age Over 65 Years Policy Upto Today: {{ count($policies) }} </p>
    @endif
    @if(isset($policiesLegal))
    <p style="font-size: large">Total number of Legal Policy where Customer Age Over 65 Years Policy Upto Today: {{ count($policiesLegal) }} </p>
    @endif
    <table class="table-responsive" style="border-collapse: collapse;width:100%;  ">
  
        <tr>
            
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Number</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Policy Status</b>
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
                    <b>DOB</b>
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
                    <b>Created At</b>
                </p>
            </td>
          
        </tr>
        @if(isset($policies) && $policies->count() > 0 )
        @foreach($policies as $p)
        <tr>
          
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $p->policyNumber }}</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                @if ($p->status == 0 )
                                    <span style="font-color:#5bc0de">Deactivated</span>
                                @elseif ($p->status == 1)
                                    <span style="font-color:#428bca">Policy Activated</span>
                                @elseif ($p->status == 2 )
                                    <span style="font-color:#d9534f">Cancelled</span>
                                @elseif ($p->status == 3 )
                                    <span style="font-color:#d9534f">Expired</span>
                                @else
                                    <span style="font-color:#292b2c ">-</span>
                                @endif
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
                @endif
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                @if(isset($p->profile))
                    <b>{{ Carbon::parse($p->profile->dob)->format('d-m-Y') }}</b>
                @endif
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
               
                    <b>
                    @if(isset($p->user))
                   {{ $p->user->firstName }}  {{ $p->user->lastName }}
                    @endif
                    
                    </b>
                    
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
                    <b> {{ \Carbon\Carbon::parse($p->created_at)->format('d-m-Y') }}
                      
                    </b>
                </p>
            </td>
        </tr>
        @endforeach
   
    @else
   
    @endif
    
        @if(isset($policiesLegal) && $policiesLegal->count() > 0)
        @foreach($policiesLegal as $r)
        <tr>
          
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b>{{ $r->policyNumber }}</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                @if ($r->status == 0 )
                                    <span style="font-color:#5bc0de">Deactivated</span>
                                @elseif ($r->status == 1)
                                    <span style="font-color:#428bca">Policy Activated</span>
                                @elseif ($r->status == 2 )
                                    <span style="font-color:#d9534f">Cancelled</span>
                                @elseif ($r->status == 3 )
                                    <span style="font-color:#d9534f">Expired</span>
                                @else
                                    <span style="font-color:#292b2c ">-</span>
                                @endif
                            </p>
                        </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                @if(isset($r->customer))
                    <b>{{ $r->customer->firstName }}  {{ $r->customer->lastName }}</b>
                @endif
                </p>
            </td>
          
            <td>
                <p style="color: #000000; margin: 0px;">
                @if(isset($r->product))
                    <b>{{ $r->product->name }}</b>
                @endif
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                @if(isset($r->profile))
                    <b>{{ Carbon::parse($r->profile->dob)->format('d-m-Y') }}</b>
                @endif
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
               
                    <b>
                    @if(isset($r->user))
                   {{ $r->user->firstName }}  {{ $r->user->lastName }}
                    @endif
                    
                    </b>
                    
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
               
                    <b>@if($r->storeID != null)
                        @php  $store = \AlphaDirect\Stores::where('id',$r->storeID)->where('status',1)->first(); @endphp
                   @if($store != null)
                   {{ $store->name }} 
                    @endif

                    @endif
                    
                    </b>
                    
                </p>
            </td>
            <td>
                <p style="color: #000000; margin: 0px;">
                    <b> {{ \Carbon\Carbon::parse($r->created_at)->format('d-m-Y') }}
                      
                    </b>
                </p>
            </td>
        </tr>
        @endforeach
   
    @else
   
    @endif
   
    </table>
</div>
</body>

</html>
