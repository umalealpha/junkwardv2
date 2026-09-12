@php
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $mbkPolicyId = data_get($policy ?? null, 'id');
    $mbkPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($mbkPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $mbkPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $machineryBreakdownCoverages = !empty($mbkPcIds)
        ? \AlphaDirect\Models\MachineryBreakdownCoverage::whereIn('policy_coverage_id', $mbkPcIds)->get()
        : collect();

    if (!function_exists('mbk_decode_items')) {
        function mbk_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('mbk_fmt_amount')) {
        function mbk_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
    // Per-section premium subtotal, so each block on the schedule shows what it
    // contributes and the Policy Schedule "Premium" is visibly the sum of the
    // four. Same parsing the model uses for the stored total.
    if (!function_exists('mbk_sum_premium')) {
        function mbk_sum_premium(array $items) {
            $sum = 0.0;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $sum += (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) ($item['premium'] ?? 0)));
            }
            return $sum;
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Machinery Breakdown</td>
    </tr>
</table>

@if($machineryBreakdownCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Machinery Breakdown data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($machineryBreakdownCoverages as $mb)
        @php
            $section1Items   = mbk_decode_items($mb->section1_items);
            $section2Items   = mbk_decode_items($mb->section2_items);
            $section3Items   = mbk_decode_items($mb->section3_items);
            $machineryList   = mbk_decode_items($mb->machinery_listing);
            $extraSection1   = mbk_decode_items($mb->extra_cover_section1);
            $extraSection2   = mbk_decode_items($mb->extra_cover_section2);
            $extraSection3   = mbk_decode_items($mb->extra_cover_section3);
            $extraAll        = mbk_decode_items($mb->extra_cover_all_sections);
            $excessDetails   = mbk_decode_items($mb->excess_details);
            $endorsements    = $mb->endorsements ?? '';
            $notesText       = $mb->notes ?? '';

            $section1Premium = mbk_sum_premium($section1Items);
            $listingPremium  = mbk_sum_premium($machineryList);
            $section2Premium = mbk_sum_premium($section2Items);
            $section3Premium = mbk_sum_premium($section3Items);
            // Header premium mirrors the Rate button and the Index of Sections:
            // sum of the four priced blocks, falling back to the stored scalar
            // when none of them carry a premium.
            $mbTotalPremium  = \AlphaDirect\Models\MachineryBreakdownCoverage::resolvedPremium($mb);
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $mb->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Insured Name</td>
                <td style="width: 25%;">{{ $mb->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Address</td>
                <td colspan="3">{{ $mb->company_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Inception Date</td>
                <td>{{ $mb->inception_date ? \Carbon\Carbon::parse($mb->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Expiry Date</td>
                <td>{{ $mb->expiry_date ? \Carbon\Carbon::parse($mb->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Today's Date</td>
                <td>{{ $mb->today_date ? \Carbon\Carbon::parse($mb->today_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($mb->is_renewable) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Currency</td>
                <td>{{ $mb->currency ?? '' }}</td>
                <td style="font-weight: bold;">Premium</td>
                <td>{{ mbk_fmt_amount($mbTotalPremium) }}</td>
            </tr>
        </table>

        {{-- Premium build-up: the header Premium above is the sum of these four
             priced blocks, so the schedule reconciles with the Rate Sheet and
             the quote sheet's Index of Sections line for this cover. --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Premium Build-Up</strong></td>
            </tr>
            <tr>
                <td style="width: 70%;">SECTION 1 — Equipment Damage and Breakdown</td>
                <td style="width: 30%;">{{ mbk_fmt_amount($section1Premium) }}</td>
            </tr>
            <tr>
                <td>Machinery Listing</td>
                <td>{{ mbk_fmt_amount($listingPremium) }}</td>
            </tr>
            <tr>
                <td>SECTION 2 — Deterioration of Stock</td>
                <td>{{ mbk_fmt_amount($section2Premium) }}</td>
            </tr>
            <tr>
                <td>SECTION 3 — Loss of Income</td>
                <td>{{ mbk_fmt_amount($section3Premium) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold; text-align: right;">Total Premium</td>
                <td style="font-weight: bold;">{{ mbk_fmt_amount($mbTotalPremium) }}</td>
            </tr>
        </table>

        {{-- SECTION 1 — Equipment Damage and Breakdown --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>SECTION 1 — Equipment Damage and Breakdown (Specified Cover Basis)</strong></td>
            </tr>
            <tr>
                <td style="width: 50%; font-weight: bold;">Coverage Item / Description</td>
                <td style="width: 30%; font-weight: bold;">Limit / Status</td>
                <td style="width: 20%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($section1Items as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ $item['limit_status'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['premium'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No Section 1 items recorded.</td>
                </tr>
            @endforelse
            @if(!empty($section1Items))
                <tr>
                    <td colspan="2" style="font-weight: bold; text-align: right;">Section 1 Total Premium</td>
                    <td style="font-weight: bold;">{{ mbk_fmt_amount($section1Premium) }}</td>
                </tr>
            @endif
        </table>

        {{-- Machinery Listing --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="7"><strong>Machinery Listing</strong></td>
            </tr>
            <tr>
                <td style="width: 5%; font-weight: bold;">Item No</td>
                <td style="width: 8%; font-weight: bold;">Qty</td>
                <td style="width: 36%; font-weight: bold;">Description</td>
                <td style="width: 14%; font-weight: bold;">Year of Manufacture</td>
                <td style="width: 13%; font-weight: bold;">Deductible</td>
                <td style="width: 8%; font-weight: bold;">Rate %</td>
                <td style="width: 16%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($machineryList as $item)
                <tr>
                    <td>{{ $item['item_no'] ?? '' }}</td>
                    <td>{{ $item['quantity'] ?? '' }}</td>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td>{{ $item['year_of_manufacture'] ?? '' }}</td>
                    <td>{{ $item['deductible'] ?? '' }}</td>
                    <td>{{ $item['rate'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['premium'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No machinery items recorded.</td>
                </tr>
            @endforelse
            @if(!empty($machineryList))
                <tr>
                    <td colspan="6" style="font-weight: bold; text-align: right;">Machinery Listing Total Premium</td>
                    <td style="font-weight: bold;">{{ mbk_fmt_amount($listingPremium) }}</td>
                </tr>
            @endif
        </table>

        {{-- Extensions Section 1 --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Extensions — Section 1 (limits per occurrence, on top of limit of liability)</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Cover</td>
                <td style="width: 40%; font-weight: bold;">Limit</td>
            </tr>
            @forelse($extraSection1 as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- SECTION 2 — Deterioration of Stock --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>SECTION 2 — Deterioration of Stock</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Item</td>
                <td style="width: 15%; font-weight: bold;">Rate %</td>
                <td style="width: 25%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($section2Items as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ $item['rate'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['premium'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No Section 2 items recorded.</td>
                </tr>
            @endforelse
            @if(!empty($section2Items))
                <tr>
                    <td colspan="2" style="font-weight: bold; text-align: right;">Section 2 Total Premium</td>
                    <td style="font-weight: bold;">{{ mbk_fmt_amount($section2Premium) }}</td>
                </tr>
            @endif
        </table>

        {{-- Extensions Section 2 --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Extensions — Section 2</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Cover</td>
                <td style="width: 40%; font-weight: bold;">Limit</td>
            </tr>
            @forelse($extraSection2 as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- SECTION 3 — Loss of Income --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>SECTION 3 — Loss of Income</strong></td>
            </tr>
            <tr>
                <td style="width: 40%; font-weight: bold;">Item</td>
                <td style="width: 25%; font-weight: bold;">Gross Profit</td>
                <td style="width: 15%; font-weight: bold;">Rate %</td>
                <td style="width: 20%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($section3Items as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['value'] ?? '') }}</td>
                    <td>{{ $item['rate'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['premium'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No Section 3 items recorded.</td>
                </tr>
            @endforelse
            @if(!empty($section3Items))
                <tr>
                    <td colspan="3" style="font-weight: bold; text-align: right;">Section 3 Total Premium</td>
                    <td style="font-weight: bold;">{{ mbk_fmt_amount($section3Premium) }}</td>
                </tr>
            @endif
        </table>

        {{-- Extensions Section 3 --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Extensions — Section 3</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Cover</td>
                <td style="width: 40%; font-weight: bold;">Limit</td>
            </tr>
            @forelse($extraSection3 as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Extensions Applying to All Sections --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Extensions — Applying to All Sections</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">Cover</td>
                <td style="width: 40%; font-weight: bold;">Limit</td>
            </tr>
            @forelse($extraAll as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['limit'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        {{-- Excess Details --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Excess Details</strong></td>
            </tr>
            <tr>
                <td style="width: 50%; font-weight: bold;">Description</td>
                <td style="width: 30%; font-weight: bold;">Minimum Excess</td>
                <td style="width: 20%; font-weight: bold;">Rate %</td>
            </tr>
            @forelse($excessDetails as $item)
                <tr>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td>{{ mbk_fmt_amount($item['value'] ?? '') }}</td>
                    <td>{{ $item['rate'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No excess details recorded.</td>
                </tr>
            @endforelse
        </table>

        @if(trim((string) $endorsements) !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Endorsements</strong></td>
                </tr>
                <tr>
                    <td style="white-space: pre-wrap;">{{ $endorsements }}</td>
                </tr>
            </table>
        @endif

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
     quote sheet/policy document; subtotal reconciles with the misc premium
     already folded into the Machinery Breakdown coverage total. --}}
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
                <td>{{ mbk_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ mbk_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ mbk_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
