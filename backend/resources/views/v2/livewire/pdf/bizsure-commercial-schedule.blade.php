{{--
    BizSure / Commercial (COMD) Policy Schedule
    -------------------------------------------
    Rendered by DocumentController@generatePolicyDocument for policies whose
    leadSource === 'bizsure' OR whose policyNumber starts with 'COMD'. Replaces
    the wrong domestic-motor fallback (mail_schedule.index) these policies used
    to fall through to.

    Data contract (all optional / null-safe):
      $policy                   – policies row (incl. 12 BizSure business fields)
      $premium                  – annual premium (treated as VAT-inclusive, to
                                  match the existing commercial schedule)
      $fromDate / $toDate       – term start / end, 'd-m-Y' strings
      $today                    – issue date, 'd F Y' string
      $commercialCoverages      – Collection<PolicyCoverage> with coverage,
                                  coverageDetail(+coverage), extentionDetail
                                  (+extention), specifedItems(+specifiedCoverages
                                  Items), riskAddress(+state,+city)
      $commercialDirectors      – Collection<PolicyDirector>
      $commercialRiskAddresses  – Collection<RiskAddress> (+state,+city)

    Must render under BOTH DomPDF and Puppeteer: inline CSS only, no external
    fonts / images / stylesheets.
--}}
@php
    $NAVY   = '#010066';
    $ORANGE = '#FE7F0C';

    // Currency: "P 1,234.56". Always cast + default so a null never crashes.
    $money = function ($v) {
        return 'P ' . number_format((float) str_replace(',', '', (string) ($v ?? 0)), 2, '.', ',');
    };

    $policyNumber = $policy->policyNumber ?? '';
    $businessName = $policy->business_name
        ?? trim(($name ?? '') !== '' ? $name : (($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')));
    $businessName = trim($businessName) !== '' ? trim($businessName) : 'N/A';

    // VAT split — mirror the existing commercial schedule: annual premium is
    // treated as VAT-inclusive, VAT rate from Region 7 (Botswana), fallback 14%.
    // exclVat + vat === gross by construction, so the header always balances.
    try {
        $vatRate = (float) (optional(\AlphaDirect\Region::where('id', 7)->first())->vat ?? 14);
    } catch (\Throwable $e) {
        $vatRate = 14;
    }
    $grossInclVat   = (float) str_replace(',', '', (string) ($premium ?? 0));
    $premiumExclVat = $vatRate > 0 ? $grossInclVat / (1 + ($vatRate / 100)) : $grossInclVat;
    $vatAmount      = $grossInclVat - $premiumExclVat;

    $coverages = collect($commercialCoverages ?? []);
    $directors = collect($commercialDirectors ?? []);
    $riskAddrs = collect($commercialRiskAddresses ?? []);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $policyNumber }} - Commercial Policy Schedule</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #202020;
            margin: 0;
            padding: 18px;
        }
        h1, h2, h3 { margin: 0; color: {{ $NAVY }}; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #cfcfe0; padding: 5px 7px; vertical-align: top; text-align: left; }
        .no-border, .no-border td { border: none; }
        .section-title {
            background: {{ $NAVY }};
            color: #ffffff;
            font-weight: bold;
            padding: 6px 7px;
        }
        .subhead td { background: #e7e7f4; color: {{ $NAVY }}; font-weight: bold; }
        .cover-head td { background: {{ $NAVY }}; color: #ffffff; font-weight: bold; }
        .accent { color: {{ $ORANGE }}; }
        .right { text-align: right; }
        .muted { color: #666666; }
        .mt { margin-top: 14px; }
        .mb { margin-bottom: 6px; }
        .label { color: {{ $NAVY }}; font-weight: bold; width: 32%; }
        .total-row td { background: #fff2e6; font-weight: bold; }
    </style>
</head>
<body>

    {{-- ============================ HEADER ============================ --}}
    <table class="no-border mb">
        <tr>
            <td class="no-border" style="width:62%;">
                <h1 style="font-size:18px;">Alpha Direct Insurance Co. (Pty) Ltd</h1>
                <div class="muted">Botswana Innovation Hub, Gaborone, Botswana</div>
                <div class="muted">debtors@alphadirect.co.bw | www.alphadirect.co.bw</div>
            </td>
            <td class="no-border right" style="width:38%;">
                <h2 style="font-size:15px;" class="accent">COMMERCIAL POLICY SCHEDULE</h2>
                <div>Issued: {{ $today ?? '' }}</div>
            </td>
        </tr>
    </table>

    <table class="mb">
        <tr>
            <td class="label">Insured (Business)</td>
            <td>{{ $businessName }}</td>
            <td class="label">Policy Number</td>
            <td>{{ $policyNumber !== '' ? $policyNumber : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Period of Insurance</td>
            <td>{{ $fromDate ?? 'N/A' }} to {{ $toDate ?? 'N/A' }}</td>
            <td class="label">Product</td>
            <td>{{ optional($product)->name ?? optional($product)->product_name ?? 'Commercial' }}</td>
        </tr>
    </table>

    {{-- ===================== PREMIUM SUMMARY ===================== --}}
    <table class="mb">
        <tr class="subhead"><td colspan="2">Premium Summary (Annual)</td></tr>
        <tr>
            <td>Premium (Excl. VAT)</td>
            <td class="right">{{ $money($premiumExclVat) }}</td>
        </tr>
        <tr>
            <td>VAT ({{ rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.') }}%)</td>
            <td class="right">{{ $money($vatAmount) }}</td>
        </tr>
        <tr class="total-row">
            <td>Total Premium (Incl. VAT)</td>
            <td class="right">{{ $money($grossInclVat) }}</td>
        </tr>
        @if(isset($balance_due) && $balance_due !== null)
        <tr>
            <td>Balance Due</td>
            <td class="right">{{ $money($balance_due) }}</td>
        </tr>
        @endif
    </table>

    {{-- ===================== BUSINESS DETAILS ===================== --}}
    <table class="mb">
        <tr class="subhead"><td colspan="4">Business Details</td></tr>
        <tr>
            <td class="label">Occupation / Trade</td>
            <td>{{ $policy->occupation_type ?? 'N/A' }}</td>
            <td class="label">Business Structure</td>
            <td>{{ $policy->business_structure ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Annual Turnover</td>
            <td>{{ isset($policy->annual_turnover) && $policy->annual_turnover !== null ? $money($policy->annual_turnover) : 'N/A' }}</td>
            <td class="label">Years in Business</td>
            <td>{{ $policy->years_in_business ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Number of Employees</td>
            <td>{{ $policy->number_of_employees ?? 'N/A' }}</td>
            <td class="label">Floor Area (sqm)</td>
            <td>{{ $policy->floor_area_sqm ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Property Ownership</td>
            <td>{{ $policy->property_ownership ?? 'N/A' }}</td>
            <td class="label">Company Reg. No.</td>
            <td>{{ $policy->company_reg_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">TIN Number</td>
            <td>{{ $policy->tin_number ?? 'N/A' }}</td>
            <td class="label">VAT Number</td>
            <td>{{ $policy->vat_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Postal Address</td>
            <td colspan="3">{{ $policy->postal_address ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- ===================== RISK ADDRESS(ES) ===================== --}}
    <table class="mb">
        <tr class="subhead"><td colspan="3">Risk Address / Insured Premises</td></tr>
        <tr class="subhead" style="font-weight:normal;">
            <td style="width:55%;">Address</td>
            <td style="width:25%;">City</td>
            <td style="width:20%;">State / District</td>
        </tr>
        @forelse($riskAddrs as $addr)
            @php
                $line = trim(implode(', ', array_filter([
                    $addr->address_name ?: null,
                    $addr->physical_address ?: null,
                ])));
            @endphp
            <tr>
                <td>{{ $line !== '' ? $line : 'N/A' }}</td>
                <td>{{ optional($addr->city)->name ?? 'N/A' }}</td>
                <td>{{ optional($addr->state)->name ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">{{ $policy->postal_address ?? 'No risk address captured.' }}</td>
            </tr>
        @endforelse
    </table>

    {{-- ========================= COVERAGES ========================= --}}
    <div class="mt mb"><h3 style="font-size:13px;">Coverage Schedule</h3></div>

    @php $grandPremium = 0; @endphp
    @forelse($coverages as $cov)
        @php
            $coverName = optional($cov->coverage)->s_ScreenName
                ?? optional($cov->coverage)->s_CoverageName
                ?? 'Cover';
            $coverPremium = 0;

            $subCovers = collect($cov->coverageDetail ?? [])->filter(fn ($d) => empty($d->deleted_at));
            $exts      = collect($cov->extentionDetail ?? [])->filter(fn ($e) => empty($e->deleted_at));
            $items     = collect($cov->specifedItems ?? [])->filter(fn ($i) => empty($i->deleted_at));

            $covRiskLine = '';
            if ($cov->riskAddress) {
                $covRiskLine = trim(implode(', ', array_filter([
                    $cov->riskAddress->address_name ?: null,
                    $cov->riskAddress->physical_address ?: null,
                    optional($cov->riskAddress->city)->name ?: null,
                    optional($cov->riskAddress->state)->name ?: null,
                ])));
            }
        @endphp

        <table class="mb">
            <tr class="cover-head">
                <td colspan="3">{{ $coverName }}</td>
            </tr>
            @if($covRiskLine !== '')
            <tr>
                <td colspan="3"><span class="accent">Risk Address:</span> {{ $covRiskLine }}</td>
            </tr>
            @endif

            {{-- Sub-covers --}}
            <tr class="subhead">
                <td style="width:56%;">Section / Sub-cover</td>
                <td style="width:22%;" class="right">Sum Insured</td>
                <td style="width:22%;" class="right">Premium</td>
            </tr>
            @forelse($subCovers as $detail)
                @php $coverPremium += (float) str_replace(',', '', (string) ($detail->calculated_value ?? 0)); @endphp
                <tr>
                    <td>{{ optional($detail->coverage)->s_ScreenName ?? optional($detail->coverage)->s_CoverageName ?? 'Sub-cover' }}</td>
                    <td class="right">{{ $money($detail->coverage_value) }}</td>
                    <td class="right">{{ $money($detail->calculated_value) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No sections captured.</td></tr>
            @endforelse

            {{-- Extensions --}}
            @if($exts->count() > 0)
                <tr class="subhead"><td colspan="3">Extensions</td></tr>
                @foreach($exts as $ext)
                    @php $extName = optional($ext->extention)->s_CoverageName; @endphp
                    @if(!empty($extName))
                        @php $coverPremium += (float) str_replace(',', '', (string) ($ext->extention_calculated_value ?? 0)); @endphp
                        <tr>
                            <td>{{ $extName }}</td>
                            <td class="right">{{ $money($ext->extention_coverage_value) }}</td>
                            <td class="right">{{ $money($ext->extention_calculated_value) }}</td>
                        </tr>
                    @endif
                @endforeach
            @endif

            {{-- Specified / portable items --}}
            @if($items->count() > 0)
                <tr class="subhead"><td colspan="3">Specified Items</td></tr>
                @foreach($items as $item)
                    @php $coverPremium += (float) str_replace(',', '', (string) ($item->calculated_value ?? 0)); @endphp
                    <tr>
                        <td>{{ optional($item->specifiedCoveragesItems)->specified_name ?? ($item->custom_name ?? 'Item') }}</td>
                        <td class="right">{{ $money($item->sum_insured) }}</td>
                        <td class="right">{{ $money($item->calculated_value) }}</td>
                    </tr>
                @endforeach
            @endif

            <tr class="total-row">
                <td colspan="2">Sub-total premium — {{ $coverName }}</td>
                <td class="right">{{ $money($coverPremium) }}</td>
            </tr>
        </table>
        @php $grandPremium += $coverPremium; @endphp
    @empty
        <table class="mb"><tr><td class="muted">No coverages captured for this policy.</td></tr></table>
    @endforelse

    @if($coverages->count() > 0)
    <table class="mb">
        <tr class="total-row">
            <td>Total Coverage Premium (sum of sections above)</td>
            <td class="right">{{ $money($grandPremium) }}</td>
        </tr>
    </table>
    @endif

    {{-- ========================= DIRECTORS ========================= --}}
    <table class="mt mb">
        <tr class="subhead"><td colspan="5">Directors / Shareholders</td></tr>
        <tr class="subhead" style="font-weight:normal;">
            <td style="width:26%;">Name</td>
            <td style="width:14%;">ID Type</td>
            <td style="width:22%;">ID Number</td>
            <td style="width:16%;">Nationality</td>
            <td style="width:22%;">Address</td>
        </tr>
        @forelse($directors as $director)
            <tr>
                <td>{{ trim(($director->first_name ?? '') . ' ' . ($director->last_name ?? '')) ?: 'N/A' }}</td>
                <td>{{ $director->id_type ?? 'N/A' }}</td>
                <td>{{ $director->id_number ?? 'N/A' }}</td>
                <td>{{ $director->nationality ?? 'N/A' }}</td>
                <td>{{ $director->address ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No directors captured (sole proprietor).</td></tr>
        @endforelse
    </table>

    <div class="mt muted" style="font-size:9px;">
        This schedule is issued subject to the policy wording, terms, conditions and exclusions.
        All amounts are in Botswana Pula (P).
    </div>

</body>
</html>
