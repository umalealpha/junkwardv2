{{--
  Shared claim-form body. ONE template renders BOTH the PDF we attach and the
  online form the claimant opens — driven by $mode ('pdf' | 'web'). Two
  renderers over one source is deliberate: a paper form and a web form that ask
  different questions is exactly how a claimant ends up filling something twice.

  $mode  'pdf' = flat printable | 'web' = editable inputs
  $data  ClaimFormPrefill::build() output
  $form  claim_type_forms row
  $claim claims row

  CFO decision 11-Aug-2026: the pre-filled details are EDITABLE by the claimant.
  Every pre-filled input therefore carries its original value in data-prefill so
  the submission can record what we filled in AND what they changed — the claim
  file shows any alteration without getting in the customer's way.
--}}
@php
    $isPdf   = ($mode ?? 'pdf') === 'pdf';
    $insured = $data['insured'] ?? [];
    $policy  = $data['policy']  ?? [];
    $vehicle = $data['vehicle'] ?? [];
    $c       = $data['claim']   ?? [];

    // A pre-filled value: printed as text on the PDF, as an editable input on
    // the web. Blank stays blank — never a guess on a document the claimant
    // will treat as authoritative.
    $show = function ($label, $value, $name = null) use ($isPdf) {
        $v = trim((string) ($value ?? ''));
        if ($isPdf) {
            $printed = $v !== '' ? e($v) : '<span style="color:#9aa1ab">&nbsp;</span>';
            return '<div class="f"><span class="l">' . e($label) . '</span>'
                 . '<span class="v">' . $printed . '</span></div>';
        }
        $n = $name ?: \Illuminate\Support\Str::slug($label, '_');
        return '<label class="f"><span class="l">' . e($label) . '</span>'
             . '<input class="v in" name="prefill[' . e($n) . ']" value="' . e($v) . '" '
             . 'data-prefill="' . e($v) . '"></label>';
    };
@endphp

<div class="doc">
    <div class="hdr">
        <div class="brand">Alpha Direct Insurance Company (Pty) Ltd</div>
        <div class="ttl">{{ $form->form_title ?? 'Claim Form' }}</div>
        <div class="ref">Claim {{ $c['number'] ?? '' }}</div>
    </div>

    @unless($isPdf)
        <p class="intro">
            We have already filled in everything we hold. Please check it, correct
            anything that is wrong, and answer the questions at the bottom — those
            are the parts only you can know.
        </p>
    @endunless

    <div class="sec">Your details</div>
    <div class="grid">
        {!! $show('Name of insured', $insured['name'] ?? null, 'insured_name') !!}
        {!! $show('Email address',   $insured['email'] ?? null, 'insured_email') !!}
        {!! $show('Contact number',  $insured['phone'] ?? null, 'insured_phone') !!}
    </div>

    <div class="sec">Your policy</div>
    <div class="grid">
        {!! $show('Policy number',       $policy['number'] ?? null, 'policy_number') !!}
        {!! $show('Product',             $policy['product'] ?? null, 'policy_product') !!}
        {!! $show('Cover started',       $policy['inception'] ?? null, 'policy_inception') !!}
        {!! $show('Cover ends',          $policy['expiry'] ?? null, 'policy_expiry') !!}
        {!! $show('Sum insured',         $policy['sum_insured'] ?? null, 'policy_sum_insured') !!}
        {!! $show('Excess',              $policy['excess'] ?? null, 'policy_excess') !!}
    </div>

    @if(!empty(array_filter($vehicle, fn($v) => !is_null($v) && $v !== '' && !is_array($v))))
        <div class="sec">Your vehicle</div>
        <div class="grid">
            {!! $show('Registration number', $vehicle['registration'] ?? null, 'vehicle_registration') !!}
            {!! $show('Make',                $vehicle['make'] ?? null, 'vehicle_make') !!}
            {!! $show('Model',               $vehicle['model'] ?? null, 'vehicle_model') !!}
            {!! $show('Year',                $vehicle['year'] ?? null, 'vehicle_year') !!}
            {!! $show('Chassis number',      $vehicle['chassis'] ?? null, 'vehicle_chassis') !!}
        </div>
    @endif

    @if(!empty($vehicle['glass']))
        {{-- Glass detail is ALREADY captured in claim_vehicle when the claim is
             registered, so a glass claimant should not be asked for it twice. --}}
        <div class="sec">The damaged glass</div>
        <div class="grid">
            {!! $show('Type of glass',        $vehicle['glass']['type_of_glass'] ?? null, 'glass_type') !!}
            {!! $show('Size of broken pane',  $vehicle['glass']['plate_size'] ?? null, 'glass_size') !!}
            {!! $show('How it was damaged',   $vehicle['glass']['damage_cause'] ?? null, 'glass_cause') !!}
            {!! $show('Extent of the damage', $vehicle['glass']['damage_extent'] ?? null, 'glass_extent') !!}
            {!! $show('Where the vehicle is', $vehicle['glass']['vehicle_situated'] ?? null, 'glass_where') !!}
            {!! $show('Replacement estimate', $vehicle['glass']['estimate'] ?? null, 'glass_estimate') !!}
        </div>
    @endif

    <div class="sec">What happened</div>
    <div class="qs">
        @foreach(($data['claimant_to_complete'] ?? []) as $q)
            @php $req = !empty($q['req']); @endphp
            @if($isPdf)
                <div class="q">
                    <div class="ql">{{ $q['label'] }}@if($req)<span class="req"> *</span>@endif</div>
                    <div class="qa {{ $q['type'] === 'textarea' ? 'tall' : '' }}"></div>
                </div>
            @else
                <label class="q">
                    <span class="ql">{{ $q['label'] }}@if($req)<span class="req"> *</span>@endif</span>
                    @if($q['type'] === 'textarea')
                        <textarea name="answers[{{ $q['key'] }}]" rows="3" @if($req) required @endif></textarea>
                    @elseif($q['type'] === 'yesno')
                        <select name="answers[{{ $q['key'] }}]" @if($req) required @endif>
                            <option value="">Please choose</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    @else
                        <input type="{{ in_array($q['type'], ['date', 'time']) ? $q['type'] : 'text' }}"
                               name="answers[{{ $q['key'] }}]" @if($req) required @endif>
                    @endif
                </label>
            @endif
        @endforeach
    </div>

    @if($isPdf)
        <div class="sign">
            <div class="sl"><span></span>Signature of insured</div>
            <div class="sl"><span></span>Date</div>
        </div>
        <div class="foot">
            Please return this form with your supporting documents.
            Alpha Direct Insurance Company (Pty) Ltd &middot; Gaborone, Botswana
        </div>
    @endif
</div>
