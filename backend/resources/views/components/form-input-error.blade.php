@props(['name','bag'])
@error($name, $bag)
    <div style='display:block !important' {{ $attributes->merge(['class' => 'invalid-feedback']) }} >
	  {!! $message !!}
	</div>
@enderror