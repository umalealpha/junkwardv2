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

<div style="margin-bottom: 0px;max-width: 850px; margin: auto; position: relative;">
    <!-- <div style="position: absolute; top:0;left:0;">
        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/tcorner/top-corner.png" alt="" style="z-index: -1;">
    </div> -->

    <div style="position: relative; padding: 40px 0px;margin-left: 60px;margin-right: 60px;">
        <div class="inner-container clearfix" style="z-index: 11; margin: auto; ">
            <div style="margin-bottom: 0px;text-align: center;">
                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-logo/new_logo.png" alt="logo" width="270px" height="125px" style="width: 270px !important; height:125px !important; " >
            </div>
            <div>
                @if($product->id == 1)
                <h2 style="margin-bottom:30px;text-align: center;"> INSTANT ACCIDENTAL DEATH INSURANCE POLICY</h2>
                @endif
                @if($product->id == 2)
                <h2 style="margin-bottom:30px;text-align: center;"> INSTANT MOTOR THIRD PARTY INSURANCE POLICY</h2>
                @endif
               
                @if($product->id == 4)
                <h2 style="margin-bottom:30px;text-align: center;"> INSTANT LEGAL INSURANCE POLICY</h2>
                @endif
                @if($product->id == 5)
                <h2 style="margin-bottom:30px;text-align: center;"> INSTANT MOBILE ELECTRONIC DEVICE POLICY</h2>
                @endif
                @if($product->id == 9)
                <h2 style="margin-bottom:30px;text-align: center;"> INSTANT HOSPITAL CASH BACK POLICY</h2>
                @endif
                @if($product->id == 10)
                     @if($policy->plan_id ==21)
                    <h2 style="margin-bottom:30px;text-align: center;"> HEALTH IN A BOX BASIC</h2>
                     @endif
                     @if($policy->plan_id ==22)
                     <h2 style="margin-bottom:30px;text-align: center;"> HEALTH IN A BOX PLUS</h2>
                     @endif
                    
                @endif
                <h4 style="margin-bottom:10px;text-align: center;"> Thank you for your purchase!</h4>
            </div>
           


            <!-- ===== Customer Info ======= -->
            <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
                <tr>
                    <td style='border:none;padding: 0px;'>
                        <h3 style="margin-top: 0px;margin-bottom: 10px; color: #383884!important;-webkit-print-color-adjust: exact;padding-top: 20px;"> Customer Information</h3>
                        <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">First Name : </span> {{ ucwords($firstName) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Last Name : </span> {{ ucwords($lastName) }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang : </span>@if($profile->omang) {{ $profile->omang }} @else - @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">passport : </span> @if($profile->passport) {{ $profile->passport }} @else - @endif</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Gender : </span> @if($profile->gender == 1) Male @else Female @endif</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Mobile Number : </span> {{ $customer->cellphone }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Email : </span> {{ $customer->email }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Date Of Birth : </span> {{ $profile->dob }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang Expiry date : </span>{{ $customer_kyc->omangExpiry }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Expiry date : </span>{{ $customer_kyc->passportExpiry }}</p>
                                </td>

                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Marital Status : </span>
                                        @if($profile->maritalstatus == 1)
                                            Single
                                        @elseif($profile->maritalstatus == 2)
                                            Married
                                        @elseif($profile->maritalstatus == 3)
                                            Divorced
                                        @elseif($profile->maritalstatus == 4)
                                            Widowed
                                        @elseif($profile->maritalstatus == 5)
                                            Living Together(Not Married)
                                        @elseif($profile->maritalstatus == 6)
                                            Living Seperately(Married)
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Physical Address : </span> {{ ucwords($profile->address) }}</p>
                                </td>

                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">City: </span>
                                        @if($profile->city != null)
                                            @if(is_numeric($profile->city))
                                                {{  ucfirst(\AlphaDirect\Helper::getCityName($profile->city))  }}
                                            @else
                                                {{  ucfirst($profile->city)  }}
                                            @endif
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">State/District: </span> {{  ucfirst($state)  }}</p>
                                </td>
                            </tr>
                            @if($passpostIssueCountry != null)
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Issuing Country: </span> {{ $passpostIssueCountry }}</p>
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
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Product : </span>{{ $product->name }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Plan : </span> {{ $product_plan->name }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Premium : </span> P{{ $premium }} <span style="font-size: 10px;">(Including VAT)</span></p>
                                </td>
                                <td style="width: 50%;">
                                    @if($policy->premium_freq == 1)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Monthly</p>
                                    @elseif($policy->premium_freq == null)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Monthly</p>
                                    @elseif($policy->premium_freq == 2)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Three Installments</p>
                                    @elseif($policy->premium_freq == 3)
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> Annual</p>
                                    @else
                                        <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Frequency : </span> N/A</p>
                                    @endif
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Store : </span>{{ ucwords($policy_stores) }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Agent : </span> {{ ucwords($agentName) }}</p>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">PolicyNumber : </span>{{ $policy->policyNumber }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;"></p>
                                </td>
                            </tr>
                            @if($product->id == 10)
                                <tr style="width: 100%;">
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">No. Adult Dependents : </span>{{ $AdultDependents }}</p>
                                </td>
                                <td style="width: 50%;">
                                    <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">No. Child Dependents: </span>{{ $ChildDependents }}</p>
                                </td>
                               </tr>
                               @endif
                        </table>
                    </td>
                </tr>
            </table>

            
            <!-- ===== Yours Sincerely ======= -->
        
            <div style="margin-top: 10px; margin-bottom: 10px">
            <img src="https://alphadirect.s3.ap-south-1.amazonaws.com/Document/Policy_Document/schudulerimg/Screenshot_(1088).png" alt="" width="100%">
            </div>
            
        
         
            <div style="position: absolute; bottom:0;text-align: center;">
                <small style="font-size: 10px;">Copyright  {{ date('Y') }} . Alpha Direct Insurance Company (Pty) Ltd. All Rights Reserve Alpha Direct Insurance Company (Proprietary) Limited is an Authorised
                Financial Services Provider | NBFIRA License No. 2/9/179.</small>
            </div>
        </div>
       
    
        <!-- <div style="position: absolute; bottom:0;right:0;z-index: -1;">
            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/new-bottom-corner/bottom-corner.png" alt="" style="z-index: -1;">
        </div> -->
    </div>
    
</div>


</body>

</html>
