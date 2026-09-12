@php
    // Recover rows where either policy_id OR policy_coverage_id links to this
    // policy. The original whereNotNull('policy_coverage_id') filter dropped any
    // record whose policy_id was wrong/empty (legacy save path / direct-
    // navigation to the schedule without a ?policy_coverage_id= URL param),
    // which in turn hid its Miscellaneous Items. Mirrors marine_cargo_open_pdf.
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $travelPolicyId = data_get($policy ?? null, 'id');
    $travelPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($travelPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $travelPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $travelCoverages = !empty($travelPcIds)
        ? \AlphaDirect\Models\TravelCoverage::whereIn('policy_coverage_id', $travelPcIds)->get()
        : collect();

    $formatDate = function ($value) {
        if (empty($value)) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $e) {
            return $value;
        }
    };

    $formatMoney = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 2);
        }
        $sanitized = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $sanitized !== '' && is_numeric($sanitized)
            ? number_format((float) $sanitized, 2)
            : $value;
    };

    $parseArray = function ($value) {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?? [];
        }
        return [];
    };
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Risk Address: {{ $addressName }}</td>
    </tr>
    <tr class="section-title text-center">
        <td colspan="4">{{ $coverages->coverage->s_ScreenName ?? $coverages->coverage->s_CoverageCode }}</td>
    </tr>
</table>

