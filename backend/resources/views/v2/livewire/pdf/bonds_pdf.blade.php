@php
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $bondsPolicyId = data_get($policy ?? null, 'id');
    $bondsPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($bondsPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $bondsPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $bondsCoverages = !empty($bondsPcIds)
        ? \AlphaDirect\Models\BondsCoverage::whereIn('policy_coverage_id', $bondsPcIds)->whereNull('deleted_at')->get()
        : collect();

    if (!function_exists('bonds_decode_items')) {
        function bonds_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('bonds_fmt_amount')) {
        function bonds_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Bonds Insurance — Comprehensive Policy Schedule</td>
    </tr>
</table>

@if($bondsCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Bonds and Guarantees data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($bondsCoverages as $bond)
        @php
            $bondSchedule = bonds_decode_items($bond->bond_schedule);
            $bondsNotesText = $bond->notes ?? '';
        @endphp

        {{-- Section 1 — Policy Details --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Section 1 — Policy Details</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $bond->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Insured / Principal</td>
                <td style="width: 25%;">{{ $bond->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Postal Address</td>
                <td>{{ $bond->company_address ?? '' }}</td>
                <td style="font-weight: bold;">Risk Address</td>
                <td>{{ $bond->risk_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Effective Date</td>
                <td>{{ $bond->inception_date ? \Carbon\Carbon::parse($bond->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Expiry Date</td>
                <td>{{ $bond->expiry_date ? \Carbon\Carbon::parse($bond->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Type of Bond</td>
                <td>{{ $bond->type_of_bond ?? '' }}</td>
                <td style="font-weight: bold;">Limit Insured (BWP)</td>
                <td>{{ bonds_fmt_amount($bond->limit_insured ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Premium (BWP)</td>
                <td>{{ bonds_fmt_amount($bond->premium ?? 0) }}</td>
                <td style="font-weight: bold;">Excess (BWP)</td>
                <td>{{ bonds_fmt_amount($bond->excess ?? '') }}</td>
            </tr>
            {{-- Currency, Broker and Renewable Policy removed from this
                 schedule. Premium (BWP) above is unaffected. --}}
        </table>

        {{-- Section 2 — Bond Coverage Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="7"><strong>Section 2 — Bond Coverage Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 22%; font-weight: bold;">Bond Type</td>
                <td style="width: 15%; font-weight: bold;">Sum Insured (BWP)</td>
                <td style="width: 9%; font-weight: bold;">Rate (%)</td>
                <td style="width: 14%; font-weight: bold;">Annual Premium (BWP)</td>
                <td style="width: 14%; font-weight: bold;">Principal / Contractor</td>
                <td style="width: 14%; font-weight: bold;">Duration</td>
                <td style="width: 12%; font-weight: bold;">Status</td>
            </tr>
            @php
                // Section banners mirror the operator-facing schedule: the
                // first 6 seeded rows are Contract Bonds, the next 4 are
                // Commercial Bonds; anything beyond row 10 (added via Add
                // More) is a free-form "Other" line with no banner.
                $bondGroupBreaks = [0 => 'CONTRACT BONDS', 6 => 'COMMERCIAL BONDS'];
            @endphp
            @forelse($bondSchedule as $bi => $item)
                @if(isset($bondGroupBreaks[$bi]))
                    <tr><td colspan="7" style="background-color:#4472C4; color:#fff; font-weight:bold;">{{ $bondGroupBreaks[$bi] }}</td></tr>
                @endif
                <tr>
                    <td>{{ $item['bond_type'] ?? '' }}</td>
                    <td>{{ bonds_fmt_amount($item['sum_insured'] ?? '') }}</td>
                    <td>{{ $item['rate'] ?? '' }}</td>
                    <td>{{ bonds_fmt_amount($item['annual_premium'] ?? '') }}</td>
                    <td>{{ $item['principal_contractor'] ?? '' }}</td>
                    <td>{{ $item['duration'] ?? '' }}</td>
                    <td>{{ $item['status'] ?? 'Active' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No bond schedule items recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section 3 — Key Policy Conditions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Section 3 — Key Policy Conditions</strong></td>
            </tr>
            @if(trim((string) $bond->insuring_agreement) !== '')
                <tr><td style="width: 22%; font-weight: bold;">Insuring Agreement</td><td style="white-space: pre-wrap;">{{ $bond->insuring_agreement }}</td></tr>
            @endif
            @if(trim((string) $bond->trigger_event) !== '')
                <tr><td style="font-weight: bold;">Trigger Event</td><td style="white-space: pre-wrap;">{{ $bond->trigger_event }}</td></tr>
            @endif
            @if(trim((string) $bond->subrogation_right) !== '')
                <tr><td style="font-weight: bold;">Subrogation Right</td><td style="white-space: pre-wrap;">{{ $bond->subrogation_right }}</td></tr>
            @endif
            @if(trim((string) $bond->non_cancellable_clause) !== '')
                <tr><td style="font-weight: bold;">Non-Cancellable</td><td style="white-space: pre-wrap;">{{ $bond->non_cancellable_clause }}</td></tr>
            @endif
            @if(trim((string) $bond->collateral_security) !== '')
                <tr><td style="font-weight: bold;">Collateral &amp; Security</td><td style="white-space: pre-wrap;">{{ $bond->collateral_security }}</td></tr>
            @endif
            @if(trim((string) $bond->exclusions) !== '')
                <tr><td style="font-weight: bold;">Exclusions</td><td style="white-space: pre-wrap;">{{ $bond->exclusions }}</td></tr>
            @endif
            @if(trim((string) $bond->dispute_resolution) !== '')
                <tr><td style="font-weight: bold;">Dispute Resolution</td><td style="white-space: pre-wrap;">{{ $bond->dispute_resolution }}</td></tr>
            @endif
        </table>

        {{-- Section 4 — Extensions/Endorsements --}}
        @php
            $bondExtensions = bonds_decode_items($bond->extensions_endorsements ?? null);
        @endphp
        @if(collect($bondExtensions)->filter(fn($e) => trim((string) ($e['description'] ?? '')) !== '')->isNotEmpty())
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Section 4 — Extensions/Endorsements</strong></td>
                </tr>
                @foreach($bondExtensions as $ext)
                    @if(trim((string) ($ext['description'] ?? '')) !== '')
                        <tr><td style="white-space: pre-wrap;">{{ $ext['description'] }}</td></tr>
                    @endif
                @endforeach
            </table>
        @endif

        @if(trim((string) $bondsNotesText) !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Notes</strong></td>
                </tr>
                <tr>
                    <td style="white-space: pre-wrap;">{{ $bondsNotesText }}</td>
                </tr>
            </table>
        @endif
    @endforeach
@endif

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Same shared block as machinery_breakdown_pdf, medical_evacuation_pdf,
     commercial_crime_pdf, environmental_liability_pdf, etc. so it reconciles
     with the React CoverageMiscItemsSection editor. --}}
@php
    $miscPolicyCoverageKey = $coverages->id ?? null;
    $miscSpecifiedItems = !empty($miscPolicyCoverageKey)
        ? \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
            ->where('policy_coverage_id', $miscPolicyCoverageKey)
            ->whereNull('deleted_at')
            ->get()
        : collect();
@endphp
@if($miscSpecifiedItems->isNotEmpty())
    @php $miscSubtotal = 0; @endphp
    <table style="margin-top: 6px;">
        <tr class="section-subtitle">
            <td colspan="4"><strong>Miscellaneous Items</strong></td>
        </tr>
        <tr>
            <td style="width: 46%; font-weight: bold;">Description Of Items</td>
            <td style="width: 22%; font-weight: bold;">Sum Insured</td>
            <td style="width: 12%; font-weight: bold;">Rate %</td>
            <td style="width: 20%; font-weight: bold;">Premium</td>
        </tr>
        @foreach($miscSpecifiedItems as $specifiedItem)
            @php $miscSubtotal += (float) str_replace(',', '', (string) ($specifiedItem->calculated_value ?? 0)); @endphp
            <tr>
                <td>{{ optional($specifiedItem->specifiedCoveragesItems)->specified_name ?: ($specifiedItem->custom_name ?? '') }}</td>
                <td>{{ bonds_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ bonds_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ bonds_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
