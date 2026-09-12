<!Doctype html>
<html>

<head>
    {{-- <TITLE>{{ $title }}</TITLE> --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> --}}
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
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
            width: auto;
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

        /* .column {
            float: left;
            width: 50%;
        } */

        /* Clear floats after the columns */
        /* .row:after {
            content: "";
            display: table;
            clear: both;
        } */

        table.center {
            margin-left: auto;
            margin-right: auto;
        }

        div#AmountDiv {
            border-style: solid;
        }

        div.centered
        {
            text-align: center;
        }

        div.centered table
        {
            margin: 0 auto;
            text-align: left;
        }
        .outer-div {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
        }

        .inner-div {
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            height: auto;
        }

        .table-header,
        .table-footer {
            border-collapse: collapse;
            margin: 5px;
            width: 460px;
        }

        th,
        td {
            text-align: center;
            /* column-width: 50%; */
            /* padding: 5px 5px; */
        }
    </style>
</head>

<body>
    <div id="content" style="margin-bottom: 0px;">
        <div style="text-align: left">
            <h4>{{ Carbon::now()->format('d/M/Y H:i:s') }}</h4>
        </div>
        <div style="text-align: center">
            <img src="{{ asset('alphadirect_logo.png') }}" class="rounded mx-auto d-block" alt="..."
                width="30%">
        </div>

        <div class="container" style="width: auto;">
            <table class="center table-responsive" style="border-collapse: collapse;width:100%;">
                <tr>
                    <th><b>Botswana Daily Collections Report</b></th>
                </tr>
                <tr>
                    <td><b>Revolving 30 Days</b></td>
                </tr>
                <tr>
                    <td><b>From {{Carbon::createFromFormat('Y-m-d', $toDate)->format('d M Y')}} - {{Carbon::createFromFormat('Y-m-d', $fromDate)->format('d M Y')}}</b></td>
                </tr>
            </table>
        </div>


        <br><br><br><br>
        <div class="container" id="AmountDiv" style="width: auto;">
            <div class="outer-div" style="width: auto;">
                <div class="inner-div" style="float: left;">
                    <table class="table-responsive table-header">
                        <tr>
                            <th><b>NEW SALES (Amount)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>

                    <table class="table-responsive table-footer">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($amtNewSales as $getNewSales)
                                @if ($getNewSales['getBilled']->paymentMethod != "")

                                    <tr>
                                        <td>{{$getNewSales['getBilled']->paymentMethod}}</td>
                                        @if (isset($getNewSales['getBilled']->total_billed_amount))
                                            <td>P {!! number_format($getNewSales['getBilled']->total_billed_amount,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif

                                        @if (isset($getNewSales['getPaid']->total_paid_amount))
                                            <td>P {!! number_format($getNewSales['getPaid']->total_paid_amount,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif
                                    </tr>
                                @endif
                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($totalAmtNewSalesBilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($totalAmtNewSalesPaid,2,'.',',') !!}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="inner-div" style="float: right;">
                    <table class="right table-responsive table-header">
                        <tr>
                            <th><b>RECURRING COLLECTION (Amount)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>

                    <table class="right table-responsive table-footer">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                                <th><b>Collection Rate</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($amtRecurringCollection as $getRecurringCollect)
                                @if ($getRecurringCollect['getBilled']->paymentMethod != "")
                                    <tr>
                                        <td>{{$getRecurringCollect['getBilled']->paymentMethod}}</td>
                                        @if (isset($getRecurringCollect['getBilled']->total_recurring_billed_amount))
                                            <td>P {!! number_format($getRecurringCollect['getBilled']->total_recurring_billed_amount,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif

                                        @if (isset($getRecurringCollect['getPaid']->total_recurring_paid_amount))
                                            <td>P {!! number_format($getRecurringCollect['getPaid']->total_recurring_paid_amount,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif

                                        @if (isset($getRecurringCollect['getPaid']) && $getRecurringCollect['getBilled'])
                                            @if ($getRecurringCollect['getBilled']->total_recurring_billed_amount != 0)
                                                <td>{!! number_format(($getRecurringCollect['getPaid']->total_recurring_paid_amount / $getRecurringCollect['getBilled']->total_recurring_billed_amount) * 100,2,'.',',') !!} %</td>
                                            @else
                                                <td>0 %</td>
                                            @endif
                                        @else
                                            <td>0 %</td>
                                        @endif
                                    </tr>
                                @endif
                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($totalAmtRecurringBilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($totalAmtRecurringPaid,2,'.',',') !!}</b></td>

                                @if ($totalAmtRecurringPaid != 0 && $totalAmtRecurringBilled != 0)
                                    <td><b>{!! number_format(($totalAmtRecurringPaid / $totalAmtRecurringBilled) * 100,2,'.',',') !!} %</b></td>
                                @else
                                    <td>0 %</td>
                                @endif

                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="outer-div centered" style="margin-top: 50%;">
                <div class="innder-div-two">
                    <table class="right table-responsive table-header" style="width: 500px;">
                        <tr>
                            <th><b>Sales + Collection (Amount)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>

                    <table class="right table-responsive table-footer" style="width: 500px;">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method (BWP000)</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                                <th><b>Collection Rate</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- @php
                                $totalBilledSalesAndColl = 0;
                                $totalPaidSalesColl = 0;
                            @endphp --}}
                            {{-- @php dd($amtRecurringAndNewSales); @endphp --}}
                            @foreach ($amtRecurringAndNewSales as $getRecurrNewSales)
                            @php
                                $billedSalesColl = 0;
                                $paidSalesColl = 0;

                                // dd($getRecurrNewSales);
                                // if (isset($item['getBilledNewSales'])) {
                                //     if (isset($item['getBilledRecurring'])) {
                                        $billedSalesColl = (isset($getRecurrNewSales['getBilledNewSales']->total_billed_amount) ? $getRecurrNewSales['getBilledNewSales']->total_billed_amount:0) + (isset($getRecurrNewSales['getBilledRecurring']->total_recurring_billed_amount) ? $getRecurrNewSales['getBilledRecurring']->total_recurring_billed_amount:0);
                                //     } else {
                                //         $billedSalesColl = $getRecurrNewSales['getBilledRecurring']->total_recurring_billed_amount + 0;
                                //     }
                                // } else {
                                //     $billedSalesColl = $getRecurrNewSales['getBilledNewSales']->total_billed_amount + 0;
                                // }

                                // if (isset($item['getPaidNewSales'])) {
                                //     if (isset($item['getPaidRecurring'])) {
                                        $paidSalesColl = (isset($getRecurrNewSales['getPaidNewSales']->total_paid_amount) ? $getRecurrNewSales['getPaidNewSales']->total_paid_amount:0)  + (isset($getRecurrNewSales['getPaidRecurring']->total_recurring_paid_amount) ? $getRecurrNewSales['getPaidRecurring']->total_recurring_paid_amount:0);
                                //     } else {
                                //         $paidSalesColl = $getRecurrNewSales['getPaidRecurring']->total_recurring_paid_amount + 0;
                                //     }
                                // } else {
                                //     $paidSalesColl = $getRecurrNewSales['getPaidNewSales']->total_paid_amount + 0;
                                // }

                            @endphp

                            @if ($getRecurrNewSales['getBilledNewSales']->paymentMethod != "")
                                <tr>
                                    <td>{{$getRecurrNewSales['getBilledNewSales']->paymentMethod}}</td>
                                    <td>
                                        P {!! number_format($billedSalesColl,2,'.',',') !!}
                                    </td>

                                    <td>P {!! number_format($paidSalesColl,2,'.',',') !!}</td>

                                    @if ($paidSalesColl != 0 && $billedSalesColl != 0)
                                        <td>{!! number_format(($paidSalesColl / $billedSalesColl) * 100,2,'.',',') !!} %</td>
                                    @else
                                        <td>0 %</td>
                                    @endif

                                </tr>
                            @endif

                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($salesCollTotalbilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($salesCollTotalPaid,2,'.',',') !!}</b></td>

                                @if ($salesCollTotalPaid != 0 && $salesCollTotalbilled != 0)
                                    <td><b>{!! number_format(($salesCollTotalPaid / $salesCollTotalbilled) * 100,2,'.',',') !!} %</b></td>
                                @else
                                    <td>0 %</td>
                                @endif

                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <br><br><br><br>
        <div class="container" id="AmountDiv" style="width: auto;">
            <div class="outer-div" style="width: auto;">
                <div class="inner-div" style="float: left;">
                    <table class="table-responsive table-header">
                        <tr>
                            <th><b>NEW SALES (No. of Transactions)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>

                    <table class="table-responsive table-footer">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($amtNewSales as $getNewSales)
                            @if ($getNewSales['getBilled']->paymentMethod != "")
                                <tr>
                                    <td>{{$getNewSales['getBilled']->paymentMethod}}</td>
                                    @if (isset($getNewSales['getBilled']->new_sales))
                                        <td>P {!! number_format($getNewSales['getBilled']->new_sales,2,'.',',') !!}</td>
                                    @else
                                        <td> - </td>
                                    @endif

                                    @if (isset($getNewSales['getPaid']->new_sales))
                                        <td>P {!! number_format($getNewSales['getPaid']->new_sales,2,'.',',') !!}</td>
                                    @else
                                        <td> - </td>
                                    @endif
                                </tr>
                            @endif
                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($totalCountNewSalesBilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($totalCountNewSalesPaid,2,'.',',') !!}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="inner-div" style="float: right;">
                    <table class="right table-responsive table-header">
                        <tr>
                            <th><b>RECURRING COLLECTION (No. of Transactions)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>

                    <table class="right table-responsive table-footer">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                                <th><b>Collection Rate</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($amtRecurringCollection as $getRecurringCollect)
                                @if ($getRecurringCollect['getBilled']->paymentMethod != "")
                                    <tr>
                                        <td>{{$getRecurringCollect['getBilled']->paymentMethod}}</td>
                                        @if (isset($getRecurringCollect['getBilled']->recurring))
                                            <td>P {!! number_format($getRecurringCollect['getBilled']->recurring,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif

                                        @if (isset($getRecurringCollect['getPaid']->recurring))
                                            <td>P {!! number_format($getRecurringCollect['getPaid']->recurring,2,'.',',') !!}</td>
                                        @else
                                            <td> - </td>
                                        @endif

                                        @if (isset($getRecurringCollect['getPaid']) && isset($getRecurringCollect['getBilled']))
                                            @if ($getRecurringCollect['getPaid']->recurring != 0 && $getRecurringCollect['getBilled']->recurring != 0)
                                                <td>{!! number_format(($getRecurringCollect['getPaid']->recurring / $getRecurringCollect['getBilled']->recurring) * 100,2,'.',',') !!} %</td>
                                            @else
                                                <td>0 %</td>
                                            @endif
                                        @else
                                            <td>0 %</td>
                                        @endif

                                    </tr>
                                @endif
                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($totalCountRecurringBilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($totalCountRecurringPaid,2,'.',',') !!}</b></td>

                                @if ($totalCountRecurringPaid != 0 && $totalCountRecurringBilled != 0)
                                    <td><b>{!! number_format(($totalCountRecurringPaid / $totalCountRecurringBilled) * 100,2,'.',',') !!} %</b></td>
                                @else
                                    <td>0 %</td>
                                @endif

                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="outer-div centered">
                <div class="innder-div-two"  style="margin-top: 50%;">
                    <table class="right table-responsive table-header" style="width: 500px;">
                        <tr>
                            <th><b>Sales + Collection (Count)</b></th>
                        </tr>
                        <tr>
                            <td><b>Billed vs PAID</b></td>
                        </tr>
                    </table>
                    <table class="right table-responsive table-footer" style="width: 500px;">
                        <thead style="color:white;background-color:#1569C7;">
                            <tr>
                                <th><b>Payment Method (BWP000)</b></th>
                                <th><b>Billed</b></th>
                                <th><b>Paid</b></th>
                                <th><b>Collection Rate</b></th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach ($amtRecurringAndNewSales as $getRecurrNewSales)
                            @php
                                if (isset($getRecurrNewSales['getBilledNewSales'])) {
                                    if (isset($getRecurrNewSales['getBilledRecurring'])) {
                                        $billedSalesColl = $getRecurrNewSales['getBilledNewSales']->new_sales + $getRecurrNewSales['getBilledRecurring']->recurring;
                                    }
                                }

                                if (isset($getRecurrNewSales['getPaidNewSales'])) {
                                    if (isset($getRecurrNewSales['getPaidRecurring'])) {
                                        $paidSalesColl = $getRecurrNewSales['getPaidNewSales']->new_sales + $getRecurrNewSales['getPaidRecurring']->recurring;
                                    }
                                }


                            @endphp

                            @if ($getRecurrNewSales['getBilledNewSales']->paymentMethod != "")
                                <tr>
                                    <td>{{$getRecurrNewSales['getBilledNewSales']->paymentMethod}}</td>
                                    <td>
                                        P {!! number_format($billedSalesColl,2,'.',',') !!}
                                    </td>

                                    <td>P {!! number_format($paidSalesColl,2,'.',',') !!}</td>

                                    @if ($paidSalesColl != 0 && $billedSalesColl != 0)
                                        <td>{!! number_format(($paidSalesColl / $billedSalesColl) * 100,2,'.',',') !!} %</td>
                                    @else
                                        <td>0 %</td>
                                    @endif
                                </tr>
                            @endif

                            @endforeach
                            <tr>
                                <td><b>Total</b></td>
                                <td><b>P {!! number_format($salesCollCountTotalbilled,2,'.',',') !!}</b></td>
                                <td><b>P {!! number_format($salesCollCountTotalPaid,2,'.',',') !!}</b></td>

                                @if ($salesCollCountTotalPaid != 0 && $salesCollCountTotalbilled != 0)
                                    <td><b>{!! number_format(($salesCollCountTotalPaid / $salesCollCountTotalbilled) * 100,2,'.',',') !!} %</b></td>
                                @else
                                    <td>0 %</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
