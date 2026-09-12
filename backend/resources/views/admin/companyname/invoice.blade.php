<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title>Tax Invoice</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="license" href="https://www.opensource.org/licenses/mit-license/"> --}}

    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/arial" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/montserrat" rel="stylesheet">

    <style>
        @import url('https://fonts.cdnfonts.com/css/arial');
    </style>
     <style>
        @import url('https://fonts.cdnfonts.com/css/montserrat');

        body {
            font-family: 'Montserrat','Arial', 'sans-serif' !important;
            font-weight: 500;
            font-size: 12px;
            line-height: 1 !important;
        }

        .container {
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
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
            line-height: 1 !important;
            /* border: 1px solid #000000; */
        }

        td,
        th {
            padding: 5px 5px;
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
                line-height: 1 !important;
                /* border: 1px solid #ddd !important; */
            }
        }
        .column {
        float: left;

        }

        /* Clear floats after the columns */
        .row:after {
        content: "";
        display: table;
        clear: both;
        }

    </style>

    {{-- <style>
        @import url('https://fonts.cdnfonts.com/css/montserrat');
        .allign {
            text-align: right;
        }

        body {
            color: black;
            -webkit-print-color-adjust: economy;
            -webkit-print-color-adjust: exact;
            -webkit-print-color-adjust: inherit;
            -webkit-print-color-adjust: initial;
            -webkit-print-color-adjust: unset;
        }

        @media print {
            *,
             :after,
             :before {
                color: #000 !important;
                background: 0 0 !important;
            }
            /* body {
                font-size: 14px !important;
                color: black !important;
                font-weight: normal !important;
                font-family: 'Poppins'!important;
                line-height: 0.5 !important;
                -webkit-print-color-adjust: economy;
                -webkit-print-color-adjust: exact;
                -webkit-print-color-adjust: inherit;
                -webkit-print-color-adjust: initial;
                -webkit-print-color-adjust: unset;
                 height: 80%;
            } */

            body {
                font-family: 'Montserrat','Arial', 'sans-serif' !important;
                font-weight: 500;
                font-size: 12px;
            }
            table tr td {
                vertical-align: top;
            }
            table,
            table tr {
                page-break-after: auto;
            }
            img {
                max-width: 100% !important;
            }
            p {
                padding: 0px;
            }
        }
    </style> --}}
</head>

<header>
    <div>
        <table style="width:100%;">
            <tr valign="middle">
                <td width="50%">
                    <div>
                        <img  width="50%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
                    </div>
                    <br>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">Alpha Direct Insurance Co. (Pty) Ltd.</p>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">Office: Floor 2, Bar 2,<br>Botswana Innovation Hub Icon Building,</p>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">Plot 69184 Block 8 Industrial</p>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">Postal Address: P.O. Box 26 ADC<br>Gaborone, Botswana</p>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">Phone +267 392 8264 | Fax +267 392 8265</p>
                    <p style="font-family: ;font-size: 13px; margin: 0px;color: black!important;">accountsdpt@alphadirect.co.bw | <a href="mailto: accountsdpt@alphadirect.co.bw" style="color: #2e77c3!important;">www.alphadirect.co.bw</a></p>
                </td>
                <td width="50%">
                    <p style="text-align: right; color: #2e77c3!important;font-size: 24px;">TAX INVOICE</p>
                    <p style="text-align: right; color: #2e77c3!important;  margin: 0px;">INVOICE # <span style="color: black!important;">{!! $company->vat_no.'_001' !!}</span></p>
                    {{-- <p style="text-align: right; color: #2e77c3!important;margin-bottom: 15px;">INVOICE DATE : <span style="color: black!important;">{!! \Carbon\Carbon::now()->format('d-m-Y') !!}</span></p>
                    <p style="text-align: right; font-weight: 600;  margin: 0px;"> Co. Regn. No. : BW00000123907</p>
                    <p style="text-align: right; font-weight: 600;"> VAT No. : BW00000123907-00-05-27</p> --}}
                    <p style="text-align: right; color: #2e77c3!important;">INVOICE DATE : <span style="color: black!important;">{!! \Carbon\Carbon::now()->format('d-m-Y') !!}</span></p>
                    <p style="text-align: right; color: #!important;"> Co. Regn. No. : BW00000123907</p>
                    <p style="text-align: right; color: #!important;"> VAT No. : BW00000123907-00-05-27</p>
                </td>
            </tr>
        </table>
    </div>
</header>
<div style="margin-top: 2px;">
    <table style="width:100%;">
        <tr valign="middle">
            <td width="50%">
                <p style="color: #2e77c3!important; font-size: 18px;line-height:20px;font-weight: 600;margin-bottom: 4px;">To,</p>
                <p style="font-family: ;  margin: 0px;">
                   
                    {!! $company->name??"" !!},
                </p>
                <p style="font-family: ;  margin: 0px;">{!! $company->address !!}</p>
            </td>
            <td width="50%">
                <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
                <p style="color: #2e77c3!important;text-align: left; font-family: ;  margin: 0px;">Line of Business :<span style="color: black!important;">
                    
                </span></p>
               
                <p style="color: #2e77c3!important;text-align: left; font-family: ;  margin: 0px;">Period of Insurance :<span style="color: black!important;"> {!! \Carbon\Carbon::now()->format('d-m-Y') !!} To {!! \Carbon\Carbon::now()->addMonth(1)->format('d-m-Y') !!}</span> </p>
                <p style="color: #2e77c3!important;text-align: left; font-family: ;  margin: 0px;">Company VAT NO. : <span style="color: black!important;">{!! $company->vat_no ? strtoupper($company->vat_no) : null !!}</span></p>
                <hr style="border: 2px solid #2e77c3!important;color: #2e77c3!important;background-color:#2e77c3!important;">
            </td>
        </tr>
    </table>
