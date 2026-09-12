<!Doctype html >
<html>

<head>
    <TITLE>Third Party Policy</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <style>
        body {
            font-size: 14px !important;
            color: black !important;
            font-weight: normal !important;
            font-family: 'Poppins'!important;
            line-height: 10px !important;
            -webkit-print-color-adjust: economy!important;
            -webkit-print-color-adjust: exact!important;
            -webkit-print-color-adjust: inherit!important;
            -webkit-print-color-adjust: initial!important;
            -webkit-print-color-adjust: unset!important;
        }

            table {
                font-family: arial, sans-serif;
                border-collapse: collapse;
                width: 100%;
                }

                td, th {
                border: 1px solid #dddddd;
                text-align: left;
                padding: 8px;
                }

                tr:nth-child(even) {
                background-color: #dddddd;
                }

        @media print {
            *,
             :after,
             :before {
                color: #000 !important;
                background: 0 0 !important;
            }
            body {
                font-size: 14px !important;
                color: black !important;
                font-weight: normal !important;
                font-family: 'Poppins'!important;
                line-height: 10px !important;
                -webkit-print-color-adjust: economy;
                -webkit-print-color-adjust: exact;
                -webkit-print-color-adjust: inherit;
                -webkit-print-color-adjust: initial;
                -webkit-print-color-adjust: unset;
                height: 100%;
            }

            img {
                max-width: 100% !important;
            }

        }
    </style>
</head>
<body style="padding: 3px">
<header>
    <div>
       <img class="center" style="width: 360px;margin-bottom: 10px;margin-left: 150px" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
       <p style="color: #f37000;text-align: center; font-size: x-large!important; font-weight: 600!important">THIRD PARTY LIABILITY</p>
       <p style="color: #f37000;text-align: center; font-size: x-large!important; font-weight: 600!important">INSURANCE POLICY</p>
    </div>
</header>
<br>
    <p></p>
<div>

    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">
        Policyholder :  <span>
            {{ $name }}
         </span></p><br>
         <p></p>
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">Omang ID :
        <span>
        @if ($profile->omang != null)
        {{ $profile->omang }}
        @else
        N/A
        @endif
      </span>
    </p><br>
    <p></p>
    <p style="color: #f37000!important;-family: Poppins!important; margin: 0px!important;font-size: large!important">Policy no :
    <span>
      @if ($policy->policyNumber != null)
        {{ $policy->policyNumber }}
      @else
        N/A
      @endif
    </span>
    </p><br>
    <p></p>
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">Vehicle Plate Number :
        <span>
            @if ($vehicle->vehiclePlate != null)
            {{ $vehicle->vehiclePlate }}
            @else
                N/A
            @endif
      </span>
    </p><br>
    <p></p>
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">
        Term Start Date :
    <span>
        @if ($policy->billingStartDate != null)
        {{ $policy->billingStartDate }}
        @else
        N/A
        @endif
    </span>
    </p><br>
    <p></p>
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">

        Total Premium :
        <span>
        @if ($premium != null)
        {{ $premium }}
        @else
        N/A
        @endif
        </span>
    </p><br>
    <p></p>
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">
        Payout Limit :
        <span>
            @if ($policy->sum_assured != null)
            {{ $policy->sum_assured }}
            @else
            N/A
            @endif
        </span>
    </p>
</div>
<br><br><br><br>
<div>
    <p style="font-weight: 600!important;">Postal Address: P.O. Box 26 ADC, Gaborone, Botswana </p>
    <p style="font-weight: 600!important;">Physical Address: Floor 1 | Bar 1 | Botswana Innovation Hub Icon Building | Plot 69184 | Block 8</p>
    <p style="font-weight: 600!important;"> Industrial | P.O. Box 26ADC | Gaborone, Botswana </p>
    <p style="font-weight: 600!important;"> Phone: Toll Free: <span style="color: #f37000">0800 601 029 | Tel: + 267 392 8264 | Fax: + 267 392 8265 |</span></p>
