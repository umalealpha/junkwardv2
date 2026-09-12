@extends('inventory::layouts.master')
@section('breadcrum')
	<div class="kt-subheader kt-grid__item" id="kt_subheader">
		<div class="kt-subheader__main">
			<h3 class="kt-subheader__title">
				Inventory
			</h3>
			<span class="kt-subheader__separator kt-hidden"></span>
			<div class="kt-subheader__breadcrumbs">
				<a href="#" class="kt-subheader__breadcrumbs-home">
					<i class="flaticon2-shelter"></i>
				</a>
				<span class="kt-subheader__breadcrumbs-separator"></span> 
					<a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> 	Dashboard 
					</a> 
				<span class="kt-subheader__breadcrumbs-separator"></span>
				<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">	Inventory Dashboard
				</span>
			</div>
		</div>
	</div>
@endsection
@section('content')
<div class="kt-portlet kt-portlet--mobile">
	<div class="kt-portlet__body">
	<p>
        This view is loaded from module: {!! config('inventory.name') !!}
		<livewire:inventory::dashboard />
	</p>
	</div>
</div>
@endsection
