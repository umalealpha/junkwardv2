@props([
	'file' => "",
	'accept' => 'image/jpg,image/jpeg,image/png,application/pdf',
	'multiple' => false,
	'mode' => 'attachment',
	'profileClass' => '',
	'placeholder' => ''
])
<div x-data="{ isUploading: false, progress: 0 }"
	x-on:livewire-upload-start="isUploading = true"
	x-on:livewire-upload-finish="isUploading = false"
	x-on:livewire-upload-error="isUploading = false"
	x-on:livewire-upload-progress="progress = $event.detail.progress"
}"
>
<!--begin::Image input-->

<div class="image-input image-input-empty image-input-placeholder" data-kt-image-input="true">
    <!--begin::Image preview wrapper-->
	<div wire:ignore class="image-input-wrapper w-125px h-125px" @if($file) style="background-image: url({{ \AlphaDirect\Library\Helper::getCloudFrontURL($file) }})" @endif>
		
	</div>
	<!--end::Image preview wrapper-->

    <!--begin::Edit button-->
    <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
		data-kt-image-input-action="change"
		data-bs-toggle="tooltip"
		data-bs-dismiss="click"
		title="Change">
        <i class="bi bi-pencil-fill fs-7"></i>
		<!--begin::Inputs-->
		{{-- hack to get the progress of upload file --}}
		<input 	type="file" 
				name="{{$attributes->get('name')}}"
				accept="{{ $accept }}" 
			{{ $attributes->wire('model') }}
		/>
		<input type="hidden" name="avatar_remove" />
        <!--end::Inputs-->
    </label>
    <!--end::Edit button-->

    <!--begin::Cancel button-->
    <span wire:ignore class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
		data-kt-image-input-action="cancel"
		data-bs-toggle="tooltip"
		data-bs-dismiss="click"
		title="Cancel">
        <i class="bi bi-x fs-2"></i>
    </span>
    <!--end::Cancel button-->

    <!--begin::Remove button-->
    <span wire:ignore class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
		data-kt-image-input-action="remove"
		data-bs-toggle="tooltip"
		data-bs-dismiss="click"
		title="Remove avatar">
        <i class="bi bi-x fs-2"></i>
    </span>
    <!--end::Remove button-->
</div>
<!--end::Image input-->
<div x-show="isUploading">
	<progress max="100" x-bind:value="progress"></progress>
</div>

</div>