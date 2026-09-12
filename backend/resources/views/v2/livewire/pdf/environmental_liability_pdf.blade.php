@php
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $elPolicyId = data_get($policy ?? null, 'id');
    $elPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($elPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $elPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $environmentalLiabilityCoverages = !empty($elPcIds)
        ? \AlphaDirect\Models\EnvironmentalLiabilityCoverage::whereIn('policy_coverage_id', $elPcIds)->whereNull('deleted_at')->get()
        : collect();

    if (!function_exists('el_decode_items')) {
        function el_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('el_fmt_amount')) {
        function el_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Environmental Liability Insurance</td>
    </tr>
</table>

@if($environmentalLiabilityCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Environmental Liability data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($environmentalLiabilityCoverages as $el)
        @php
            $elSites           = el_decode_items($el->sites);
            $elCoverageSections = el_decode_items($el->coverage_sections);
            $elDeductibles     = el_decode_items($el->deductibles);
            $elPollutants      = el_decode_items($el->pollutants_covered);
            $elExclusions      = el_decode_items($el->key_exclusions);
            $elEndorsements    = el_decode_items($el->endorsements);
            $elNotesText       = $el->notes ?? '';
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $el->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Named Insured</td>
                <td style="width: 25%;">{{ $el->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Address</td>
                <td colspan="3">{{ $el->company_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Nature of Business</td>
                <td>{{ $el->nature_of_business ?? '' }}</td>
                <td style="font-weight: bold;">Contact Person</td>
                <td>{{ $el->contact_person ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Telephone</td>
                <td>{{ $el->telephone ?? '' }}</td>
                <td style="font-weight: bold;">Email</td>
                <td>{{ $el->email ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Policy Inception Date</td>
                <td>{{ $el->inception_date ? \Carbon\Carbon::parse($el->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Policy Expiry Date</td>
                <td>{{ $el->expiry_date ? \Carbon\Carbon::parse($el->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Retroactive Date</td>
                <td>{{ $el->retroactive_date ? \Carbon\Carbon::parse($el->retroactive_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Basis of Cover</td>
                <td>{{ $el->basis_of_cover ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Policy Duration</td>
                <td>{{ $el->policy_duration ?? '' }}</td>
                <td style="font-weight: bold;">Renewable</td>
                <td>{{ yes_no($el->is_renewable) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Currency</td>
                <td>{{ $el->currency ?? '' }}</td>
                <td style="font-weight: bold;">Broker</td>
                <td>{{ $el->broker ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Overall Annual Aggregate Limit (BWP)</td>
                <td>{{ el_fmt_amount($el->overall_annual_aggregate_limit ?? '') }}</td>
                <td style="font-weight: bold;">Annual Premium (BWP)</td>
                <td>{{ el_fmt_amount($el->premium ?? 0) }}</td>
            </tr>
        </table>

        {{-- Section 3 — Insured Locations / Scheduled Sites --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Insured Locations / Scheduled Sites</strong></td>
            </tr>
            <tr>
                <td style="width: 10%; font-weight: bold;">Site No</td>
                <td style="width: 25%; font-weight: bold;">Site Name</td>
                <td style="width: 35%; font-weight: bold;">Physical Address</td>
                <td style="width: 30%; font-weight: bold;">Nature of Operations</td>
            </tr>
            @forelse($elSites as $i => $item)
                <tr>
                    <td>{{ $item['site_no'] ?? ($i + 1) }}</td>
                    <td>{{ $item['site_name'] ?? '' }}</td>
                    <td>{{ $item['physical_address'] ?? '' }}</td>
                    <td>{{ $item['nature_of_operations'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No scheduled sites recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section 4 — Coverage Sections & Limits of Indemnity --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Coverage Sections &amp; Limits of Indemnity</strong></td>
            </tr>
            <tr>
                <td style="width: 46%; font-weight: bold;">Coverage Section</td>
                <td style="width: 14%; font-weight: bold;">Included</td>
                <td style="width: 20%; font-weight: bold;">Limit of Indemnity (BWP)</td>
                <td style="width: 20%; font-weight: bold;">Sub-Limit (BWP)</td>
            </tr>
            @forelse($elCoverageSections as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td>{{ $item['included'] ?? '' }}</td>
                    <td>{{ el_fmt_amount($item['limit_of_indemnity'] ?? '') }}</td>
                    <td>{{ el_fmt_amount($item['sub_limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No coverage sections recorded.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3" style="font-weight: bold; text-align: right;">Overall Annual Aggregate Limit</td>
                <td style="font-weight: bold;">{{ el_fmt_amount($el->overall_annual_aggregate_limit ?? '') }}</td>
            </tr>
        </table>

        {{-- Section 5 — Deductible / Self-Insured Retention --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Deductible / Self-Insured Retention (SIR)</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Coverage Section</td>
                <td style="width: 40%; font-weight: bold;">Deductible (BWP)</td>
            </tr>
            @forelse($elDeductibles as $item)
                <tr>
                    <td>{{ $item['coverage_section'] ?? '' }}</td>
                    <td>{{ el_fmt_amount($item['deductible'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No deductibles recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section 6 — Premium --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Premium</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Annual Premium (excl. taxes/levies)</td>
                <td style="width: 25%;">{{ el_fmt_amount($el->premium ?? 0) }}</td>
                <td style="width: 25%; font-weight: bold;">Applicable Taxes / Levies</td>
                <td style="width: 25%;">{{ el_fmt_amount($el->tax_levies ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Premium Payable</td>
                <td>{{ el_fmt_amount($el->total_premium_payable ?? '') }}</td>
                <td style="font-weight: bold;">Payment Terms</td>
                <td>{{ $el->premium_payment_terms ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Due Date</td>
                <td>{{ $el->premium_due_date ? \Carbon\Carbon::parse($el->premium_due_date)->format('d/m/Y') : '' }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>

        {{-- Section 7 — Pollutants Covered --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Pollutants Covered</strong></td>
            </tr>
            @forelse($elPollutants as $item)
                <tr>
                    <td style="width: 4%;">{{ ($item['included'] ?? 'Yes') === 'No' ? '✗' : '✓' }}</td>
                    <td>{{ is_array($item) ? ($item['pollutant'] ?? '') : $item }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No pollutants recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section 8 — Key Exclusions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Key Exclusions (Summary)</strong></td>
            </tr>
            @forelse($elExclusions as $item)
                <tr>
                    <td style="width: 4%;">&#10007;</td>
                    <td>{{ is_array($item) ? ($item['exclusion'] ?? '') : $item }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No exclusions recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Section 10 — Endorsements & Special Conditions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Endorsements &amp; Special Conditions</strong></td>
            </tr>
            <tr>
                <td style="width: 10%; font-weight: bold;">No.</td>
                <td style="width: 40%; font-weight: bold;">Endorsement Title</td>
                <td style="width: 50%; font-weight: bold;">Effect / Detail</td>
            </tr>
            @forelse($elEndorsements as $i => $item)
                <tr>
                    <td>{{ $item['no'] ?? ($i + 1) }}</td>
                    <td>{{ $item['title'] ?? '' }}</td>
                    <td>{{ $item['effect'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No endorsements or special conditions recorded.</td>
                </tr>
            @endforelse
        </table>

        @if(trim((string) $elNotesText) !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Notes</strong></td>
                </tr>
                <tr>
                    <td style="white-space: pre-wrap;">{{ $elNotesText }}</td>
                </tr>
            </table>
        @endif
    @endforeach
@endif

{{-- Miscellaneous Items (shared specified-items, keyed by policy_coverage_id).
     Same shared block as machinery_breakdown_pdf, medical_evacuation_pdf,
     commercial_crime_pdf, etc. so it reconciles with the React
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
                <td>{{ el_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ el_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ el_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
