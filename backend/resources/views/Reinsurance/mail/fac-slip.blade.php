{{-- Covering note for an outbound facultative slip. The slip PDF is attached. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Facultative slip {{ $slip->slip_no }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Georgia,'Book Antiqua',serif;color:#17202b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:6px;overflow:hidden;">

    <tr><td style="background:#0D1B2A;padding:18px 24px;">
      <div style="color:#F4A623;font-size:17px;font-weight:bold;letter-spacing:.4px;">Facultative Slip {{ $slip->slip_no }}</div>
      <div style="color:#c8d2df;font-size:12px;margin-top:3px;">Alpha Direct Insurance Company (Pty) Ltd &middot; Gaborone</div>
    </td></tr>

    <tr><td style="padding:22px 24px 4px;font-size:14px;line-height:1.6;">
      <p style="margin:0 0 12px;">{{ $slip->counterparty_name }}</p>

      <p style="margin:0 0 12px;">
        Attached is our facultative slip {{ $slip->slip_no }}@if ($slip->version > 1) (version {{ $slip->version }}, replacing all earlier versions)@endif
        for {{ $slip->insured_name ?: 'the insured named in the slip' }}
        @if ($slip->policy_number) under policy {{ $slip->policy_number }}@endif.
      </p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;margin:0 0 14px;">
        @php
          $cur = $lines->first()->currency ?? 'BWP';
          $gross = $lines->sum(fn ($l) => (float) $l->gross_ceded_premium);
          $net   = $lines->sum(fn ($l) => (float) $l->net_ceded_premium);
          $ppw   = $lines->pluck('ppw_due_date')->filter()->sort()->first();
        @endphp
        <tr>
          <td style="padding:5px 10px 5px 0;color:#55637a;width:190px;">Lines on this slip</td>
          <td style="padding:5px 0;">{{ $lines->count() }}</td>
        </tr>
        <tr>
          <td style="padding:5px 10px 5px 0;color:#55637a;">Gross ceded premium</td>
          <td style="padding:5px 0;"><strong>{{ $cur }} {{ number_format($gross, 2) }}</strong></td>
        </tr>
        <tr>
          <td style="padding:5px 10px 5px 0;color:#55637a;">Net of commission</td>
          <td style="padding:5px 0;">{{ $cur }} {{ number_format($net, 2) }}</td>
        </tr>
        @if ($ppw)
        <tr>
          <td style="padding:5px 10px 5px 0;color:#55637a;">Premium payment warranty</td>
          <td style="padding:5px 0;">{{ \Illuminate\Support\Carbon::parse($ppw)->format('d F Y') }}</td>
        </tr>
        @endif
      </table>

      <p style="margin:0 0 12px;">
        Please confirm acceptance by return. If any detail on the slip does not
        match your records, tell us before you confirm and we will correct it and
        reissue.
      </p>

      <p style="margin:0 0 4px;">Regards</p>
      <p style="margin:0;">Reinsurance<br>Alpha Direct Insurance Company (Pty) Ltd</p>
    </td></tr>

    <tr><td style="padding:18px 24px 24px;">
      <p style="margin:0;font-size:11px;color:#6b7789;line-height:1.5;">
        Floor 2, Bar 2, Botswana Innovation Hub Icon Building, Plot 69184,
        Block 8 Industrial, P.O. Box 26ADC, Gaborone, Botswana &middot;
        Tel +267 3928264 &middot; reinsurance@alphadirect.co.bw
      </p>
    </td></tr>

  </table>
</td></tr>
</table>
</body>
</html>
