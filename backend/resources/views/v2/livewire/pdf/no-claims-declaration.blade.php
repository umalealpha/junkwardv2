<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>No Claims Declaration - {{ $policyNumber }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: 'Book Antiqua', 'Palatino Linotype', Palatino, Georgia, serif; font-size: 12.5px; color: #0D1B2A; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #0D1B2A; padding-bottom: 10px; margin-bottom: 22px; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: .5px; }
        .brand small { display: block; font-size: 10px; font-weight: normal; letter-spacing: 2px; color: #F4A623; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        .title .ref { font-size: 11px; color: #555; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.meta td { padding: 6px 8px; border: 1px solid #C9CED6; vertical-align: top; }
        table.meta td.k { width: 32%; background: #F3F5F8; font-weight: bold; }
        h2 { font-size: 13.5px; text-transform: uppercase; letter-spacing: .8px; margin: 22px 0 8px; color: #0D1B2A; border-left: 4px solid #F4A623; padding-left: 8px; }
        ol { padding-left: 20px; margin: 6px 0; }
        ol li { margin-bottom: 6px; }
        .sig { margin-top: 26px; border: 1.5px solid #0D1B2A; padding: 14px 16px; background: #FAFBFC; }
        .sig h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .6px; }
        .sig table { width: 100%; border-collapse: collapse; }
        .sig td { padding: 4px 6px; }
        .sig td.k { width: 34%; font-weight: bold; }
        .stamp { display: inline-block; margin-top: 10px; padding: 6px 12px; border: 2px solid #1D7A46; color: #1D7A46; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 11px; }
        .footer { margin-top: 26px; font-size: 10px; color: #666; border-top: 1px solid #C9CED6; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Alpha Direct<small>INSURANCE COMPANY (PTY) LTD</small></div>
        <div class="title">
            <h1>No Claims Declaration</h1>
            <div class="ref">Policy {{ $policyNumber }} &middot; Ref NCD-{{ $signatureId }}</div>
        </div>
    </div>

    <table class="meta">
        <tr><td class="k">Policyholder</td><td>{{ $customerName }}</td></tr>
        <tr><td class="k">Policy number</td><td>{{ $policyNumber }}</td></tr>
        <tr><td class="k">Product</td><td>{{ $productName }}</td></tr>
        @if($vehicle)
        <tr><td class="k">Insured vehicle</td><td>{{ $vehicle }}</td></tr>
        @endif
        <tr><td class="k">Registered cellphone</td><td>{{ $cellphoneDisplay }}</td></tr>
        <tr><td class="k">Declaration date</td><td>{{ $signedAtDisplay }}</td></tr>
    </table>

    <h2>Declaration</h2>
    <p>I, <strong>{{ $customerName }}</strong>, the policyholder named above, declare to Alpha Direct Insurance Company (Pty) Ltd that:</p>
    <ol>
        <li>I have not submitted, and am not aware of, any claim, loss, accident, theft or damage in respect of the insured risk under this policy, or under any previous policy covering the same risk, during the preceding {{ $claimFreeYears }} year(s), other than any disclosed to Alpha Direct in writing.</li>
        <li>No insurer has declined, cancelled or refused to renew insurance for this risk, or imposed special terms, during the same period, other than as disclosed.</li>
        <li>I understand that the premium, discount and terms of this policy are calculated in reliance on this declaration.</li>
        <li>I understand that if this declaration is false or incomplete, Alpha Direct may re-rate the policy, recover any discount granted, reject a claim, or cancel the policy in accordance with its terms and applicable Botswana law.</li>
        <li>I confirm the information given is true, complete and correct to the best of my knowledge and belief.</li>
    </ol>

    <div class="sig">
        <h3>Electronic signature</h3>
        <p style="margin:0 0 8px">This declaration was signed electronically by one-time PIN (OTP) verification. A unique code was sent by SMS to the policyholder's registered cellphone number. The SMS stated that sharing the code with the Alpha Direct sales agent constitutes consent to sign this declaration. The code was entered and verified as set out below.</p>
        <table>
            <tr><td class="k">Signed by</td><td>{{ $customerName }}</td></tr>
            <tr><td class="k">Signature method</td><td>OTP verification via SMS</td></tr>
            <tr><td class="k">Cellphone number verified</td><td>{{ $cellphoneDisplay }}</td></tr>
            <tr><td class="k">Date and time signed</td><td>{{ $signedAtDisplay }} ({{ $timezone }})</td></tr>
            <tr><td class="k">OTP reference</td><td>NCD-{{ $signatureId }}</td></tr>
            <tr><td class="k">Requested by (agent)</td><td>{{ $agentName ?: '—' }}</td></tr>
        </table>
        <div class="stamp">Signed via OTP &middot; {{ $signedAtDisplay }}</div>
    </div>

    <div class="footer">
        Generated by Graphite on {{ $generatedAtDisplay }}. This electronic record is retained by Alpha Direct Insurance Company (Pty) Ltd together with the OTP delivery and verification log as evidence of the policyholder's consent.
    </div>
</body>
</html>
