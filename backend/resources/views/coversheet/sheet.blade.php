{{--
    The one-page policy cover sheet — what we post to the client instead of the
    full 10–20 page pack.

    Set in the SAME house style as v2-quote-sheet.blade.php, the document
    clients already hold, so the sheet reads as page one of something they
    recognise rather than a new kind of letter: Arial, the #2e77c3 accent with
    blue labels and black values, 2px blue rules, the #ecd5c2 section bar and
    the #e5f4e3 note box. Same logo artwork and address block.

    Rendered by CoverSheetDocumentService with DomPDF, so no flex/grid — tables
    and floats only, exactly like the document blade.

    The QR carries a per-policy token pointing at /p/d/{token}. It grants
    nothing on its own: the client still has to pass the identity check.
--}}
<!Doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $policyNumber }} - Policy Cover Sheet</title>
    <style>
        @page { margin: 26px 30px; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            font-weight: 500;
            color: #1a1a1a;
            margin: 0;
        }
        table { border-spacing: 0; border-collapse: collapse; width: 100%; }
        td { padding: 0; vertical-align: top; }
        p { margin: 0; }

        hr.blue {
            border: none;
            border-top: 2px solid #2e77c3;
            background-color: #2e77c3;
            height: 0;
            margin: 5px 0;
        }
        .ason { font-size: 10px; text-align: right; }
        .addr p { font-size: 9.5px; color: #000; line-height: 13px; }
        .addr p.web span { color: #2e77c3; }
        .doctitle { text-align: right; color: #2e77c3; font-size: 19px; line-height: 22px; }
        .doctitle-sub { text-align: right; color: #1a1a1a; font-size: 9.5px; padding-top: 4px; }
        .readwith { text-align: center; font-size: 12px; font-weight: bold; color: #000; }

        .lob p { color: #2e77c3; font-size: 11px; line-height: 16px; }
        .lob p span { color: #000; }

        table.sched td { border-top: 1px solid #2e77c3; border-bottom: 1px solid #2e77c3; padding: 5px 4px; }
        table.sched td.k { width: 38%; color: #2e77c3; font-size: 10px; }
        table.sched td.v { color: #000; font-size: 10.5px; }
        table.sched td.v b { font-weight: bold; font-size: 11px; }
        table.sched td.v span.sub { color: #444; font-size: 8.5px; }

        .qrbar {
            background-color: #ecd5c2; color: #1a1a1a; font-size: 9.5px; font-weight: bold;
            text-align: center; padding: 4px 5px; border: 1px solid #2e77c3;
        }
        .qrbody {
            background-color: #e5f4e3; text-align: center; padding: 8px 7px;
            border-left: 1px solid #2e77c3; border-right: 1px solid #2e77c3;
        }
        .qrbody p.cta { font-size: 10px; font-weight: bold; color: #000; padding-top: 6px; }
        .qrbody p.fine { font-size: 8.5px; color: #333; line-height: 11px; padding-top: 3px; }
        .qrlock {
            background-color: #C6EED8; border: 1px solid #2e77c3;
            font-size: 8.5px; line-height: 11px; color: #1a1a1a; padding: 5px 6px;
        }
        .typed { font-size: 8px; color: #444; padding-top: 5px; word-break: break-all; }
        .note {
            background-color: #e5f4e3; border: 1px solid #2e77c3;
            font-size: 9.5px; line-height: 13px; color: #1a1a1a; padding: 6px 7px;
        }
        .foot { border-top: 2px solid #2e77c3; padding-top: 4px; font-size: 8.5px; color: #555; }
    </style>
</head>
<body>

<p class="ason">As On: {{ $today }}</p>

<table style="margin-top:4px;">
    <tr valign="top">
        <td width="55%">
            {{-- Same artwork the policy document pulls, so the pair match. --}}
            <img style="width: 250px;" src="{{ $logoUrl }}">
            <div class="addr" style="padding-top:6px;">
                <p>Alpha Direct Insurance Co. (Pty) Ltd.</p>
                <p>Office: Floor 2, Bar 2, Botswana Innovation Hub,</p>
                <p>Icon Building, Plot 69184 Block 8 Industrial</p>
                <p>Postal Address: P.O. Box 26ADC, Gaborone, Botswana</p>
                <p>Phone +267 392 8264 | Fax +267 392 8265</p>
                <p class="web">debtors@alphadirect.co.bw | <span>www.alphadirect.co.bw</span></p>
            </div>
        </td>
        <td width="45%">
            <p class="doctitle">POLICY COVER SHEET</p>
            <p class="doctitle-sub">Summary of your insurance &mdash; please keep this page</p>
        </td>
    </tr>
</table>

<p class="readwith" style="padding-top:10px;">This cover sheet should be read in conjunction with the policy wording.</p>

<hr class="blue">
<div class="lob">
    <p>Line of Business : <span>{{ $lineOfBusiness }}</span></p>
    <p>Policy No. : <span>{{ $policyNumber }}</span></p>
</div>
<hr class="blue">

<table style="margin-top:8px;">
    <tr valign="top">
        <td width="62%" style="padding-right:12px;">

            <table class="sched">
                <tr>
                    <td class="k">Insured Name :</td>
                    <td class="v"><b>{{ $insuredName }}</b></td>
                </tr>
                @if ($insuredAddress)
                <tr>
                    <td class="k">Insured Mailing Address :</td>
                    <td class="v">{{ $insuredAddress }}</td>
                </tr>
                @endif
                <tr>
                    <td class="k">Class of Cover :</td>
                    <td class="v">{{ $classOfCover }}</td>
                </tr>
                <tr>
                    <td class="k">Period of Insurance :</td>
                    <td class="v">{{ $periodFrom }} to {{ $periodTo }}
                        <br><span class="sub">Both dates inclusive</span>
                    </td>
                </tr>
                <tr>
                    <td class="k">Total Sum Insured :</td>
                    <td class="v">
                        @if ($sumInsured)
                            <b>{{ $sumInsured }}</b>
                            <br><span class="sub">Sum insured per section is set out in the full schedule</span>
                        @else
                            {{-- No verified whole-policy total exists for a
                                 multi-section risk, so we point at the schedule
                                 rather than print a figure we cannot stand
                                 behind on a client document. --}}
                            As set out per section in the full schedule
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="k">Premium Payable :</td>
                    <td class="v"><b>{{ $premium }}</b>
                        <br><span class="sub">{{ $premiumBasis }}</span>
                    </td>
                </tr>
                @if ($intermediary)
                <tr>
                    <td class="k">Intermediary :</td>
                    <td class="v">{{ $intermediary }}</td>
                </tr>
                @endif
            </table>

        </td>
        <td width="38%">

            <p class="qrbar">YOUR FULL POLICY DOCUMENT</p>
            <div class="qrbody">
                <img style="width:130px;height:130px;" src="{{ $qrDataUri }}">
                <p class="cta">Scan to open your policy document</p>
                <p class="fine">Full wording, schedule, sums insured per section, excesses and exceptions.</p>
            </div>
            <p class="qrlock"><b>Identity check first.</b> Sign in, or enter the one-time code sent to the
                mobile number on your policy. The scan alone will not open the document.</p>
            {{-- Printed so a client who cannot scan still has a way in. --}}
            <p class="typed">Or type: {{ $scanUrl }}</p>

        </td>
    </tr>
</table>

<p class="note" style="margin-top:12px;">
    <b>This is a summary only.</b> Your policy wording, full schedule, sums insured per section, excesses,
    exceptions and conditions are contained in the document behind the QR code and prevail over this sheet.
    Claims and enquiries: +267 392 8264 &nbsp;|&nbsp; debtors@alphadirect.co.bw
</p>

<table style="margin-top:14px;">
    <tr>
        <td class="foot" width="62%">Alpha Direct Insurance Co. (Pty) Ltd. &middot; Licensed by NBFIRA</td>
        <td class="foot" width="38%" style="text-align:right;">Issued {{ $issuedOn }} &middot; Page 1 of 1</td>
    </tr>
</table>

</body>
</html>
