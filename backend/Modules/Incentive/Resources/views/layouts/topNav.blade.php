<div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor kt-wrapper" id="kt_wrapper">
	<div id="kt_header" class="kt-header kt-grid__item kt-header--fixed " >
		<div class="kt-header-menu-wrapper" id="kt_header_menu_wrapper">
			<h4 style="padding:10px">{!! config('incentive.name') !!} Module</h4>
		</div>
		<div class="kt-header__topbar">
			<div class="kt-header__topbar-item kt-header__topbar-item--user">
				<div class="kt-header__topbar-wrapper" data-toggle="dropdown" data-offset="0px, 0px">
					<div class="kt-header__topbar-user">
						<span class="kt-header__topbar-welcome kt-hidden-mobile">Hi,</span> <span class="kt-header__topbar-username kt-hidden-mobile">{{Auth::user()->firstName}}</span> 
						 @if(auth()->user()->profile->profile_photo != NULL)
						   @if(str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->exists(auth()->user()->profile->profile_photo)))
								<img class="img-fluid"  src="{{ str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url(auth()->user()->profile->profile_photo)) }}" style="height:50px;width:42px;" alt="{{ auth()->user()->firstname }}">
							@else
								<img alt="Pic" style="height:50px;width:42px !important;" src="{{ auth()->user()->profile->profile_photo }}" />
							@endif
						 @else
							@if(auth()->user()->profile->gender == '0')
								<img alt="No Profile picture added" style="height:50px;width:42px !important;" src="http://cbs.iiit.ac.in/wp-content/uploads/2017/11/blank_profile_female-1.jpg" />
							@else
							<img alt="No Profile picture added" style="height:50px;width:42px !important;" src="https://www.instituteofphotography.in/wp-content/uploads/2015/05/dummy-profile-pic.jpg" />
							@endif
						@endif
						
						<!--use below badge element instead the user avatar to display username's first letter(remove kt-hidden class to display it) -->
						<span class="kt-badge kt-badge--username kt-badge--lg kt-badge--brand kt-hidden kt-badge--bold">S</span> 
					</div>
				</div>
				<div class="dropdown-menu dropdown-menu-fit dropdown-menu-right dropdown-menu-anim dropdown-menu-top-unround dropdown-menu-sm">
					<div class="kt-user-card kt-margin-b-50 kt-margin-b-30-tablet-and-mobile" style="background-image: url({{asset('media/misc/head_bg_sm.jpg')}})">
						<div class="kt-user-card__wrapper">
							<div class="kt-user-card__pic"> 
							  @if(auth()->user()->profile->profile_photo != NULL)
									@if(str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->exists(auth()->user()->profile->profile_photo)))
										 <img class="img-fluid"  src="{{ str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url(auth()->user()->profile->profile_photo)) }}" style="height:50px;width:50px" alt="{{ auth()->user()->firstname }}">

									@else
										<img alt="Pic" style="height:50px;width:50px" src="{{ auth()->user()->profile->profile_photo }}" />
									@endif
								@else
									<img alt="Pic" style="height:50px;width:50px" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" />

								@endif
							</div>
							<div class="kt-user-card__details">
								<div class="kt-user-card__name">{{Auth::user()->firstName}} {{Auth::user()->lastName}}</div>
								<div class="kt-user-card__position">COO</div>
							</div>
						</div>
					</div>
					<ul class="kt-nav kt-margin-b-10">
						<li class="kt-nav__item">
							<a class="kt-nav__link" href="{{ route('admin.user.edit',auth()->user()->id) }}">
								<span class="kt-nav__link-icon"><i class="flaticon2-calendar-3"></i></span>
								<span class="kt-nav__link-text">My Profile</span> 
							</a>
						</li>
						<li class="kt-nav__item kt-nav__item--custom kt-margin-t-15"> <a href="logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();style.display='block';" class="btn btn-outline-metal btn-hover-brand btn-upper btn-font-dark btn-sm btn-bold">Sign Out</a> </li>

					   <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
						   {{ csrf_field() }}
					   </form>
					</ul>
				</div>				
			</div>
		</div>
	</div>


