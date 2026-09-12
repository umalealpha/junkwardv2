
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
<div style="text-align: left">
        <h4>{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</h4>
</div>
    <!-- Table for count -->
    @if(count($count)> 0)
    <p style="font-size: large">Statistics</p>
        <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Total records actioned till date</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Yesterday count</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Todays count</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Approve and Unapprove count</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                  <b>Remaining Action(Today) </b>
                </p>
            </td>
            {{-- <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Kyc Status </b>
                </p>
            </td> --}}
        </tr>
        @if(count($count) > 0)
            @foreach($count as $d)
                <tr>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['total'] != null)
                            <b>{{ $d['total'] }}</b>
                           @else
                              <b>0</b>  
                           @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['total_upto_yesterday'] != null)
                              <b>{{ $d['total_upto_yesterday'] }}</b>
                            @else
                               <b>0</b>  
                           @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['today_unchecked'])
                            <b>{{ $d['today_unchecked'] }}</b>
                           @else
                               <b>0</b> 
                           @endif
                        </p>
                    </td>
                    <td>
                        @if($d['today_other_count'] != null)
                            <b>{{ $d['today_other_count'] }}</b>
                        @else
                            <b>0</b>    
                        @endif
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                            {{-- <b>{{ $d['policy_status'] }}</b> --}}

{{--                            @php--}}
{{--                                $remain= ;--}}

{{--                            @endphp--}}
                              {{ $d['total']-$d['total_upto_yesterday'] -$d['today_other_count'] }}
                        </p>

                    </td>

                </tr>
            @endforeach
        @else
            <p style="text-align:center">No data found</p>
        @endif
    </table>

    @endif
    <!--table for count end-->



    @if(count($data) > 0)
    <p style="font-size: large"> Daily Kyc list for activated Policies </p>
    <table class="table-responsive" style="border-collapse: collapse;width:100%">
        <tr>
              <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>S No.</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center; margin: 0px;">
                    <b>Policy Number</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Customer Name</b>
                </p>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Product Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Agent Name</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Performed By</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b>Approved Date</b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Kyc Compliance </b>
                </p>
            </td>
            <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Kyc Status </b>
                </p>
            </td>
             <td style="background-color: #dbe5f1;">
                <p style="text-align: center;margin: 0px;">
                    <b> Remark </b>
                </p>
            </td>
        </tr>
        @if(count($data) > 0)
            @foreach($data as $i => $d)
                <tr>
                      <td>
                        <p style="color: #000000; margin: 0px;">
                          
                            <b>{{ $i + 1 }}</b>
                       
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['policyNumber'] != null)
                            <b>{{ $d['policyNumber'] }}</b>
                           @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['Customer'] != null)
                           <b>{{ ucfirst($d['Customer']) }}</b>
                           @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['Product'])
                            <b>{{ $d['Product'] }}</b>
                           @endif
                        </p>
                    </td>
                    <td>
                    <p style="color: #000000; margin: 0px;">
                          @php
                            if($d != null && $d['agent_id']!=null)
                              {
                                $usera=AlphaDirect\User::where('id',$d['agent_id'])->first(array('firstName' , 'lastName'));
                              }
                          @endphp
                            @if(isset($usera) && ($usera->firstName || $usera->lastName))
                                <b>{{ $usera->firstName }}  {{ $usera->lastName }}</b>
                            @endif   
                        </p>
                    </td>
                    <td>
                    <p style="color: #000000; margin: 0px;">
                          @php
                            if($d != null && $d['performed_by']!=null)
                              {
                                $user=AlphaDirect\User::where('id',$d['performed_by'])->first(array('firstName' , 'lastName'));
                              }
                          @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   
                        </p>
                    </td>
                    <td>
                    <p style="color: #000000; margin: 0px;">

                           @php
                               if($d != null && $d['approved_date'] !=null)
                               {
                                   $approved_date = \Carbon\Carbon::parse($d['approved_date'])->format('d-m-Y') ;   
                               }
                           @endphp
                           @if(isset($approved_date) && $d != null && $d['approved_date'] != null)
                            {{ $approved_date }}
                           @endif

                        </p>
                   </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">

                            {{-- <b>{{ $d['compliance'] }}</b> --}}
                            @if($d['compliance']!= null)
                              @if($d['compliance']==1) <b>Yes</b> @else <b> No </b> @endif
                            @else
                               <b>No</b>
                            @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['Kyc_status'] != null)
                             <b>{{ $d['Kyc_status'] }}</b>
                           @endif
                        </p>
                    </td>
                    <td>
                        <p style="color: #000000; margin: 0px;">
                           @if($d['Kyc_status'] != null && ($d['Kyc_status'] == 'Unapprove' || $d['Kyc_status'] == 'rejected'))
                             <b>{{ $d['remark'] }}</b>
                           @endif
                        </p>
                    </td>
                </tr>
            @endforeach
        @else
            <p style="text-align:center">No data found</p>
        @endif
    </table>
    @else
    <p>No data found</p>
    @endif

    <p><a href="https://reports.alphadirect.co.bw/report/kycReportActivatedPolicies">Click here to get detailed report</a></p>
     <!-- Since Begining -->
</div>
</body>

</html>
