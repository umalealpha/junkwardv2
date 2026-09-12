<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading">
    <!-- begin:: Header Mobile -->
    {{-- <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " > --}}
    {{-- <div class="kt-header-mobile__logo"> --}}
    {{-- <a> --}}
    {{-- <img alt="Logo" src="{{asset('images/logo.png')}}"/> --}}
    {{-- </a> --}}
    {{-- </div> --}}
    {{-- <div class="kt-header-mobile__toolbar"> --}}
    {{-- <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button> --}}
    {{-- <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button> --}}
    {{-- <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button> --}}
    {{-- </div> --}}
    {{-- </div> --}}
    <!-- end:: Header Mobile -->
    <!-- begin:: Root -->
    <div class="kt-grid kt-grid--hor kt-grid--root">
        <!-- begin:: Page -->
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
            <!-- begin:: Aside -->
            <button class="kt-aside-close " id="kt_aside_close_btn"><i class="la la-close"></i></button>
            <div class="kt-aside kt-aside--fixed kt-grid__item kt-grid kt-grid--desktop kt-grid--hor-desktop"
                id="kt_aside">
                <!-- begin::Aside Brand -->
                <div class="kt-aside__brand kt-grid__item " id="kt_aside_brand">
                    <div class="kt-aside__brand-logo">
                        <a>
                            <img alt="Logo" src="{{ asset('images/logo.png') }}" />
                        </a>
                    </div>
                    <div class="kt-aside__brand-tools">
                        <button class="kt-aside__brand-aside-toggler kt-aside__brand-aside-toggler--left"
                            id="kt_aside_toggler"><span></span></button>
                    </div>
                </div>
                <!-- end:: Aside Brand -->
                <!-- begin:: Aside Menu -->

                <div class="kt-aside-menu-wrapper kt-grid__item kt-grid__item--fluid" id="kt_aside_menu_wrapper">
                    <div id="kt_aside_menu" class="kt-aside-menu " data-ktmenu-vertical="1" data-ktmenu-scroll="1"
                        data-ktmenu-dropdown-timeout="500">

                        <ul class="kt-menu__nav ">

                            {{-- ============================================================ --}}
                            {{-- 1. OVERVIEW --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Overview</h4>
                            </li>

                            {{-- React Portal --}}
                            <li class="kt-menu__item" aria-haspopup="true">
                                <a href="{{ route('admin.sso.redirect') }}" target="_blank" class="kt-menu__link">
                                    <i class="kt-menu__link-icon fas fa-external-link-alt" style="color:#2563eb"></i>
                                    <span class="kt-menu__link-text">React Portal</span>
                                </a>
                            </li>

                            {{-- AI Assistant --}}
                            <li class="kt-menu__item" aria-haspopup="true">
                                <a href="{{ config('services.react_portal.url', 'http://localhost:3000') }}/ai-assistant" target="_blank" class="kt-menu__link">
                                    <i class="kt-menu__link-icon fa fa-robot" style="color:#7c3aed"></i>
                                    <span class="kt-menu__link-text">AI Assistant</span>
                                </a>
                            </li>

                            {{-- ============================================================ --}}
                            {{-- 2. SYSTEM CONFIG --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">System Config</h4>
                            </li>

                            {{-- Menu Settings --}}
                            @can('menu-settings')
                                <li class="kt-menu__item kt-menu__item--submenu">
                                    <a href="javascript:void(0);" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon-security"></i><span
                                            class="kt-menu__link-text">Menu Settings</span><i
                                            class="kt-menu__ver-arrow la la-angle-right"></i>
                                    </a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu">
                                                <a href="{{ url('admin/menumaster') }}"
                                                    class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-placeholder-1"></i><span
                                                        class="kt-menu__link-text">Menu Master listing</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/menu-master') ?: '' }}">
                                                <a href="{{ url('admin/menu-master') }}"
                                                    class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-placeholder-1"></i><span
                                                        class="kt-menu__link-text">Menu Master</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu ">
                                                <a href="#" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-placeholder-1"></i><span
                                                        class="kt-menu__link-text">Assign Url To User</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- ============================================================
                                 DEVELOPER TOOLS (consolidated)
                                 All developer/ops-facing pages live under a single menu so
                                 the top-level sidebar stays short. Previously API Error Log,
                                 API Docs, Credentials Vault, and AI Configuration were
                                 scattered across the sidebar — they all belong together.
                                 Gated to Super Admin / admin / developer roles.
                                 ============================================================ --}}
                            @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('developer'))
                                @php
                                    $devOpen = Request::is('admin/api-error-log*')
                                        || Request::is('admin/vault*')
                                        || Request::is('admin/activityLog*')
                                        || Request::is('dev/*');
                                @endphp
                                <li class="kt-menu__item kt-menu__item--submenu {!! $devOpen ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon fas fa-code" style="color:#0ea5e9"></i>
                                        <span class="kt-menu__link-text">Developer Tools</span>
                                        <i class="kt-menu__ver-arrow la la-angle-right"></i>
                                    </a>
                                    <div class="kt-menu__submenu">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            {{-- Children below match the Cron Jobs / Menu Settings
                                                 submenu pattern exactly: kt-menu__item--submenu on
                                                 the <li>, kt-menu__toggle on the <a>, plus the
                                                 Metronic data attributes so hover/active states
                                                 share the rest of the sidebar's CSS. Active state
                                                 uses kt-menu__item--open + kt-menu__item--here
                                                 (not kt-menu__item--active) so it picks up the
                                                 same blue highlight other submenu items use. --}}

                                            {{-- API Docs (Swagger UI) --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('dev/swagger*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ url('/dev/swagger') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-book" style="color:#0ea5e9"></i>
                                                    <span class="kt-menu__link-text">API Docs (Swagger)</span>
                                                </a>
                                            </li>

                                            {{-- API Endpoints list --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('dev/endpoints*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ url('/dev/endpoints') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-route" style="color:#0ea5e9"></i>
                                                    <span class="kt-menu__link-text">API Endpoints</span>
                                                </a>
                                            </li>

                                            {{-- API Error Log --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/api-error-log*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ route('admin.api-error-log.index') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-bug" style="color:#e74c3c"></i>
                                                    <span class="kt-menu__link-text">API Error Log</span>
                                                </a>
                                            </li>

                                            {{-- Application Logs — laravel.log tail --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('dev/logs*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ url('/dev/logs') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-file-alt" style="color:#e74c3c"></i>
                                                    <span class="kt-menu__link-text">Application Logs</span>
                                                </a>
                                            </li>

                                            {{-- Activity Log — internal admin page audit trail.
                                                 Route name is admin.activityLog.index (resource
                                                 route) — the inline ->name('activityLog') on the
                                                 prefix group is shadowed by the later
                                                 Route::resource at web.php:1457 so we use the
                                                 resource-generated name. --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/activityLog*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/activityLog') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-history" style="color:#8b5cf6"></i>
                                                    <span class="kt-menu__link-text">Activity Log</span>
                                                </a>
                                            </li>

                                            {{-- Credentials Vault --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/vault*') && !request()->has('category') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/vault') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fa fa-shield-alt" style="color:#7c3aed"></i>
                                                    <span class="kt-menu__link-text">Credentials Vault</span>
                                                </a>
                                            </li>

                                            {{-- AI Configuration (vault filtered to ai category) --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/vault*') && request()->query('category') === 'ai' ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/vault?category=ai') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fa fa-robot" style="color:#7c3aed"></i>
                                                    <span class="kt-menu__link-text">AI Configuration</span>
                                                </a>
                                            </li>

                                            {{-- OpenAPI raw JSON --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('dev/openapi*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ url('/dev/openapi') }}" class="kt-menu__link kt-menu__toggle" target="_blank">
                                                    <i class="kt-menu__link-icon fas fa-file-code" style="color:#0ea5e9"></i>
                                                    <span class="kt-menu__link-text">OpenAPI JSON</span>
                                                </a>
                                            </li>

                                            {{-- Dev Docs — markdown under backend/dev/ --}}
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('dev/docs*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ url('/dev/docs') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fas fa-book-open" style="color:#0ea5e9"></i>
                                                    <span class="kt-menu__link-text">Dev Docs</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endif

                            {{-- Cron Jobs Management --}}
                            @can('Cron_Mail_list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/cron') || Request::is('admin/cron*') || Request::is('admin/cronkernel') || Request::is('admin/cronkernel*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i>
                                        <span class="kt-menu__link-text">Cron Jobs</span>
                                        <i class="kt-menu__ver-arrow la la-angle-right"></i>
                                    </a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/cronkernel') || Request::is('admin/cronkernel*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/cronkernel') }}"
                                                    class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-network"></i><span
                                                        class="kt-menu__link-text">Cron Kernel</span>
                                                </a>
                                            </li>
                                            @if(auth()->user()->hasRole('Super Admin'))
                                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/cron/cancel') || Request::is('admin/cron/cancel*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('admin.cron.cancel') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon la la-ban"></i><span
                                                            class="kt-menu__link-text">Cancel Cron Job</span>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- SMS Control --}}
                            @can('sms_control_list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/smsControl') || Request::is('admin/smsControl*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/smsControl') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i><span
                                            class="kt-menu__link-text">SMS Control</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Activity Log --}}
                            @can('activity-list')
                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                    data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/activityLog') }}"
                                        class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon2-copy"></i><span class="kt-menu__link-text">Activity Log</span></a>
                                </li>
                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                    data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/whatsAppLog') }}"
                                        class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon2-copy"></i><span class="kt-menu__link-text">WhatsApp Log</span></a>
                                </li>
                            @endcan

                            {{-- Cron Emails --}}
                            @can('Cron_Mail_list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/cron') || Request::is('admin/cron*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/cron') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i><span
                                            class="kt-menu__link-text">Cron Emails</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- ============================================================ --}}
                            {{-- 3. MASTER DATA --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Master Data</h4>
                            </li>

                            {{-- Agencies --}}
                            @can('agency-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/agency') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/agency/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/agency') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-avatar"></i>
                                        <span class="kt-menu__link-text">Agencies</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Companies --}}
                            @if(auth()->user()->hasRole('Super Admin'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/companyname') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} "
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ route('admin.companyname.index') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-book"></i><span
                                            class="kt-menu__link-text">Companies</span>
                                    </a>
                                </li>
                            @endif

                            {{-- Branches --}}
                            @can('branch-list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/branch') || Request::is('admin/branch/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/branch') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-placeholder-2"></i><span
                                            class="kt-menu__link-text">Branches</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Suppliers --}}
                            @can('supplier-list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/supplier') || Request::is('admin/supplier/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/supplier') }}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-cube-1"></i><span class="kt-menu__link-text">Suppliers</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Vendors --}}
                            @can('vendor-list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/vendor') || Request::is('admin/vendor/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/vendor') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i><span
                                            class="kt-menu__link-text">Vendors</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Repair Centers --}}
                            @can('repair_centers-list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/repairCenters') || Request::is('admin/repairCenters/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/repairCenters') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i><span
                                            class="kt-menu__link-text">Repair Centers</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Staff Management --}}
                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('user-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('role-list'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/user') || request()->is('admin/user/*') || request()->is('admin/roles') || request()->is('admin/roles/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon-user-ok"></i><span
                                            class="kt-menu__link-text">Staff</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            @can('user-list')
                                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/user') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/user/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/user') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon flaticon2-avatar"></i><span
                                                            class="kt-menu__link-text">User</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('role-list')
                                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/roles') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/roles/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('admin.roles.index') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon fa fa-user-astronaut"></i><span
                                                            class="kt-menu__link-text">Roles</span>
                                                    </a>
                                                </li>
                                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/roles/') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/roles/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('admin.roles.roles') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon fa fa-user-astronaut"></i><span
                                                            class="kt-menu__link-text">Roles Under Role</span>
                                                    </a>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </li>
                            @endif

                            {{-- ============================================================ --}}
                            {{-- 4. PRODUCT CONFIG --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Product Config</h4>
                            </li>

                            {{-- Coverages --}}
                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('coverages-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('sub-coverages-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('specified-coverages-items-list'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/coverages') || request()->is('admin/coverages/*') || request()->is('admin/subCoverages') || request()->is('admin/subCoverages/*') || request()->is('admin/specifiedCoveragesItems') || request()->is('admin/specifiedCoveragesItems/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon-open-box"></i><span
                                            class="kt-menu__link-text">Coverages</span><i
                                            class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            @can('coverages-list')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/coverages') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon flaticon-interface-6"></i><span
                                                            class="kt-menu__link-text">Coverages</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('sub-coverages-list')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/subCoverages') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon flaticon-interface-6"></i><span
                                                            class="kt-menu__link-text">Sub Coverages</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('specified-coverages-items-list')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/specifiedCoveragesItems') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon flaticon-interface-6"></i><span
                                                            class="kt-menu__link-text">Specified Coverages Items</span>
                                                    </a>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </li>
                            @endif

                            {{-- Bundled Settings --}}
                            @can('bundled-setting')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/bundledsetting') || Request::is('admin/bundledsetting/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/bundledsetting') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-interface-1"></i><span
                                            class="kt-menu__link-text">Bundled Settings</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Premium Calculate --}}
                            <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                data-ktmenu-submenu-toggle="hover">
                                <a href="{{ route('premiumCalculate') }}"
                                    class="kt-menu__link kt-menu__toggle"><i
                                        class="kt-menu__link-icon flaticon2-copy"></i><span class="kt-menu__link-text">Premium Calculate</span></a>
                            </li>

                            {{-- ============================================================ --}}
                            {{-- 5. COMMISSION & INCENTIVES --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Commission & Incentives</h4>
                            </li>

                            {{-- Commission Config --}}
                            @can('commission-list')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/commission') || Request::is('admin/commission/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon fas fa-landmark"></i><span
                                            class="kt-menu__link-text">Commissions</span><i
                                            class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu  {!! Request::is('admin/commission') || Request::is('admin/commission/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/commission') }}"
                                                    class="kt-menu__link kt-menu__toggle"><i
                                                        class="kt-menu__link-icon la la-columns"></i><span
                                                        class="kt-menu__link-text">Commission</span></a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu  {!! Request::is('admin/commissionReports') || Request::is('admin/commissionReports/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/commissionReports/') }}"
                                                    class="kt-menu__link kt-menu__toggle"><i
                                                        class="kt-menu__link-icon la la-columns"></i><span
                                                        class="kt-menu__link-text">Commission Summary</span></a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu  {!! Request::is('admin/commissionReports/commissionReport') || Request::is('admin/commissionReports/commissionReport/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/commissionReports/commissionReport') }}"
                                                    class="kt-menu__link kt-menu__toggle"><i
                                                        class="kt-menu__link-icon la la-columns"></i><span
                                                        class="kt-menu__link-text">Commission Reports</span></a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- Cashback --}}
                            @can('inventory-Menus')
                                <li class="kt-menu__item kt-menu__item--submenu  {!! Request::is('cashback') || Request::is('cashback*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('cashback/customer') }}" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon2-drop"></i><span class="kt-menu__link-text">Cashback</span></a>
                                </li>
                            @endcan

                            {{-- Rewards --}}
                            @can('customer-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reward-tiers') || request()->is('admin/reward-tiers/*') || request()->is('admin/benefits') || request()->is('admin/benefits/*') || request()->is('admin/customer-rewards') || request()->is('admin/customer-rewards/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-trophy"></i>
                                        <span class="kt-menu__link-text">Rewards</span>
                                        <i class="kt-menu__ver-arrow la la-angle-right"></i>
                                    </a>
                                    <div class="kt-menu__submenu">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reward-tiers') || request()->is('admin/reward-tiers/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/reward-tiers') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-trophy"></i><span
                                                        class="kt-menu__link-text">Reward Tiers</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/benefits') || request()->is('admin/benefits/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/benefits') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-gift"></i><span
                                                        class="kt-menu__link-text">Benefits</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/customer-rewards') || request()->is('admin/customer-rewards/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/customer-rewards') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-star"></i><span
                                                        class="kt-menu__link-text">Customer Rewards</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- Incentives --}}
                            @can('inventory-Menus')
                                <li class="kt-menu__item kt-menu__item--submenu  {!! Request::is('incentive') || Request::is('incentive*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('incentive/agents') }}" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon2-drop"></i><span class="kt-menu__link-text">Incentives</span></a>
                                </li>
                            @endcan

                            {{-- Month Rate Discounts --}}
                            @can('customer-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/policy-discount-eligibility') || request()->is('admin/policy-discount-eligibility/*') || request()->is('admin/applied-discounts-list') || request()->is('admin/applied-discounts-list/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-percentage"></i>
                                        <span class="kt-menu__link-text">Month Rate Discounts</span>
                                        <i class="kt-menu__ver-arrow la la-angle-right"></i>
                                    </a>
                                    <div class="kt-menu__submenu">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/policy-discount-eligibility') || request()->is('admin/policy-discount-eligibility/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ route('admin.policy-discount-eligibility') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-percentage"></i><span
                                                        class="kt-menu__link-text">Policy Discount Eligibility</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/applied-discounts-list') || request()->is('admin/applied-discounts-list/*') ? 'kt-menu__item--open kt-menu__item--here' : '' }}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ route('admin.applied-discounts-list') }}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-check"></i><span
                                                        class="kt-menu__link-text">Applied Discounts List</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- ============================================================ --}}
                            {{-- 6. PAYMENT CONFIG --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Payment Config</h4>
                            </li>

                            {{-- RealPay Billing Config --}}
                            @if (\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('reconciliation-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('transaction-list') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('billing-SMS') || \Illuminate\Support\Facades\Auth::user()->hasPermissionTo('sms-logs'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllBanks') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllClients') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllContracts') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllTransactions') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon-coins"></i><span
                                            class="kt-menu__link-text">RealPay Billing</span><i
                                            class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            @can('billing-SMS')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('getSMSData') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon fas fa-comments-dollar"><span></span></i>
                                                        <span class="kt-menu__link-text">Import Data for SMS</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('billing-SMS')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('smsPage') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon fas fa-comments-dollar"><span></span></i>
                                                        <span class="kt-menu__link-text">Send Billing SMS</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('billing-Days')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/billing') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon fas fa-comments-dollar"><span></span></i>
                                                        <span class="kt-menu__link-text">Set Billing Days</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('sms-logs')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ route('genericServicePage') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon la flaticon2-tools-and-utensils"><span></span></i>
                                                        <span class="kt-menu__link-text">Generic SMS</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('sms-logs')
                                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true"
                                                    data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/smsLogs') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon la flaticon2-tools-and-utensils"><span></span></i>
                                                        <span class="kt-menu__link-text">SMS Logs</span>
                                                    </a>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </li>
                            @endif

                            {{-- Activation Codes --}}
                            @can('activation-code-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/activation') || request()->is('admin/activation/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i
                                            class="kt-menu__link-icon flaticon-security"></i><span
                                            class="kt-menu__link-text">Activation Codes</span><i
                                            class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/activation') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/activation') }}"
                                                    class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-placeholder-1"></i><span
                                                        class="kt-menu__link-text">Codes</span>
                                                </a>
                                            </li>
                                            @can('check-activation-Check Code')
                                                <li class="kt-menu__item kt-menu__item--submenu"
                                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                    <a href="{{ URL::to('admin/checkActivationCode') }}"
                                                        class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-icon flaticon-placeholder-1"></i><span
                                                            class="kt-menu__link-text">Check Activation Code</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            <li class="kt-menu__item kt-menu__item--submenu"
                                                aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{ URL::to('admin/activation/activatedCodeList') }}"
                                                    class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon fa fa-book-reader"></i><span
                                                        class="kt-menu__link-text">Activated Code List</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endcan

                            {{-- ============================================================ --}}
                            {{-- 7. AGENT CONFIG --}}
                            {{-- ============================================================ --}}
                            <li class="kt-menu__section">
                                <h4 class="kt-menu__section-text">Agent Config</h4>
                            </li>

                            {{-- Agent Pin Setting --}}
                            @can('bundled-setting')
                                <li class="kt-menu__item kt-menu__item--submenu {!! Request::is('admin/agentPinSetting') || Request::is('admin/agentPinSetting/*') ? 'kt-menu__item--open kt-menu__item--here' : '' !!}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/agentPinSetting') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-interface-1"></i><span
                                            class="kt-menu__link-text">Agent Pin Setting</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Agents Pin List --}}
                            @can('agent-pin-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/user/PinIndex') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/user/PinIndex*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/user/PinIndex') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-avatar"></i><span
                                            class="kt-menu__link-text">Agents Pin</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- KYC Fields Config --}}
                            @can('kyc-fields')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/KycCompliance') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/KycCompliance/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/KycFields') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-avatar"></i><span
                                            class="kt-menu__link-text">KYC Fields Config</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Reinsurance Formula --}}
                            @can('reinsurance-formula-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reinsuranceFormula') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/reinsuranceFormula/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/reinsuranceFormula') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-interface-6"></i><span
                                            class="kt-menu__link-text">Reinsurance Formula</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Reinsurance Group Coverage --}}
                            @can('reinsurance-coverage-group-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reinsuranceGroupCoverage') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/reinsuranceGroupCoverage/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/reinsuranceGroupCoverage') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-infographic"></i><span
                                            class="kt-menu__link-text">Reinsurance Group Coverage</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Reinsurance Type --}}
                            @can('reinsurance-type-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reinsuranceType') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/reinsuranceType/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/reinsuranceType') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-copy"></i><span
                                            class="kt-menu__link-text">Reinsurance Type</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Reinsurance Treaty --}}
                            @can('reinsurance-treaty-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/reinsuranceTreaty') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/reinsuranceTreaty/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/reinsuranceTreaty') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-users"></i><span
                                            class="kt-menu__link-text">Reinsurance Treaty</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Products --}}
                            @can('product-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/product') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/product/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/product') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-dropbox"></i><span
                                            class="kt-menu__link-text">Products</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Product Plan --}}
                            @can('product-plan-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/productPlan') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/productPlan/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/productPlan') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-list-alt"></i><span
                                            class="kt-menu__link-text">Product Plan</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Product Type --}}
                            @can('product-type-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/productType') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/productType/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/productType') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-files-o"></i><span
                                            class="kt-menu__link-text">Product Type</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Companies --}}
                            @if(auth()->user()->hasRole('Super Admin'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/companyname') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/companyname/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/companyname') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-book"></i><span
                                            class="kt-menu__link-text">Companies</span>
                                    </a>
                                </li>
                            @endif

                            {{-- Regions --}}
                            @can('product-region-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/region') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/region/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/region') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-columns"></i><span
                                            class="kt-menu__link-text">Regions</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Departments --}}
                            @can('department-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/department') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/department/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/department') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-indent"></i><span
                                            class="kt-menu__link-text">Departments</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Stores --}}
                            @can('store-list')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/store') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/store/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/store') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-network"></i><span
                                            class="kt-menu__link-text">Stores</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- VCS Event Log --}}
                            @can('vcs-event-log-view')
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/vcs-event-log') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/vcs-event-log/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/vcs-event-log') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-copy"></i><span
                                            class="kt-menu__link-text">VCS Event Log</span>
                                    </a>
                                </li>
                            @endcan

                            {{-- Cancel Cron Job --}}
                            @if(auth()->user()->hasRole('Super Admin'))
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/cron/cancel') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/cron/cancel/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}"
                                    aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{ URL::to('admin/cron/cancel') }}"
                                        class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon la la-ban"></i><span
                                            class="kt-menu__link-text">Cancel Cron Job</span>
                                    </a>
                                </li>
                            @endif

                        </ul>
                    </div>
                </div>
                <!-- end:: Aside Menu -->
                <!-- begin:: Aside Footer -->
                @can('footer-tool-list')
                    <div class="kt-aside__footer kt-grid__item" id="kt_aside_footer">
                        <div class="kt-aside__footer-nav">
                        </div>
                    </div>
                @endcan
                <!-- end:: Aside Footer-->
            </div>

            <!-- end:: Aside -->
