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
    @if($resent_note != NULL)
        <p>{!! $resent_note !!}</p>
    @endif
    <a href="{!! route('supplier.uploadInvoice',['claim_id'=>$claim_id, 'quote_id'=>$quote_id]) !!}"><button type="button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Upload Invoice</button></a>
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