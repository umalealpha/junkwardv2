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
<div class="container">
    Hi @isset($mail_data['firstName']) {!! $mail_data['firstName'] !!} @endisset @isset($mail_data['lastName']) {!! $mail_data['lastName'] !!} @endisset,
    <br><br>
    Please update card details by clicking on <strong>Click me</strong> button
    <br>
    <br>
    <div class="row" style="text-align: center;">
        <div class="form-group col-md-4">
            <a href='{!! url($link) !!}'><button type="button" id="" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Click me</button></a>
        </div>
    </div>
    <br><br>
</div>
Regards,
{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; Copyright {{ now()->year }} Alpha Direct, All Rights Reserved.

@endcomponent
@endslot
@endcomponent
