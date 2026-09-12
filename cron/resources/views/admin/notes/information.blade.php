<!doctype html >
<html>

<head>
    <TITLE>Customer Information Verification</TITLE>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <style>
        html {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            -webkit-text-size-adjust: 100%!important;
            -ms-text-size-adjust: 100%!important;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            -webkit-print-color-adjust: exact;
        }

        table,
        th,
        td {
            border: 1px solid #c1c6d1;
            border-collapse: collapse;
            padding: 4px 4px;
        }

        th,
        td {
            padding: 6px 10px;
            text-align: left;
        }
        /* ============================= */

        @media print {
            body {
                font-size: 14px;
                -webkit-text-size-adjust: 100%!important;
                -ms-text-size-adjust: 100%!important;
                -webkit-print-color-adjust: exact !important;
                color: #000 !important;
            }
            *,
            :after,
            :before {
                color: #000 !important;
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
            table,
            th,
            td {
                border: 1px solid #c1c6d1;
                border-collapse: collapse;
            }
            th,
            td {
                padding: 10px;
                text-align: left;
            }
            .table-responsive {
                width: auto;
            }
            p {
                margin: 0px;
                color: #000 !important;
            }
            h3 {
                color: #383884 !important;
                color: #000 !important;
            }
            a {
                color: #383884 !important;
            }
        }

        @page {
            margin: 0px 0px;
            padding: 0px 0px;
        }
    </style>
</head>

<body>

<div style="margin-bottom: 0px;max-width: 900px; margin: auto; position: relative;">
    <div style="position: absolute; top:0;left:0;">
        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/tcorner/top-corner.png" alt="" style="z-index: -1;">
    </div>

    <div style="position: relative; padding: 40px 0px;">
        <div class="inner-container clearfix" style="z-index: 11; max-width:650px;margin: auto; ">
            <div style="margin-bottom: 0px;text-align: center;">
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-logo/new_logo.png" alt="logo" width="230px">
            </div>
            <div>
                <p style="margin-bottom:30px;text-align: center;"> Policy & Customer Information </p>
            </div>
            <div style="width:100%;">
                <p style="margin-top:0px;margin-bottom: 10px;">{{ $created_date }}</p>
                @if($banking->billing == "RealPay")
                    <h4 style="margin-top:0px;margin-bottom: 10px;">{{ $banking->bankName }}</h4>
                    <h4 style="margin-top:0px;margin-bottom: 10px;">{{ $banking->branchCode }}</h4>
                @endif
            </div>

            <div style="width:100%;">
                <p style="margin-top:0px;margin-bottom: 10px;"> Dear {{ $data->firstName.' '.$data->lastName }} ,</p>
            </div>


            <!-- ===== Customer Info ======= -->
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Customer Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">First Name : </span> {{ ucwords($data->firstName) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Last Name : </span> {{ ucwords($data->lastName) }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang : </span>@if($data->omang) {{ $data->omang }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">passport : </span> @if($data->passport) {{ $data->passport }} @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Gender : </span> @if($data->gender == 1) Male @else Female @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Mobile Number : </span> {{ $data->cellphone }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Email : </span> {{ $data->email }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Date Of Birth : </span> {{ $data->dob }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang Expiry date : </span>{{ $data->omangExpiry }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Expiry date : </span>{{ $data->pExpiry }}</p>
                                </td>

                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Marital Status : </span>
                                        @if($data->maritalstatus == 1)
                                            Single
                                        @elseif($data->maritalstatus == 2)
                                            Married
                                        @elseif($data->maritalstatus == 3)
                                            Divorced
                                        @elseif($data->maritalstatus == 4)
                                            Widowed
                                        @elseif($data->maritalstatus == 5)
                                            Living Together(Not Married)
                                        @elseif($data->maritalstatus == 6)
                                            Living Seperately(Married)
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Physical Address : </span> {{ ucwords($data->address) }}</p>
                                </td>

                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">City: </span>
                                        @if($data->city != null)
                                            @if(is_numeric($data->city))
                                                {{  ucfirst(\AlphaDirect\Helper::getCityName($data->city))  }}
                                            @else
                                                {{  ucfirst($data->city)  }}
                                            @endif
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">State/District: </span> {{ $data->stateName }}</p>
                                </td>
                            </tr>
                            @if($data->passport != null)
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Issuing Country: </span> {{ $data->pic }}</p>
                                </td>
                                <td style="width: 0%;">

                                </td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>


             <!-- ===== Product info ======= -->
             <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Product Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Product : </span>{{ $data->product_name }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Plan : </span> {{ $data->plan_name }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium : </span> P{{ $data->premium }} <span style="font-size: 10px;">(Including VAT)</span></p>
                                </td>
                                <td style="width: 50%;">
                                    @if($data->premium_freq == 1)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Monthly</p>
                                    @elseif($data->premium_freq == null)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Monthly</p>
                                    @elseif($data->premium_freq == 2)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Three Installments</p>
                                    @elseif($data->premium_freq == 3)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Annual</p>
                                    @else
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> N/A</p>
                                    @endif
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Store : </span>{{ ucwords($data->store_name) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Agent : </span> {{ ucwords($data->agent_fname.' '.$data->agent_lname) }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @if ($bundles != null)

                @foreach($bundles as $bundle_product)
                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Product : </span>
                                @if ($bundle_product['product_id'] == 1)
                                P1 Million Accidental Death Insurance
                                @elseif ($bundle_product['product_id'] == 2)
                                Third Party Car Insurance
                                @elseif ($bundle_product['product_id'] == 3)
                                Motor Comprehensive
                                @elseif ($bundle_product['product_id'] == 4)
                                Legal Insurance
                                @elseif ($bundle_product['product_id'] == 5)
                                Cellphone and device insurance
                                @elseif ($bundle_product['product_id'] == 6)
                                Tyres and Rims Insurance
                                @else
                                -
                                @endif
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Plan : </span>
                                @if ($bundle_product['product_id'] == 1 && $bundle_product['plan_name'] == 1)
                                P1 Million Accidental Death Insurance
                                @elseif ($bundle_product['product_id'] == 2 && $bundle_product['plan_name'] == 2)
                                P29_P100000_Cover
                                @elseif ($bundle_product['product_id'] == 2 && $bundle_product['plan_name'] == 3)
                                P39_P500000_Cover
                                @elseif ($bundle_product['product_id'] == 2 && $bundle_product['plan_name'] == 4)
                                P49_P1000000_Cover
                                @elseif ($bundle_product['product_id'] == 4 && $bundle_product['plan_name'] == 5)
                                P49_Legal
                                @elseif ($bundle_product['product_id'] == 4 && $bundle_product['plan_name'] == 6)
                                P79_Funeral_cover
                                @elseif ($bundle_product['product_id'] == 4 && $bundle_product['plan_name'] == 7)
                                P79_Funeral_cover
                                @elseif ($bundle_product['product_id'] == 3 && $bundle_product['plan_name'] == 8)
                                P79_Funeral_cover
                                @elseif ($bundle_product['product_id'] == 5 && $bundle_product['plan_name'] == 9)
                                P79_Funeral_cover
                                @elseif ($bundle_product['product_id'] == 6 && $bundle_product['plan_name'] == 10)
                                P79_Funeral_cover
                                @else
                                -
                                @endif

                            </p>
                        </td>
                    </tr>
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium : </span> P{{ $bundle_product['premium'] }} <span style="font-size: 10px;">(Including VAT)</span></p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Final Premium : </span> P{{ $bundle_product['final_premium'] }} <span style="font-size: 10px;">(Including VAT)</span></p>
                        </td>
                    </tr>
                </table>
                @endforeach
            @endif

            <!-- ======  Beneficiary info ========= -->
            {{-- @if($data->has_member == 1) --}}
            @if(!empty($benef))
            {{-- @if(count($benef)>0) --}}
                <div>
                    @foreach($benef as $key =>$b)
                        @if ($b->relation == 'Spouse')
                               <br><br>
                                <div>
                                    <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;">Spouse Details</h3>
                                </div>
                                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Name</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->first_name }} {{ $b->middle_name }} {{ $b->last_name }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Date of Birth</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->dob }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Omang</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">
                                                @if(!empty($b->omang))
                                                {{ $b->omang }}
                                                @else
                                                N/A
                                                @endif
                                            </p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Passport </h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">
                                                @if(!empty($b->passport))
                                                {{ $b->passport }}
                                                @else
                                                N/A
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Omang Expiry</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">
                                                @if(!empty($b->legalOmangExpiry))
                                                {{ $b->legalOmangExpiry }}
                                                @else
                                                N/A
                                                @endif
                                            </p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Passport Expiry</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">
                                                @if(!empty($b->legalPassportExpiry))
                                                {{ $b->legalPassportExpiry }}
                                                @else
                                                N/A
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Email</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->email }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Mobile Number</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->cellphone }}</p>
                                        </td>
                                    </tr>
                                </table>
                                @else
                                <br><br><br>
                                <div>
                                    <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;">Beneficiary Information : {{ $key + 1  }}</h3>
                                </div>
                                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Relation</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->relation }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">First Name</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->first_name }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Last Name</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->last_name }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Gender </h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">@if($b->gender == 1) Male @else Female @endif </p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Date of Birth</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->dob }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Omang</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->omang }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Passport</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->passport }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">How much Beneficiary payment? (%)</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $b->payment }}</p>
                                        </td>
                                    </tr>
                                </table>
                        @endif
                    @endforeach
                </div>
            @endif
            {{-- @endif --}}
            <!-- ======  Legal info ========= -->
            @if(!empty($data->e_name))
                <div>
                    <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                        <tr>
                            <td style='border:none;padding: 0px;'>
                                <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Employer Details</h3>
                                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            @if(!empty($data->e_name))
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Employer Name</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $data->e_name }}</p>
                                            @endif
                                        </td>
                                        <td style="width: 50%;">
                                            @if(!empty($data->emp_no))
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Employee Number	</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $data->emp_no }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            @if(!empty($data->emp_phone))
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Cellphone</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $data->emp_phone }}</p>
                                            @endif
                                        </td>
                                        <td style="width: 50%;">
                                            @if(!empty($data->salary_pay_date))
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Salary Pay Date</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $data->salary_pay_date }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif

            <!-- ======  Vehicle info ========= -->
            {{-- @if($data->has_vehicle == 1) --}}
            @if(isset($vehicle))
            <div>
                <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                    <tr>
                        <td style='border:none;padding: 0px;'>
                            <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Vehicle Information</h3>
                            <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                <tr style="width: 100%;">
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Is your car imported from Japan?</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">@if($vehicle->is_imported == 1) Yes @else No @endif</p>
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Vehicle Make</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->make }}</p>
                                    </td>
                                </tr>
                                <tr style="width: 100%;">
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Manufacturing Year</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->year }}</p>
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Vehicle Model</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->model }}</p>
                                    </td>
                                </tr>
                                <tr style="width: 100%;">
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Estimated Value of Vehicle</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">
                                            @if (isset($vehicle->estimated_value))
                                            {{ $vehicle->estimated_value }}
                                            @else
                                             N/A
                                            @endif
                                        </p>
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Number of Prior Accidents</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->claim_count }}</p>
                                    </td>
                                </tr>
                                <tr style="width: 100%;">
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Vehicle Registration Number</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->vehiclePlate }}</p>
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Vehicle Purpose</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">@if($vehicle->purpose == 27) Personal @else Personal @endif</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <br><br>
                <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Vehicle Images</h3>
                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                    <tr style="width: 90%;">
                        <td style="width: 15%;">
                            <p>Right</p>
                            @if($vehicle && $vehicle->right)
                                <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->right) }}" alt="" style="max-width: 100px; height: auto; border-radius: 12px;">
                                <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->right) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>
                        <td style="width: 15%;">
                            <p>Left</p>
                            @if($vehicle && $vehicle->left != null)
                                <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->left) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->left) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>

                        <td style="width: 15%;">
                            <p>Front</p>
                            @if($vehicle && $vehicle->front != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->front) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->front) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>
                        <td style="width: 15%;">
                            <p>Back</p>
                            @if($vehicle && $vehicle->back != null)
                                <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->back) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->back) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>
                        <td style="width: 15%;">
                            <p>Registration</p>
                            @if($vehicle && $vehicle->vehicleRegistration != null)
                                <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->vehicleRegistration) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->vehicleRegistration) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>
                        <td style="width: 15%;">
                            <p>Invoice</p>
                            @if($vehicle && $vehicle->vehicle_valuation != null)
                                <img src="{{ \AlphaDirect\Helper::getImageSrc($vehicle->vehicle_valuation) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                <a href="{{ \AlphaDirect\Helper::getImageSrc($vehicle->vehicle_valuation) }}" target="_blank" style="text-decoration: none">View</a>
                            @else
                                <span>Not uploaded</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
            @endif
            <hr>
            <!-- ======  Cellphone info ========= -->
            {{-- @if($data->product_id == 5) --}}
            @if(!empty($policy_cellphone))
                @foreach($policy_cellphone as $key => $policy_cellphone_data)
                <div>
                    <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                        <tr>
                            <td style='border:none;padding: 0px;'>
                                <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Device Details : {!! $key+1 !!}</h3>
                                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Device Type :</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $policy_cellphone_data->device_type }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            @if($policy_cellphone_data->device_type == 'Cellphone')
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">IMEI</h4>
                                            @else
                                                <h4>Serial Number</h4>
                                            @endif
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $policy_cellphone_data->imei }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Device Brand </h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $policy_cellphone_data->cell_phone_make }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Device Model </h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $policy_cellphone_data->cell_phone_model }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 100%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Device Value </h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">P{{ $policy_cellphone_data->phone_value }}</p>
                                        </td>
                                        <td>
                                        </td>
                                    </tr>

                                </table>
                            </td>
                        </tr>
                    </table>
                    <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Device Images</h3>
                    <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                        <tr style="width: 90%;">
                            <td style="width: 15%;">
                                <p>Front</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_front != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_front) }}" alt="" style="max-width: 100px; height: auto; border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_front) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>
                            <td style="width: 15%;">
                                <p>Back</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_back != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_back) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_back) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>

                            <td style="width: 15%;">
                                <p>Left</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_left != null)
                                        <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_left) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                        <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_left) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>
                            <td style="width: 15%;">
                                <p>Right</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_right != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_right) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_right) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>
                            <td style="width: 15%;">
                                <p>Top</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_top != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_top) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_top) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>
                            <td style="width: 15%;">
                                <p>Bottom</p>
                                @if($policy_cellphone_data && $policy_cellphone_data->cell_phone_bottom != null)
                                    <img src="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_bottom) }}" alt="" style="max-width: 100px; height: auto;border-radius: 12px;">
                                    <a href="{{ \AlphaDirect\Helper::getImageSrc($policy_cellphone_data->cell_phone_bottom) }}" target="_blank" style="text-decoration: none">View</a>
                                @else
                                    <span>Not uploaded</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
               @endforeach
            @endif

            <!-- ===== Customer banking and billing info ======= -->
            <div>
                <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                    <tr>
                        <td style='border:none;padding: 0px;'>
                            <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Customer Banking And Billing Information</h3>
                            <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                                <tr style="width: 100%;">
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">payment method</h4>
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->billing }}</p>
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Billing day</h4>
                                        @if ($banking->billing_day != null)
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->billing_day }}</p>
                                        @else
                                        <p style="margin-bottom: 6px; margin-top: 0px;"> N/A </p>
                                        @endif
                                    </td>
                                    <td style="width: 50%;">
                                        <h4 style="margin-top: 0px;margin-bottom: 4px;">Financial Interest</h4>
                                        @if ($vehicle != null)
                                        <p style="margin-bottom: 6px; margin-top: 0px;">{{ $vehicle->financial_interest }}</p>
                                        @else
                                        <p style="margin-bottom: 6px; margin-top: 0px;"> N/A </p>
                                        @endif
                                    </td>
                                </tr>
                               @if($banking->billing == "RealPay")
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Bank</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->bankName }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Branch</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->branchCode }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Account Number</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->accountNumber }}</p>
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Confirm Account Number</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $banking->accountNumber }}</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Account Type</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">@if($banking->accountType == 1) Cheque @elseif($banking->accountType == 2) Savings @else N/A @endif</p>
                                        </td>
                                        <td style="width: 50%;">
                                            <h4 style="margin-top: 0px;margin-bottom: 4px;">Billing start date</h4>
                                            <p style="margin-bottom: 6px; margin-top: 0px;">{{ $data->billingStartDate }}</p>
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>
                <div>
                    @if ($data->note != null)
                    <p> <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Note</h3>{{ $data->note }}</p>
                    @else
                        <p> </p>
                    @endif
                </div>
            </div>

            <!-- ===== Yours Sincerely ======= -->
            <div style="width:100%;">
                <p style="margin-top: 20px;">Kindly render any assistance they may need and do not hesitate to contact the undersigned for any enquiries, clarifications and amendments.</p>
                <p style="margin-bottom: 0px; margin-top: 0px;">Yours Sincerely,</p>
                <p style="margin-bottom: 6px; margin-top: 0px;">For <span style="font-weight: 600;">Alpha Direct Insurance Company</span></p>
                <div class="clearfix">
                    <p style="font-size: 14px;">System Generated(+267 392-2718)</p>
                    <h5 style="margin: 0px;">Digital Policy Underwriting Unit - Alpha Direct</h5>
                    <a href="#" style="font-style: italic; font-size: 14px;font-weight: 500;">underwriting@alphadirect.co.bw</a>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <p style="margin-bottom: 100px"></p>
            </div>
        </div>
        <div style="position: absolute; bottom:0;right:0;z-index: -1;">
            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-bottom-corner/bottom-corner.png" alt="" style="z-index: -1;">
        </div>
    </div>
</div>


</body>

</html>
