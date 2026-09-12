@php
    // Recover rows where either policy_id OR policy_coverage_id links to this
    // policy. The original whereNotNull('policy_coverage_id') filter dropped any
    // record whose policy_id was wrong/empty (legacy save path / direct-
    // navigation to the schedule without a ?policy_coverage_id= URL param),
    // which in turn hid its Miscellaneous Items. Mirrors marine_cargo_open_pdf.
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $mcoPolicyId = data_get($policy ?? null, 'id');
    $mcoPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($mcoPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $mcoPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $marineCargoOnceOffCoverages = !empty($mcoPcIds)
        ? \AlphaDirect\Models\MarineCargoOnceOffCoverage::whereIn('policy_coverage_id', $mcoPcIds)->get()
        : collect();

    if (!function_exists('mco_decode_items')) {
        function mco_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('mco_fmt_amount')) {
        function mco_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }

    $clauseNames = [
        1 => 'Institute Cargo Clause (A)',
        2 => 'Institute Cargo Clause (B)',
        3 => 'Institute Cargo Clause (C)',
        4 => 'Malicious Damage Clause',
        5 => 'Institute Theft, Pilferage, Nondelivery Clause',
        6 => 'Institute Replacement Clause',
        7 => 'Replacement Clause (Second Hand Machinery)',
        8 => 'Label Clause',
        9 => 'Pair and Set Clause',
        10 => 'Institute War Clause (Cargo)',
        11 => 'Institute Strikes Clause (Cargo)',
        12 => 'Institute Cargo Clause (Air) (excluding sendings by post)',
        13 => 'Institute War Clauses (sendings by post)',
        14 => 'Institute War Clauses (Air Cargo) (excluding sendings by post)',
        15 => 'Institute Strikes Clause (Air Cargo)',
        16 => 'Institute War Cancellation Clause (Cargo)',
        17 => 'Institute Classification Clause',
        18 => 'Inland Transit (Rail or Road) A – All Risks',
        19 => 'Inland Transit (Rail or Road) B – Basic Cover',
        20 => 'Inland Transit (Rail or Road) C – Fire Risk',
        21 => 'Inland SRCC Clause',
        22 => 'Inland Transit (Inland Vessels) Clause',
        23 => 'Sailing Vessels Clause',
        24 => 'Important Notice',
        25 => 'Duty Clause',
        26 => 'Increased Value Insurance Clause',
        27 => 'Institute Radioactive Contamination Exclusion Clause',
        28 => 'Terrorism Exclusion Clause',
    ];
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Marine Cargo — Once-Off Policy</td>
    </tr>
</table>

@if($marineCargoOnceOffCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Marine Cargo Once-Off data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($marineCargoOnceOffCoverages as $mco)
        @php
            // Normalize clauses to {clauseNum: true, ...}. SpecialistCoveragePage
            // saves a flat array of selected ID strings (["1","2","5"]); legacy
            // Livewire form saves an associative map ({1: true, 2: true}). The
            // isTicked check below assumes the associative form, so coerce both
            // to that shape here to keep the rendering loop untouched.
            $clausesRaw = mco_decode_items($mco->clauses);
            $clauses = [];
            foreach ($clausesRaw as $k => $v) {
                if (in_array($v, [true, 1, '1', 'on', 'true'], true)) {
                    $clauses[(int) $k] = true;
                } elseif (is_numeric($v)) {
                    $clauses[(int) $v] = true;
                }
            }
            $perConveyanceLimits = mco_decode_items($mco->per_conveyance_limits);

            // Specified items via PolicySpecifiedItem (Miscellaneous Items section).
            // Fall back to the in-scope PolicyCoverage id when the product row's
            // policy_coverage_id is NULL (legacy/direct-nav save path) so the items
            // still resolve. policy_specified_items.policy_coverage_id references
            // policy_coverages.id == $coverages->id. Mirrors travel_insurance_pdf.
            $policyCoverageKey = $mco->policy_coverage_id ?: ($coverages->id ?? null);
            $specifiedItems = collect();
            if (!empty($policyCoverageKey)) {
                $specifiedItems = \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
                    ->where('policy_coverage_id', $policyCoverageKey)
                    ->whereNull('deleted_at')
                    ->get();
            }
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>MARINE CARGO — Once Off Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Name of Assured</td>
                <td style="width: 25%;">{{ $mco->assured_name ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Open Policy No.</td>
                <td style="width: 25%;">{{ $mco->open_policy_no ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Address of Assured</td>
                <td colspan="3">{{ $mco->assured_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Agent / Broker Code No.</td>
                <td>{{ $mco->agent_broker_code_no ?? $mco->agent_broker_code ?? '' }}</td>
                <td style="font-weight: bold;">Conveyance</td>
                <td>{{ $mco->conveyance ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Policy Period From</td>
                <td>{{ $mco->policy_period_from ? \Carbon\Carbon::parse($mco->policy_period_from)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Policy Period To</td>
                <td>{{ $mco->policy_period_to ? \Carbon\Carbon::parse($mco->policy_period_to)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Voyage From</td>
                <td>{{ $mco->voyage_from ? \Carbon\Carbon::parse($mco->voyage_from)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Voyage To</td>
                <td>{{ $mco->voyage_to ? \Carbon\Carbon::parse($mco->voyage_to)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Commodities Covered</td>
                <td colspan="3">{{ $mco->commodities_covered ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Nature of Packing</td>
                <td>{{ $mco->nature_of_packing ?? '' }}</td>
                <td style="font-weight: bold;">Terms of Cover</td>
                <td>{{ $mco->terms_of_cover ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Location Limit</td>
                <td>{{ mco_fmt_amount($mco->location_limit ?? '') }}</td>
                <td style="font-weight: bold;">Premium Rate (%)</td>
                <td>{{ $mco->premium_rate ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Sum Insured</td>
                <td>{{ mco_fmt_amount($mco->sum_insured ?? '') }}</td>
                <td style="font-weight: bold;">Premium</td>
                <td>{{ mco_fmt_amount($mco->premium ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Today's Date</td>
                <td>{{ $mco->today_date ? \Carbon\Carbon::parse($mco->today_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Currency</td>
                <td>{{ $mco->currency ?? '' }}</td>
            </tr>
        </table>

        {{-- Clauses (only those ticked) --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Clauses (applicable)</strong></td>
            </tr>
            @php
                $tickedAny = false;
            @endphp
            @foreach($clauseNames as $num => $name)
                @php
                    $isTicked = !empty($clauses[$num]) && ($clauses[$num] === true || $clauses[$num] === 1 || $clauses[$num] === '1' || $clauses[$num] === 'on');
                @endphp
                @if($isTicked)
                    @php $tickedAny = true; @endphp
                    <tr>
                        <td style="width: 8%; text-align: center;">{{ $num }}</td>
                        <td>{{ $name }}</td>
                    </tr>
                @endif
            @endforeach
            @if(!$tickedAny)
                <tr><td colspan="2" class="text-center">No clauses ticked.</td></tr>
            @endif
        </table>

        {{-- Survey and Claim Settlement --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Survey and Claim Settlement</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Survey and Claim Settlement</td>
                <td style="white-space: pre-wrap;">{{ $mco->survey_claim_settlement ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Claim Payable at</td>
                <td>{{ $mco->claim_payable_at ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Claim Payable by</td>
                <td>{{ $mco->claim_payable_by ?? '' }}</td>
            </tr>
        </table>

        {{-- In Witness Whereof --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>In Witness Whereof</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Place</td>
                <td>{{ $mco->place ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Date</td>
                <td>{{ $mco->signing_date ? \Carbon\Carbon::parse($mco->signing_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Examined By</td>
                <td>{{ $mco->examined_by ?? '' }}</td>
            </tr>
        </table>

        {{-- Memorandum: Basis of Valuation + Per Conveyance Limits --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Memorandum Attaching to and Forming Part of Policy</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Basis of Valuation / Inspection of Records</td>
                <td style="white-space: pre-wrap;">{{ $mco->basis_of_valuation ?? '' }}</td>
            </tr>
        </table>

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Per Conveyance Limit</strong></td>
            </tr>
            <tr>
                <td style="width: 60%; font-weight: bold;">a. Per any one rail transit</td>
                <td>{{ mco_fmt_amount($mco->per_conveyance_rail ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">b. Per any one road vehicle</td>
                <td>{{ mco_fmt_amount($mco->per_conveyance_road ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">c. Per any one air transit and connecting conveyance</td>
                <td>{{ mco_fmt_amount($mco->per_conveyance_air ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">d. Per any one registered post or courier</td>
                <td>{{ mco_fmt_amount($mco->per_conveyance_post ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">e. Per any one vessel and connecting conveyance</td>
                <td>{{ mco_fmt_amount($mco->per_conveyance_vessel ?? '') }}</td>
            </tr>
        </table>

        {{-- Deductible / Notice of Cancellation / Refund --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2"><strong>Deductible / Cancellation / Refund</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Deductible</td>
                <td style="white-space: pre-wrap;">{{ $mco->deductible ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Notice of Cancellation</td>
                <td style="white-space: pre-wrap;">{{ $mco->notice_of_cancellation ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Refund</td>
                <td style="white-space: pre-wrap;">{{ $mco->refund ?? '' }}</td>
            </tr>
        </table>

        @if(!empty(trim((string) ($mco->notes ?? ''))))
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td><strong>Additional Notes</strong></td>
                </tr>
                <tr>
                    <td style="white-space: pre-wrap;">{{ $mco->notes }}</td>
                </tr>
            </table>
        @endif

        @if($specifiedItems->isNotEmpty())
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
                @foreach($specifiedItems as $specifiedItem)
                    @php $miscSubtotal += (float) str_replace(',', '', (string) ($specifiedItem->calculated_value ?? 0)); @endphp
                    <tr>
                        <td>{{ $specifiedItem->specifiedCoveragesItems->specified_name ?? '' }}</td>
                        <td>{{ mco_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                        <td>{{ $specifiedItem->rate ?? '' }}</td>
                        <td>{{ mco_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
                    <td style="font-weight: bold;">{{ mco_fmt_amount($miscSubtotal) }}</td>
                </tr>
            </table>
        @endif
    @endforeach
@endif
