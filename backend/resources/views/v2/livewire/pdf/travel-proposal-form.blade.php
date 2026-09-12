{{--
    Travel Insurance Proposal Form — generated from a signed
    travel_proposal_forms row (PublicTravelProposalController).

    Reproduces the paper form the customer used to sign in ink (Alpha Direct /
    MAPFRE, rev. 2022): particulars of proposer, other persons travelling, the
    four health questions, and the declaration. The Insured's Signature block
    is replaced by the OTP verification evidence — that verified code IS the
    signature (ECTA 2014 s.17), so the block prints the number it went to, the
    channel it went on, and the timestamp it was verified at, plus the evidence
    hash that seals the row.

    Layout is table-based on purpose: the renderer falls back from Puppeteer to
    wkhtmltopdf (Snappy), whose old WebKit does not lay out flex/grid.
--}}
@php
    $fmtDate = function ($value) {
        if (empty($value)) return '—';
        try { return \Carbon\Carbon::parse($value)->format('d/m/Y'); }
        catch (\Throwable $e) { return (string) $value; }
    };
    $fmtStamp = function ($value) {
        if (empty($value)) return '—';
        try { return \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s'); }
        catch (\Throwable $e) { return (string) $value; }
    };
    $val = fn ($v) => ($v === null || $v === '') ? '—' : $v;
    // Inlined as a data URI rather than linked: this document is rendered by
    // Puppeteer in prod and by wkhtmltopdf / DomPDF on the fallback path, and
    // only the first of those reliably fetches an http(s) asset. A remote
    // reference produced "Image not found" on the fallback renderers.
    $logoFile = public_path('motor/img/2.png');
    $logoUrl = is_file($logoFile)
        ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoFile))
        : URL::to('/motor/img/2.png');
    $signed  = ($proposal->status ?? '') === 'signed';
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->reference ?? 'Travel Insurance Proposal Form' }} — Travel Insurance Proposal Form</title>
    <style>
        @page { margin: 18mm 14mm; }
        body {
            font-family: "Book Antiqua", "Palatino Linotype", Palatino, Georgia, serif;
            font-size: 10.5px;
            color: #0D1B2A;
            margin: 0;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .head-rule { border-bottom: 3px solid #F4A623; height: 4px; }
        .doc-title {
            color: #F4A623;
            font-size: 22px;
            line-height: 1.1;
            font-weight: normal;
            letter-spacing: 0.3px;
        }
        .logo { width: 150px; }
        .meta { font-size: 9.5px; color: #44546A; }
        .meta strong { color: #0D1B2A; }
        .section {
            background: #0D1B2A;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 7px;
            margin-top: 12px;
        }
        .fields td { padding: 4px 8px 4px 0; }
        .label { color: #44546A; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }
        .value { font-size: 11px; border-bottom: 1px solid #C9D1D9; padding-bottom: 2px; }
        .grid td { width: 33.33%; }
        .grid-2 td { width: 50%; }
        .persons { margin-top: 6px; font-size: 10px; }
        .persons th {
            background: #F2F4F7;
            border: 1px solid #C9D1D9;
            padding: 4px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .persons td { border: 1px solid #C9D1D9; padding: 4px 6px; }
        .q { margin-top: 8px; }
        .q-text { font-size: 10.5px; }
        .q-answer { font-weight: bold; }
        .q-answer.yes { color: #B42318; }
        .q-details {
            margin-top: 2px;
            padding: 4px 6px;
            background: #FFF7E6;
            border-left: 3px solid #F4A623;
            font-size: 10px;
        }
        ol.clauses { margin: 8px 0 0 16px; padding: 0; }
        ol.clauses li { margin-bottom: 6px; text-align: justify; }
        .sig {
            margin-top: 10px;
            border: 1px solid #0D1B2A;
            padding: 8px 10px;
        }
        .sig-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; }
        .sig table td { padding: 3px 8px 3px 0; font-size: 10px; }
        .hash { font-family: "Courier New", monospace; font-size: 8.5px; word-break: break-all; color: #44546A; }
        .stamp {
            border: 2px solid #1B7F4C;
            color: #1B7F4C;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 10px;
            text-align: center;
        }
        .stamp.draft { border-color: #B42318; color: #B42318; }
        .footer {
            margin-top: 14px;
            border-top: 1px solid #C9D1D9;
            padding-top: 6px;
            font-size: 8.5px;
            color: #44546A;
            text-align: center;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width: 62%;">
            <div class="doc-title">TRAVEL INSURANCE<br>PROPOSAL FORM</div>
        </td>
        <td style="text-align: right;">
            <img class="logo" src="{{ $logoUrl }}" alt="Alpha Direct">
            <div class="meta" style="margin-top: 4px;">In partnership with <strong>MAPFRE</strong></div>
        </td>
    </tr>
    <tr><td colspan="2" class="head-rule"></td></tr>
</table>

<table class="meta" style="margin-top: 8px;">
    <tr>
        <td>Proposal reference <strong>{{ $val($proposal->reference ?? null) }}</strong></td>
        <td style="text-align: center;">
            @if(!empty($proposal->contract_number))
                Policy / contract <strong>{{ $proposal->contract_number }}</strong>
            @elseif(!empty($proposal->quote_id))
                Quote <strong>{{ $proposal->quote_id }}</strong>
            @else
                &nbsp;
            @endif
        </td>
        <td style="text-align: right;">Generated {{ $fmtStamp(now()) }}</td>
    </tr>
</table>

<p style="margin: 8px 0 0; text-align: justify;">
    I/ We the undersigned wish to apply for travel insurance and provide the following details which
    we understand shall form part of the contract between me/ ourselves and the Company.
</p>

<div class="section">Particulars of proposer</div>
<table class="fields grid">
    <tr>
        <td><div class="label">Surname</div><div class="value">{{ $val($proposal->surname ?? null) }}</div></td>
        <td><div class="label">First name(s)</div><div class="value">{{ $val($proposal->first_names ?? null) }}</div></td>
        <td><div class="label">Date of birth</div><div class="value">{{ $fmtDate($proposal->dob ?? null) }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Passport no</div><div class="value">{{ $val($proposal->passport_no ?? null) }}</div></td>
        <td><div class="label">Departure date</div><div class="value">{{ $fmtDate($proposal->departure_date ?? null) }}</div></td>
        <td><div class="label">Return date</div><div class="value">{{ $fmtDate($proposal->return_date ?? null) }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Destination</div><div class="value">{{ $val($proposal->destination ?? null) }}</div></td>
        <td><div class="label">Occupation</div><div class="value">{{ $val($proposal->occupation ?? null) }}</div></td>
        <td><div class="label">Trip type</div><div class="value">{{ $val($proposal->trip_type ?? null) }}</div></td>
    </tr>
    <tr>
        <td colspan="2"><div class="label">Address</div><div class="value">{{ $val($proposal->address ?? null) }}</div></td>
        <td><div class="label">Email address</div><div class="value">{{ $val($proposal->email ?? null) }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Next of kin</div><div class="value">{{ $val($proposal->next_of_kin_name ?? null) }}</div></td>
        <td><div class="label">Phone</div><div class="value">{{ $val($proposal->next_of_kin_phone ?? null) }}</div></td>
        <td><div class="label">Relationship</div><div class="value">{{ $val($proposal->next_of_kin_relationship ?? null) }}</div></td>
    </tr>
</table>

<div class="section">Other persons travelling</div>
@if(empty($travellers))
    <p style="margin: 6px 0 0;">No other persons travelling — the proposer travels alone.</p>
@else
    <table class="persons">
        <tr>
            <th style="width: 34%;">Full names</th>
            <th style="width: 18%;">Date of birth</th>
            <th style="width: 24%;">Passport number</th>
            <th style="width: 24%;">Relationship with proposer</th>
        </tr>
        @foreach($travellers as $t)
            <tr>
                <td>{{ $val($t['full_name'] ?? null) }}</td>
                <td>{{ $fmtDate($t['dob'] ?? null) }}</td>
                <td>{{ $val($t['passport_no'] ?? null) }}</td>
                <td>{{ $val($t['relationship'] ?? null) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="section">Health declaration</div>
@php $n = 0; @endphp
@foreach($questions as $key => $text)
    @php
        $n++;
        $answer  = $medical[$key]['answer'] ?? null;
        $details = $medical[$key]['details'] ?? null;
    @endphp
    <div class="q">
        <table>
            <tr>
                <td class="q-text">{{ $n }}. {{ $text }}</td>
                <td style="width: 60px; text-align: right;">
                    <span class="q-answer {{ $answer ? 'yes' : '' }}">
                        {{ $answer === null ? '—' : ($answer ? 'YES' : 'NO') }}
                    </span>
                </td>
            </tr>
        </table>
        @if($answer && !empty($details))
            <div class="q-details"><strong>Details:</strong> {{ $details }}</div>
        @endif
    </div>
@endforeach

<div class="section">Declaration</div>
<ol class="clauses">
    @foreach($clauses as $clause)
        <li>{{ $clause }}</li>
    @endforeach
</ol>

<div class="sig">
    <table>
        <tr>
            <td style="width: 68%;">
                <div class="sig-title">Insured's signature — verified by one-time code</div>
                <p style="margin: 4px 0 6px; font-size: 10px;">
                    The proposer accepted the declaration above and signed this form electronically by
                    entering the one-time code sent to their mobile number. Under the Electronic
                    Communications and Transactions Act 2014, that verified code is the proposer's
                    signature; the evidence of it is recorded below.
                </p>
                <table>
                    <tr>
                        <td class="label">Declaration accepted</td>
                        <td>{{ $fmtStamp($proposal->declaration_accepted_at ?? null) }}
                            (wording {{ $val($proposal->declaration_version ?? null) }})</td>
                    </tr>
                    <tr>
                        <td class="label">Code sent to</td>
                        <td>{{ $val($proposal->otp_cellphone ?? null) }}
                            @if(!empty($proposal->otp_channel)) via {{ strtoupper($proposal->otp_channel) }} @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Code sent at</td>
                        <td>{{ $fmtStamp($proposal->otp_sent_at ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Signed (code verified) at</td>
                        <td><strong>{{ $fmtStamp($proposal->otp_verified_at ?? $proposal->signed_at ?? null) }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Verification reference</td>
                        <td>OTP #{{ $val($proposal->otp_id ?? null) }}@if(!empty($proposal->otp_attempts)) · {{ (int) $proposal->otp_attempts }} failed attempt(s) before success @endif</td>
                    </tr>
                    <tr>
                        <td class="label">Evidence hash</td>
                        <td class="hash">{{ $val($proposal->evidence_hash ?? null) }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 32%; padding-left: 10px;">
                @if($signed)
                    <div class="stamp">
                        Signed &amp; verified<br>
                        <span style="font-size: 9px; letter-spacing: 0.5px;">
                            {{ $fmtStamp($proposal->signed_at ?? null) }}
                        </span>
                    </div>
                @else
                    <div class="stamp draft">
                        Unsigned draft<br>
                        <span style="font-size: 9px; letter-spacing: 0.5px;">Not a valid proposal</span>
                    </div>
                @endif
                <table class="meta" style="margin-top: 8px;">
                    @if(!empty($proposal->product_name))
                        <tr><td class="label">Cover</td></tr>
                        <tr><td>{{ $proposal->product_name }}</td></tr>
                    @endif
                    @if($proposal->premium !== null && $proposal->premium !== '')
                        <tr><td class="label">Premium</td></tr>
                        <tr><td>{{ ($proposal->currency ?: 'BWP') }} {{ number_format((float) $proposal->premium, 2) }}</td></tr>
                    @endif
                    @if(!empty($proposal->agent_id))
                        <tr><td class="label">Sold by agent</td></tr>
                        <tr><td>{{ $proposal->agent_id }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Alpha Direct Insurance Company (Pty) Ltd · Botswana Innovation Hub, Plot 69184, Floor 1, Bar 1, Block 8 Industrial, Gaborone<br>
    P.O. Box 26 ADC, Gaborone · (+267) 370 27 00 / 392 82 64 · www.alphadirect.co.bw · NBFIRA License No. 2/9/179
</div>

</body>
</html>
