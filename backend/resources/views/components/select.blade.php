@props([
    'options' => [],
    'model' => $attributes->wire('model')->value ?? $attributes->get('name'),
    'disabled' => false

])
<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'form-select form-select-solid']) !!}>
	<option value="">- Select -</option>
	@foreach($options as $optionValue => $optionLabel)
		<option value="{{ $optionValue }}">{{ $optionLabel }}</option>
	@endforeach
</select>


