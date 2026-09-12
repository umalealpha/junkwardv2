
<!--begin::Menu-->
<div class="menu menu-column menu-rounded menu-sub-indention fw-semibold px-3" id="#kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">
	<!--begin:Menu item-->
	<div class="menu-item pt-5">
		<!--begin:Menu content-->
		<div class="menu-content">
			<span class="menu-heading fw-bold text-uppercase fs-7">App</span>
		</div>
		<!--end:Menu content-->
	</div>
	<!--end:Menu item-->
    <!--begin:Menu item-->
    <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='admin-dashboard' ? 'active' : '' }}" href="{{route('admin-dashboard')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Dashboard</span>
        </a>
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->

	<!--begin:Menu item-->
    <div class="menu-item">
    @if( auth()->user()->hasRole('Manager') || auth()->user()->hasRole('Super Admin'))
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='dashboard' ? 'active' : '' }}" href="{{route('dashboard')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Dashboard V2</span>
        </a>
    @endif
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->

     <!--begin:Menu item-->
     <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='admin/policy' ? 'active' : '' }}" href="{{ URL::to('admin/policy') }}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Policy</span>
        </a>
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->

    <!--begin:Menu item-->
    <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='policy' ? 'active' : '' }}" href="{{route('policy')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Dom Com Policy</span>
        </a>
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->

    <!--begin:Menu item-->
    <div class="menu-item">
        <!--begin:Menu link-->
        @can('product-list')
        <a class="menu-link" href="{{ URL::to('admin/product') }}">
			<span class="menu-icon">
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Products</span>
        </a>
        @endcan
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->

    <div class="menu-item">
        <a class="menu-link {{\Request::route()->getName()=='companies' ? 'active' : '' }}" href="{{route('companies')}}">
			<span class="menu-icon">
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
			</span>
            <span class="menu-title">Companies</span>
        </a>
    </div>


    <div class="menu-item">
        <a class="menu-link {{\Request::route()->getName()=='customerKyc' ? 'active' : '' }}" href="{{route('customerKyc')}}">
			<span class="menu-icon">
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
			</span>
            <span class="menu-title">Customer Kyc</span>
        </a>
    </div>
    <!--begin:Menu item-->
    <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='validationrule' ? 'active' : '' }}" href="{{route('validationrule')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Policy Validation Rule</span>
        </a>
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->


    <!--begin:Menu item-->
    <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='validationrulegroup' ? 'active' : '' }}" href="{{route('validationrulegroup')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Policy Validation Group</span>
        </a>
        <!--end:Menu link-->
    </div>

    <!--end:Menu item-->
    {{-- reinsurance --}}
    @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('reinsurance-treaty-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('reinsurance-formula-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('reinsurance-type-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('reinsurance-coverage-group-list'))
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item here {{(\Request::route()->getName()=='reinsurance-type')||(\Request::route()->getName()=='reinsurance-group-coverage')||(\Request::route()->getName()=='reinsuranceFormula')||(\Request::route()->getName()=='reinsuranceTreaty') ? 'show' : '' }} menu-accordion">
        <!--begin:Menu link-->
        <span class="menu-link">
            <span class="menu-icon">
                <!--begin::Svg Icon | path: icons/duotune/general/gen022.svg-->
                <span class="svg-icon svg-icon-2">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.2929 2.70711C11.6834 2.31658 12.3166 2.31658 12.7071 2.70711L15.2929 5.29289C15.6834 5.68342 15.6834 6.31658 15.2929 6.70711L12.7071 9.29289C12.3166 9.68342 11.6834 9.68342 11.2929 9.29289L8.70711 6.70711C8.31658 6.31658 8.31658 5.68342 8.70711 5.29289L11.2929 2.70711Z" fill="currentColor" />
                        <path d="M11.2929 14.7071C11.6834 14.3166 12.3166 14.3166 12.7071 14.7071L15.2929 17.2929C15.6834 17.6834 15.6834 18.3166 15.2929 18.7071L12.7071 21.2929C12.3166 21.6834 11.6834 21.6834 11.2929 21.2929L8.70711 18.7071C8.31658 18.3166 8.31658 17.6834 8.70711 17.2929L11.2929 14.7071Z" fill="currentColor" />
                        <path opacity="0.3" d="M5.29289 8.70711C5.68342 8.31658 6.31658 8.31658 6.70711 8.70711L9.29289 11.2929C9.68342 11.6834 9.68342 12.3166 9.29289 12.7071L6.70711 15.2929C6.31658 15.6834 5.68342 15.6834 5.29289 15.2929L2.70711 12.7071C2.31658 12.3166 2.31658 11.6834 2.70711 11.2929L5.29289 8.70711Z" fill="currentColor" />
                        <path opacity="0.3" d="M17.2929 8.70711C17.6834 8.31658 18.3166 8.31658 18.7071 8.70711L21.2929 11.2929C21.6834 11.6834 21.6834 12.3166 21.2929 12.7071L18.7071 15.2929C18.3166 15.6834 17.6834 15.6834 17.2929 15.2929L14.7071 12.7071C14.3166 12.3166 14.3166 11.6834 14.7071 11.2929L17.2929 8.70711Z" fill="currentColor" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </span>
            <span class="menu-title">Reinsurance</span>
            <span class="menu-arrow"></span>
        </span>
        <!--end:Menu link-->
        <!--begin:Menu sub-->
        <div class="menu-sub menu-sub-accordion">
            <!--begin:Menu item-->
            @can('reinsurance-type-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='reinsurance-type' ? 'active' : '' }}" href="{{route('reinsurance-type')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title" title="View Reinsurance Type">Reinsurance Type</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
            <!--end:Menu item-->
            <!--begin:Menu item-->
            @can('reinsurance-coverage-group-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='reinsurance-group-coverage' ? 'active' : '' }}" href="{{route('reinsurance-group-coverage')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title" title="View Reinsurance Coverage Grouping">Reinsurance Coverage Grouping</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
            <!--end:Menu item-->
            <!--begin:Menu item-->
            @can('reinsurance-formula-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='reinsuranceFormula' ? 'active' : '' }}" href="{{route('reinsuranceFormula')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title" title="View Reinsurance Formula">Reinsurance Formula</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
             <!--end:Menu item-->
            <!--begin:Menu item-->
            @can('reinsurance-treaty-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='reinsuranceTreaty' ? 'active' : '' }}" href="{{route('reinsuranceTreaty')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title" title="View Reinsurance Treaty">Reinsurance Treaty</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
        </div>
        <!--end:Menu sub-->
    </div>
    <!--end:Menu item-->
    @endif

     <!--begin:Menu item-->
     <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='reinsurer' ? 'active' : '' }}" href="{{route('reinsurer')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/abstract/abs014.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
						<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Reinsurer</span>
        </a>
        <!--end:Menu link-->
    </div>

    {{-- coverages --}}
    @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('coverages-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('sub-coverages-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('specified-coverages-items-list'))
    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item here {{(\Request::route()->getName()=='coverage')||(\Request::route()->getName()=='subcoverage')||(\Request::route()->getName()=='extentions')||(\Request::route()->getName()=='specifiedcoverage') ? 'show' : '' }} menu-accordion">
        <!--begin:Menu link-->
        <span class="menu-link">
            <span class="menu-icon">
                <!--begin::Svg Icon | path: icons/duotune/general/gen022.svg-->
                <span class="svg-icon svg-icon-2">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.2929 2.70711C11.6834 2.31658 12.3166 2.31658 12.7071 2.70711L15.2929 5.29289C15.6834 5.68342 15.6834 6.31658 15.2929 6.70711L12.7071 9.29289C12.3166 9.68342 11.6834 9.68342 11.2929 9.29289L8.70711 6.70711C8.31658 6.31658 8.31658 5.68342 8.70711 5.29289L11.2929 2.70711Z" fill="currentColor" />
                        <path d="M11.2929 14.7071C11.6834 14.3166 12.3166 14.3166 12.7071 14.7071L15.2929 17.2929C15.6834 17.6834 15.6834 18.3166 15.2929 18.7071L12.7071 21.2929C12.3166 21.6834 11.6834 21.6834 11.2929 21.2929L8.70711 18.7071C8.31658 18.3166 8.31658 17.6834 8.70711 17.2929L11.2929 14.7071Z" fill="currentColor" />
                        <path opacity="0.3" d="M5.29289 8.70711C5.68342 8.31658 6.31658 8.31658 6.70711 8.70711L9.29289 11.2929C9.68342 11.6834 9.68342 12.3166 9.29289 12.7071L6.70711 15.2929C6.31658 15.6834 5.68342 15.6834 5.29289 15.2929L2.70711 12.7071C2.31658 12.3166 2.31658 11.6834 2.70711 11.2929L5.29289 8.70711Z" fill="currentColor" />
                        <path opacity="0.3" d="M17.2929 8.70711C17.6834 8.31658 18.3166 8.31658 18.7071 8.70711L21.2929 11.2929C21.6834 11.6834 21.6834 12.3166 21.2929 12.7071L18.7071 15.2929C18.3166 15.6834 17.6834 15.6834 17.2929 15.2929L14.7071 12.7071C14.3166 12.3166 14.3166 11.6834 14.7071 11.2929L17.2929 8.70711Z" fill="currentColor" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </span>
            <span class="menu-title">Coverages</span>
            <span class="menu-arrow"></span>
        </span>
        <!--end:Menu link-->
        <!--begin:Menu sub-->
        <div class="menu-sub menu-sub-accordion">
            <!--begin:Menu item-->
            @can('coverages-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='coverage' ? 'active' : '' }}" href="{{route('coverage')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Coverages</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
            <!--end:Menu item-->
            @can('sub-coverages-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='subcoverage' ? 'active' : '' }}" href="{{route('subcoverage')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Sub Coverages</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
            <!--end:Menu item-->

             {{-- @can('sub-coverages-list') --}}
             <div class="menu-item">
                 <!--begin:Menu link-->
                 <a class="menu-link {{\Request::route()->getName()=='extentions' ? 'active' : '' }}" href="{{route('extentions')}}">
                     <span class="menu-bullet">
                         <span class="bullet bullet-dot"></span>
                     </span>
                     <span class="menu-title">Ext,Excess & Misc</span>
                 </a>
                 <!--end:Menu link-->
             </div>
             {{-- @endcan --}}
             <!--end:Menu item-->

            <!--begin:Menu item-->
            @can('specified-coverages-items-list')
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='specifiedcoverage' ? 'active' : '' }}" href="{{route('specifiedcoverage')}}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Specified Coverages Items</span>
                </a>
                <!--end:Menu link-->
            </div>
            @endcan
        </div>
        <!--end:Menu sub-->
    </div>
    <!--end:Menu item-->
    @endif

    <!--begin:Menu item-->
    <div data-kt-menu-trigger="click" class="menu-item here {{(\Request::route()->getName()=='reward-tiers')||(\Request::route()->getName()=='benefits')||(\Request::route()->getName()=='customer-rewards') ? 'show' : '' }} menu-accordion">
        <!--begin:Menu link-->
        <span class="menu-link">
            <span class="menu-icon">
                <!--begin::Svg Icon | path: icons/duotune/general/gen022.svg-->
                <span class="svg-icon svg-icon-2">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.2929 2.70711C11.6834 2.31658 12.3166 2.31658 12.7071 2.70711L15.2929 5.29289C15.6834 5.68342 15.6834 6.31658 15.2929 6.70711L12.7071 9.29289C12.3166 9.68342 11.6834 9.68342 11.2929 9.29289L8.70711 6.70711C8.31658 6.31658 8.31658 5.68342 8.70711 5.29289L11.2929 2.70711Z" fill="currentColor" />
                        <path d="M11.2929 14.7071C11.6834 14.3166 12.3166 14.3166 12.7071 14.7071L15.2929 17.2929C15.6834 17.6834 15.6834 18.3166 15.2929 18.7071L12.7071 21.2929C12.3166 21.6834 11.6834 21.6834 11.2929 21.2929L8.70711 18.7071C8.31658 18.3166 8.31658 17.6834 8.70711 17.2929L11.2929 14.7071Z" fill="currentColor" />
                        <path opacity="0.3" d="M5.29289 8.70711C5.68342 8.31658 6.31658 8.31658 6.70711 8.70711L9.29289 11.2929C9.68342 11.6834 9.68342 12.3166 9.29289 12.7071L6.70711 15.2929C6.31658 15.6834 5.68342 15.6834 5.29289 15.2929L2.70711 12.7071C2.31658 12.3166 2.31658 11.6834 2.70711 11.2929L5.29289 8.70711Z" fill="currentColor" />
                        <path opacity="0.3" d="M17.2929 8.70711C17.6834 8.31658 18.3166 8.31658 18.7071 8.70711L21.2929 11.2929C21.6834 11.6834 21.6834 12.3166 21.2929 12.7071L18.7071 15.2929C18.3166 15.6834 17.6834 15.6834 17.2929 15.2929L14.7071 12.7071C14.3166 12.3166 14.3166 11.6834 14.7071 11.2929L17.2929 8.70711Z" fill="currentColor" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </span>
            <span class="menu-title">Customer</span>
            <span class="menu-arrow"></span>
        </span>
        <!--end:Menu link-->
        <!--begin:Menu sub-->
        <div class="menu-sub menu-sub-accordion">
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='admin.reward-tiers' ? 'active' : '' }}" href="{{ URL::to('admin/reward-tiers') }}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Reward Tiers</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='admin.benefits' ? 'active' : '' }}" href="{{ URL::to('admin/benefits') }}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Benefits</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link {{\Request::route()->getName()=='admin.customer-rewards' ? 'active' : '' }}" href="{{ URL::to('admin/customer-rewards') }}">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Customer Rewards</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
        </div>
        <!--end:Menu sub-->
    </div>
    <!--end:Menu item-->

    <!--begin:Menu item - Cancel Cron (Super Admin only)-->
    @if(auth()->user()->hasRole('Super Admin'))
    <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link {{\Request::route()->getName()=='admin.cron.cancel' ? 'active' : '' }}" href="{{route('admin.cron.cancel')}}">
			<span class="menu-icon">
				<!--begin::Svg Icon | path: icons/duotune/general/gen019.svg-->
				<span class="svg-icon svg-icon-2">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M14 18V16H10V18L9 20H15L14 18Z" fill="currentColor"/>
						<path opacity="0.3" d="M20 4H17V2H7V4H4C3.4 4 3 4.4 3 5V9C3 9.6 3.4 10 4 10H20C20.6 10 21 9.6 21 9V5C21 4.4 20.6 4 20 4ZM5 8V6H19V8H5Z" fill="currentColor"/>
					</svg>
				</span>
                <!--end::Svg Icon-->
			</span>
            <span class="menu-title">Cancel Cron Job</span>
        </a>
        <!--end:Menu link-->
    </div>
    <!--end:Menu item-->
    @endif

</div>
<!--end::Menu-->
