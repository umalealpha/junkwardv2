@php
    // This view is included by v2-quote-sheet.blade.php after the Index section.
    $travelCoveragesForPdf = collect($policy_coverages ?? [])->filter(function($pc) {
        $code = $pc->coverage->s_CoverageCode ?? null;
        return in_array($code, ['TRAVEL', 'TRAVELINSURANCE']);
    })->values();
@endphp

@if($travelCoveragesForPdf->isNotEmpty())
    <div>
      

        @foreach($travelCoveragesForPdf as $pc)
            @php
                $tc = $pc->travelCoverage ?? null;
                // `benefits` may be stored as JSON string in DB
                $benefitsRaw = $tc?->benefits;
                if (is_array($benefitsRaw)) {
                    $benefits = $benefitsRaw;
                } elseif (is_string($benefitsRaw) && !empty($benefitsRaw)) {
                    $benefits = json_decode($benefitsRaw, true) ?? [];
                } else {
                    $benefits = [];
                }

                // `custom_benefits` is cast to array, but support string JSON too
                $customBenefitsRaw = $tc?->custom_benefits;
                if (is_array($customBenefitsRaw)) {
                    $customBenefits = $customBenefitsRaw;
                } elseif (is_string($customBenefitsRaw) && !empty($customBenefitsRaw)) {
                    $customBenefits = json_decode($customBenefitsRaw, true) ?? [];
                } else {
                    $customBenefits = [];
                }
                $wordingUrl = !empty($tc?->policy_wording_path) ? (config('app.S3_BASE_URL') . $tc->policy_wording_path) : '';
            @endphp

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: 1px solid #2e77c3!important; margin-bottom: 6px;">
                <tbody style="color: #2e77c3!important;">
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Coverage:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $pc->coverage->s_ScreenName ?? 'Travel Insurance' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Policyholder:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->policyholder ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Passport:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->passport ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Phone Number:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->phone_num ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Travel Policy Number:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->policy_number ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">No. of Passengers:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->number_passengers ?? '' }}</p></td>
                    </tr>

                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Effective From:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ optional($tc->effective_from)->format('d/m/Y') }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Expiry:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ optional($tc->expiry)->format('d/m/Y') }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Policy Period (Months):</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">Upto {{ $tc->policy_period_months ?? '' }} Months Max</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Renewable Policy:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ yes_no($tc->is_renewable) }}</p></td>
                    </tr>

                    @php
                        $pa = $tc?->policy_amount;
                        $vatVal = $tc?->vat;
                        $totVal = $tc?->total;
                    @endphp
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Policy Amount:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ is_numeric($pa) ? number_format((float)$pa, 2, '.', ',') : ($pa ?? '') }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">VAT:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ is_numeric($vatVal) ? number_format((float)$vatVal, 2, '.', ',') : ($vatVal ?? '') }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Total:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ is_numeric($totVal) ? number_format((float)$totVal, 2, '.', ',') : ($totVal ?? '') }}</p></td>
                    </tr>

                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Destination Area:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->destination_area ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Country of Origin:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->country_of_origin ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Product:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->product ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Code:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->code ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Insurance Company:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->insurance_company ?? '' }}</p></td>
                    </tr>
                    <tr style="border-top: 0.5px solid #2e77c3!important;border-bottom: 0.5px solid #2e77c3!important;font-size:10px;">
                        <td width="30%"><p style="text-align:left;margin:0;padding:0;font-size:12px;">Company Location:</p></td>
                        <td width="70%"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $tc->company_location ?? '' }}</p></td>
                    </tr>
                
                </tbody>
            </table>

            {{-- Benefits & Coverage --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 10px;">
                <thead style="border-top: 2px solid #2e77c3!important; color: #2e77c3!important;">
                    <tr style="border-top: 1px solid #2e77c3!important;border-bottom: 1px solid #2e77c3!important;font-size:10px;background-color:#e5f4e3;">
                        <td width="55%" style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;"><b>Benefit Description</b></p></td>
                        <td width="25%" style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;"><b>Sum Insured</b></p></td>
                        <td width="20%" style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;"><b>Excess</b></p></td>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Filter out removed fixed benefits (legacy keyed-map format only).
                        $benefitsFiltered = [];
                        foreach ($benefits as $k => $b) {
                            if (is_array($b) && (($b['_removed'] ?? false) === true)) {
                                continue;
                            }
                            $benefitsFiltered[$k] = $b;
                        }
                        // New React form writes a flat list with `category`;
                        // legacy form writes a keyed map. Render category headings
                        // only for the flat-array shape.
                        $isFlatArray = array_is_list($benefitsFiltered);
                        $lastCategory = null;
                    @endphp

                    @foreach($benefitsFiltered as $k => $b)
                        @php
                            $desc = is_array($b) ? ($b['description'] ?? $k) : $k;
                            $si = is_array($b) ? ($b['sum_insured'] ?? '') : '';
                            $ex = is_array($b) ? ($b['excess'] ?? '') : '';
                            $siDisplay = is_numeric($si) ? number_format((float)$si, 2, '.', ',') : $si;
                            $category = is_array($b) ? ($b['category'] ?? null) : null;
                            $emitFlatHeading = $isFlatArray && $category && $category !== $lastCategory;
                            if ($emitFlatHeading) { $lastCategory = $category; }
                        @endphp
                        @if($emitFlatHeading)
                            <tr style="font-size:10px;background-color:#e5f4e3;">
                                <td colspan="3" style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;text-transform:uppercase;font-weight:bold;">{{ $category }}</p></td>
                            </tr>
                        @endif
                        <tr style="font-size:10px;">
                            <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $desc }}</p></td>
                            <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $siDisplay }}</p></td>
                            <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $ex }}</p></td>
                        </tr>
                    @endforeach

                    @if(!empty($customBenefits))
                        @foreach($customBenefits as $cb)
                            @php
                                $cbSi = $cb['sum_insured'] ?? '';
                                $cbSiDisplay = is_numeric($cbSi) ? number_format((float)$cbSi, 2, '.', ',') : $cbSi;
                            @endphp
                            <tr style="font-size:10px;">
                                <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $cb['description'] ?? '' }}</p></td>
                                <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $cbSiDisplay }}</p></td>
                                <td style="border: 1px solid #2e77c3!important;"><p style="text-align:left;margin:0;padding:0;color:black;font-size:10px;">{{ $cb['excess'] ?? '' }}</p></td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        @endforeach
    </div>
@endif

