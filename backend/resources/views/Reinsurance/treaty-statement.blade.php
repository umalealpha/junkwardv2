@php
    /*
     * The quarterly statement of account — RI-18 step 8, BR-ACC-03.
     *
     * BROKEN DOWN BY CLASS, AND BY SHARE WHEN THERE IS A PANEL. Article 10.2
     * requires both. The classes are always available; the shares are not, and
     * where they are missing this document says so on its face rather than
     * printing a total that looks complete.
     *
     * THE LETTERHEAD IS INLINED, for the same reason as the FAC slip: the PDF
     * engine has no session and no route back into the app, so asset() would
     * fetch nothing or, worse, the login page.
     */
    $logoPath = public_path('alphadirect_logo.png');
    $logo     = is_readable($logoPath)
        ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
        : null;

    $money = static fn ($v) => number_format((float) $v, 2);

    $treatyName = strtoupper((string) $statement->treaty);
    $year       = (int) $statement->underwriting_year;
    $yearLabel  = $year . '/' . substr((string) ($year + 1), -2);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement of Account - {{ $treatyName }} {{ $yearLabel }} Q{{ $statement->quarter }}</title>
    <style>
        @page { margin: 16mm 18mm; }
        body {
            font-family: "Book Antiqua", "Palatino Linotype", Palatino, Georgia, serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
            line-height: 1.35;
        }

        .brand { text-align: right; margin-bottom: 22px; }
        .brand .logo { width: 150px; }
        .brand .name { font-size: 17pt; font-weight: bold; letter-spacing: -.4px; }
        .brand .name .alpha  { color: #0D1B2A; }
        .brand .name .direct { color: #F4A623; font-style: italic; }
        .brand .sub { font-size: 7.5pt; color: #333; margin-top: -3px; }

        h1, h2 {
            font-size: 11.5pt; text-align: center; text-decoration: underline;
            margin: 0; font-weight: bold;
        }
        h1 { margin-bottom: 2px; }
        h2 { margin-bottom: 18px; }

        table.terms { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.terms td {
            border-top: 1px solid #000; border-bottom: 1px solid #000;
            padding: 4px 6px; vertical-align: top;
        }
        table.terms td.k { width: 26%; font-weight: bold; }

        .schedhead { font-weight: bold; margin: 14px 0 4px; font-size: 9pt; }
        .sched { width: 100%; border-collapse: collapse; }
        .sched td { padding: 1.5px 0; vertical-align: top; }
        .sched td.r { text-align: right; white-space: nowrap; width: 26%; }
        .sched td.rr { text-align: right; white-space: nowrap; width: 18%; color: #333; }
        .sched tr.subtot td { border-top: .5px solid #999; font-weight: bold; padding-top: 3px; }
        .sched tr.grandtot td { border-top: 1px solid #0D1B2A; border-bottom: 1px solid #0D1B2A;
                                font-weight: bold; padding: 4px 0; }

        /* A document that must not be treated as issued has to LOOK unissued,
           not merely say so in a footnote somebody skims past. */
        .notissuable {
            border: 1.5px solid #F4A623; padding: 8px 10px; margin-bottom: 16px;
            font-size: 9.5pt;
        }
        .notissuable .h { font-weight: bold; color: #0D1B2A; }

        .note { font-size: 8.5pt; color: #333; margin-top: 3px; }
        .foot { margin-top: 26px; font-size: 8.5pt; color: #333; }
    </style>
</head>
<body>

<div class="brand">
    @if ($logo)
        <img src="{{ $logo }}" alt="Alpha Direct Insurance Co." class="logo">
    @else
        {{-- Never a silent gap: a contractual document with no letterhead has to
             be visibly wrong, so somebody fixes the asset path. --}}
        <div class="name"><span class="alpha">Alpha</span><span class="direct">Direct</span></div>
        <div class="sub">Insurance Co.</div>
    @endif
</div>

<h1>ALPHA DIRECT INSURANCE COMPANY (PTY) LTD.</h1>
<h2>{{ $treatyName }} QUOTA SHARE - STATEMENT OF ACCOUNT</h2>

@if (! $issuable)
    {{--
        WHY IT CANNOT BE ISSUED, ON ITS FACE. BR-ACC-03 requires the account
        broken down by share and BR-SEC-08 requires every ceded amount allocated
        per reinsurer. Without the signing schedule the figures are right and the
        allocation is absent — which is exactly the document somebody would send
        by mistake, so it declares itself.
    --}}
    <div class="notissuable">
        <div class="h">INTERNAL WORKING COPY - NOT FOR ISSUE TO REINSURERS</div>
        @foreach ($blockers as $b)
            {{-- An ASCII bullet, not &bull;. The entity is ASCII in this file and
                 so passes a naive template scan, but DomPDF expands it to U+2022
                 and then prints a replacement character — which is how a single
                 mojibake byte survived the first fix. --}}
            <div>* {{ $b }}</div>
        @endforeach
    </div>
@endif

<table class="terms">
    <tr>
        <td class="k">Treaty</td>
        <td>{{ $treatyName }} Quota Share, underwriting year {{ $yearLabel }}</td>
    </tr>
    <tr>
        <td class="k">Account period</td>
        <td>Quarter {{ $statement->quarter }} - {{ $period['start'] }} to {{ $period['end'] }}</td>
    </tr>
    <tr>
        <td class="k">Accounts rendered by</td>
        <td>
            {{ $period['render_due'] }}
            <span class="note">(BR-ACC-01 - within 45 days of the close of the quarter)</span>
        </td>
    </tr>
    <tr>
        <td class="k">Confirmation due</td>
        <td>
            @if ($confirmDue)
                {{ $confirmDue }}
                <span class="note">(BR-ACC-02 - within 14 days of receipt)</span>
            @else
                Fourteen days from receipt (BR-ACC-02). Runs from the date this account is rendered.
            @endif
        </td>
    </tr>
    <tr>
        <td class="k">Currency</td>
        <td>Botswana Pula (BWP). All figures exclude VAT (BR-ACC-18).</td>
    </tr>
</table>

{{--
    THE SETTLING ITEMS, BY CLASS. Every line here enters the balance; the totals
    are the sum of the per-class lines, so the account adds up as printed rather
    than as recomputed from a rate.
--}}
@foreach ($sections as $section)
    @continue(empty($section['rows']))

    <div class="schedhead">{{ $section['heading'] }}</div>
    <table class="sched">
        @foreach ($section['rows'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="rr">{{ $row['rate'] ?? '' }}</td>
                <td class="r">{{ $money($row['amount']) }}</td>
            </tr>
        @endforeach
        <tr class="subtot">
            <td>{{ $section['total_label'] }}</td>
            <td class="rr"></td>
            <td class="r">{{ $money($section['total']) }}</td>
        </tr>
    </table>
@endforeach

<table class="sched" style="margin-top:14px">
    <tr class="grandtot">
        <td>BALANCE DUE {{ $balance >= 0 ? 'TO REINSURERS' : 'FROM REINSURERS' }}</td>
        <td class="rr"></td>
        <td class="r">{{ $money(abs($balance)) }}</td>
    </tr>
</table>
<div class="note">
    Settled on a cheque attached basis at the same time these accounts are rendered
    (BR-ACC-09). Delay in payment carries interest at 110% of the market prime
    lending rate from the due date to the date of payment (BR-ACC-10).
</div>

@if (! empty($memorandum))
    {{--
        REPORTED, NEVER SETTLED. Article 10.2.4 puts outstanding losses on the
        account because reinsurers need the reserve position; salvages and
        recoveries are already netted inside claims paid. Printing them below the
        balance rather than inside it is the whole point.
    --}}
    <div class="schedhead">MEMORANDUM - REPORTED, NOT SETTLED THIS QUARTER</div>
    <table class="sched">
        @foreach ($memorandum as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="rr">{{ $row['rate'] ?? '' }}</td>
                <td class="r">{{ $money($row['amount']) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="note">
        These figures do not enter the balance above. Outstanding losses are shown
        by {{ $outstandingAxis }} (BR-ACC-07).
    </div>
@endif

@if (! empty($shares))
    <div class="schedhead">ALLOCATION BY REINSURER (BR-ACC-03, BR-SEC-08)</div>
    <table class="sched">
        @foreach ($shares as $s)
            <tr>
                <td>{{ $s['reinsurer'] }}</td>
                <td class="rr">{{ number_format($s['share_pct'], 4) }}%</td>
                <td class="r">{{ $money($s['amount']) }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($reserves))
    <div class="schedhead">RESERVE DEPOSIT (BR-ACC-12 to 16)</div>
    <table class="sched">
        @foreach ($reserves as $r)
            <tr>
                <td>
                    {{ $r['reinsurer'] }}
                    @if ($r['exemption'])
                        <span class="note">- {{ $r['exemption'] }}</span>
                    @endif
                </td>
                <td class="rr">{{ $r['retained_pct'] ? number_format($r['retained_pct'], 2) . '%' : '' }}</td>
                <td class="r">{{ $money($r['balance_carried']) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="note">Balances carried, including interest accrued to the close of this quarter.</div>
@endif

<div class="foot">
    Prepared {{ $generatedAt }} from the {{ $cessionBasis }} cession basis.
    Alpha Direct Insurance Company (Pty) Ltd, Bar 2, Floor 2, Botswana Innovation Hub,
    Plot 69184, Block 8, Gaborone.
</div>

</body>
</html>
