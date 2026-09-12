<x-jet-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>
	<x-slot name="form">
        
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="firstName" value="{{ __('First Name') }}" />
            <x-jet-input id="firstName" type="text" class="mt-1 block w-full form-control" wire:model.defer="state.firstName" autocomplete="firstName" />
            <x-jet-input-error for="firstName" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="lastName" value="{{ __('Last Name') }}" />
            <x-jet-input id="lastName" type="text" class="mt-1 block w-full form-control" wire:model.defer="state.lastName" autocomplete="lastName" />
            <x-jet-input-error for="lastName" class="mt-2" />
        </div>
		
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="email" value="{{ __('Email') }}" />
            <x-jet-input id="email" type="email" class="mt-1 block w-full form-control" wire:model.defer="state.email" />
            <x-jet-input-error for="email" class="mt-2" />
        </div>
		
		<div class="col-span-6 sm:col-span-4">
            <x-jet-label for="dob" value="{{ __('Date of Birth') }}" />
            <x-jet-input id="dob" type="text" class="mt-1 block w-full form-control kt_datepicker" wire:model.defer="state.dob" autocomplete="dob" />
            <x-jet-input-error for="dob" class="mt-2" />
        </div>
		<div class="col-span-6 sm:col-span-4">
            <x-jet-label for="department_id" value="{{ __('Department') }}" />
            <x-jet-input id="department_id" type="text" class="mt-1 block w-full form-control" wire:model.defer="state.profile.department_id" autocomplete="state.profile.department_id" />
            <x-jet-input-error for="department_id" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-jet-action-message class="mr-3" on="saved">
            {{ __('Your Profile Updated Sucessfully.') }}
        </x-jet-action-message>

        <x-jet-button wire:loading.attr="disabled">
            {{ __('Save') }}
        </x-jet-button>
    </x-slot>
	@push('js')
		<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
		<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
		<script>
			var KTBootstrapDatepicker = function () {
				var arrows;
				if (KTUtil.isRTL()) {
					arrows = {
						leftArrow: '<i class="la la-angle-right"></i>',
						rightArrow: '<i class="la la-angle-left"></i>'
					}
				} else {
					arrows = {
						leftArrow: '<i class="la la-angle-left"></i>',
						rightArrow: '<i class="la la-angle-right"></i>'
					}
				}
				// Private functions
				var demos = function () {
					// minimum setup
					$('.kt_datepicker').datepicker({
						rtl: KTUtil.isRTL(),
						todayHighlight: true,
						orientation: "bottom left",
						templates: {
							leftArrow: '<i class="la la-angle-left"></i>',
							rightArrow: '<i class="la la-angle-right"></i>'
						},
						format: 'yyyy-mm-dd'
					});
				}
				return {
					// public functions
					init: function() {
						demos();
					}
				};
			}();
			jQuery(document).ready(function() {
				KTBootstrapDatepicker.init();
			});
		</script>
	@endpush
</x-jet-form-section>
