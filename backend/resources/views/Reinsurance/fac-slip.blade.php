{{--
  ALPHA DIRECT FAC SLIP.

  This reproduces the REAL Alpha Direct facultative slip, taken from the 12
  signed 2026 Professional Indemnity slips supplied by Kago Tshutlhedi on
  30 July 2026 (hard-locked at ~/.claude/skills/prat-skill/facdoc/slip-samples/).
  It is NOT a table of placement lines — it is a single-risk TERM SHEET, then an
  acceptance panel the reinsurer signs, stamps and returns.

  Row order, labels and wording follow the samples exactly, because the
  reinsurers already accept that document. Two labels look wrong and are correct:
  "Gross Rate" is the gross ceded premium, and "Broker/Agent" prints the word
  DIRECT when no broker fronts the placement rather than being left blank.

  Where a slip carries several placement lines (a monthly risk, for instance) the
  money rows are the SUM of those lines and each accepting company's share
  appears in the panel — one slip per risk, never one per instalment.
--}}
@php
    use Illuminate\Support\Carbon;

    $pct  = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format(((float) $v) * 100, 2, '.', ''), '0'), '.') . '%';
    $date = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '—';

    $live  = $lines->reject(fn ($l) => $l->status === 'cancelled' || $l->is_reversal);
    $first = $live->first() ?: $lines->first();

    /*
     * The currency the slip is written in.
     *
     * Every sample prints Pula as "P" and the reinsurers accept that document, so
     * BWP keeps its symbol exactly. Anything else prints its ISO code: the FAC
     * master sheet carries a US Dollar tab, and a Dollar figure prefixed "P" is a
     * mislabelled contractual document. The placement record has always held the
     * currency — only this template ignored it.
     *
     * A slip cannot span two currencies: FacSlipService::generate() refuses to
     * produce one, because the money rows here are the SUM of the lines and a
     * total across currencies is a figure in no currency at all. The fallback to
     * BWP below therefore never fires on a mixed slip — it covers a line that
     * carries no currency at all, where Pula is the documented default.
     */
    $ccy = (string) ($live->pluck('currency')->filter()->first()
        ?: $lines->pluck('currency')->filter()->first()
        ?: 'BWP');

    $P = fn ($v) => $v === null
        ? '—'
        : ($ccy === 'BWP' ? 'P' : $ccy . ' ') . number_format((float) $v, 2);

    $grossTotal = (float) $live->sum(fn ($l) => (float) $l->gross_ceded_premium);
    $netTotal   = (float) $live->sum(fn ($l) => (float) $l->net_ceded_premium);

    /*
     * How the premium is paid, and what one instalment comes to.
     *
     * The signed slip states "(Quarterly Payments)" under the period and carries the
     * quarterly figure beside the annual one — 6,383.23 against 25,532.91. Both were
     * missing entirely.
     *
     * The instalment is DERIVED, never stored. On slip 2026-002 the quarterly figure
     * is exactly the annual divided by four, so a stored one could only ever drift
     * from the annual it divides. Where the frequency is annual or not stated there
     * is no instalment to print and nothing is printed — silence is correct, rather
     * than repeating the annual figure under a second heading.
     */
    $freq = strtolower(trim((string) ($first->premium_frequency ?? '')));
    $instalments = ['quarterly' => 4, 'monthly' => 12, 'semi_annual' => 2, 'semi-annual' => 2][$freq] ?? null;
    $freqLabel = $instalments ? ucfirst(str_replace(['_', '-'], ' ', $freq)) : null;
    $instalmentNet = $instalments && $netTotal > 0 ? round($netTotal / $instalments, 2) : null;
    $commPct    = $first->commission_pct ?? null;

    $periodFrom = $slip->period_from ?? $first->period_from ?? null;
    $periodTo   = $slip->period_to   ?? $first->period_to   ?? null;

    /*
     * TOTAL LIMITS OF INDEMNITY — the whole sum insured, not the ceded share.
     *
     * This printed the CESSION amount, which Reinsurance flagged against slip
     * 2026-002: the slip states total limits of indemnity 300,580,000 and the
     * 50,000,000 cession appears only in the acceptance panel's Amount column.
     * Printing the cession under this heading told a reinsurer the risk was six
     * times smaller than it is.
     *
     * Taken from the schedule where one has been captured — it is the sum of the
     * itemised lines, and on the signed slip those agree exactly. Falls back to
     * the stored figure, then to the cession, so a placement with no schedule
     * still prints something rather than a blank contractual term.
     */
    $scheduleTotal = isset($schedule) ? (float) ($schedule['totalLimitsOfIndemnity'] ?? 0) : 0.0;
    $limit = $scheduleTotal > 0
        ? $scheduleTotal
        : ($slip->limit_of_indemnity ?: $live->max(fn ($l) => (float) $l->cession_sum_insured));

    // Whether a broker fronts this placement. Decides only the wording of the two
    // premium rows — see the note there. Mirrors the Broker/Agent row, which
    // prints DIRECT when nobody fronts it.
    $viaBroker = strtoupper(trim((string) ($slip->broker_agent ?: 'DIRECT'))) !== 'DIRECT';

    // The original policy's annual premium. Only printed when the register holds
    // one; it is not derivable from the ceded figures.
    $annualPremium = ($first->source_premium ?? null) > 0 ? (float) $first->source_premium : null;

    /*
     * The letterhead logo, inlined as a data URI.
     *
     * Read from disk rather than linked: this template is rendered by a PDF engine
     * with no session and no route back into the app, so asset() would resolve to a
     * URL the renderer cannot usefully fetch. Null when the file is missing, and
     * the markup then falls back visibly rather than printing a broken image.
     */
    $logoPath = public_path('alphadirect_logo.png');
    $logo     = is_readable($logoPath)
        ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
        : null;

    /*
     * PREMIUM PAYMENT WARRANTY IS NOT PRINTED HERE.
     *
     * Reinsurance instructed on 24 August 2026 that the PPW terms must not appear
     * on the automated slip. They are the reinsurer's to set, and they arrive on the
     * countersigned slip that comes back — printing our own assumption first invites
     * a reinsurer to sign against a window they never agreed. Where the returned
     * slip states nothing, the default assumption is 90 days.
     *
     * The register still holds ppw_terms and ppw_days, and still runs the breach
     * alarm off ppw_due_date. This removes the terms from the DOCUMENT only.
     */

    // Total ceded share across this slip's lines. Printed the way the samples do
    // — "100% (Nil Retention by Reinsured)" when the whole risk went out.
    $cededPct  = (float) $live->sum(fn ($l) => (float) ($l->risk_pct ?? 0));

    /*
     * A panel row's share OF THE CESSION, as the signed slips express it.
     *
     * One reinsurer taking the whole ceded 17% reads "17% of 100% Cession", not
     * "17% of 17%". Three splitting it read 50%, 30% and 20% against the same
     * first figure. Falls back to 100% when the cession total is nil or unknown,
     * because a lone reinsurer on a slip with no stated cession still holds all
     * of whatever was ceded — printing 0% would understate their commitment.
     */
    $ofCession = function ($sharePct) use ($cededPct, $pct) {
        if ($cededPct <= 0) {
            return '100%';
        }
        $ratio = (float) $sharePct / $cededPct;

        return $ratio >= 0.9999 ? '100%' : $pct($ratio);
    };
    $cededText = $slip->risk_ceded_text
        ?: ($cededPct >= 0.9999
            ? '100% (Nil Retention by Reinsured)'
            : ($cededPct > 0 ? $pct($cededPct) : '—'));

    $acceptances = ($slip->relationLoaded('acceptances') && $slip->acceptances->isNotEmpty())
        ? $slip->acceptances
        // Nothing countersigned yet — pre-print the panel off the placement lines
        // so the reinsurer has something to sign and return.
        : $live->map(fn ($l) => (object) [
            'accepting_company' => $l->counterparty_name,
            'share_pct'         => $l->risk_pct,
            'amount'            => $l->cession_sum_insured,
            'signatory_name'    => null,
            'accepted_on'       => null,
        ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>FAC Slip {{ $slip->slip_no }}</title>
    <style>
        @page { margin: 16mm 18mm; }
        body {
            font-family: "Book Antiqua", "Palatino Linotype", Palatino, Georgia, serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
            line-height: 1.35;
        }

        /* The schedule blocks. Laid out like the signed slips: a bold block
           heading, then label on the left and money right-aligned. */
        .schedhead { font-weight: bold; margin: 14px 0 4px; font-size: 9pt; }
        .sched { width: 100%; border-collapse: collapse; }
        .sched td { padding: 1.5px 0; vertical-align: top; }
        .sched td.r { text-align: right; white-space: nowrap; width: 32%; }
        .sched tr.subtot td { border-top: .5px solid #999; font-weight: bold; padding-top: 3px; }
        .sched tr.grandtot td { border-top: 1px solid #0D1B2A; border-bottom: 1px solid #0D1B2A;
                                font-weight: bold; padding: 4px 0; }

        .brand { text-align: right; margin-bottom: 22px; }
        /* Width only, so the aspect ratio is the file's own and the mark is never
           stretched. The source is 1277x311. */
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
        h2 { margin-bottom: 22px; }

        table.terms { width: 100%; border-collapse: collapse; }
        table.terms td {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.terms td.k { width: 38%; }
        table.terms tr:first-child td { border-top: none; }
        .clause { font-style: italic; display: block; margin-top: 4px; }

        .deductible { margin: 20px 0 18px; }

        /* Notes sit between the deductible and the acceptance panel, in the same
           weight as the rest of the terms — this is contractual wording, not an
           aside. page-break-inside so a long note is not split across the page
           break that lands on the signature table. */
        .notes { margin: 0 0 18px; page-break-inside: avoid; }
        .notes-head { font-weight: bold; margin: 0 0 4px; }
        .notes-body { margin: 0; }

        .tsi { margin: 18px 0 2px; }
        .subjectto { margin-top: 14px; }

        table.accept { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.accept th, table.accept td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 10pt;
            vertical-align: top;
        }
        table.accept th { font-weight: normal; text-align: center; }
        table.accept td.sigcell { height: 46px; }
        table.accept .c { text-align: center; }

        .prepared { text-align: center; font-weight: bold; margin-top: 6px; letter-spacing: .3px; }

        .foot { margin-top: 26px; font-size: 7.5pt; color: #555;
                border-top: .5px solid #999; padding-top: 6px; }
    </style>
</head>
<body>

{{--
    The real logo, embedded rather than linked.

    This used to be the brand name set in text, which Reinsurance rejected as the
    wrong logo. It is base64-inlined because the PDF renderer has no session and
    no route to the app — a plain asset() URL would either fetch nothing or, worse,
    fetch the login page. Inlining means the slip carries its own logo and renders
    identically from a queue worker, a CLI command or a web request.
--}}
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
{{--
    The heading names the BASIS, not just the document. An automatic facultative
    placement and a facultative one are different instruments on different terms,
    and a reinsurer reading "FAC SLIP" on an Auto FAC placement is being told the
    wrong thing about what they are signing.
--}}
<h2>{{ $slip->placement_type === 'auto_fac' ? 'AUTOMATIC FACULTATIVE SLIP NO' : 'FAC SLIP NO' }} {{ $slip->slip_no }}</h2>

<table class="terms">
    <tr>
        <td class="k">Insured(s)</td>
        <td>{{ $slip->insured_name ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Type and Extent of Cover Granted</td>
        <td>{{ strtoupper($slip->cover_granted ?: ($first->ri_group_label ?? '-')) }}</td>
    </tr>
    <tr>
        {{-- The samples print DIRECT rather than leaving this blank. --}}
        <td class="k">Broker/Agent</td>
        <td>{{ strtoupper($slip->broker_agent ?: 'DIRECT') }}</td>
    </tr>
    <tr>
        <td class="k">Description of Risk:</td>
        <td>
            @if ($slip->description_of_risk)
                {{ $slip->description_of_risk }}
            @else
                {{ strtoupper($slip->cover_granted ?: ($first->ri_group_label ?? 'INSURANCE')) }}
                PERTAINING THE RISK KNOWN AS {{ strtoupper($slip->insured_name ?: 'THE INSURED') }}@if ($slip->policy_number) PER ALPHA DIRECT INSURANCE POLICY NUMBER {{ $slip->policy_number }}@endif.
            @endif
            {{-- Standard clause, present on every sample. Verbatim. --}}
            <span class="clause">
                All other terms and conditions as per original policy issued by
                Alpha Direct and Reinsurers to follow the fortunes of Reinsured.
            </span>
        </td>
    </tr>
    <tr>
        <td class="k">Situation/territorial Scope</td>
        <td>{{ $slip->territorial_scope ?: 'BOTSWANA and other Territories as Per Policy Document' }}</td>
    </tr>
    <tr>
        <td class="k">Basis of Cover</td>
        <td>{{ $slip->basis_of_cover ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Reinsurance Commission</td>
        <td>{{ $pct($commPct) }}</td>
    </tr>
    <tr>
        <td class="k">Period of Reinsurance and/or Insurance</td>
        {{-- The signed slips put the payment frequency here, in brackets under the
             period, because it qualifies the period rather than the premium. --}}
        <td>
            {{ $date($periodFrom) }} to {{ $date($periodTo) }}
            @if ($freqLabel)
                <br><strong>({{ $freqLabel }} Payments)</strong>
            @endif
        </td>
    </tr>
    <tr>
        <td class="k">Risk Ceded to Re-Insurer(s)</td>
        <td>{{ $cededText }}</td>
    </tr>
    <tr>
        {{-- Worded as the signed slips word it, and only pluralised where a
             schedule is behind it. --}}
        <td class="k">{{ $scheduleTotal > 0 ? 'Total Limits of Indemnity' : 'Limit of Indemnity' }}</td>
        <td>{{ $P($limit ?: null) }}</td>
    </tr>
    @if ($annualPremium)
    <tr>
        {{-- The original policy's full annual premium, which the ceded share is
             worked out from. Printed on the broker-fronted samples above the
             premium rows; omitted when the register holds no source premium
             rather than printing an invented figure. --}}
        <td class="k">Annual Premium</td>
        <td>{{ $P($annualPremium) }}</td>
    </tr>
    @endif
    <tr>
        {{-- Two wordings are in use on the signed slips, and they track who the
             premium is actually due to. A broker-fronted placement says "due to
             Re-insurance Broker" (slip 2026-026, via SSRE); a direct placement
             says "Gross Rate" / "due to Re-insurer(s)" (slip 2026-004, DIRECT).
             The amounts are identical either way — only the label moves, so the
             document the reinsurers already accept does not change under them. --}}
        <td class="k">{{ $viaBroker ? 'Gross due to Re-insurance Broker' : 'Gross Rate' }}</td>
        <td>{{ $P($grossTotal) }}</td>
    </tr>
    <tr>
        <td class="k">{{ $viaBroker ? 'Premium due to Re-insurance Broker (Net)' : 'Premium due to Re-insurer(s) (Net)' }}</td>
        <td>{{ $P($netTotal) }}</td>
    </tr>
    @if ($instalmentNet !== null)
    <tr>
        {{-- The instalment, beside the annual figure, exactly as slip 2026-002
             prints 6,383.23 against 25,532.91. Derived from the annual, so the two
             can never disagree. --}}
        <td class="k">{{ $freqLabel }} Premium Due to Re-insurer(s)</td>
        <td><strong>{{ $P($instalmentNet) }}</strong></td>
    </tr>
    @endif
</table>

{{--
    THE SCHEDULE — what is actually insured.

    Reinsurance's defect 8: the generated slip stated a single figure where the
    signed slip itemises nine lines of Fire & Allied Perils and six of Business
    Interruption. A reinsurer cannot underwrite a total; they price the items.

    Printed only where a schedule has been captured, so placements without one
    render exactly as before rather than growing an empty heading. Each block
    carries its own subtotal, and a line with no amount — "Indemnity period –
    15 months" — prints its label alone rather than a nil figure.
--}}
@if (!empty($schedule['sections']))
    @foreach ($schedule['sections'] as $section)
    <p class="schedhead">{{ $section['title'] }}</p>
    <table class="sched">
        <tbody>
            @foreach ($section['lines'] as $line)
            <tr>
                <td>{{ $line['label'] }}</td>
                {{-- A null amount is not nil cover. The cell is left empty. --}}
                <td class="r">{{ $line['amount'] === null ? '' : $P($line['amount']) }}</td>
            </tr>
            @endforeach
            @if (count($section['lines']) > 1 && $section['subtotal'] > 0)
            <tr class="subtot">
                <td>{{ $section['title'] }} - total</td>
                <td class="r">{{ $P($section['subtotal']) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endforeach

    {{-- Restated under the schedule, where the signed slip puts it, so the
         itemised lines and the figure they add to are read together. --}}
    <table class="sched">
        <tbody>
            <tr class="grandtot">
                <td>TOTAL LIMITS OF INDEMNITY</td>
                <td class="r">{{ $P($schedule['totalLimitsOfIndemnity']) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<p class="deductible">
    Deductible: {{ $slip->deductible_text ?: 'as per the original policy.' }}
</p>

{{--
    The placement terms that do not fit one of the named rows above (Reinsurance,
    7 September 2026). Printed only when there are some: an empty "Notes" heading
    on a signed contractual document reads as terms omitted rather than terms
    absent. nl2br+e, not raw — the underwriter types this, and a slip is not a
    place to render markup they did not intend.
--}}
@if (trim((string) $slip->slip_notes) !== '')
    <div class="notes">
        <p class="notes-head">Notes</p>
        <p class="notes-body">{!! nl2br(e($slip->slip_notes)) !!}</p>
    </div>
@endif

{{-- Caption the signed slips carry above this table. --}}
<p class="tsi">Reinsurer's Proportion of T.S.I (Collective/RI)</p>

<table class="accept">
    <thead>
        <tr>
            <th style="width:24%">Accepting Company</th>
            <th style="width:14%">%</th>
            <th style="width:18%">Amount</th>
            <th style="width:18%">Name</th>
            <th style="width:26%">Signature, Seal &amp; Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($acceptances as $a)
        <tr>
            <td>{{ strtoupper($a->accepting_company ?? '-') }}</td>
            {{--
                "17.00% of 100.00% Cession", as the signed slips word it.

                Two DIFFERENT percentages, and printing the same one twice was the
                defect Reinsurance reported as "17% of 17%":
                  · the first is this reinsurer's share of the whole risk;
                  · the second is its share OF THE CESSION — 100% where it takes
                    the cession alone, and its slice where a panel splits it.
                Derived here rather than stored, so a panel of three reading
                50% / 30% / 20% always adds to the cession and cannot drift from it.
            --}}
            <td class="c">
                @if ($a->share_pct !== null)
                    {{ $pct($a->share_pct) }} of {{ $ofCession($a->share_pct) }} Cession
                @else
                    -
                @endif
            </td>
            <td class="c">{{ $P($a->amount) }}</td>
            <td class="sigcell">{{ $a->signatory_name ?? '' }}</td>
            <td class="sigcell">{{ $a->accepted_on ? $date($a->accepted_on) : '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{--
    The underwriter in charge of the placement, and nobody else.

    It used to prefer whoever happened to be logged in when the slip was generated,
    which is not the same person and on the sample came out blank. The placement
    records its underwriter; that is who prepared it. The generator's identity is on
    the audit trail, which is where it belongs.
--}}
<p class="prepared">PREPARED BY {{ strtoupper($first->underwriter_name ?: ($slip->prepared_by_name ?? '')) }}</p>

{{--
    NO "SUBJECT TO" BLOCK, AND NO PPW.

    Removed on Reinsurance's instruction of 24 August 2026. The premium payment
    warranty, the nil-COVID and nil-claims conditions and the reinsurer's own
    subjectivities are theirs to state, and they arrive on the countersigned slip.
    Printing our assumption first invites them to sign against a window we invented.
    Where the returned slip is silent, the default assumption is 90 days.
--}}

<div class="foot">
    Alpha Direct Insurance Company (Pty) Ltd | Floor 2, Bar 2, Botswana Innovation Hub
    Icon Building, Plot 69184, Block 8 Industrial, P.O. Box 26ADC, Gaborone, Botswana
    | Tel +267 3928264 | reinsurance@alphadirect.co.bw<br>
    Generated from the Graphite FAC register on {{ $generatedAt->format('d F Y H:i') }}.
    Slip {{ $slip->slip_no }} version {{ $slip->version }}@if ($slip->version > 1) - this replaces all earlier versions.@endif
    @if ($live->count() > 1) Covering {{ $live->count() }} placement lines. @endif
</div>

</body>
</html>
