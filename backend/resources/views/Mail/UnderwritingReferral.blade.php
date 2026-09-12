<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; }
        .wrap { max-width: 620px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .head { background: #0B1272; color: #fff; padding: 14px 20px; font-size: 15px; font-weight: 600; }
        .body { padding: 20px; font-size: 14px; }
        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.fields td.label { width: 180px; color: #6b7280; font-weight: 600; }
        .muted { color: #9ca3af; font-size: 11px; margin-top: 16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">High-value Motor Comprehensive — Underwriting referral</div>
        <div class="body">
            <p>A customer's vehicle exceeds the P500,000 online auto-quote ceiling and has requested a callback. Please contact them to complete a manual quotation.</p>
            <table class="fields">
                <tr><td class="label">Customer name</td><td>{{ $lead->full_name }}</td></tr>
                <tr><td class="label">Contact number</td><td>{{ $lead->cellphone }}</td></tr>
                <tr><td class="label">Email address</td><td>{{ $lead->email ?: '—' }}</td></tr>
                <tr><td class="label">Vehicle details</td><td>{{ $lead->vehicle_details ?: '—' }}</td></tr>
                <tr><td class="label">Sum insured</td><td>{{ $lead->sum_insured !== null ? 'P' . number_format((float) $lead->sum_insured, 2) : '—' }}</td></tr>
                <tr><td class="label">Quote reference</td><td>{{ $lead->quote_reference ?: 'Not yet available' }}</td></tr>
            </table>
        </div>
    </div>
    <p class="muted">This is an automated notification from start.alphadirect.co.bw (lead #{{ $lead->id }}).</p>
</div>
</body>
</html>
