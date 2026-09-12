@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => 'https://alphadirect.co.bw'])
<div class="clearfix float-my-children">
    <img style="width: 200px; " src="https://graphite.alphadirect.co.bw/Logo.png">
</div>
@endcomponent
@endslot

{{-- Body --}}
<div style="text-align: center;">
    {!! $mail_template !!}
</div>

{{--<img src="{{ asset('/images/mail_footer.gif') }}">--}}

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; Copyright {{ now()->year }} Alpha Direct, All Rights Reserved.
@endcomponent
@endslot
@endcomponent