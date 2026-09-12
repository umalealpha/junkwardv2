<!Doctype html >
<html>

<head>
    <TITLE>Account Statement</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Calibri:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

      <style>
        /* Font sizing matched to the Tax Invoice template for a clean,
           readable layout: 12px body, 11px table data, ~14px section
           headers, line-height 1.45. Side margins reduced from 100px so the
           8-column ledger table is no longer cramped. */
        body {
            margin:30px 45px 40px 45px;
            font-family: 'Calibri', 'sans-serif';
            color:#002060;
            font-size: 12px;
            line-height: 1.45;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            /* border: 1px solid black; */
            padding: 3px 6px;
            text-align: left;
            font-size: 11px;
            line-height: 1.45;
        }
        .no-border td {
            border: none;
            padding: 2px 6px;
        }
        .bank-table th, .bank-table td {
            text-align: center;
        }
        .contact {
            font-size: 13px;
            margin:30px 0 0 0;
            font-weight:bold;
            /* display: flex; justify-content: center; */
            text-align:center;
        }
    </style>
</head>
<header>
    <div>
        <table style="width:100%;">
            <tr>
                <td width="60%" style="border: 0px" >
                    <div>
                        <img style="width: 220px;margin-bottom: 10px;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
                    </div>
                    <p style="font-size: 16px!important;margin: 0px!important;color: black!important;">You could save 15% or more on your insurance!</p>
                </td>
            </tr>
        </table>
</header>

<body>
     @php 
               $tbalance1=0;  
               $balance1=0;
               @endphp
     
     @foreach($data as $account_statement)
            @php
            $balance1 = $account_statement->balance;
            
            // Reset per row: these two are plain loop-local variables, so a
            // Payment or Refund row following a Credit Note kept the previous
            // row's lookup and printed the credit note's "<invoice>_<ref>" in
            // its own Reference cell.
            $credit_note_file = null;
            $invoiceRefNumber = null;
            if(\AlphaDirect\Services\AccountStatementService::canonicalTransType($account_statement->trans_type ?? '')!='Invoice'){
                $credit_note_file = \AlphaDirect\Models\CreditNote::where('credit_note_no',$account_statement->trans_ref)->first();
                if(isset($credit_note_file) && !empty($credit_note_file)){
                $invoiceRefNumber =  \AlphaDirect\Ledger::where('id', $credit_note_file->invoice_id)->value('invoice_no');
                }
            }
                            // Per-trans_type amount, NOT the debit/credit pair:
                            // policy_ledger.debit is stale on 'Invoice' rows and
                            // under-states the invoiced value (see
                            // AccountStatementService::rowAmount()). This pre-pass
                            // produces the header's Closing Balance, so it must
                            // use the identical resolver as the table below.
                            $rowAmt1   = \AlphaDirect\Services\AccountStatementService::rowAmount($account_statement);
                            $ctype1    = \AlphaDirect\Services\AccountStatementService::canonicalTransType($account_statement->trans_type ?? '');
                            $credit1   = in_array($ctype1, ['Payment', 'Credit Note'], true) ? $rowAmt1 : 0;
                            $debit1    = $ctype1 === 'Invoice' ? $rowAmt1 : 0;
                            $tbalance1 += \AlphaDirect\Services\AccountStatementService::signedAmount($account_statement);

                            $finalBal1 = str_replace(',', '',number_format($tbalance1, 2));
                            $balance1 =  $finalBal1;
                       
              @endphp
              @endforeach
                  


