@php
    // Recover rows where either policy_id OR policy_coverage_id links to this
    // policy. The original whereNotNull('policy_coverage_id') filter dropped any
    // record whose policy_id was wrong/empty (legacy save path / direct-
    // navigation to the schedule without a ?policy_coverage_id= URL param),
    // which in turn hid its Miscellaneous Items. Mirrors marine_cargo_open_pdf.
    // Scope to the selected transaction's coverages ($specialistPcIds from the
    // job); fall back to policy-wide only if not provided.
    $piPolicyId = data_get($policy ?? null, 'id');
    $piPcIds = !empty($specialistPcIds)
        ? $specialistPcIds
        : ($piPolicyId
            ? \DB::table('policy_coverages')->where('policy_id', $piPolicyId)->whereNull('deleted_at')->pluck('id')->all()
            : []);
    $professionalIndemnityCoverages = !empty($piPcIds)
        ? \AlphaDirect\Models\ProfessionalIndemnityCoverage::whereIn('policy_coverage_id', $piPcIds)->get()
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

    $formatDateTime = function ($value) {
        if (empty($value)) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
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
@endphp

<table style="margin-top: 8px;">
    <tr class="section-title text-center">
        <td colspan="4">Risk Address: {{ $addressName }}</td>
    </tr>
    <tr class="section-title text-center">
        <td colspan="4">{{ $coverages->coverage->s_ScreenName ?? $coverages->coverage->s_CoverageCode }}</td>
    </tr>
</table>

@if($professionalIndemnityCoverages->isEmpty())
    <table style="margin-top: 6px;">
        <tr>
            <td class="text-center">No Professional Indemnity data available for this policy.</td>
        </tr>
    </table>
@else
    @foreach($professionalIndemnityCoverages as $pi)
        @php
            $approverName = '';
            if (!empty($pi->approved_by)) {
                $approver = \AlphaDirect\Models\User::find($pi->approved_by);
                $approverName = $approver ? trim(($approver->firstName ?? '') . ' ' . ($approver->lastName ?? '')) : $pi->approved_by;
            }

            $descriptions = is_array($pi->descriptions ?? null) ? $pi->descriptions : [];
            $insuredPersons = is_array($pi->insured_persons ?? null) ? $pi->insured_persons : [];
            $extensions = is_array($pi->extensions ?? null) ? $pi->extensions : [];
            $additionalExtensions = is_array($pi->additional_extensions ?? null) ? $pi->additional_extensions : [];
            $allExtensions = array_merge($extensions, $additionalExtensions);
            // Two save paths write this column in two different shapes:
            //  - Legacy Livewire admin form (professional-indemnity.blade.php)
            //    wire:models to a nested {"basic": {...}, "others": [...]}
            //    structure.
            //  - Current React capture form (SpecialistCoveragePage's generic
            //    json-array field) saves a flat list of {description, percent,
            //    minimum_excess} rows via SpecialistCoverageController, with no
            //    basic/others wrapper. Reading only the nested shape (as before)
            //    meant every Excess captured through the current React form
            //    came back empty here even though it was saved correctly.
            $excesses = is_array($pi->excesses ?? null) ? $pi->excesses : [];
            $excessesIsLegacyShape = array_key_exists('basic', $excesses) || array_key_exists('others', $excesses);
            if ($excessesIsLegacyShape) {
                $basicExcess = $excesses['basic'] ?? [];
                $otherExcesses = $excesses['others'] ?? [];
                if (isset($otherExcesses['percent']) || isset($otherExcesses['minimum_excess']) || isset($otherExcesses['description'])) {
                    $otherExcesses = [$otherExcesses];
                }
            } else {
                $basicExcess = [];
                $otherExcesses = $excesses;
            }

            // Fall back to the in-scope PolicyCoverage id when the product row's
            // policy_coverage_id is NULL (legacy/direct-nav save path) so Miscellaneous
            // Items (and coverage notes) still resolve. policy_specified_items.policy_coverage_id
            // references policy_coverages.id == $coverages->id. Mirrors travel_insurance_pdf.
            $policyCoverageKey = $pi->policy_coverage_id ?: ($coverages->id ?? null);
            $specifiedItems = collect();

            if (!empty($policyCoverageKey)) {
                $specifiedItems = \AlphaDirect\Models\PolicySpecifiedItem::with('specifiedCoveragesItems')
                    ->where('policy_coverage_id', $policyCoverageKey)
                    ->whereNull('deleted_at')
                    ->get();
            }

            // Notes come from the PI row's OWN `notes` column — that's what the
            // capture form writes (SpecialistCoverageController's
            // 'professional-indemnity' field list) and what every other
            // specialist schedule renders ($mm->notes, $mcop->notes,
            // $mb->notes, $bond->notes).
            //
            // This blade previously read policy_coverage_notes instead — the
            // generic DomCom coverage-level Notes box — and concatenated EVERY
            // row it found with "\n". Two defects fell out of that: the PI
            // form's captured Notes never appeared at all, and because the
            // notes table keeps older revisions (see PolicyCoverage::note(),
            // which deliberately takes ->coverageLevel()->latest('id') for
            // exactly this reason) each superseded revision was stacked into
            // the output.
            //
            // policy_coverage_notes is kept as a FALLBACK for legacy PI
            // coverages whose notes were captured through the generic Notes
            // box before the PI form owned the field — but now via the same
            // coverageLevel() + latest('id') semantics as the rest of the
            // codebase, so a single current note is shown, never a pile of
            // revisions.
            $notesText = trim((string) ($pi->notes ?? ''));
            if ($notesText === '' && !empty($policyCoverageKey)) {
                $notesText = trim((string) (
                    \AlphaDirect\Models\PolicyCoverageNote::where('policy_coverage_id', $policyCoverageKey)
                        ->coverageLevel()
                        ->whereNull('deleted_at')
                        ->latest('id')
                        ->value('note') ?? ''
                ));
            }
        @endphp

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="4" style="text-align: center;"><strong>Professional Indemnity Coverage</strong></td>
            </tr>
            <tr>
                <td style="width: 25%; font-weight: bold;">Insured</td>
                <td style="width: 25%;">{{ $pi->insured ?? '' }}</td>
                <td style="width: 25%; font-weight: bold;">Profession/Business</td>
                <td style="width: 25%;">{{ $pi->profession_business ?? '' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Basis of Cover</td>
                <td>{{ $pi->basis_of_cover ?? '' }}</td>
                <td style="font-weight: bold;">Period of Insurance (Month's)</td>
                <td>{{ $pi->period_of_insurance ?? '' }} Month's</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Policy Inception Date</td>
                <td>{{ Carbon\Carbon::parse($policyTerm->term_start_date)->format('d/m/Y') }}
                </td>
                <td style="font-weight: bold;">Policy Expiry Date</td>
                <td> {{ Carbon\Carbon::parse($policyTerm->term_end_date)->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Today's Date</td>
                <td> @if($pi->today_date)
                    {{ Carbon\Carbon::parse($pi->today_date)->format('d/m/Y') }}
                @else
                   {{ Carbon\Carbon::parse(now())->format('d/m/Y') }}
                @endif
                </td>
                <td style="font-weight: bold;">Retroactive Date</td>   
                <td>{{ $formatDate($pi->retroactive_date ?? null) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">New/Altered</td>
                <td>{{ $pi->new_altered ?? '' }}</td>
                <td style="font-weight: bold;">Renewable Policy</td>
                <td>{{ yes_no($pi->is_renewable) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Project Specific Policy</td>
                <td>{{ yes_no($pi->is_project_specific) }}</td>
                <td style="font-weight: bold;">Approved By</td>
                <td>{{ $approverName }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Approved At</td>
                <td>{{ $formatDateTime($pi->approved_at ?? null) }}</td>
                <td></td>
                <td></td>
            </tr>
            
           
        </table>

        

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="5" style="text-align: center;font-weight: bold;">Insured Persons</td>
            </tr>
            <tr>
                <td style="width: 20%; font-weight: bold;">Description</td>
                <td style="width: 23%; font-weight: bold;">Insured Person</td>
                <td style="width: 14%; font-weight: bold;">Length of Service</td>
                <td style="width: 20%; font-weight: bold;">Limit of Liability</td>
                <td style="width: 23%; font-weight: bold;">Designation</td>
            </tr>
            @forelse($insuredPersons as $person)
                <tr>
                    <td>{{ $person['description'] ?? '' }}</td>
                    <td>{{ $person['insured_person'] ?? '' }}</td>
                    <td>{{ $person['length_of_service'] ?? '' }}</td>
                    <td>{{ $formatMoney($person['limit_of_liability'] ?? '') }}</td>
                    <td>{{ $person['designation'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No insured persons recorded.</td>
                </tr>
            @endforelse
        </table>

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3" style="text-align: center;font-weight: bold;">Extensions</td>
            </tr>
            <tr>
                <td style="width: 45%; font-weight: bold;">Extension</td>
                <td style="width: 23%; font-weight: bold;">Limit of Liability</td>
                <td style="width: 32%; font-weight: bold;">Premium</td>
            </tr>
            @forelse($allExtensions as $extension)
                <tr>
                    <td>{{ $extension['extension'] ?? '' }}</td>
                    <td>{{ $formatMoney($extension['limit_of_liability'] ?? '') }}</td>
                    <td>{{ $formatMoney($extension['premium'] ?? '0.00') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No extensions recorded.</td>
                </tr>
            @endforelse
        </table>

        <table style="margin-top: 6px;">
            <tr class="section-subtitle">
                <td colspan="3" style="text-align: center;font-weight: bold;">Excesses</td>
            </tr>
            <tr>
                <td style="width: 45%; font-weight: bold;">Type/Description</td>
                <td style="width: 20%; font-weight: bold;">%</td>
                <td style="width: 35%; font-weight: bold;">Minimum Excess</td>
            </tr>
            @if($excessesIsLegacyShape)
                <tr>
                    <td style="font-weight: bold;">Basic</td>
                    <td>{{ $formatMoney($basicExcess['percent'] ?? '') }}</td>
                    <td>{{ $formatMoney($basicExcess['minimum_excess'] ?? '') }}</td>
                </tr>
            @endif
            @forelse($otherExcesses as $other)
                <tr>
                    <td>{{ $other['description'] ?? 'Others' }}</td>
                    <td>{{ $formatMoney($other['percent'] ?? '') }}</td>
                    <td>{{ $formatMoney($other['minimum_excess'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">{{ $excessesIsLegacyShape ? 'No other excesses recorded.' : 'No excesses recorded.' }}</td>
                </tr>
            @endforelse
        </table>
    {{-- Notes + Miscellaneous Items stay INSIDE the per-$pi loop. They were
         previously emitted after the loop closed, so with more than one PI
         coverage on the policy both blocks rendered once using whatever the
         LAST iteration happened to leave in $notesText / $specifiedItems —
         i.e. the final coverage's notes shown once at the end instead of each
         coverage's own notes under its own section. Identical output for the
         single-PI case. --}}
    @if($notesText !== '')
            <table style="margin-top: 6px;">
                <tr class="section-subtitle">
                    <td colspan="3" style="text-align: center;font-weight: bold;">Notes</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: left; white-space: pre-wrap;">{{ $notesText }}</td>
                </tr>
            </table>
        @endif
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
                        <td>{{ optional($specifiedItem->specifiedCoveragesItems)->specified_name ?: ($specifiedItem->custom_name ?? '') }}</td>
                        <td>{{ $formatMoney($specifiedItem->sum_insured ?? '') }}</td>
                        <td>{{ $specifiedItem->rate ?? '' }}</td>
                        <td>{{ $formatMoney($specifiedItem->calculated_value ?? '') }}</td>
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