</div>
<br>
    <div style="text-align: center;">
       <p style="font-weight: 400!important;color: #f37000">Third Party Motor Instant Insurance</p>
    </div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p>Your Instant Third-Party Motor Insurance section explains the types of benefits and conditions that apply to you.  <p>
    <p>You must read this section together with the general terms and conditions and your policy schedule. </p>
    <ul>
      <li><p>Your insurance coverage only begins once the product has been successfully activated, and you have received a confirmation SMS. To  activate, please follow the steps on the enclosed insurance activation card.</p></li>
      <li><p>Your cover will be deemed null and void if you provide us with false information or fail to activate the policy.</p></li>
      <li><p>Cover is subject to selecting an appropriate annual plan. You may cancel this plan by notifying us of your intent to cancel in writing, or through our mobile app.</p></li>
      <li><p style="font-weight: 600!important;">Cancellation must be made with 15 days notice to allow for changes to the billing cycle. Definitions that apply to your Motor vehicle (Third party cover)</p></li>
    </ul>
    <hr>
</div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p>Where we refer to you” in the Motor vehicle (Third party cover) section, it also means anyone who drives the motor vehicle with your permission. The following definitions are used in the Motor vehicle (Third party cover) section of this policy.<p>
    <p><span style="font-weight: 600!important;">Motor Vehicle - </span>A passenger motor vehicle, 4x4, SUV (sports utility vehicle), bakkie, mini-bus, light delivery vehicle, panel van and motorized caravan with a gross vehicle mass not exceeding 3 500 kg. The specific motor vehicle that we cover is set out in the policy schedule.</p>
    <p><span style="font-weight: 600!important;">Regular driver - </span>The person who drives or is in control or possession of the motor vehicle most of the time. The name of the regular driver is set out in the policy schedule. </p>
    <p><span style="font-weight: 600!important;"> Off-road - </span> Off-road means that you use your motor vehicle or motorbike for four-wheel driving, trail driving, sand dune driving or any other driving away from a public, prepared or graded private road.</p>
    <p><span style="font-weight: 600!important;"> Credit agreement - </span> An enforceable credit agreement entered between you and the credit provider. </p>
    <p><span style="font-weight: 600!important;"> Credit provider - </span> A registered financial institution whose interest in the insured property forms the subject of the credit agreement. </p>
    <p><span style="font-weight: 600!important;"> Outstanding loan amount - </span> The outstanding loan amount is the amount you owe to a credit provider in terms of a credit agreement. </p>
    <p style="font-weight: 600!important;" >This amount excludes : </p>
    <ul>
      <li><p>arrear instalments.</p></li>
      <li><p>interest and finance charges on arrear instalments; and</p></li>
      <li><p>any early settlement penalties.</p></li>
    </ul>
</div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">3 Conditions for Cover</p>
    <p>3.1 Your motor vehicle must be registered in terms of current Botswana legislation.<p>
    <p>3.2 You, or any other person driving the motor vehicle must be in possession of a valid Botswana driver s license, or a valid driver s license that complies with Botswana legislation. This includes a person with a valid learner s license, but only while accompanied by a person with a valid driver s license.<p>
    <p>3.3 Your motor vehicle must be fully paid up and may not be financed in terms of a credit agreement with a credit provider. If your motor vehicle is financed, we may cancel your cover from the cover start date and pay back all premiums, less the amount of any claims we may have paid.<p>
    <p>3.4 We will only cover you for third-party liability within the borders of Botswana.<p>
    <p>3.5 There is no third-party liability cover when you travel outside Botswana – you must take out separate third-party liability insurance cover.<p>
    <p>3.6 Your motor vehicle is not covered for any loss or damage to the motor vehicle itself. Your motor vehicle is only covered for third party liability.<p>
    <p>You must tell us what you use your motor vehicle for<p>
    <p>You must tell us what you mainly use your motor vehicle for, as set out below. We will set this out on the policy schedule.<p>
</div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p><span style="font-weight: 600!important;">4.1 Personal use:  </span>You use your motor vehicle for private, domestic and pleasure purposes. This includes travelling to and from your place of work but excludes business use as explained below. </p>
    <p><span style="font-weight: 600!important;">4.2 Business use:  </span>You use your motor vehicle for personal use as explained above, and on a regular basis for professional or business travelling. </p>
    <p style="font-weight: 600!important;">Third-party liability<p>
    <p>A third-party is another person whose property is damaged because of an accident that involved your motor vehicle, for example the owner of another motor vehicle or the owner of property.<p>
    <p>You can be held legally responsible if this other person s property is damaged. This is called legal liability. We will cover the following types of liability, including reasonable legal costs and expenses that we agreed to in writing, up to the limit set out in the policy schedule.<p>
