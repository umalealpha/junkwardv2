
<!Doctype html >
<html>
<head>
    <TITLE>Quote Sheet</TITLE>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link href="{{ public_path('v2/Arial.woff') }}" rel="stylesheet">
    <link href="{{ public_path('v2/Montserrat-Regular.woff') }}" rel="stylesheet">
    <!-- <link href="{{ public_path('v2/Times-New-Roman-Subsetted.woff') }}" rel="stylesheet"> -->
    
    <style>
    body {
      font-family: 'Montserrat','Arial', 'sans-serif' !important;
      font-weight: 500;
      font-size: 12px;
      }
      table {
      width: 100%;
      border-collapse: collapse;
      font-size:16px;
      }
      td, th {
      padding: 5px;
      vertical-align: top;
      }
      .section-title {
      font-size:15px;
      }
      .description {
      font-style: italic;
      margin-top: 5px;
      font-size: 11px;
      text-transform: capitalize;
      }
      h1, h2 {
      text-align: center;
      color: #1a1a1a;
      }
      h1 {
      display: block;
      font-size: 2em;
      margin-block-start: 0.67em;
      margin-block-end: 0.67em;
      margin-inline-start: 0px;
      margin-inline-end: 0px;
      font-weight: bold;
      unicode-bidi: isolate;
      }
      .centered-container {
      display: flex;
      justify-content: center;
      align-items: center; /* optional: for vertical centering if needed */
      /* optional: fill height to center vertically */
      }
      .bordered-word {        
      text-decoration: underline;
      text-decoration-thickness: 2px;
      text-underline-offset: 4px;
      font-size: 18px;
      text-align:center;
      font-weight: bold;
      text-transform: uppercase;
      }
      .section-sub-title
      {
      font-size:15px;
      text-transform: uppercase;
      }
      .right {
      text-align: left;
      }
      .subtitle {
      padding: 5px;
      font-size:16px;
      }
      .border-bottom
      {
      border-bottom:1px solid #000;
      }
      </style>
   </head>
   <body>
      <div class="container" style="position: relative;">
      <img src="{{ public_path('images/logo.png') }}" style="float: right;">
      </div>
      
      <table >
         <tr class="border-bottom" style="border: none;">
            <td class="bordered-word" colspan="2">ALPHA DIRECT INSURANCE COMPANY (PTY) LTD.</td>
         </tr>
         <tr class="border-bottom" style="border: none;">
           <td class="bordered-word" colspan="2" style="font-size: 15px;">AUTO FAC SLIP NO {{ $data['policySlipNumber'] ?? 'N/A' }}</td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Insured(s)</td>
            <td class="section-sub-title">{{ $data['Insured'] ?? 'N/A' }}</td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Type and Extent of Cover Granted</td>
            <td class="section-sub-title">{{ $data['TypeandExtentofCoverGranted'] ?? 'N/A' }}</td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Broker/Agent</td>
            <td class="section-sub-title">{{ $data['reinsurersBroker'] ?? 'N/A' }}</td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Description of Risk</td>
            <td class="section-sub-title">
               {{ $data['TypeandExtentofCoverGranted'] ?? 'N/A' }} COVERS PERTAINING THE RISK ADDRESS KNOWN AS {{ $data['PhysicalLocation'] ?? 'N/A' }}. <strong>{{ $data['policyNo'] ?? 'N/A' }}</strong>.
               <div class="description">
                  All other terms and conditions as per original policy issued by Alpha Direct and Reinsurers to follow the fortunes of Reinsured.
               </div>
            </td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Situation/territorial Scope</td>
            <td>
               BOTSWANA and other Territories as Per Policy Document
            </td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Basis of cover</td>
            <td>
               {{ $data['reinsurersCover'] ?? 'N/A' }}
            </td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Reinsurance Commission</td>
            <td>
               {{ $data['ReinsuranceCommission'] ?? 'N/A' }}%
            </td>
         </tr>
         <tr class="border-bottom">
            <td class="section-title">Period of Reinsurance and/or Insurance</td>
            <td>
               @if(!empty($data['PolicyStartDate']) && !empty($data['PolicyEndDate']))
                  {{ \Carbon\Carbon::parse($data['PolicyStartDate'])->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($data['PolicyEndDate'])->format('d/m/Y') }}
               @else
                  N/A
               @endif
            </td>
         </tr>
      
      <td colspan="2"  style="padding-top: 2em;">
         <span class="bordered-word">LIMIT OF INDEMNITY – {{ $data['TypeandExtentofCoverGranted'] ?? 'N/A' }}</span>
      </td>
      </table>
      <br/><br/>
      <table>
         <tr class="section-title">
            <td colspan="2" class="subtitle">Fire & Allied Perils</td>
         </tr>
         @php
         $totalExposure = 0.0;
         @endphp
         @foreach($coverageDetails as $coverData)
         <tr>
            <td class="subtitle">{{ $coverData->s_ScreenName }}</td>
            <td class="right subtitle">P {{number_format((float)$coverData->coverage_value, 2, '.', ',')}}</td>
            @php
            $totalExposure += (float) str_replace(',', '', $coverData->coverage_value);
            @endphp
         </tr>
         @endforeach
         
      </table>
      <br/><br/>
      <!-- <table>
         <tr class="section-title">
            <td colspan="2" class="subtitle">Business Interruption</td>
         </tr>
         <tr>
            <td class="subtitle">Gross Profit-All Premises</td>
            <td class="right subtitle">P50,000,000.00</td>
         </tr>
         <tr>
            <td class="subtitle">Additional increase in the cost of working</td>
            <td class="right subtitle">P4,650,000.00</td>
         </tr>
         <tr>
            <td class="subtitle">Claims preparation costs</td>
            <td class="right subtitle">P32,000,000.00</td>
         </tr>
      </table>
      <br/><br/> -->
      <table >
         <tbody>
            <tr class="border-bottom">
               <td class="section-title">Total exposure</td>
               <td class="left">Total exposure P {{number_format((float)$totalExposure, 2, '.', ',')}}</td>
            </tr>
            <tr class="border-bottom">
               <td class="section-title">Risk Ceded to Re-Insurer(s)</td>
               <td class="left">40.86% (40. 86% Retention by Insured)</td>
            </tr>
            <tr class="border-bottom">
               <td class="section-title">Gross Rate </td>
               <td class="left">P {{number_format((float)$data['totalPremium'], 2, '.', ',')}}</td>
            </tr>
         </tbody>
      </table>
      <br/><br/>
      <table border="1">
         <tbody>
            <tr>
               <td class="section-title" colspan="5">Reinsurer’s Proportion of T.S.I (Collective/RI)</td>
            </tr>
            <tr>
               <td class="section-title">Accepting Company</td>
               <td>%</td>
               <td>Amount</td>
               <td>Net Premium</td>
               <td>Signature, Seal & Date</td>
            </tr>
            <tr>
               <td class="section-title">Grand Re</td>
               <td>40.86% of
                  100%
                  Total Risk
               </td>
               <td>P50,000,000.00</td>
               <td>P {{ $result = number_format(($data['ReinsuranceCommission'] / 100) * $data['totalPremium'], 2, '.', ',');
                }} </td>
               <td></td>
            </tr>
            <tr>
               <td class="section-title" colspan="5" align="center" border="none">PREPARED BY THABANG B MAKABA</td>
            </tr>
         </tbody>
      </table>
      <br/><br/>
   </body>
</html>