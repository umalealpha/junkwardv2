@php
    // Recover rows where either policy_id OR policy_coverage_id links to this
    // policy. The original whereNotNull('policy_coverage_id') filter dropped any
    // row saved with policy_coverage_id NULL (legacy save path / direct-navigation
    // to the schedule without a ?policy_coverage_id= URL param). Mirrors
    // marine_cargo_open_pdf.
    $mdoPolicyId = data_get($policy ?? null, 'id');
    $mdoPcIds = $mdoPolicyId
        ? \DB::table('policy_coverages')
            ->where('policy_id', $mdoPolicyId)
            ->whereNull('deleted_at')
            ->pluck('id')->all()
        : [];
    $marineDirectorsOfficersCoverages = \AlphaDirect\Models\MarineDirectorsOfficersCoverage::where(function ($q) use ($mdoPolicyId, $mdoPcIds) {
            $q->where('policy_id', $mdoPolicyId);
            if (!empty($mdoPcIds)) {
                $q->orWhereIn('policy_coverage_id', $mdoPcIds);
            }
        })
        ->get();

    if (!function_exists('mdo_decode_items')) {
        function mdo_decode_items($value) {
            if (is_array($value)) return $value;
            if ($value === null || $value === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
    }
    if (!function_exists('mdo_fmt_amount')) {
        function mdo_fmt_amount($value) {
            if ($value === null || $value === '') return '';
            $clean = is_numeric($value) ? (float) $value : (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));
            return $clean !== 0.0 ? number_format($clean, 2, '.', ',') : ($value === 0 || $value === '0' ? '0.00' : '');
        }
    }
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="5">Directors & Officers</td>
    </tr>
</table>

@if($marineDirectorsOfficersCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Directors & Officers data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($marineDirectorsOfficersCoverages as $mdo)
        @php
            // The D&O capture form persists exactly three repeatable groups —
            // Insuring Clauses, Extensions and Coverage Extensions — plus the
            // Policy Schedule / Previous Insurance scalar fields and Notes. Only
            // those are rendered here; the table's unused Marine-Cargo section
            // columns (section1_items, insured_persons_listing, misc_items, …)
            // are never written by the form, so rendering them produced empty
            // "No … recorded" tables and a premium computed from nothing.
            $insuringClauses   = mdo_decode_items($mdo->insuring_clauses);
            $extensions        = mdo_decode_items($mdo->extensions);
            $coverageExt       = mdo_decode_items($mdo->coverage_extensions);
            $notesText         = $mdo->notes ?? '';
        @endphp

        {{-- Policy Schedule --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Policy Schedule</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy No</td>
                <td style="width: 25%;">{{ $mdo->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Insured Name</td>
                <td style="width: 25%;">{{ $mdo->company_name ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Address</td>
                <td colspan="3">{{ $mdo->company_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Inception Date</td>
                <td>{{ $mdo->inception_date ? \Carbon\Carbon::parse($mdo->inception_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Expiry Date</td>
                <td>{{ $mdo->expiry_date ? \Carbon\Carbon::parse($mdo->expiry_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Today's Date</td>
                <td>{{ $mdo->today_date ? \Carbon\Carbon::parse($mdo->today_date)->format('d/m/Y') : '' }}</td>
                <td style="font-weight: bold;">Period of Insurance</td>
                <td>{{ $mdo->period_of_insurance ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">New / Altered</td>
                <td>{{ $mdo->new_altered ?? '' }}</td>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($mdo->is_renewable) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Project Specific Policy</td>
                <td>{{ yes_no($mdo->is_project_specific) }}</td>
                <td style="font-weight: bold;">Currency</td>
                <td>{{ $mdo->currency ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Limit of Liability</td>
                <td>{{ mdo_fmt_amount($mdo->limit_of_liability ?? '') }}</td>
                <td style="font-weight: bold;">Premium</td>
                <td>{{ mdo_fmt_amount($mdo->premium ?? '') }}</td>
            </tr>
        </table>

        {{-- Insuring Clauses --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="5"><strong>Insuring Clauses</strong></td>
            </tr>
            <tr>
                <td style="width: 8%; font-weight: bold;">Section</td>
                <td style="width: 42%; font-weight: bold;">Insuring Clause</td>
                <td style="width: 16%; font-weight: bold;">Included</td>
                <td style="width: 18%; font-weight: bold;">Limit of Liability</td>
                <td style="width: 16%; font-weight: bold;">Retention</td>
            </tr>
            @forelse($insuringClauses as $clause)
                @php $clause = is_array($clause) ? $clause : ['name' => $clause]; @endphp
                <tr>
                    <td>{{ $clause['section'] ?? '' }}</td>
                    <td>{{ $clause['name'] ?? '' }}</td>
                    <td>{{ $clause['included'] ?? '' }}</td>
                    <td>{{ $clause['limit_of_liability'] ?? '' }}</td>
                    <td>{{ $clause['retention'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">No insuring clauses recorded.</td></tr>
            @endforelse
        </table>

        {{-- Extensions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="5"><strong>Extensions</strong></td>
            </tr>
            <tr>
                <td style="width: 8%; font-weight: bold;">Section</td>
                <td style="width: 42%; font-weight: bold;">Extension</td>
                <td style="width: 16%; font-weight: bold;">Included</td>
                <td style="width: 18%; font-weight: bold;">Additional Limit / Sub Limit</td>
                <td style="width: 16%; font-weight: bold;">Retention</td>
            </tr>
            @forelse($extensions as $ext)
                @php $ext = is_array($ext) ? $ext : ['name' => $ext]; @endphp
                <tr>
                    <td>{{ $ext['section'] ?? '' }}</td>
                    <td>{{ $ext['name'] ?? $ext['section_name'] ?? '' }}</td>
                    <td>{{ $ext['included'] ?? '' }}</td>
                    <td>{{ $ext['limit'] ?? $ext['limit_of_indemnity'] ?? '' }}</td>
                    <td>{{ $ext['retention'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">No extensions recorded.</td></tr>
            @endforelse
        </table>

        {{-- Coverage Extensions --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3"><strong>Coverage Extensions</strong></td>
            </tr>
            <tr>
                <td style="width: 8%; font-weight: bold;">Section</td>
                <td style="width: 72%; font-weight: bold;">Extension</td>
                <td style="width: 20%; font-weight: bold;">Included</td>
            </tr>
            @forelse($coverageExt as $ext)
                @php $ext = is_array($ext) ? $ext : ['name' => $ext]; @endphp
                <tr>
                    <td>{{ $ext['section'] ?? '' }}</td>
                    <td>{{ $ext['name'] ?? $ext['section_name'] ?? '' }}</td>
                    <td>{{ $ext['included'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center">No coverage extensions recorded.</td></tr>
            @endforelse
        </table>

        {{-- Previous Insurance --}}
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Previous Insurance</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Previous Insurer</td>
                <td style="width: 25%;">{{ $mdo->previous_insurer_name ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Previous Policy Type</td>
                <td style="width: 25%;">{{ $mdo->previous_policy_type ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Previous Policyholder</td>
                <td>{{ $mdo->previous_policyholder ?? '' }}</td>
                <td style="font-weight: bold;">Previous Policy Number</td>
                <td>{{ $mdo->previous_policy_number ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Previous Policy Period</td>
                <td>{{ $mdo->previous_policy_period ?? '' }}</td>
                <td style="font-weight: bold;">Backdated Continuity Date</td>
                <td>{{ $mdo->backdated_continuity_date ? \Carbon\Carbon::parse($mdo->backdated_continuity_date)->format('d/m/Y') : '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Jurisdictional Cover</td>
                <td colspan="3">{{ $mdo->jurisdictional_cover ?? '' }}</td>
            </tr>
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
     Rendered from policy_specified_items so they appear on the quote sheet and
     policy document, and their subtotal reconciles with the misc premium folded
     into the coverage total in specialist-product.blade.php. --}}
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
                <td>{{ $specifiedItem->specifiedCoveragesItems->specified_name ?? '' }}</td>
                <td>{{ mdo_fmt_amount($specifiedItem->sum_insured ?? '') }}</td>
                <td>{{ $specifiedItem->rate ?? '' }}</td>
                <td>{{ mdo_fmt_amount($specifiedItem->calculated_value ?? '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
            <td style="font-weight: bold;">{{ mdo_fmt_amount($miscSubtotal) }}</td>
        </tr>
    </table>
@endif