</div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">5 Legal liability for damage</p>
    <p>We will cover you for your legal liability to third parties if your motor vehicle is involved in an insured event that causes damage to the property of any person.<p>
    <p>We will also cover your legal liability to local authorities for damage because of an accident.<p>
    <p>5.1 Other people driving your motor vehicle<p>
    <p>If someone else, other than you (the Insured) drives your motor vehicle, that person will NOT be covered for legal liability under this policy. This is regardless of whether you must have given the person permission to drive your motor vehicle or not.<p>
    <p>5.2 Driving a motor vehicle that does not belong to you<p>
    <p>If you (the Insured) drive a motor vehicle other than the Specified Motor Vehicle, regardless of whether you own that vehicle or not, you are NOT covered for legal liability as described above.<p>
    <p>For example, you are covered for legal liability to third parties ONLY if you (the Insured) drive the Specified Motor Vehicle that is insured under this policy, and you are in an accident that causes damage to another motor vehicle.<p>
    <p>You are only covered for legal liability, and not for loss of or damage to the motor vehicle you were driving, or for any property carried by that motor vehicle.</p>
    <p>5.3 When there is no legal liability</p>
    <p>There is no legal liability cover in the following instances:</p>
    <ul>
      <li>
        <p>Damage to property:</p>
        <p>- Belonging to you, or a member of your household or any person in your employ.</p>
        <p>- In the care, custody or control of you or any other person covered by this policy.</p>
        <p>- Being carried in or on a caravan or a trailer towed by your motor vehicle.</p>
      </li>
      <li>
        <p>Loss or damage to a caravan, trailer or another vehicle that does not belong to you while it is towed by your motor vehicle.</p>
      </li>
      <li>
        <p>Legal costs to defend criminal acts or fines for breaches of the laws of Botswana.</p>
      </li>
      <li>
        <p>When you travel outside Botswana.</p>
      </li>
    </ul>
</div>
<br>

<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">6 Specific exclusions are in addition to the exclusions set out in the General exclusions section.</p>
    <p>We will not pay a claim for any of the benefits set out in the motor vehicle (Third party cover) section of this policy that was caused by, or related to any of the following specific exclusions.<p>
    <p>6.1 While your motor vehicle is in the custody and control of the motor trade for any purpose other than the overhaul, upkeep or repair of the motor vehicle. For example, if your motor vehicle is parked at the dealer while the dealer is trying to sell it for you.<p>
    <p>6.2 Using your motor vehicle for off-road driving or 4x4 track driving.<p>
    <p>6.3 Exposing your motor vehicle to situations that clearly have a high risk of loss or damage, for example crossing a flooded road or making a U-turn on a highway.<p>
    <p>6.4 Using your motor vehicle to give driving lessons for which you or the driver of the motor vehicle receive payment.<p>
    <p>6.5 Using your motor vehicle for commercial travelling or as a tool of trade, for example:<p>
    <ul>
      <li>
        <p>Using your motor vehicle as a courier or delivery car.</p>
      </li>
      <li>
        <p>renting out your motor vehicle for use by others.</p>
      </li>
      <li>
        <p>using your motor vehicle to carry passengers for reward, such as a taxi or limousine (excluding lift clubs).</p>
      </li>
      <li>
        <p>using your motor vehicle to carry out your trade, such as plumbers, electricians, builders, garden services, farmers, etc.</p>
      </li>
    </ul>
    <p>1.6 Using your motor vehicle to carry dangerous, hazardous, flammable goods or substances that pollute or contaminate, in quantities
