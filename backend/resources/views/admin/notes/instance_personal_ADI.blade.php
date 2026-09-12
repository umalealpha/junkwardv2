<!Doctype html >
<html>

<head>
    <TITLE>Accidental Death Insurance</TITLE>
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
       <p style="color: #f37000;text-align: center; font-size: x-large!important; font-weight: 600!important">INSTANT PERSONAL ACCIDENTAL</p>
       <p style="color: #f37000;text-align: center; font-size: x-large!important; font-weight: 600!important">DEATH INSURANCE POLICY</p>
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
    <p style="color: #f37000!important;font-family: Poppins!important; margin: 0px!important;font-size: large!important">Passport ID :
    <span>
        @if ($profile->passport != null)
        {{ $profile->passport }}
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
    </p><br><br>
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
<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">What is Covered?</p>
    <p style="">1. This policy covers death or total permanent disability resulting from an Accidental Injury.  <p>
    <p style="">2. Accidental Injury which shall mean bodily injury caused by accidental,violent, external and visible means and which </p>
    <p>a. directly and independently of all other causes results in death, or
        permanent disability within 12 calendar months from the date of
        occurrence of the injury.</p>
    <p>b. includes injury caused by exposure, starvation and thirst</p>
</div>
<br>
<br>
<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">Who Can be Covered Under this Insurance Policy?</p>
    <p style="">1. Citizens of Botswana and Legal Residents of Botswana aged between eighteen (18) to sixty-five (65) years of age. <p>
    <p style="">2. Any person not meeting the above criteria who purchases this product
        may be entitled, at the most, to a return of any premium paid during the
        period that they did not meet the criteria. </p>
</div>

<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">Specific Definitions</p>
    <p style="">1. Insured person means any person who, following the processes
        outlined on the “Instant Insurance Activation Card’’ included in this
        package, successfully activates the policy, and receives a written
        acknowledgement from the Company of such activation.  <p>
    <p style="font-weight: 600!important;">1. Permanent disability means: </p>
    <p>a. physical severance or total loss of the use of a limb having lasted
        twelve (12) consecutive months and at the expiry of that period is
        beyond hope of improvement; or</p>
    <p>b. irrecoverable loss of all sight in one or both eyes; or</p>
    <p>c. entire and irrecoverable loss of hearing; or</p>
    <p>d. entire and irrecoverable loss of the ability to speak, which in each case
        is caused by an Accidental Injury. </p>
</div>


<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">1. Commencement Date:
        <p> is defined as the date and time at which the
        insured following the processes outlined on the “Instant Insurance
        Activation Card’’ included in this package, successfully activates the
        policy, and receives a written acknowledgement from the Company
        of such activation.</p>
    </p>

    <p style="font-weight: 600!important;">1. Cooling Off Period:
        <p> You have thirty (30) days from the date We confirmed, electronically or in
            writing, that You are covered under Your Policy to decide if the Policy meets
            Your needs. You may cancel Your Policy simply by advising Us in writing
            within those thirty (30) days to cancel it. If You do this, we will refund any
            premiums You have paid during this period. These cooling off rights do not
            apply if You have made or You are entitled to make a claim during this
            period.</p>
    </p>
    <p style="font-weight: 600!important;">What is Payable? :
        <p> 1. In the event of Accidental Injury to any insured person the company will
            pay the amount of compensation as stated in the table of benefits to the
            insured person or their estate. </p>
            <p>2. The company will not be liable to pay more than the death or
                permanent disability benefit (whichever is the higher) under any
                circumstances. </p>
            <p>3. The company will only pay a death benefit where a death certificate or a
                certified copy of a death certificate, issued by a competent authority is
                provided by the estate of the insured. </p>
    </p>
</div>

<div style="margin: 20px 0px 0px 0px;">
    <p style="font-weight: 600!important;">What’s Not Covered
        <p>The company will not be liable to pay benefit in respect of:</p>
        <p>1. Accidental Injury</p>
        <p>a. Arising after the insured person attains the age of 65 years. </p>
        <p>f. Caused by an insured person being insane or under the influence of
            alcohol or drugs (unless prescribed by and taken in accordance with the
            directions of or administered by a licensed member of the medical
            profession other than themselves), committing suicide or any act of
            intentional self-injury, intentional self-exposure to unnecessary danger,
            venereal disease or in the case of a female by child bearing or
            sequelae thereof or other causes peculiar to the female sex. </p>
        <p>g. Arising whilst the insured person is: </p>
        <p>a. travelling by air except as a passenger in any aircraft fully licensed for
            the carriage of passengers provided that the insured person is not
            acting as a member of the aircraft crew nor flying for the purpose of any
            trade or technical operation connected with the aircraft in which they
            are travelling. </p>
        <p>h. Engaging in</p>
        <p>1. motor cycling (whether as driver or passenger). </p>
        <p>2. racing of any kind involving the use of any power-driven vehicle, vessel,
            aircraft or pedal cycle.</p>
        <p>3. steeple chasing, polo, winter sports (involving snow or ice),
            mountaineering necessitating the use of ropes.</p>
        <p>4. professional sportsmen or sports teams of any kind.</p>
        <p>5. any sport or pastime involving exceptional risk of accident. </p>
        <p>d. directly or indirectly caused by or related to or in consequence of war,
            invasion, act of foreign enemy, hostilities (whether war be declared or
            not), civil war, mutiny, insurrection, rebellion, revolution, military or
            usurped power.</p>
         <p>i. In addition, Alpha Direct will not recognize any claim which is directly or
            indirectly caused by, or attributable to: </p>
         <p>1. Suicide within the first 12 months of the applicable cover under the
            policy by any insured. </p>
         <p>2. War</p>
         <p>3. Invasion</p>
         <p>4. Act of foreign enemy</p>
    </p>


        <div style="margin: 10px 0px;">
        <table>
            <tr>
                <th>Description of Injury</th>
                <th>Amount of Compensation</th>
            </tr>
            <tr>
                <td><p>1. Death</p></td>
                <td></td>
            </tr>
            <tr>
                <td><p>a. By means of driving or being a passenger in a private motor vehicle</p></td>
                <td><p>P 250,000</p></td>
            </tr>
            <tr>
                <td><p>b. By means of being a passenger in a licensed public transport vehicle</p></td>
                <td><p>P 500,000</p></td>
            </tr>
            <tr>
                <td><p>c. By means of being a fare paying passenger in any aircraft fully licensed </p><p> for the carriage of passengers</p></td>
                <td><p>P 1,000,000</p></td>
                </tr>
                <tr>
                <td><p>2. Permanent disability, as defined in “Specific Definitions” above</p></td>
                <td><p>P 100,000</p></td>
                </tr>
            </table>
        </div>

</div>
<br><br>
<div style="margin: 10px 0px;">
    <p style="font-weight: 600!important;">
        <p style="text-align: center;">Copyright {{ now()->year }} . Alpha Direct Insurance Company (Pty) Ltd. </p>
        <p style="text-align: center;">All Rights Reserve Alpha Direct Insurance Company (Proprietary) Limited is</p>
        <p style="text-align: center;">an Authorised Financial Services Provider | NBFIRA License No. 2/9/179</p>
    </p>
</div>

</body>

</html>
