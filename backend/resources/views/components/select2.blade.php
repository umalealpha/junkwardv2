<div x-data="{
	data: @js($options),
	trackBy:@js($trackBy ?? 'id'),
	name:@js($attributes->get('id')),
	title:@js($label ?? 'name'),
	listner: @js($attributes->get('listner') ?? '0'),
	value: @entangle($attributes->wire('model')),
	rebuild(event){
		if(event.detail.key==this.listner){
			this.data=event.detail.data;
			if ($('#'+this.name).data('select2')) {
			   $('#'+this.name).select2('destroy');
			 }
			function testing(seleced_id,obj) {
                if(seleced_id){
                    $('#'+obj.name).val(seleced_id).select2();
                }else{
                    $('#'+obj.name).select2();
                }
            }
            setTimeout(testing,500,event.detail.selected_id,this);
		}
	}
}"
wire:ignore
x-on:dropdown-changed.window="rebuild($event)"
>
<select class="form-select form-select-solid ss" id="{{$attributes->get('id')}}" value="{{ $attributes->get('value') }}">
	<option value=""> - Select -</option>
	<template  x-for="(key, index) in Object.keys(data)" :key="index">
		<option :value="Object.values(data)[index][trackBy]" :selected="key === value" x-text="Object.values(data)[index][title]">
		</option>
	</template>
</select>
</div>
@push('scripts')
<script>
	$(function() {
	    @if($attributes->get("value"))
            $('#{{$attributes->get("id")}}').val('{{ $attributes->get("value") }}').select2();
        @else
            $('#{{$attributes->get("id")}}').select2();
        @endif
		$('#{{$attributes->get("id")}}').on('change', function (e) {
			@this.set("{{ $attributes->wire('model')->value }}",$(this).select2("val"));
		});
	});
    // @todo
    // need to change class base change event to id base change event
    // having issue when use multiple drop-down on single page
	{{--document.addEventListener("DOMContentLoaded", () => {--}}
	{{--	Livewire.hook('message.processed', (message, component) => {--}}
	{{--		$('.ss').select2();--}}
	{{--		$('.ss').on('change', function (e) {--}}
	{{--			@this.set("{{ $attributes->wire('model')->value }}",$(this).select2("val"));--}}
	{{--		});--}}
	{{--	})--}}
	{{--});--}}
</script>
@endpush