greater than that used for domestic purposes. Examples are nitro glycerine or dynamite, chemicals or compressed gas, gas in liquid form, hazardous waste or liquid petroleum.<p>
    <p>6.7 During any motor sport, racing, rally, time trial or while being tested in preparation for any motor sporting activity, or while being driven on a motor sporting circuit or track of any kind.</p>
    <p>6.8 Using your motor vehicle in connection with any experiments, tests, trials, performance or any other motor vehicle demonstration purpose.</p>
    <p>6.9 Using your motor vehicle to carry or tow a load that is greater than what the motor vehicle was designed or licensed for.</p>
    <p>6.10 While the motor vehicle is driven or towed by you, or any other person that you gave your permission to, where:</p>
    <ul>
      <li>
        <p>the driver does not have a valid driver s license.</p>
      </li>
      <li>
        <p>the driver is under the influence of alcohol or drugs.</p>
      </li>
      <li>
        <p>the alcohol content in the driver s blood or breath exceeds the legal limit; or</p>
      </li>
      <li>
        <p>the driver refuses to submit to any test to determine the level of alcohol or drugs in his blood, when requested to do so by the authorities. Any test includes a blood test and breathalyzer test.</p>
      </li>
    </ul>
    <p>6.11 Where the driver of the motor vehicle leaves the scene of the accident unreasonably.</p>
    <p>6.12 If your motor vehicle does not meet the roadworthy requirements of the applicable legislation.</p>
</div>
<br>


<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">7 Your specific responsibilities</p>
    <p>In addition to your responsibilities set out in the Your responsibilities section, you have extra responsibilities that specifically apply to your motor vehicle s (Third party cover) section.<p>
    <p>7.1 Tell us if your motor vehicle was modified from the manufacturers specifications, for example:</p>
    <ul>
      <li>
        <p>changes to engine capacity.</p>
      </li>
      <li>
        <p>enhancing the motor vehicles performance; or</p>
      </li>
      <li>
        <p>changes to the suspension.</p>
      </li>
    </ul>
    <p>7.2 Tell us if any fact that material to the risk of the motor vehicle is changes within 14 days from the date that it has changed, for example:</p>
    <ul>
      <li>
        <p>the use of the motor vehicle.</p>
      </li>
      <li>
        <p>the regular driver of the motor vehicle.</p>
      </li>
    </ul>
    <p>7.3 Keep your motor vehicle roadworthy. You must maintain your motor vehicle according to the roadworthy requirements of the applicable legislation.</p>
    <p>7.4 Take out separate third-party liability insurance cover when you travel outside Botswana. There is no third-party liability cover when you travel outside Botswana</p>
</div>
<br>


<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">How to claim</p>
    <p>In addition to the conditions set out in the Claiming under this policy section, there are extra conditions that specifically apply to claiming for third party liability cover.<p>
    <p>8.1 You must tell us immediately after you become aware of any action or possible action against you, for example if you receive a summons from the court. We will tell you what evidence and other documents we need to process the claim.</p>
    <p>8.2 You must send us the evidence and other documents we ask for within the time that we will give you.</p>
    <p>You must never do any of the following:</p>
    <ul>
      <li>
        <p>Admit guilt, fault, liability, or incur any legal costs without first getting our permission.</p>
      </li>
      <li>
        <p>Offer or negotiate to pay a claim.</p>
      </li>
      <li>
        <p>Accept any offer from another person for any damage that you want to claim for under this policy. If you do, you will not have any claim under this policy.</p>
      </li>
    </ul>
    <p>8.4 If the steps above are not followed, or you do not send us the information we ask for within the time we gave you, we will reject your claim.</p>
    <p>8.5 If you choose not to continue with the claim after you have told us, you may still claim under this policy, but only within the time that we will give you. After this time, we will no longer consider the claim and you would have lost all your rights to claim for that incident.</p>
    <p></p>
</div>
<br>
<br>

<div style="margin: 10px 0px;">
    <p style="font-weight: 600!important;">
        <p style="text-align: center;">Copyright {{ now()->year }} . Alpha Direct Insurance Company (Pty) Ltd. </p>
        <p style="text-align: center;">All Rights Reserve Alpha Direct Insurance Company (Proprietary) Limited is</p>
        <p style="text-align: center;">an Authorised Financial Services Provider | NBFIRA License No. 2/9/179</p>
    </p>
</div>

</body>
</html>
