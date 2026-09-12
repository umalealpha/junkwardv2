{{-- Internal FAC alert. Brand: navy #0D1B2A header, orange #F4A623 title. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>FAC register</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Georgia,'Book Antiqua',serif;color:#17202b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:6px;overflow:hidden;">

    <tr><td style="background:#0D1B2A;padding:18px 24px;">
      <div style="color:#F4A623;font-size:17px;font-weight:bold;letter-spacing:.4px;">FAC Register</div>
      <div style="color:#c8d2df;font-size:12px;margin-top:3px;">Alpha Direct Insurance &middot; Reinsurance</div>
    </td></tr>

    <tr><td style="padding:22px 24px 6px;">
      <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">{{ $summaryLine }}</p>
    </td></tr>

    @if ($placement)
    <tr><td style="padding:0 24px 6px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
        @php
          $row = function ($k, $v) {
              if ($v === null || $v === '') return '';
              return '<tr><td style="padding:5px 10px 5px 0;color:#55637a;width:170px;vertical-align:top;">'
                   . e($k) . '</td><td style="padding:5px 0;">' . e($v) . '</td></tr>';
          };
          $money = fn ($v) => $v === null ? null : number_format((float) $v, 2);
        @endphp
        {!! $row('Reference', $placement->fac_reference) !!}
        {!! $row('Policy', $placement->policy_number) !!}
        {!! $row('Insured', $placement->insured_name) !!}
        {!! $row('Cover', $placement->ri_group_label) !!}
        {!! $row('We pay', $placement->counterparty_name) !!}
        {!! $row('Risk carried by', $placement->risk_carrier) !!}
        {!! $row('Gross ceded premium', $placement->currency . ' ' . $money($placement->gross_ceded_premium)) !!}
        {!! $row('Net of commission', $placement->currency . ' ' . $money($placement->net_ceded_premium)) !!}
        {!! $row('Premium payment warranty', optional($placement->ppw_due_date)->format('d F Y')) !!}
        {!! $row('Underwriter', $placement->underwriter_name) !!}
        {!! $row('Status', str_replace('_', ' ', (string) $placement->status)) !!}
      </table>
    </td></tr>
    @endif

    @if ($event === 'cancelled')
    <tr><td style="padding:8px 24px 0;">
      <div style="background:#fdecec;border-left:3px solid #c0392b;padding:11px 13px;font-size:13px;">
        <strong>Do not settle this placement.</strong>
        The payable has been reversed in the register. If a payment has already
        been raised in omni, stop it.
      </div>
    </td></tr>
    @elseif ($event === 'ppw_breached')
    <tr><td style="padding:8px 24px 0;">
      <div style="background:#fdecec;border-left:3px solid #c0392b;padding:11px 13px;font-size:13px;">
        The premium payment warranty date has passed and the insured's premium has
        not been received. Cover may be voidable. Please confirm the position with
        the insured today.
      </div>
    </td></tr>
    @elseif ($event === 'ppw_warned')
    <tr><td style="padding:8px 24px 0;">
      <div style="background:#fdf6e8;border-left:3px solid #F4A623;padding:11px 13px;font-size:13px;">
        The warranty date is close and the premium is not in yet. Chase it now
        rather than after the date passes.
      </div>
    </td></tr>
    @elseif ($event === 'client_paid')
    <tr><td style="padding:8px 24px 0;">
      <div style="background:#edf7ef;border-left:3px solid #2e7d43;padding:11px 13px;font-size:13px;">
        Debtors: the receipt is recorded. Reinsurance: this placement is ready to
        settle. The payment itself is raised in omni.
      </div>
    </td></tr>
    @elseif ($event === 'auto_generated')
    <tr><td style="padding:8px 24px 0;">
      <div style="background:#fdf6e8;border-left:3px solid #F4A623;padding:11px 13px;font-size:13px;">
        This line was raised automatically from the slip's existing terms and is
        a <strong>draft</strong>. It does not count towards the payable until
        somebody confirms it.
      </div>
    </td></tr>
    @endif

    <tr><td style="padding:20px 24px 24px;">
      <p style="margin:0;font-size:12px;color:#6b7789;line-height:1.5;">
        Sent by the FAC register in Graphite. This message is a record of an event
        on the register — the event itself is logged whether or not this email
        arrives.
      </p>
    </td></tr>

  </table>
</td></tr>
</table>
</body>
</html>
