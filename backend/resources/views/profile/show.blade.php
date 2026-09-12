<x-app-layout>
	<x-slot name="breadcrum">
		<div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit User
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home">
						<i class="flaticon2-shelter"></i>
					</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> 
					<a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
					<span class="kt-subheader__breadcrumbs-separator"></span> 
					<a href="{{  URL::to('admin/user') }}" class="kt-subheader__breadcrumbs-link"> User </a> 
						<span class="kt-subheader__breadcrumbs-separator"></span>
						<span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
	</x-slot>
	
		<div class="kt-portlet">
			<div class="kt-portlet__body" style="padding-top:0px" >
				<div class="row">  
					<div class="kt-portlet kt-portlet--tabs">
						<div class="kt-portlet__head"> 
							<div class="kt-portlet__head-label">
								<h3 class="kt-portlet__head-title">Profile Account
									@if(auth()->user()->active == null)  
										<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Account Not-Active</span>
									@elseif(auth()->user()->active == 1)  
										<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Account Active</span>
									@elseif(auth()->user()->active == 2)  
										<span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Account Suspended</span>
									@endif
								</h3>
							</div>
							<div class="kt-portlet__head-toolbar"> 
								<ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold" role="tablist">
									<li class="nav-item">
										<a class="nav-link active" data-toggle="tab" href="#kt_portlet_base_demo_3_1_personal_info" role="tab">Personal Info</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_5_change_profile_picture" role="tab">Change Profile Picture</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#Two_Factor_Authentication" role="tab">Two Factor Authentication</a>
									</li>
									@if(auth()->user()->hasPermissionTo('password-reset'))
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_2_change_password" role="tab">Change Password </a>
									</li>
									@endif
								</ul>
							</div> 
						</div> 
					</div> 
				</div>  
			</div>
			<div class="row"> 
				<div class="col-3 pd-0">
					<div class="kt-portlet__body"> 
						<div class="kt-user-card__wrapper text-center">  
							<div class="kt-user-card__pic">   
								@if(auth()->user()->profile->profile_photo != null) 

										 <img class="mt-5 rounded border border-light" src="{{\AlphaDirect\Helper::getCloudFrontURL(auth()->user()->profile->profile_photo)}}" style="height:25%;width:55%;border-radius: 50%!important;margin: 0 auto;" >


								@else  
									@if(auth()->user()->profile->gender == '0') 
										<img alt="Pic" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" src="http://cbs.iiit.ac.in/wp-content/uploads/2017/11/blank_profile_female-1.jpg" /> 
									@else
									<img alt="Pic" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" src="https://www.instituteofphotography.in/wp-content/uploads/2015/05/dummy-profile-pic.jpg" />
									@endif
								@endif
							</div> 
							<div class="kt-user-card__details mt-4">
								<h3>{{ auth()->user()->firstName }}  {{ auth()->user()->lastName }}</h3>
								<h4 class="mt-2 kt-font-info"> {{ auth()->user()->profile->work_position }}</h4> 
								<h5><i class="fas fa-envelope"></i> {{ auth()->user()->email }}</h5> 
								<h5><i class="mt-2  fas fa-mobile-alt"></i> {{ auth()->user()->profile->cellphone }}</h5>  
							</div>
						</div>
					</div>
				</div>
				
				<div class="col-9">  
					<div class="kt-portlet__body" style="padding-top:0px" >
						<div class="tab-content">
							<div class="tab-pane active" id="kt_portlet_base_demo_3_1_personal_info" role="tabpanel">
								@livewire('profile.profile-information')
							</div>
							<div class="tab-pane" id="kt_portlet_base_demo_3_5_change_profile_picture" role="tabpanel">
								@livewire('profile.change-profile-picture')
							</div>
							<div class="tab-pane " id="Two_Factor_Authentication" role="tabpanel">
								@if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
									@livewire('profile.two-factor-authentication-form')
								  <x-jet-section-border />
								@endif
								@livewire('profile.logout-other-browser-sessions-form')
							</div>
							<div class="tab-pane" id="kt_portlet_base_demo_3_2_change_password" role="tabpanel"> 
								@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
									@livewire('profile.update-password-form')
								@endif
							</div>
						</div>
					</div>
				</div>
			</div>	
		</div>
	
@push('css')
<style>
	.jetstream-modal{
		left:34%;
		top:34%;
	}
</style>
@endpush	
</x-app-layout>
