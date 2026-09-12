<div class="kt-subheader kt-grid__item" id="kt_subheader">
	<div class="kt-subheader__main">
		<h3 class="kt-subheader__title">
			Incentive Details
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
			<a href="{{url('incentive')}}" class="kt-subheader__breadcrumbs-link">
				Incentive Dashboard
			</a>
			<span class="kt-subheader__breadcrumbs-separator"></span>
			@if(request()->route()->getName()=='incentive.settings.create')
			<a href="{{route('incentive.settings')}}" class="kt-subheader__breadcrumbs-link">
				Manage Incentive Details
			</a>
			<span class="kt-subheader__breadcrumbs-separator"></span>
			<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
				Add New Incentive Details
			</span>
			@elseif(request()->route()->getName()=='incentive.settings.edit')
			<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
				Edit Incentive Details
			</span>
			@else
				<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">
					Incentive Details
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

			<a href="{{ route('incentive.settings.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" data-placement="left" data-original-title="Add New">
				<span class="kt-opacity-11">Add New</span>&nbsp;
				<i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
			</a>
		</div>
	</div>
</div>