<div class="section">
         <h2 style="text-align: center !important; font-family: 'Century Gothic Paneuropean' !important; font-size: 18px; margin: 6px 0;">
            <b>STATEMENT OF ACCOUNT</b>
        </h2>
        @php
            $clientName = ($company && !empty($company->name))
                ? strtoupper($company->name)
                : ucwords(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? ''));
            $clientAddress = ($company && !empty($company->address) && !empty($company->name))
                ? $company->address
                : ($customer_profile->address ?? '-');
            $clientPhone = $company->contact_person_number ?? ($customer->cellphone ?? '-');
            $clientEmail = $company->primary_email ?? ($customer->email ?? '-');
            $clientVat   = $company ? ($company->VAT_registration_number ?? 'Not Applicable') : 'Not Applicable';
        @endphp

        {{-- Single unified header table with visible borders. Column widths
             tuned so labels ("Client Details", "Policy Number", "Opening
             Balance" etc.) stay on a single line and values get the rest. --}}
        @php
            $cellBase = 'padding:5px 8px; border:1px solid #b8c1d4; vertical-align:top; font-size:13px;';
            $labelBg  = 'background:#f3f5fa;';
        @endphp
        <table style="width:100%; margin-top:30px; border-collapse:collapse; border:1px solid #b8c1d4;">
            <tr>
                <td style="width:18%; {{ $cellBase }} {{ $labelBg }}"><strong>Client Details</strong></td>
                <td style="width:34%; {{ $cellBase }}"><strong style="text-transform:uppercase;">{!! $clientName !!}</strong></td>
                <td style="width:20%; {{ $cellBase }} {{ $labelBg }}"><strong>As At:</strong></td>
                <td style="width:28%; {{ $cellBase }}">{{ Carbon::now()->format('d F Y') }}</td>
            </tr>
            <tr>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Address:</strong></td>
                <td style="{{ $cellBase }}">{!! $clientAddress !!}</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Policy Number:</strong></td>
                <td style="{{ $cellBase }}">{{ $policyNumber }}</td>
            </tr>
            <tr>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Phone:</strong></td>
                <td style="{{ $cellBase }}">{{ $clientPhone }}</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Opening Balance:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) $opening_balance, 2) }}</td>
            </tr>
            <tr>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Email:</strong></td>
                <td style="{{ $cellBase }} word-break:break-all;">{{ $clientEmail }}</td>
                {{-- Invoiced Amount now comes from the SAME resolver and the SAME
                     filtered row set as the Closing Balance below. $sumInvoiceAmount
                     was a separate raw SUM(invoice_amount) over every Invoice row on
                     the policy, so it could never be relied on to foot. --}}
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Invoiced Amount:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) ($final_invoiced_amount ?? $sumInvoiceAmount), 2) }}</td>
            </tr>
            <tr>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>VAT No.:</strong></td>
                <td style="{{ $cellBase }}">{{ $clientVat }}</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Amount Paid:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) $final_payment, 2) }}</td>
            </tr>
            {{-- Credit notes and refunds move the Closing Balance, so without
                 these two lines the header could not be reconciled by hand:
                 Invoiced - Paid - Credit Notes + Refunds = Closing. Only shown
                 when non-zero, so an ordinary statement keeps its short header.
                 Null-coalesced because the legacy DocumentController caller does
                 not pass them. --}}
            @if (round((float) ($final_credit_notes ?? 0), 2) != 0.00)
            <tr>
                <td style="{{ $cellBase }}" colspan="2">&nbsp;</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Credit Notes:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) $final_credit_notes, 2) }}</td>
            </tr>
            @endif
            @if (round((float) ($final_refunds ?? 0), 2) != 0.00)
            <tr>
                <td style="{{ $cellBase }}" colspan="2">&nbsp;</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Refunds:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) $final_refunds, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td style="{{ $cellBase }}" colspan="2">&nbsp;</td>
                <td style="{{ $cellBase }} {{ $labelBg }}"><strong>Closing Balance:</strong></td>
                <td style="{{ $cellBase }}">P {{ number_format((float) $balance1, 2) }}</td>
            </tr>
        </table>

   

    <div class="section">
         
        <table  style='border: 1px solid black;'>
            <thead>
                <tr style='border: 1px solid black;'>
                    <th  style='border: 1px solid black;'>Invoice Date</th>
                    <th style='border: 1px solid black;'>Transaction / Invoice Number</th>
                    <th style='border: 1px solid black;'>Renewal Type</span></th>
                    <th style='border: 1px solid black;'>Invoice</th>
                    <th style='border: 1px solid black;'>Payment</th>
                    <th style='border: 1px solid black;'>Refund</th>
                    <th style='border: 1px solid black;'>Reference</th>
                    <th style='border: 1px solid black;'>Running Balance (BWP)</th>
                </tr>
            </thead>
            <tbody>
              @php 
               $tbalance=0;  
               @endphp
            @foreach($data as $account_statement)
            @php
            $balance = $account_statement->balance;
            // Reset per row: these two are plain loop-local variables, so a
            // Payment or Refund row following a Credit Note kept the previous
            // row's lookup and printed the credit note's "<invoice>_<ref>" in
            // its own Reference cell.
            $credit_note_file = null;
            $invoiceRefNumber = null;
            if(\AlphaDirect\Services\AccountStatementService::canonicalTransType($account_statement->trans_type ?? '')!='Invoice'){
                $credit_note_file = \AlphaDirect\Models\CreditNote::where('credit_note_no',$account_statement->trans_ref)->first();
                if(isset($credit_note_file) && !empty($credit_note_file)){
                $invoiceRefNumber =  \AlphaDirect\Ledger::where('id', $credit_note_file->invoice_id)->value('invoice_no');
                }
            }
                            // Same per-trans_type resolver as the header pre-pass
                            // above — see AccountStatementService::rowAmount().
                            // $rowAmt also feeds the Invoice / Payment / Refund
                            // cells below so the printed figures and the Running
                            // Balance can never disagree.
                            $rowAmt   = \AlphaDirect\Services\AccountStatementService::rowAmount($account_statement);
                            $ctype    = \AlphaDirect\Services\AccountStatementService::canonicalTransType($account_statement->trans_type ?? '');
                            $credit   = in_array($ctype, ['Payment', 'Credit Note'], true) ? $rowAmt : 0;
                            $debit    = $ctype === 'Invoice' ? $rowAmt : 0;
                            $tbalance += \AlphaDirect\Services\AccountStatementService::signedAmount($account_statement);

                            $finalBal = str_replace(',', '',number_format($tbalance, 2));
                            $balance =  $finalBal;
                         
              @endphp
            @if ($account_statement->credit != '-0.00')
                <tr style='border: 1px solid black;'>
                              
                    <td   style='border: 1px solid black;'>    
                        @if ($ctype == 'Invoice')
                            {{ Carbon::parse($account_statement->invoice_date)->format('d M Y') }}
                            @elseif ($account_statement->trans_type == 'Payment')
                            {{ Carbon::parse($account_statement->accounting_date)->format('d M Y') }}
                            @else
                            {{ Carbon::parse($account_statement->accounting_date)->format('d M Y') }}
                            @endif
                    </td>
  
                    <td  style='border: 1px solid black;'> @if($ctype!='Invoice' && isset($credit_note_file) && !empty($credit_note_file))
                             {{ $invoiceRefNumber.'_'.$account_statement->trans_ref ?? "" }}
                            @elseif ($account_statement->invoice_no != null)
                             {{ $account_statement->invoice_no }}
                            @elseif ($account_statement->trans_type == 'Payment')
                             {{$account_statement->trans_ref}}
                             @elseif ($account_statement->trans_type == 'Refund')
                             {{$account_statement->trans_ref}}
                            @endif
                    </td>
                    {{-- Renewal Type --}}
                    <td style='border: 1px solid black;'>
                        @if ($premium_freq==3) Annual
                        @elseif ($premium_freq==1) Monthly
                        @else Quarterly @endif
                    </td>
                    {{-- Invoice / Payment / Refund — ALWAYS exactly three cells
                         so every row lines up under the 8 column headers no
                         matter the transaction type (Credit Note / Refund / etc).
                         Invoice = invoice_amount, Payment = credit (or Credit
                         Note debit), Refund = Refund debit. --}}
                    <td style="text-align: right; border: 1px solid black;">
                        @if($ctype=='Invoice')
                            P {{ number_format($rowAmt, 2, '.', ',') }}
                        @endif
                    </td>
                    <td style="text-align: right; border: 1px solid black;">
                        @if(in_array($ctype, ['Credit Note','Payment'], true))
                            P {{ number_format($rowAmt, 2, '.', ',') }}
                        @endif
                    </td>
                    <td style="text-align: right; border: 1px solid black;">
                        @if($ctype=='Refund')
                            P {{ number_format($rowAmt, 2, '.', ',') }}
                        @endif
                    </td>
                     <td  style='border: 1px solid black;'>
                        
                            @if($ctype=='Invoice')
                                Invoice
                            @elseif($account_statement->trans_type=='Payment')
                            Payment
                             @elseif($account_statement->trans_type=='Refund')
                            Refund
                            @elseif($account_statement->trans_type=='Credit Note')
                            Credit Note
                            @elseif($account_statement->credit != NULL)
                                PAYMENT
                            @elseif((int)$account_statement->credit > 0 && $account_statement->credit != NULL)
                                PAYMENT FAILED
                            @else

                            @endif
                       
                    </td>
                    <td style="text-align: right;border: 1px solid black;">
                        <p style="margin: 0px 4px 0px!important;padding: 4px 0px!important;">
                            P {{ number_format($finalBal, 2, '.', ',') }} 
                        </p>
                    </td>
                </tr>
                 @endif
            @endforeach
            <tr><td colspan=7 style="text-align:right;border: 1px solid black;"><b>Closing Balance:</b></td><td style="text-align:right"><b> P {{ number_format((float) $balance, 2) }}</b></td></tr>
   
        </table>

    </div>

    <div class="section">
    
    <table style="width: 100%; border: 1px solid #000; font-size: 13px; font-family: Arial, sans-serif;">

  <tr >
    <td colspan="2" style="padding: 8px;border:1px solid #000;">
        <strong>BANKING DETAILS</strong>
        </td>
  </tr>
  <tr >
    <td style="width: 30%;padding:0px 0px 0px 5px;border:1px solid #000;">
        <strong>Bank Name</strong><br/>
        <strong>Account No</strong><br/>
        <strong>Branch</strong><br/>
    <strong>Branch Code</strong><br/>
