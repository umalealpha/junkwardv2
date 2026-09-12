@props(['value','required'])

<label {{ $attributes->merge(['class' => '']) }}>
    {{ $value ?? $slot }}
	@if(isset($required))
		<span class="req"><strong> *</strong></span>
	@endif
</label>

