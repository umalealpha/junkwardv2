<div class="kt-aside kt-aside--fixed kt-grid__item kt-grid kt-grid--desktop kt-grid--hor-desktop" id="kt_aside">
	<div class="kt-aside__brand kt-grid__item" id="kt_aside_brand">
		<div class="kt-aside__brand-logo">
			<a>
				<img alt="Logo" src="{{asset('images/logo.png')}}" />
			</a>
		</div>
		<div class="kt-aside__brand-tools">
			<button class="kt-aside__brand-aside-toggler kt-aside__brand-aside-toggler--left"
					id="kt_aside_toggler"><span></span></button>
		</div>
	</div>
	<div class="kt-aside-menu-wrapper kt-grid__item kt-grid__item--fluid" id="kt_aside_menu_wrapper">
		<div id="kt_aside_menu" class="kt-aside-menu " data-ktmenu-vertical="1" data-ktmenu-scroll="1" data-ktmenu-dropdown-timeout="500">
			<ul class="kt-menu__nav">
				<li class="kt-menu__item kt-menu__item--submenu"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('admin-dashboard')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon la la-university"></i>
						<span class="kt-menu__link-text">Dashboard</span></a>
				</li>
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback') ?  'kt-menu__item--here ': '' }}"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('cashback')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon2-graphic"></i>
						<span class="kt-menu__link-text">Cashback Dashboard</span></a>
				</li>
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback/customer') ?  'kt-menu__item--here ': '' }}"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('cashback.customer')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon-users-1"></i>
						<span class="kt-menu__link-text">Customers</span></a>
				</li>
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback/report') ?  'kt-menu__item--here ': '' }}"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('cashback.report')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon-file"></i>
						<span class="kt-menu__link-text">Report</span></a>
				</li>
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback/settings') ?  'kt-menu__item--here ': '' }}"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('cashback.settings')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon la la-cog"></i>
						<span class="kt-menu__link-text">Settings</span></a>
				</li>

				<!-- <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback/settings/*') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="javascript:;" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon-open-box"></i>
						<span class="kt-menu__link-text" title="Manage cashback">
							cashback
						</span>
						<i class="kt-menu__ver-arrow la la-angle-right"></i>
					</a>
					<div class="kt-menu__submenu ">
						<span class="kt-menu__arrow"></span>
						<ul class="kt-menu__subnav">
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('cashback/settings') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('cashback.settings')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-dropbox"></i>
								<span class="kt-menu__link-text">Cashback Settings</span></a>
							</li>

						</ul>
					</div>
				</li> -->
			</ul>
		</div>
	</div>
</div>
