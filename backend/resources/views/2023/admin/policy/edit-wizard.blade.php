<x-app-layout>
    <x-slot name="breadcrum">
        <div class="d-flex align-items-center flex-wrap mr-1">
            <div class="d-flex align-items-baseline flex-wrap mr-5">
                <h5 class="text-dark font-weight-bold my-1 mr-5">Policy Details</h5>
                {{ Breadcrumbs::render('policy-edit', $policy) }}
            </div>
        </div>
    </x-slot>
	@push('css')
		<style>

		</style>
	@endpush
	 <div class="d-flex flex-column-fluid">
        <div class="container-fluid">
			@include('message-out')
			@livewire('policy.edit-wizard',['policy'=>$policy],key($policy->id))
		</div>
	</div>

</x-app-layout>
