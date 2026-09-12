<x-app-layout>
	<x-slot name="breadcrum">
		@if(isset($breadcrum))
			{{ Breadcrumbs::render($breadcrum) }}
		@endif
	</x-slot>
	@livewire($page)
</x-app-layout>