@if($travelCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Travel Insurance data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($travelCoverages as $tc)
        @php
            $benefits = $parseArray($tc->benefits ?? null);
            $customBenefits = $parseArray($tc->custom_benefits ?? null);
            $wordingUrl = !empty($tc->policy_wording_path)
                ? (config('app.S3_BASE_URL') . $tc->policy_wording_path)
                : '';

            $benefitsFiltered = [];
            foreach ($benefits as $key => $benefit) {
                if (is_array($benefit) && (($benefit['_removed'] ?? false) === true)) {
                    continue;
                }
                $benefitsFiltered[$key] = $benefit;
            }
            // policy_coverage_id on the travel_coverages row can be NULL for rows
            // recovered via policy_id (legacy / direct-nav save path — see the
            // header comment). Fall back to the in-scope PolicyCoverage id so the
            // Miscellaneous Items still resolve: policy_specified_items.policy_coverage_id
            // references policy_coverages.id, which is exactly $coverages->id.
            // $coverages is always in scope — this partial is @included inside the
            // $policy_coverages loop in both specialist-product (quote sheet) and
            // engineering-policy-document (policy document).
            $policyCoverageKey = $tc->policy_coverage_id ?: ($coverages->id ?? null);
            $specifiedItems = collect();
            if (!empty($policyCoverageKey)) {
                $specifiedItems = \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
                    ->where('policy_coverage_id', $policyCoverageKey)
                    ->whereNull('deleted_at')
                    ->get();
            }
            // additional_notes column was removed (migration 2026_01_21_111900);
            // notes lives on the policy/coverage form now and isn't surfaced here.
            $isFlatArray = array_is_list($benefitsFiltered);
        @endphp

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Travel Insurance Coverage</strong></td>
            </tr>
            <tr>
                <td style="width: 25%;font-weight: bold;">Policyholder</td>
                <td style="width: 25%;">{{ $tc->policyholder ?? '' }}</td>
                <td style="width: 25%;font-weight: bold;">Passport</td>
                <td style="width: 25%;">{{ $tc->passport ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Phone Number</td>
                <td>{{ $tc->phone_num ?? '' }}</td>
                <td style="font-weight: bold;">Policy Number</td>
                <td>{{ $tc->policy_number ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Number of Passengers</td>
                <td>{{ $tc->number_passengers ?? '' }}</td>
                <td style="font-weight: bold;">Policy Period (Month's)</td>
                <td>{{ $tc->policy_period_months ?? '' }} Month's</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Effective From</td>
                <td>{{ $formatDate($tc->effective_from ?? null) }}</td>
                <td style="font-weight: bold;">Expiry</td>
                <td>{{ $formatDate($tc->expiry ?? null) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($tc->is_renewable) }}</td>
                <td style="font-weight: bold;">Policy Amount</td>
                <td>{{ $formatMoney($tc->policy_amount ?? null) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">VAT</td>
                <td>{{ $formatMoney($tc->vat ?? null) }}</td>
                <td style="font-weight: bold;">Total</td>
                <td>{{ $formatMoney($tc->total ?? null) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Destination Area</td>
                <td colspan="3">{{ $tc->destination_area ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Country of Origin</td>
                <td>{{ $tc->country_of_origin ?? '' }}</td>
                <td style="font-weight: bold;">Product</td>
                <td>{{ $tc->product ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Code</td>
                <td>{{ $tc->code ?? '' }}</td>
                <td style="font-weight: bold;">Insurance Company</td>
                <td>{{ $tc->insurance_company ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Location</td>
                <td colspan="3">{{ $tc->company_location ?? '' }}</td>
            </tr>
            
            <!-- @if(!empty($wordingUrl))
                <tr>
                    <td>Policy Wording</td>
                    <td colspan="3">{{ $wordingUrl }}</td>
                </tr>
            @endif -->
        </table>

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Benefits & Coverage</strong></td>
            </tr>
            <tr>
                <td style="width: 55%; font-weight: bold;">Benefit Description</td>
                <td style="width: 25%; font-weight: bold;">Sum Insured</td>
                <td style="width: 20%; font-weight: bold;">Excess</td>
            </tr>
            @php
                // Legacy keyed-map format used these as heading-keys; the new
                // React flat-array format uses a `category` column on each row
                // and is detected via $isFlatArray above.
                $headingKeys = [
                    'personal_assistance',
                    'medical_transportation_repatriation',
                    'medical_expenses',
                    'repatriation_mortal_remains',
                    'baggage',
                    'cancellation',
                    'curtailment',
                    'personal_accident',
                    'personal_liability',
                    'medical_complementary_services',
                    'cards',
                    'delays',
                ];
                $lastCategory = null;
            @endphp
            @forelse($benefitsFiltered as $key => $benefit)
                @php
                    $desc = is_array($benefit) ? ($benefit['description'] ?? $key) : $key;
                    $sumInsured = is_array($benefit) ? ($benefit['sum_insured'] ?? '0.00') : '0.00';
                    $excess = is_array($benefit) ? ($benefit['excess'] ?? '-') : '-';
                    $category = is_array($benefit) ? ($benefit['category'] ?? null) : null;
                    $emitFlatHeading = $isFlatArray && $category && $category !== $lastCategory;
                    if ($emitFlatHeading) { $lastCategory = $category; }
                @endphp
                @if($emitFlatHeading)
                    <tr>
                        <td class="fw-bold" colspan="3" style="text-align: center;text-transform: uppercase;font-weight: bold;">{{ $category }}</td>
                    </tr>
                @endif
                <tr>
                    @if(!$isFlatArray && in_array($key, $headingKeys))
                        <td class="fw-bold" colspan="3" style="text-align: center;text-transform: uppercase;font-weight: bold;">{{ $desc }}</td>
                    @else
                        <td>{{ $desc }}</td>
                        <td>{{ $formatMoney($sumInsured) }}</td>
                        <td>{{ $excess }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No benefits recorded.</td>
                </tr>
            @endforelse

            @if(!empty($customBenefits))
                @foreach($customBenefits as $customBenefit)
                    <tr>
                        <td>{{ $customBenefit['description'] ?? '' }}</td>
                        <td>{{ $formatMoney($customBenefit['sum_insured'] ?? '0.00') }}</td>
                        <td>{{ $customBenefit['excess'] ?? '' }}</td>
                    </tr>
                @endforeach
            @endif
        </table>

        @if($specifiedItems->isNotEmpty())
            @php $miscSubtotal = 0; @endphp
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td colspan="4">Miscellaneous Items</td>
                </tr>
                <tr>
                    <td style="width: 46%; font-weight: bold;">Description Of Items</td>
                    <td style="width: 22%; font-weight: bold;">Sum Insured</td>
                    <td style="width: 12%; font-weight: bold;">Rate %</td>
                    <td style="width: 20%; font-weight: bold;">Premium</td>
                </tr>
                @foreach($specifiedItems as $specifiedItem)
                    @php $miscSubtotal += (float) str_replace(',', '', (string) ($specifiedItem->calculated_value ?? 0)); @endphp
                    <tr>
                        <td>{{ $specifiedItem->specifiedCoveragesItems->specified_name ?? '-' }}</td>
                        <td>{{ $formatMoney($specifiedItem->sum_insured ?? '0.00') }}</td>
                        <td>{{ $specifiedItem->rate ?? '' }}</td>
                        <td>{{ $formatMoney($specifiedItem->calculated_value ?? '0.00') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
                    <td style="font-weight: bold;">{{ $formatMoney($miscSubtotal) }}</td>
                </tr>
            </table>
        @endif

    @endforeach
@endif