</div>
<hr style="border: 0.5px solid #2e77c3!important;color: #2e77c3;background-color:#2e77c3;">
<div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; ">
        <thead style="border-bottom: 2px solid #2e77c3!important; color: #2e77c3!important;">
            <tr>
                <td width="50%">
                    <p style="text-align: left; font-family: ; color: #2e77c3!important; font-size: 14px; margin: 0px; font-weight: 600;">Description</p>
                </td>
                <td width="50%" class="allign">
                    <p style="text-align: right;font-family: ; color: #2e77c3!important;font-size: 14px;  margin: 0px;font-weight: 600;">Amount</p>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td width="50%" style="border-bottom: 0.5px solid #73c9ed;">
                    <p style="text-align: left;font-family: ;  margin: 0px; padding: 0px;">
                        Total Premium
                    </p>
                </td>
                <td width="50%" class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                    <p style="text-align: right;  font-family: ;  margin: 0px; padding: 0px;"> P {!! number_format($totalpremium ?? "", 2, '.', ',') !!}</p>
                </td>
            </tr>
        </tbody>
    </table>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="font-family: ;border-bottom: 0.5px solid #73c9ed;">
                    <p style="visibility: hidden; margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="font-family: ;border-bottom: 0.5px solid #73c9ed;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="font-family: ;border-bottom: 0.5px solid #73c9ed;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <td width="100%" style="border-bottom: 2px solid #2e77c3; line-height: 1.7em;">
                    <p style="visibility: hidden;margin: 0px; padding: 0px;">Emty text</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<br>
<table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
    <tbody>
        <tr>
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 0px;">
                {{-- <p style="font-weight: 600;font-size: 14px;margin: 0px; padding: 0px;"> --}}
                    Total Amount Due For Current Invoice :
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                <p style="text-align: right;margin: 0px; padding: 0px;">
                {{-- <p style="text-align: right;font-family: Poppins;margin: 0px; padding: 0px;"> --}}
                    P {{number_format((float)$premiumExcludingVAT ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>

     

        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                <p style="margin: 0px; padding: 0px;">
                {{-- <p style="font-weight: 600;font-size: 14px; margin: 0px; padding: 0px; color: black;"> --}}
                    Total VAT:
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                {{-- <p style="text-align: right;font-family: Poppins;margin: 0px; padding: 0px;"> --}}
                   <p style="text-align: right;margin: 0px; padding: 0px;">
                       P {{number_format((float)$vat ?? "", 2, '.', ',')}}
                   </p>
            </td>
        </tr>
     
        <tr style="width: 100%;">
            <td style="border-bottom: 0.5px solid #73c9ed;">
                {{-- <p style="font-weight: 600;margin: 0px; padding: 0px;font-size: 16px; color: #2e77c3 !important;"> --}}
                <p style="margin: 0px; padding: 0px;">
                    Total Amount Due to Us :
                </p>
            </td>
            <td class="allign" style="border-bottom: 0.5px solid #73c9ed;">
                {{-- <p style="text-align: right;font-weight: 600;font-family: ;margin: 0px; padding: 0px;"> --}}
                <p style="text-align: right;margin: 0px; padding: 0px;">
                    P {{number_format((float)$totalpremium ?? "", 2, '.', ',')}}
                </p>
            </td>
        </tr>
    </tbody>
</table>
<br>
<p style="text-align: justify; font-size: 14px;font-weight:;text-decoration: underline;color:#000">PAYMENTS TERMS:</p>
<p style="text-align: justify; font-size: 11px;line-height: 1.2; margin: 0px; padding: 0px;">
    Under current legislation and in terms of your policy, premiums must be paid before the inception date of the cover. The above premium is therefore payable on presentation of this debit note. Failure to do so could result in cover being withdrawn by Alpha
    Direct Insurance Co.&nbsp; Please note that our specified method of payment is by bank transfer. Alpha Direct Insurance Company will only accept payment by cheque if the cheques are received and honoured by the due date for payment. Make all checks
    payable to Alpha Direct Insurance Company (Pty) Ltd.&nbsp; All risks associated with the delivery of cheques by post will be for your account. Please quote your policy number on all payments whether by bank transfer or cheque. Our banking details
    are as follows:&nbsp;
</p><br>
<p style="text-align: justify; font-size: 11px;line-height: 1.2; margin: 0px; padding: 0px;">
    Bank: First National Bank (Botswana) Limited | Branch : Corporate | Branch Code : 282267 | SWIFT Code : FIRNBWGX Account Number : 62403392335
</p><br>

{{-- <p style="text-align: justify;color: !important;font-size:12px;font-weight: 600;line-height: 1.2;margin: 0px; padding: 0px;">
    Bank: First National Bank (Botswana) Limited | Branch : Corporate | Branch Code : 282267 | SWIFT Code : FIRNBWGX Account Number : 62403392335
</p> --}}
<p style="text-align: justify;font-size: 12px; margin: 0px; padding: 0px;">If you have any questions concerning this invoice, contact the finance department | + 267 392 8264 | accounts@alphadirect.co.bw</p><br>
<p style="text-align: center; color: #2e77c3!important;font-size: 17px;font-weight:; margin: 0px; padding: 0px;">THANK YOU FOR YOUR BUSINESS!</p>
<br>
<p style="text-align: center;font-size: 13px;margin: 0px; padding: 0px; ">You just saved a lot of money by choosing Alpha Direct!</p>
</body>

</html>