<strong>Swift Code</strong></td>
<td style="padding:0px 0px 0px 5px;border:1px solid #000;">
     <strong>First National Bank (Botswana) Ltd</strong><br/>
        <strong>62403392335</strong><br/>
        Corporate<br/>
        282267<br/>
        FIRNBWGX<br/>
    
    </td>
{{-- </td>
    <td style=""><strong>First National Bank (Botswana) Ltd</strong></td>
  </tr>
  <tr >
    <td  style="width: 15%;padding:0px 0px 0px 5px;"><strong>Account No</strong></td>
    <td ><strong>62403392335</strong></td>
  </tr>
  <tr >
    <td  style="width: 15%;padding:0px 0px 0px 5px;"><strong>Branch</strong></td>
    <td >Corporate</td>
  </tr>
  <tr >
    <td  style="width: 15%;padding:0px 0px 0px 5px;"><strong>Branch Code</strong></td>
    <td >282267</td>
  </tr>
  <tr >
    <td  style="width: 15%;padding:0px 0px 0px 5px;"><strong>Swift Code</strong></td>
    <td>FIRNBWGX</td> --}}
  </tr>
</table>

    
    </div>
    <div>
    <div class="section contact">
        For any inquiries or discrepancies, please contact our Accounts Department at<br/>
        +267 370 2734 / +267 392 8264 or email: <a href="mailto:accountsdept@alphadirect.co.bw">accountsdept@alphadirect.co.bw</a>
       
    </div>
</div>
{{-- Footer: table layout (not floats) so DomPDF places the logo and the
     address in separate columns — the float version overlapped them. --}}
<table style="width:100%; margin:30px 0; border-collapse:collapse;">
    <tr>
        <td style="width:150px; border:0; vertical-align:middle; text-align:left;">
            <img style="width:140px; opacity:0.5;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png">
        </td>
        <td style="border:0; vertical-align:middle; text-align:left; padding-left:12px;">
            <div>Address: Botswana Innovation Hub, Plot 69184, Floor 2, Bar 2, Block 8 Industrial, Gaborone</div>
            <hr style="margin:4px 0; line-height:2px;">
            <div>P.O. Box 26 ADC, Gaborone • (+267) 370 2700 / 392 8264 • <a href="https://www.alphadirect.co.bw" target="_blank">www.alphadirect.co.bw</a></div>
        </td>
    </tr>
</table>

</body>
</html>