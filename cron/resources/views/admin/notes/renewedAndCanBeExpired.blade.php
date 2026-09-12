
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
        <p style="font-size: large">Total number of policies Renewed Today: {{ count($policies) }} </p>
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
                            <b>Claim Count</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Policy Expiry Date</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Old Premium</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>New Premium</b>
                        </p>
                    </th>
                     <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Premium Changed (in percentage)</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Payment Method</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Frequency</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Rerated</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Renewed</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Renew Count</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Terms Present</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Term Expiry</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Email Sent</b>
                        </p>
                    </th>
                    <th style="background-color: #dbe5f1;">
                        <p style="text-align: center;margin: 0px;">
                            <b>Sms Sent</b>
                        </p>
                    </th>
                </tr>
            </thead>
            @foreach($policies as $key=> $w)
                <tbody>
                    <tr>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $key+1 }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->policyNumber }}</b>
                            </p>
                        </td>

                        @php $policysi =  \AlphaDirect\Policy::where('policyNumber',$w->policyNumber)->first(['id','customer_id']); @endphp
                        <td>
                           <p style="color: #000000; margin: 0px;">
                               <b>
                                @if ($policysi->customer !=null && $policysi->customer->firstName != null && $policysi->customer->lastName != null)
                                   {{ $policysi->customer->firstName.' '.$policysi->customer->lastName }}
                                @else
                                      -
                                @endif
                            </b>
                           </p>
                       </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->claim_count }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                               <b>{{ $w->expiry_date }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                               <b> {{ $w->old_premium }} </b>
                            </p>
                        </td>
                         <td>
                            <p style="color: #000000; margin: 0px;">
                                <b> {{ $w->new_premium }}</b>
                            </p>
                        </td>
                         <td>
                            <p style="color: #000000; margin: 0px;">
                                 <b>
                                    @if( $w->premium_changed_in_per != null)
                                        {{ $w->premium_changed_in_per }}
                                    @else
                                        -
                                    @endif
                                 </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b> {{ $w->paymentMethod }}
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if ($w->paymentFrequency != null)
                                        @if ($w->paymentFrequency == 1)
                                        Monthly
                                        @elseif ($w->paymentFrequency == 2)
                                        Three Instalments
                                        @elseif ($w->paymentFrequency == 3)
                                        Annual
                                        @else
                                        -
                                        @endif
                                    @else
                                       -
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if ($w->is_rated == 0)
                                    No
                                    @else
                                    Yes
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if ($w->is_renewed == 0)
                                    No
                                    @else
                                    Yes
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->renew_count }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->terms_present }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if ($w->term_expiry != null)
                                        {{ $w->term_expiry }}
                                    @else
                                        -
                                    @endif
                                </b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->email_sent }}</b>
                            </p>
                        </td>
                        <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->sms_sent }}</b>
                            </p>
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @else
        <p>Total number policies Renewed Today: 0</p>
    @endif



    @isset($policies_expired)
        @if(count($policies_expired) > 0)
            <p style="font-size: large">Total number of policies can be Expired: {{ count($policies_expired) }} </p>
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
                                <b>Claim Count</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Policy Expiry Date</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Old Premium</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>New Premium</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Premium Changed (in percentage)</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Payment Method</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Frequency</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Rerated</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Renewed</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Renew Count</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Terms Present</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Term Expiry</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Email Sent</b>
                            </p>
                        </th>
                        <th style="background-color: #dbe5f1;">
                            <p style="text-align: center;margin: 0px;">
                                <b>Sms Sent</b>
                            </p>
                        </th>
                    </tr>
                </thead>
                @foreach($policies_expired as $key=> $w)
                    <tbody>
                        <tr>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $key+1 }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->policyNumber }}</b>
                                </p>
                            </td>

                            @php $policysi =  \AlphaDirect\Policy::where('policyNumber',$w->policyNumber)->first(['id','customer_id']); @endphp
                            <td>
                            <p style="color: #000000; margin: 0px;">
                                <b>
                                    @if ($policysi->customer !=null && $policysi->customer->firstName != null && $policysi->customer->lastName != null)
                                    {{ $policysi->customer->firstName.' '.$policysi->customer->lastName }}
                                    @else
                                        -
                                    @endif
                                </b>
                            </p>
                        </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->claim_count }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                <b>{{ $w->expiry_date }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                <b> {{ $w->old_premium }} </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b> {{ $w->new_premium }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>
                                        @if( $w->premium_changed_in_per != null)
                                            {{ $w->premium_changed_in_per }}
                                        @else
                                            -
                                        @endif
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b> {{ $w->paymentMethod }}
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>
                                        @if ($w->paymentFrequency != null)
                                            @if ($w->paymentFrequency == 1)
                                            Monthly
                                            @elseif ($w->paymentFrequency == 2)
                                            Three Instalments
                                            @elseif ($w->paymentFrequency == 3)
                                            Annual
                                            @else
                                            -
                                            @endif
                                        @else
                                        -
                                        @endif
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>
                                        @if ($w->is_rated == 0)
                                        No
                                        @else
                                        Yes
                                        @endif
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>
                                        @if ($w->is_renewed == 0)
                                        No
                                        @else
                                        Yes
                                        @endif
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->renew_count }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->terms_present }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>
                                        @if ($w->term_expiry != null)
                                            {{ $w->term_expiry }}
                                        @else
                                            -
                                        @endif
                                    </b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->email_sent }}</b>
                                </p>
                            </td>
                            <td>
                                <p style="color: #000000; margin: 0px;">
                                    <b>{{ $w->sms_sent }}</b>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        @else
            <p>Total number policies can expired: 0</p>
        @endif
    @endisset


</div>
</body>

</html>
