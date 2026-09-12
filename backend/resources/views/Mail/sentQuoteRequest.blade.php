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
    {!! $mail_template !!}
@if($claims->claim_type != 'Cellphone')
    <a href="{!! route('supplier.quote',['quote_id'=>$quote_id]) !!}"><button type="button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Submit Quote</button></a>
@else
    <a href="{!! route('supplier.cellphoneQuote',['id'=>$claim_id,'supplier_id'=>$repaircenter_id]) !!}"><button type="button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Submit Quote</button></a>
    {{-- <a href=""><button type="button" style="background-color: #f44336; color: white; border: 2px solid #f44336; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Reject</button></a> --}}
@endif
</div>

Thanks,
{{--<img src="{{ asset('/images/mail_footer.gif') }}">--}}

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; {{ now()->year }} Copyrights Reserved
@endcomponent
@endslot
@endcomponent