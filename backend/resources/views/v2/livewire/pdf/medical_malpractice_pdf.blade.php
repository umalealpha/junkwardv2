@php
    // Recover rows where either policy_id OR policy_coverage_id links to this
    // policy. The original whereNotNull('policy_coverage_id') filter dropped any
    // record whose policy_id was wrong/empty (legacy save path / direct-
    // navigation to the schedule without a ?policy_coverage_id= URL param),
    // which in turn hid its Miscellaneous Items. Mirrors marine_cargo_open_pdf.
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $mmPolicyId = data_get($policy ?? null, 'id');
    $mmPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($mmPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $mmPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $medicalMalpracticeCoverages = !empty($mmPcIds)
        ? \AlphaDirect\Models\MedicalMalpracticeCoverage::whereIn('policy_coverage_id', $mmPcIds)->get()
        : collect();
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Medical Malpractice</td>
    </tr>
</table>

@if($medicalMalpracticeCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Medical Malpractice data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($medicalMalpracticeCoverages as $mm)
        @php
            // Fall back to the in-scope PolicyCoverage id when the product row's
            // policy_coverage_id is NULL (legacy/direct-nav save path) so Miscellaneous
            // Items (and coverage notes) still resolve. policy_specified_items.policy_coverage_id
            // references policy_coverages.id == $coverages->id. Mirrors travel_insurance_pdf.
            $policyCoverageKey = $mm->policy_coverage_id ?: ($coverages->id ?? null);
            $specifiedItems = collect();

            if (!empty($policyCoverageKey)) {
                $specifiedItems = \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
                    ->where('policy_coverage_id', $policyCoverageKey)
                    ->whereNull('deleted_at')
                    ->get();
            }

            // The capture form (SpecialistCoveragePage "Note" section) saves directly
            // to medical_malpractice_coverages.notes — read that column, not the
            // unrelated legacy policy_coverage_notes table (nothing writes MM notes there).
            $notesText = trim((string) ($mm->notes ?? ''));
        @endphp
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4"><strong>Medical Malpractice Coverage</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Policy Number</td>
                <td style="width: 25%;">{{ $mm->policy_number ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Type of Document</td>
                <td style="width: 25%;">{{ $mm->type_of_document ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Insured</td>
                <td>{{ $mm->insured ?? '' }}</td>
                <td style="font-weight: bold;">Insured VAT Number</td>
                <td>{{ $mm->insured_vat_number ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Company Registration Number</td>
                <td>{{ $mm->company_registration_number ?? '' }}</td>
                <td style="font-weight: bold;">Intermediary</td>
                <td>{{ $mm->intermediary ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Insured Business Description</td>
                <td colspan="3">{{ $mm->insured_business_description ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Insured Postal Address</td>
                <td colspan="3">{{ $mm->insured_postal_address ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Period of Insurance (Month's)</td>
                <td>{{ $mm->period_of_insurance ?? '' }} Month's </td>
                <td style="font-weight: bold;">Policy Inception Date</td>
                <td> @if($mm->policy_inception_date)
                    {{ fmtDate($mm->policy_inception_date, 'd/m/Y') }}
                @else
                   {{ fmtDate($policy->term_start_date, 'd/m/Y') }}
                @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Policy Expiry Date</td>
                <td> @if($mm->policy_expiry_date)
                    {{ fmtDate($mm->policy_expiry_date, 'd/m/Y') }}
                @else
                   {{ fmtDate($policy->term_end_date, 'd/m/Y') }}
                @endif
                </td>
                <td style="font-weight: bold;">Today's Date</td>
                <td> @if($mm->today_date)
                    {{ fmtDate($mm->today_date, 'd/m/Y') }}
                @else
                   {{ fmtDate(now(), 'd/m/Y') }}
                @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">New/Altered</td>
                <td>{{ $mm->new_altered ?? '' }}</td>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($mm->is_renewable) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Project Specific Policy</td>
                <td>{{ yes_no($mm->is_project_specific) }}</td>
                <td style="font-weight: bold;">Annual Premium</td>
                <td>{{ is_numeric($mm->annual_premium ?? null) ? number_format((float) $mm->annual_premium, 2) : ($mm->annual_premium ?? '') }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Anniversary/Renewal Date</td>
                <td>{{ optional($mm->anniversary_renewal_date)->format('d/m/Y') }}</td>
                <td style="font-weight: bold;">Retroactive Date</td>
                <td>{{ optional($mm->retroactive_date)->format('d/m/Y') }}</td>
            </tr>
        </table>

        @php
            $riskDetails = is_array($mm->risk_details ?? null) ? $mm->risk_details : [];
        @endphp
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="2" style="font-weight: bold;">Risk Details</td>
            </tr>
            <tr>
                <td style="width: 60%;font-weight: bold;">Risk Detail</td>
                <td style="width: 40%;font-weight: bold;">Limit of Indemnity</td>
            </tr>
            @forelse($riskDetails as $risk)
                <tr>
                    <td>{{ ucwords(strtolower($risk['risk_detail'] ?? '')) }}</td>
                    <td>{{ number_format((float) $risk['value'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="text-center">No risk details recorded.</td>
                </tr>
            @endforelse
        </table>

        @php
            $extensions = is_array($mm->extensions ?? null) ? $mm->extensions : [];
        @endphp
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="5"><strong>Extensions Applicable</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Section Name</td>
                <td style="width: 20%; font-weight: bold;">Limit of Indemnity</td>
                <td style="width: 20%; font-weight: bold;">Basis of Limit</td>
                <td style="width: 15%; font-weight: bold;">Deductible</td>
                <td style="width: 15%; font-weight: bold;">Basis of Deductible</td>
            </tr>
            @forelse($extensions as $extension)
                <tr>
                    <td>{{ ucwords(strtolower($extension['section_name'] ?? '')) }}</td>
                    <td>{{ number_format((float) $extension['limit_of_indemnity'] ?? 0, 2) }}</td>
                    <td>{{ $extension['basis_of_limit'] ?? '-' }}</td>
                    <td>{{ number_format((float) $extension['deductible'] ?? 0, 2) }}</td>
                    <td>{{ $extension['basis_of_deductible'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        @php
            $specificDeductibles = is_array($mm->specific_deductibles ?? null) ? $mm->specific_deductibles : [];
        @endphp
        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="5"><strong>Specific Deductibles</strong></td>
            </tr>
            <tr>
                <td style="width: 30%; font-weight: bold;">Section Name</td>
                <td style="width: 20%; font-weight: bold;">Limit of Indemnity</td>
                <td style="width: 20%; font-weight: bold;">Basis of Limit</td>
                <td style="width: 15%; font-weight: bold;">Deductible</td>
                <td style="width: 15%; font-weight: bold;">Basis of Deductible</td>
            </tr>
            @forelse($specificDeductibles as $deductible)
                <tr>
                    <td>{{ ucwords(strtolower($deductible['section_name'] ?? '')) }}</td>
                    <td>{{ number_format((float) $deductible['limit_of_indemnity'] ?? 0, 2) }}</td>
                    <td>{{ $deductible['basis_of_limit'] ?? '-' }}</td>
                    <td>{{ number_format((float) $deductible['deductible'] ?? 0, 2) }}</td>
                    <td>{{ $deductible['basis_of_deductible'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No specific deductibles recorded.</td>
                </tr>
            @endforelse
        </table>

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
                        <td>{{ is_numeric($specifiedItem->sum_insured ?? null) ? number_format((float) $specifiedItem->sum_insured, 2) : ($specifiedItem->sum_insured ?? '') }}</td>
                        <td>{{ $specifiedItem->rate ?? '' }}</td>
                        <td>{{ is_numeric($specifiedItem->calculated_value ?? null) ? number_format((float) $specifiedItem->calculated_value, 2) : ($specifiedItem->calculated_value ?? '') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" style="font-weight: bold; text-align: right;">Miscellaneous Items Subtotal</td>
                    <td style="font-weight: bold;">{{ number_format($miscSubtotal, 2) }}</td>
                </tr>
            </table>
        @endif
        @if($mm->standard_policy_conditions !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td colspan="3" style="text-align: center;"><strong>Standard Policy Conditions</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: left; white-space: pre-wrap;">{{ $mm->standard_policy_conditions }}</td>
                </tr>
            </table>
        @endif

        @if($notesText !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td colspan="3" style="text-align: center;"><strong>Notes</strong></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: left; white-space: pre-wrap;">{{ $notesText }}</td>
                </tr>
            </table>
        @endif
    @endforeach
@endif
