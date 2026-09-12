@php
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $medevPolicyId = data_get($policy ?? null, 'id');
    $medevPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($medevPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $medevPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $medicalEvacuationCoverages = !empty($medevPcIds)
        ? \AlphaDirect\Models\MedicalEvacuationCoverage::whereIn('policy_coverage_id', $medevPcIds)->whereNull('deleted_at')->get()
        : collect();

    if (!function_exists('medev_decode_items')) {
        function medev_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('medev_fmt_amount')) {
        function medev_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Medical Evacuation Insurance</td>
    </tr>
</table>

@if($medicalEvacuationCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Medical Evacuation data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($medicalEvacuationCoverages as $mev)
        @php
            $descriptionItems = medev_decode_items($mev->description_items);
            $extensionItems   = medev_decode_items($mev->extension_items);
            $notesText        = $mev->notes ?? '';
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $mev->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Insured Name</td>
                <td style="width: 25%;">{{ $mev->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Address</td>
                <td colspan="3">{{ $mev->company_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Period of Cover (From)</td>
                <td>{{ $mev->inception_date ? \Carbon\Carbon::parse($mev->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Period of Cover (To)</td>
                <td>{{ $mev->expiry_date ? \Carbon\Carbon::parse($mev->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Class of Business</td>
                <td>Medical Evacuation</td>
                <td style="font-weight: bold;">Type of Cover</td>
                <td>{{ $mev->type_of_cover ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Original Insured / Scheme</td>
                <td>{{ $mev->original_insured_scheme ?? '' }}</td>
                <td style="font-weight: bold;">Territorial Limit</td>
                <td>{{ $mev->territorial_limit ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Sum Insured / Limit per Event</td>
                <td>{{ medev_fmt_amount($mev->sum_insured ?? '') }}</td>
                <td style="font-weight: bold;">Aggregate Limit</td>
                <td>{{ medev_fmt_amount($mev->aggregate_limit ?? '') }}</td>
            </tr>
            {{-- Currency, Gross Written Premium (BWP) and Renewable Policy were
                 removed from this schedule. The premium itself is still shown
                 per line in Description of Cover below, and is still written to
                 medical_evacuation_coverages.premium (derived from those rows)
                 so the Rate banner and quote totals are unaffected. --}}
            <tr>
                <td style="font-weight: bold;">Broker</td>
                <td colspan="3">{{ $mev->broker ?? '' }}</td>
            </tr>
        </table>

        {{-- Description of Cover --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Description of Cover</strong></td>
            </tr>
            <tr>
                <td style="width: 50%; font-weight: bold;">Description</td>
                <td style="width: 25%; font-weight: bold;">Limit</td>
                <td style="width: 25%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($descriptionItems as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td>{{ medev_fmt_amount($item['limit'] ?? '') }}</td>
                    <td>{{ medev_fmt_amount($item['premium'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No description items recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Extensions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Extensions</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Extension</td>
                <td style="width: 40%; font-weight: bold;">Sub Limit</td>
            </tr>
            @forelse($extensionItems as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ medev_fmt_amount($item['sub_limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No extensions recorded.</td>
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
     Included on both the engineering quote sheet and the specialist-product
     quote sheet/policy document — same shared block as machinery_breakdown_pdf,
     medical_malpractice_pdf, etc. so it reconciles with the React
     CoverageMiscItemsSection editor. --}}
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
                <td>{{ medev_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ medev_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ medev_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
