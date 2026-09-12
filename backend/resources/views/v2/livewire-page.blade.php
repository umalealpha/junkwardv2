<x-app-v2-layout>
<x-slot name="breadcrum">
<!--begin::Page title-->
	<div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
		<!--begin::Title-->
		<h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">@if(isset($pageName)) {{$pageName}} @else Policy @endif</h1>
		<!--end::Title-->
		<!--begin::Breadcrumb-->
		<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
			<!--begin::Item-->
			<li class="breadcrumb-item text-muted">
				<a href="{{route('admin-dashboard')}}" class="text-muted text-hover-primary">Home</a>
			</li>
			<!--end::Item-->
			<!--begin::Item-->
			<li class="breadcrumb-item">
				<span class="bullet bg-gray-400 w-5px h-2px"></span>
			</li>
			<!--end::Item-->
			<!--begin::Item-->
			<li class="breadcrumb-item text-muted">@if(isset($pageName)) {{$pageName}} @else Policy @endif</li>
			<!--end::Item-->
		</ul>
		<!--end::Breadcrumb-->
	</div>
	<!--end::Page title-->
	<!--begin::Actions-->
	@if(isset($addRoute))
	<div class="d-flex align-items-center gap-2 gap-lg-3">
		<div class="d-flex">
			<a href="{{route($addRoute)}}" class="btn btn-icon btn-sm btn-success flex-shrink-0 ms-4">
				<!--begin::Svg Icon | path: icons/duotune/arrows/arr075.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor"></rect>
						<rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor"></rect>
					</svg>
				</span>
				<!--end::Svg Icon-->
			</a>
		</div>
	</div>
	@endif
	<!--end::Actions-->
</x-slot>
<!--begin::Content container-->
<div class="app-container container-fluid">
	<!--begin::Card-->
	<div class="card">
		<!--begin::Card body-->
		<div class="card-body">
            @include('v2.message')
			@livewire($page, ['theme'=>"bootstrap-5"])

		</div>
		<!--end::Card body-->
	</div>
	<!--begin::Card-->
</div>
<!--begin::Content container-->
</x-AppV2Layout-layout>
