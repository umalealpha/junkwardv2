@php
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $ccPolicyId = data_get($policy ?? null, 'id');
    $ccPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($ccPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $ccPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $commercialCrimeCoverages = !empty($ccPcIds)
        ? \AlphaDirect\Models\CommercialCrimeCoverage::whereIn('policy_coverage_id', $ccPcIds)->whereNull('deleted_at')->get()
        : collect();

    if (!function_exists('cc_decode_items')) {
        function cc_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('cc_fmt_amount')) {
        function cc_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Commercial Crime Insurance</td>
    </tr>
</table>

@if($commercialCrimeCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Commercial Crime data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($commercialCrimeCoverages as $cc)
        @php
            $insuringClauses        = cc_decode_items($cc->insuring_clauses);
            $excessLayers           = cc_decode_items($cc->excess_layers);
            $endorsementsExtensions = cc_decode_items($cc->endorsements_extensions);
            $notesText              = $cc->notes ?? '';
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $cc->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Insured Name</td>
                <td style="width: 25%;">{{ $cc->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Address</td>
                <td colspan="3">{{ $cc->company_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Period of Cover (From)</td>
                <td>{{ $cc->inception_date ? \Carbon\Carbon::parse($cc->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Period of Cover (To)</td>
                <td>{{ $cc->expiry_date ? \Carbon\Carbon::parse($cc->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Class of Business</td>
                <td>Commercial Crime</td>
                <td style="font-weight: bold;">Cover Type</td>
                <td>{{ $cc->cover_type ?? '' }}</td>
            </tr>
            {{-- Insured / Insured Group removed from this schedule; the
                 policyholder is already shown as Insured Name above. --}}
            <tr>
                <td style="font-weight: bold;">Industry / Sector</td>
                <td colspan="3">{{ $cc->industry_sector ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Coverage Basis</td>
                <td>{{ $cc->coverage_basis ?? '' }}</td>
                <td style="font-weight: bold;">Retroactive Date</td>
                <td>{{ $cc->retroactive_date ? \Carbon\Carbon::parse($cc->retroactive_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Limit (BWP)</td>
                <td>{{ cc_fmt_amount($cc->total_limit ?? '') }}</td>
                <td style="font-weight: bold;">Annual Aggregate Limit (BWP)</td>
                <td>{{ cc_fmt_amount($cc->annual_aggregate_limit ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Excess (BWP)</td>
                <td>{{ cc_fmt_amount($cc->excess ?? '') }}</td>
                <td style="font-weight: bold;">Broker</td>
                <td>{{ $cc->broker ?? '' }}</td>
            </tr>
            {{-- Currency removed from this schedule. --}}
            <tr>
                <td style="font-weight: bold;">Gross Written Premium (BWP)</td>
                <td colspan="3">{{ cc_fmt_amount($cc->premium ?? 0) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($cc->is_renewable) }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>

        {{-- Section A — Insuring Clauses & Sub-Limits --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Section A — Insuring Clauses &amp; Sub-Limits</strong></td>
            </tr>
            <tr>
                <td style="width: 34%; font-weight: bold;">Insuring Clause</td>
                <td style="width: 22%; font-weight: bold;">Sub-Limit (BWP)</td>
                <td style="width: 14%; font-weight: bold;">Aggregate?</td>
                <td style="width: 30%; font-weight: bold;">Comments / Conditions</td>
            </tr>
            @forelse($insuringClauses as $item)
                <tr>
                    <td>{{ $item['clause'] ?? '' }}</td>
                    <td>{{ cc_fmt_amount($item['sub_limit'] ?? '') }}</td>
                    <td>{{ $item['aggregate'] ?? '' }}</td>
                    <td>{{ $item['comments'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No insuring clauses recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section B — Layered Excess of Loss Structure --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Section B — Layered Excess of Loss Structure</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Layer</td>
                <td style="width: 40%; font-weight: bold;">Layer Limit (BWP)</td>
            </tr>
            @forelse($excessLayers as $item)
                <tr>
                    <td>{{ $item['layer'] ?? '' }}</td>
                    <td>{{ cc_fmt_amount($item['layer_limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No excess layers recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section C — Endorsements and Extensions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Section C — Endorsements and Extensions</strong></td>
            </tr>
            <tr>
                <td style="width: 10%; font-weight: bold;">#</td>
                <td style="width: 60%; font-weight: bold;">Insuring Clause</td>
                <td style="width: 30%; font-weight: bold;">Sub-Limit (BWP)</td>
            </tr>
            @forelse($endorsementsExtensions as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item['insuring_clause'] ?? '' }}</td>
                    <td>{{ cc_fmt_amount($item['sub_limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No endorsements or extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        @if(trim((string) $notesText) !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Notes</strong></td>
                </tr>
                <tr>
                    <td style="white-space: pre-wrap;">{{ $notesText }}</td>
                </tr>
            </table>
        @endif
    @endforeach
@endif

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Same shared block as machinery_breakdown_pdf, medical_evacuation_pdf, etc.
     so it reconciles with the React CoverageMiscItemsSection editor. --}}
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
                <td>{{ cc_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ cc_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ cc_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
