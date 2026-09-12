<!Doctype html >
<html>

    <head>
        <TITLE>Legal Policy Document</TITLE>
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

<body style="padding: 6px">
    <header>
        <div>
        <img class="center" style="width: 360px;margin-bottom: 10px;margin-left: 152px" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
        <p style="color: #f37000;text-align: center; font-size: x-large!important; font-weight: 600!important">INSTANT LEGAL INSURANCE POLICY</p>
        </div>
        <h4 style="color: #f37000;text-align: center;"> Thank you for your purchase! </h4>
    </header>

    <!-- ===== Customer Info ======= -->
    <table border="0" style="width: 100%;border:none; page-break-inside: avoid;">
        <p style="margin-left:3px; font-size: x-large!important; font-weight:bold;color: #383884!important;"> Customer Information</h4>
        <tr>
            <td style='border:none;padding: 0px;'>
                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid black;">
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">First Name : </span> {{ ucwords($customer->firstName) }}</p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Last Name : </span> {{ ucwords($customer->lastName) }}</p>
                        </td>
                    </tr>
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">ID Type : </span>@if(!empty($profile->id_type)) {{ $profile->id_type }} @else N/A @endif</p>
                        </td>
                    </tr>
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang : </span>@if($profile->omang) {{ $profile->omang }} @else N/A @endif</p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport : </span> @if($profile->passport) {{ $profile->passport }} @else N/A @endif</p>
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
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Email : </span> @if($customer->email) {{ $customer->email }} @else N/A @endif </p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Date Of Birth : </span> {{ $profile->dob }}</p>
                        </td>
                    </tr>
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Omang Expiry date : </span>@if($customer_kyc->omangExpiry) {{ $customer_kyc->omangExpiry }} @else N/A @endif </p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Expiry date : </span>@if($customer_kyc->passportExpiry) {{ $customer_kyc->passportExpiry }} @else N/A @endif  </p>
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
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Physical Address : </span> {{ $profile->address }}</p>
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
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">State/District: </span> {{ $state }}</p>
                        </td>
                    </tr>
                    @if($profile->passport != null)
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Passport Issuing Country: </span> @if($passpostIssueCountry != null && $passpostIssueCountry->name != null) {{ $passpostIssueCountry->name }} @else N/A @endif</p>
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
            <p style="margin-left:3px;font-size: x-large!important; font-weight:bold;color: #383884!important;">Product Information</p>
        <tr>
            <td style="border:none;padding: 0px;">
                <table class="table-responsive" style="border-collapse: collapse;width:100%; border:1px solid #c1c6d1;">
                    <tr style="width: 100%;">
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Product : </span>
                                @if ($product->name != null)
                                 {{ $product->name }}
                                @else
                                 N/A
                                @endif
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;"><span style="font-weight: 600;">Plan : </span>
                                @if ($product_plan->slug != null)
                                    {{ $product_plan->slug }}
                                @else
                                    N/A
                                @endif
                            </p>
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
                            <p style="margin-bottom: 6px; margin: 0px;">
                            <span style="font-weight: 600;">Store :
                            </span>
                                @if($policy_stores != null)
                                    {{ $policy_stores->name }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p style="margin-bottom: 6px; margin: 0px;">
                                <span style="font-weight: 600;">Agent : </span>
                                @if($agentName != null)
                                    {{ $agentName }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div  style="padding: 16px">
        <p style="font-size: x-large!important; font-weight:bold;color: #383884!important;"> Introduction </p>

        <p style="margin-bottom: 0px; margin-top: 0px;">This Instant Legal Insurance policy document explains the types of benefits and conditions that are applicable to you for this cover.</p>

        <p style="margin-bottom: 0px; margin-top: 0px;">•	Your insurance coverage only commences once the product has been successfully activated, and you have received a confirmation SMS, but subject to the waiting periods stipulated herein and the this terms and conditions.<br>
            To activate, please follow the steps on the enclosed insurance activation card.</p>

        <p style="margin-bottom: 0px; margin-top: 0px;">•	Your cover will be deemed null and void if you provide us with false information or fail to activate the policy.</p>

        <p style="margin-bottom: 0px; margin-top: 0px;">•	You may cancel this policy by notifying us of your intent to cancel in writing, or through our mobile app.</p>

        <p style="margin-bottom: 0px; margin-top: 0px;">•	No variation, amendment, or alteration thereto shall be binding on the INSURER unless agreed to in writing by the INSURER.</p>

        <p style="margin-bottom: 0px; margin-top: 0px;">•	The policy shall have a 30 day cooling off period.</p>
    </div>

    <div  style="padding: 16px">
        <p style="margin-bottom: 0px; margin-top: 0px;"> Definitions that apply to your Instant Legal Insurance Cover</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> We will indemnify you in terms of this policy during any period of insurance for which a premium has been paid, subject to commencement of cover.<br> Your policy activation is the basis of and forms part of this policy.</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> This policy document, the cover limit table and the schedule as well as your confirmation SMS will be read as one document and shall be binding on you.<br> Any word or expression given a specific meaning will have that meaning wherever it appears.</p>
        <h4> DEFINITIONS:</h4>
        <p style="margin-bottom: 0px; margin-top: 0px;"> You/Your/Yours/Yourself/ INSURED:</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> The person stated in the schedule as the insured. We/Us/Our/Ourselves/ INSURER:</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> Alpha Direct Insurance Company (Pty) Limited Covered persons:</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> Is you, the person stated in the schedule as the insured- Your spouse, dependant minor <br> children or dependent parents if they are ordinarily resident with you</p>
        <h4> Child:</h4>
        <p style="margin-bottom: 0px; margin-top: 0px;"> A person under the age of 18 years, and financially dependant on you;</p>
        <p style="margin-bottom: 0px; margin-top: 0px;"> Includes your biological, adoptive or step-child, and any foster-child placed in your care by a Court Order.</p>
        <h4>INSURED EVENTS:</h4>
        <p style="margin-bottom: 0px; margin-top: 0px;"> Shall mean the incident or the start of the transaction or series of incidents that may <br>
        lead to a claim being made under this policy, but excluding the events expressly excluded under this Policy.</p>
    </div>

</body>
</html>
