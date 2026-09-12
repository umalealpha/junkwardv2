<div>
	<div class="d-flex flex-stack"
	x-data="{
		state: 	@entangle($attributes->wire('model')),
		id:@js($attributes->get('id')),
		label:@js($attributes->get('label'))
	}">
	<!--begin::Label-->
		<div class="me-5">
			<label x-text="label" {{ $attributes->merge(['class' => '']) }} >
			</label>
		</div>
		<!--end::Label-->
		<!--begin::Switch-->
		<label class="form-check form-switch form-check-custom form-check-solid">
			<input class="form-check-input" type="checkbox" x-on:click="state = ! state" :checked="state" {{ $disabled ? 'disabled' : '' }}/>
			<span class="form-check-label fw-semibold text-muted" x-text="(state) ? 'Yes':'NO'">

			</span>
		</label>
		<!--end::Switch-->

	</div>
</div>
