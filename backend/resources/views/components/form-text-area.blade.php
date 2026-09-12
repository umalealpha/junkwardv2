@props([
    'disabled' => false,
    'model' => $attributes->wire('model')->value ?? $attributes->get('name'),
    'value' => '',
    'coverage_id' => null,
    'autocomplete' => 'off'
])

@php
    if(empty($value)) {
        $value = "do something";
    }
@endphp

@error($model)
    <textarea {{ $disabled ? 'disabled' : '' }} autocomplete="{{ $autocomplete }}" class="form-control is-invalid {{ $attributes->get('class') }}" {!! $attributes->merge() !!}>{{ $value }}</textarea>
@else
    <textarea {{ $disabled ? 'disabled' : '' }} autocomplete="{{ $autocomplete }}" class="form-control {{ $attributes->get('class') }}" {!! $attributes->merge() !!}>{{ $value }}</textarea>
@enderror
