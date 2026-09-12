<!Doctype html >
<html>

<head>
    <TITLE>Account Statement</TITLE>
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

        table tr td {
            vertical-align: top;
        }
        .yearly{
            font-family: Poppins!important;
            color:#000!important;
            padding: 4px 0px!important;
            border: 2px solid #c0d4ec!important;
            text-align: center;
            text-align: center;
            font-size: 12px;
        }
        .invoice-heading{
            font-family: Poppins!important;
            font-weight: 600!important;
            color:#2e77c3!important;
            padding: 4px 0px!important;
            border: 2px solid #c0d4ec!important;
            text-align: center;
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
            table tr td {
                vertical-align: top;
            }
            table,
            table tr {
                page-break-after: auto;
            }
            /* tr {
                page-break-inside: avoid;
            } */
            img {
                max-width: 100% !important;
            }
            p {
                padding: 0px;
            }
        }
    </style>
</head>

<header>
    <div>
        <table style="width:100%;">
            <tr>
                <td width="60%" style="border: 0px">
                    <div>
                        <img style="width: 220px;margin-bottom: 10px;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
                    </div>
                    <p style="font-family: Poppins!important;font-size: 16px!important;margin: 0px!important;color: black!important;">You could save 15% or more on your insurance!</p>
                    <br>
                    <p style="font-family: Poppins!important;margin: 0px!important;color: black!important;">Alpha Direct Insurance Co. (Pty) Ltd.</p>
                    <p style="font-family: Poppins!important;margin: 0px!important;color: black!important;">Floor 2, Bar 2</p>
                    <p style="font-family: Poppins!important;margin: 0px!important;color: black!important;">Botswana Innovation Hub</p>
                    <p style="font-family: Poppins!important;margin: 0px!important;color: black!important;">Gaborone</p>
                </td>
                <td width="40%" style="border: 0px;">
                    <div style="margin-top: 15px;">
                        <p style="font-family: Poppins!important; text-align: left; color: #2e77c3!important;font-size: 16px!important;margin: 0px 0px 30px 0px;font-weight: 600!important;">STATEMENT OF ACCOUNT</p>
                        <p style="font-family: Poppins!important; text-align: left; color: #2e77c3!important; margin: 0px!important; font-weight: 600!important;">AS AT : <span style="color: black!important;font-family: Poppins!important;"> {{  Carbon::now()->format('F d , Y') }}</span></p>
                    </div>
                </td>
            </tr>

        </table>
    </div>
</header>

<div style="margin: 10px 0px;">
    <table style="width:100%;">
        <tr>
            <td width="100%" style="border: 0px">
                <p style="font-family: Poppins!important; margin: 0px!important;">Postal Address:</p>
                <p style="font-family: Poppins!important; margin: 0px!important;">P.O. Box 26ADC</p>
                <p style="font-family: Poppins!important; margin: 0px!important;">Gaborone</p>
                <p style="font-family: Poppins!important; margin: 0px!important;">Phone +267 392 8264 | Fax +267 392 8265</p>
                <p style="font-family: Poppins!important; margin: 0px!important;">accounts@alphadirect.co.bw | www.alphadirect.co.bw</p>
            </td>
        </tr>
    </table>
</div>
<div style="margin: 20px 0px;">
    <table style="table-layout: auto;width: 300px;">
        <tr>
            <td style="border: 0px">
                <p style="font-family: Poppins!important; margin: 0px!important;font-weight: 600!important;">Co. Regn. No. :</p>
                <p style="font-family: Poppins!important; margin: 0px!important;font-weight: 600!important;">VAT No. :</p>
            </td>
            <td style="border: 0px">
                <p style="font-family: Poppins!important; margin: 0px!important;font-weight: 600!important;">BW00000123907</p>
                <p style="font-family: Poppins!important; margin: 0px!important;font-weight: 600!important;">BW00000123907</p>
            </td>
        </tr>
    </table>
</div>

<div>
    <table style="width:100%;">
        <tr>
            <td width="100%" style="border: 0px">
                <p style="font-family: Poppins!important;color: #2e77c3!important; font-size: 18px;line-height:20px;font-weight: 600!important;margin: 4px;">TO</p>
                {{-- <p style="font-family: Poppins!important; margin: 0px!important;">{{ ucwords($customer->firstName.' '.$customer->lastName) }}</p> --}}
                <p style="font-family: Poppins!important; margin: 0px!important;">{!! ucwords($company->name ?? '') !!}<</p>
                {{-- <p style="font-family: Poppins!important; margin: 0px!important;">{{ $customer_profile->address }}</p> --}}
                <p style="font-family: Poppins!important; margin: 0px!important;">{!! $company->address !!}</p>
                <p style="font-family: Poppins!important; margin: 0px!important;"></p>
            </td>
        </tr>
    </table>
</div>

<div style="margin: 20px 0px">
    <p style="font-family: Poppins!important;font-weight: 600!important;">VAT No. NOT APPLICABLE</p>
</div>
<div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;font-family: Arial, Helvetica, Poppins; ">
        <thead>
            <tr>
                <th class="invoice-heading">INVOICE DATE</th>
                <th class="invoice-heading">INVOICE NO</th>
                <th class="invoice-heading">VALUE</th>
                <th class="invoice-heading">REFERENCE</th>
                <th class="invoice-heading">PAID</th>
                <th class="invoice-heading">BALANCE</th>
            </tr>
        </thead>
        <tbody>
           @if($policyData->premium_freq == '3')
           @include('v2.livewire.policy.ledger.yearly') 
           @elseif($policyData->premium_freq == '1')
           @include('v2.livewire.policy.ledger.monthly')
           @elseif($policyData->premium_freq == '5')
           @include('v2.livewire.policy.ledger.qutarly')
           @endif
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5" style="border-bottom: 2px solid #c0d4ec!important;border-top: 2px solid #c0d4ec!important;">
                <p style="font-size: 14px;color:#2e77c3!important; margin:0px!important;padding: 20px 0px!important;font-weight: 600!important;">Total Amount Due</p>
            </td>
            <td style="border-bottom: 2px solid #c0d4ec!important;border-top: 2px solid #c0d4ec!important;">
                <p style="font-size: 14px;color:#2e77c3!important; margin:0px;padding: 20px 0px;font-weight: bold;">P 0.00</p>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<div style="margin: 20px 0px 0px 0px;">
    <p style="font-family: Poppins!important; text-align: center; color: #2e77c3!important;font-size: 16px;font-weight: 600!important; margin: 0px!important;">THANK YOU FOR YOUR BUSINESS!</p>
    <p style="font-family: Poppins!important; text-align: center;font-size: 14px; margin-top: 12px!important;font-weight: 600!important;">You just saved a lot of money by choosing Alpha Direct!</p>
    <p style="font-family: Poppins!important;margin: 10px 0px 6px 0px!important;">Bank: First National Bank (Botswana) Limited | Branch : Corporate | Branch Code : 282267 | SWIFT Code : FIRNBWGX | Account Number : 62403392335</p>
</div>

</body>

</html>
