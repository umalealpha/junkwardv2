<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alpha Transit Cover — Certificate of Insurance {{ $policy->policyNumber }}</title>
    <style>
        @page { margin: 28px 36px; }
        html, body { font-family: 'Book Antiqua', 'Palatino Linotype', Palatino, Georgia, serif; color: #1e293b; font-size: 11.5px; -webkit-print-color-adjust: exact; }
        .bar { background: #0D1B2A; color: #fff; padding: 10px 14px; font-size: 15px; font-weight: bold; }
        .bar span { color: #F4A623; }
        .bar .right { float: right; font-weight: normal; font-size: 9px; letter-spacing: 1.5px; margin-top: 4px; color: rgba(255,255,255,.75); }
        .rule { height: 3px; background: #F4A623; margin-bottom: 18px; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .head td { border: 0; padding: 0; vertical-align: top; }
        .kicker { color: #F4A623; font-weight: bold; letter-spacing: 1.5px; font-size: 10px; }
        h1 { margin: 2px 0 2px; font-size: 22px; color: #0D1B2A; }
        .sub { color: #64748b; font-style: italic; }
        .polno { text-align: right; }
        .polno .lbl { color: #64748b; font-size: 8.5px; letter-spacing: 1.2px; }
        .polno .num { font-family: Menlo, Courier, monospace; font-size: 15px; font-weight: bold; color: #0D1B2A; }
        .divider { border-top: 2px solid #0D1B2A; margin: 10px 0 12px; }
        .section { color: #0D1B2A; font-weight: bold; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; margin: 14px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { border: 0; padding: 4px 6px 6px 0; vertical-align: top; width: 50%; }
        .lbl { color: #64748b; font-size: 8.5px; letter-spacing: 1.2px; text-transform: uppercase; }
        .val { font-size: 12px; }
        .val b { color: #0D1B2A; }
        .muted { color: #64748b; }
        table.cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 4px -8px; }
        table.cards td { border: 1px solid #e2e8f0; border-top: 3px solid #F4A623; padding: 8px 10px; width: 33%; }
        .cards .n { font-size: 17px; font-weight: bold; color: #0D1B2A; }
        .cards .s { color: #64748b; font-size: 9px; }
        .banner { background: #ecfdf5; border: 1px solid #9FE1CB; color: #065f46; padding: 9px 12px; margin-top: 12px; }
        .banner b { font-size: 12.5px; }
        ul { margin: 4px 0 0 14px; padding: 0; } li { margin: 3px 0; }
        .foot { margin-top: 18px; }
    </style>
</head>
<body>
@php
    $s = $shipment ?? null;
    $zones = ['BW'=>'Botswana','ZA_GT'=>'South Africa · Gauteng','ZA_OT'=>'South Africa · other','NA'=>'Namibia','ZW'=>'Zimbabwe','ZM'=>'Zambia','LS'=>'Lesotho','SZ'=>'Eswatini'];
    $z = fn($code) => $zones[$code] ?? $code;
    $money = fn($v) => 'BWP ' . number_format((float) $v, 2);
    $d = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '—';
    $cat = ['STD' => 'Standard goods', 'ELE' => 'Electronics & fragile'];
@endphp
<div class="bar">Alpha<span>Direct</span> Insurance Co.<span class="right">CERTIFICATE OF INSURANCE · CONFIDENTIAL</span></div>
<div class="rule"></div>

<table class="head"><tr>
    <td>
        <div class="kicker">CERTIFICATE OF INSURANCE · POLICY SCHEDULE</div>
        <h1>Alpha Transit Cover</h1>
        <div class="sub">Goods-in-Transit · Underwritten by Alpha Direct Insurance (Pty) Ltd · Regulated by NBFIRA</div>
    </td>
    <td class="polno">
        <div class="lbl">POLICY NUMBER</div>
        <div class="num">{{ $policy->policyNumber }}</div>
        <div class="lbl" style="margin-top:6px">ISSUED</div>
        <div>{{ $d($s->issued_at ?? $policy->created_at) }}</div>
    </td>
</tr></table>
<div class="divider"></div>

<div class="section">Policyholder &amp; Shipment</div>
<table class="grid">
    <tr>
        <td><div class="lbl">Insured / Sender</div><div class="val"><b>{{ $name }}</b><br>{{ $customer->cellphone }}@if($customer->email)<br><span class="muted">{{ $customer->email }}</span>@endif</div></td>
        <td><div class="lbl">Receiver</div><div class="val"><b>{{ $s->receiver_name ?? '—' }}</b>@if(!empty($s->receiver_phone))<br>{{ $s->receiver_phone }}@endif</div></td>
    </tr>
    <tr>
        <td><div class="lbl">From</div><div class="val">{{ $s->from_town ?? '' }} ({{ $z($s->from_zone ?? '') }})</div></td>
        <td><div class="lbl">To</div><div class="val">{{ $s->to_town ?? '' }} ({{ $z($s->to_zone ?? '') }})</div></td>
    </tr>
    <tr>
        <td><div class="lbl">Courier / Issued by</div><div class="val">{{ $courierName ?? 'Alpha Direct' }}@if(!empty($s->courier_waybill)) · waybill <span style="font-family:Menlo,Courier,monospace">{{ $s->courier_waybill }}</span>@endif</div></td>
        <td><div class="lbl">Service</div><div class="val">{{ $s->service_type ?? '—' }}</div></td>
    </tr>
    <tr>
        <td colspan="2"><div class="lbl">Goods insured</div><div class="val"><b>{{ $cat[$s->goods_category ?? ''] ?? ($s->goods_category ?? '—') }}</b>@if(!empty($s->goods_description)) — {{ $s->goods_description }}@endif @if(!empty($s->weight_kg))<span class="muted"> · {{ $s->weight_kg }} kg</span>@endif</div></td>
    </tr>
</table>

<div class="section">Cover Summary</div>
<table class="cards"><tr>
    <td><div class="lbl">Sum insured</div><div class="n">{{ $money($s->sum_insured ?? $policy->sum_assured) }}</div><div class="s">Declared value of goods</div></td>
    <td><div class="lbl">Premium (once-off)</div><div class="n">{{ $money($s->premium ?? $policy->premium) }}</div><div class="s">VAT inclusive @if(!empty($s->rate))· rate {{ number_format($s->rate * 100, 2) }}%@endif</div></td>
    <td><div class="lbl">Excess</div><div class="n">{{ $money($s->excess ?? 0) }}</div><div class="s">or 15% of claim, whichever is higher</div></td>
</tr></table>

<div class="banner">
    <b>Cover period: {{ $d($s->cover_start ?? $policy->term_start_date) }} to {{ $d($s->cover_end ?? $policy->term_end_date) }}</b> (14 days, this shipment only)<br>
    Perils covered: accidental damage, theft, fire and collision while the goods are in transit with the named courier.
</div>

<div class="section">Important Notes</div>
<ul>
    <li>This certificate is the policyholder's proof of insurance and forms part of the Alpha Transit Cover policy wording, which sets out the full terms, conditions and exclusions.</li>
    <li>Claims: report to Alpha Direct within 48 hours of the incident. Quote this policy number and the waybill on every claim communication. Claims: claims@alphadirect.co.bw · +267 318 3000.</li>
    <li>Excluded goods: cash, jewellery, perishables and cold-chain goods, firearms, hazardous materials, antiques, controlled substances, live plants and animals.</li>
    <li>Cover is invalid if the goods are handed to a courier other than the one named above, or carried outside the route stated.</li>
</ul>

<div class="foot">@include('partials.nbfira-footer')</div>
</body>
</html>
