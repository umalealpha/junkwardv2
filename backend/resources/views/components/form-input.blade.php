@props([
	'disabled' => false,
	'model' => $attributes->wire('model')->value ?? $attributes->get('name'),
	'autocomplete' => 'off'
])

@error($model)
<input {{ $disabled ? 'disabled' : '' }} autocomplete="{{ $autocomplete }}" {!! $attributes->merge(['class' => 'form-control is-invalid']) !!} >
@else
<input {{ $disabled ? 'disabled' : '' }} autocomplete="{{ $autocomplete }}" {!! $attributes->merge(['class' => 'form-control']) !!}  >
@enderror
