@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<div class="clearfix float-my-children">
    <img style="width: 70px; " src="{{ asset('/images/logo.png') }}">
    <div>{{ config('app.name') }}</div>
</div>
@endcomponent
@endslot

{{-- Body --}}
<div style="text-align: center;">
    {!! $mail !!}
    @if($resent_note != NULL)
        <p>{!! $resent_note !!}</p>
    @endif
   <h3>Your Claim Rejected.</h3>
</div>

Thanks,

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; {{ now()->year }} Copyrights Reserved
@endcomponent
@endslot
@endcomponent