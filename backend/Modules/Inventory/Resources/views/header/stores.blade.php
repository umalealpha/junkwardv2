<div class="kt-subheader kt-grid__item" id="kt_subheader">
	<div class="kt-subheader__main">
		<h3 class="kt-subheader__title">
			Stores Details
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
			<a href="{{url('inventory')}}" class="kt-subheader__breadcrumbs-link">
				Inventory Dashboard
			</a>
			<span class="kt-subheader__breadcrumbs-separator"></span>
			@if(request()->route()->getName()=='inventory.stores.create')
			<a href="{{route('inventory.stores')}}" class="kt-subheader__breadcrumbs-link">
				Manage Store's Details
			</a>
			<span class="kt-subheader__breadcrumbs-separator"></span>
			<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
				Add New Store Details
			</span>
			@elseif(request()->route()->getName()=='inventory.stores.edit')
			<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
				Edit Store Details
			</span>
			@else
				<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
					Store's Details
				</span>
			@endif
		</div>
	</div>
	<div class="kt-subheader__toolbar">
		<div class="kt-subheader__wrapper">

            <a href="{{route('admin-dashboard')}}" class="btn btn-success btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" data-placement="left" data-original-title="Go Back">
				<span class="kt-opacity-11">Go Back</span>&nbsp;
				<i class="flaticon-reply kt-padding-l-5 kt-padding-r-0"></i>
			</a>

          @if(auth()->user()->can('store-create') || auth()->user()->hasRole(['Super Admin','Stores']))
			<a href="{{ route('inventory.stores.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" data-placement="left" data-original-title="Add New">
				<span class="kt-opacity-11">Add New</span>&nbsp;
				<i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
			</a>
          @endif
		</div>
	</div>
</div>
