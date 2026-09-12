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
<style type="text/css">
	h6{
		color: #777777 !important;
		font-size: 18px;
		
	}
</style>
 <div style="text-align: center;">
      <p>Hi User</p>
      <p>{!! $msg !!}</p>
      <p>Activation Code Link is: <a href="{!! $link !!}"><button type="button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Activation Codes</button></a></p>
  
<h6 style="text-align: left;">Regards,</h6>
{{-- Footer --}}
@slot('footer')
    @component('mail::footer')
    &copy; {{ now()->year }} Copyrights Reserved
@endcomponent
@endslot
@endcomponent
