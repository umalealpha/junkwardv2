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
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory') ?  'kt-menu__item--here ': '' }}"
					aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="{{route('inventory')}}" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon2-graphic"></i>
						<span class="kt-menu__link-text">Inventory Dashboard</span></a>
				</li>
                @if(auth()->user()->can('inventory-Menus') || auth()->user()->hasRole(['Super Admin']))
				<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/warehouse/*') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="javascript:;" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon-map"></i>
						<span class="kt-menu__link-text" title="Manage Stores">
							Warehouse Management
						</span>
						<i class="kt-menu__ver-arrow la la-angle-right"></i>
					</a>
					<div class="kt-menu__submenu ">
						<span class="kt-menu__arrow"></span>
						<ul class="kt-menu__subnav">
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/warehouse') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.warehouse')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-wordpress"></i>
								<span class="kt-menu__link-text">Warehouse</span></a>
							</li>
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/warehouse/create') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.warehouse.create')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-houzz"></i>
								<span class="kt-menu__link-text">Add New warehouse</span></a>
							</li>
                            @if(auth()->user()->can('add-stock') || auth()->user()->hasRole(['Super Admin','Stores']))
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/warehouse/stock') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.warehouse.stock')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-cart-plus"></i>
								<span class="kt-menu__link-text">Add Stock For Warehouse</span></a>
							</li>
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/warehouse/stock-reduce') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.warehouse.stock-reduce')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-calendar-minus-o"></i>
								<span class="kt-menu__link-text">Warehouse Stock Reduce</span></a>
							</li>
                            @endif
						</ul>
					</div>
				</li>
                @endif

                @if (auth()->user()->can('store-list')|| auth()->user()->hasRole(['Super Admin']))
                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores/*') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
					<a href="javascript:;" class="kt-menu__link kt-menu__toggle">
						<i class="kt-menu__link-icon flaticon-network"></i>
						<span class="kt-menu__link-text" title="Manage Stores">
							Stores Management
						</span>
						<i class="kt-menu__ver-arrow la la-angle-right"></i>
					</a>
					<div class="kt-menu__submenu ">
						<span class="kt-menu__arrow"></span>
						<ul class="kt-menu__subnav">
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.stores')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-empire"></i>
								<span class="kt-menu__link-text">Stores</span></a>
							</li>
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores/create') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                @if(auth()->user()->can('store-create') || auth()->user()->hasRole(['Super Admin','Stores']))
                                    <a href="{{route('inventory.stores.create')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-calendar-plus-o"></i>
                                    <span class="kt-menu__link-text">Add New Stores</span></a>
                                @endif
							</li>
                            @if(auth()->user()->can('add-stock') || auth()->user()->hasRole(['Super Admin','Stores']))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores/stock') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{route('inventory.stores.stock')}}" class="kt-menu__link kt-menu__toggle">
                                    <i class="kt-menu__link-icon la la-server"></i>
                                    <span class="kt-menu__link-text">
                                        Add Stock For Stores
                                    </span></a>
                                </li>
								<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores/stock-reduce') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
									<a href="{{route('inventory.stores.stock-reduce')}}" class="kt-menu__link kt-menu__toggle">
										<i class="kt-menu__link-icon la la-minus-circle"></i>
									<span class="kt-menu__link-text">Stores Stock Reduce</span></a>
								</li>
                             @endif
							<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('inventory/stores/patner') ?  'kt-menu__item--here ': '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
								<a href="{{route('inventory.store.patner')}}" class="kt-menu__link kt-menu__toggle">
									<i class="kt-menu__link-icon la la-list"></i>
								<span class="kt-menu__link-text">List Of Partner</span></a>
							</li>
							
						</ul>
					</div>
				</li>
                @endif

                @if (auth()->user()->can('activity-list')|| auth()->user()->hasRole(['Super Admin']))
                <li class="kt-menu__item kt-menu__item--submenu aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                    <a href="{{route('inventory.activitylog')}}" class="kt-menu__link kt-menu__toggle">
                        <i class="kt-menu__link-icon flaticon-interface-1"></i>
                        <span class="kt-menu__link-text">Activity log</span></a>
                </li>
                @endif
			</ul>
		</div>
	</div>
</div>
