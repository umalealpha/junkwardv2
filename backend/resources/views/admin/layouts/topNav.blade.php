 <!-- begin:: Wrapper -->
 <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor kt-wrapper" id="kt_wrapper">
     <!-- begin:: Header -->
     <div id="kt_header" class="kt-header kt-grid__item kt-header--fixed " >
         <!-- begin:: Header Menu -->
         <button class="kt-header-menu-wrapper-close" id="kt_header_menu_mobile_close_btn"><i class="la la-close"></i></button>
         <div class="kt-header-menu-wrapper" id="kt_header_menu_wrapper">
             <div id="kt_header_menu" class="kt-header-menu kt-header-menu-mobile kt-header-menu--layout- " >
                                <ul class="kt-menu__nav " style="display:flex;align-items:center;gap:8px;">
                                    <!-- Quick links -->
                                    <li class="kt-menu__item" aria-haspopup="true">
                                        <a href="{{ config('services.react_portal.url', 'http://localhost:3000') }}" target="_blank" class="kt-menu__link" style="background:linear-gradient(135deg,#1e3a5f,#2a4d7a);color:#fff;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                                            <i class="la la-external-link" style="color:#f97316;font-size:16px;"></i>
                                            <span>Staff Portal</span>
                                        </a>
                                    </li>
                                    <li class="kt-menu__item" aria-haspopup="true">
                                        <a href="{{ config('services.react_portal.url', 'http://localhost:3000') }}/ai" target="_blank" class="kt-menu__link" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                                            <i class="la la-magic" style="color:#fbbf24;font-size:16px;"></i>
                                            <span>AI Assistant</span>
                                        </a>
                                    </li>
                                    <li class="kt-menu__item kt-menu__item--submenu kt-menu__item--rel kt-menu__item--active" data-ktmenu-submenu-toggle="click" aria-haspopup="true" style="display:none;">
                                    <li class="kt-menu__item kt-menu__item--submenu kt-menu__item--rel" data-ktmenu-submenu-toggle="click" aria-haspopup="true" style="display:none;">
                                       {{--  <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><span class="kt-menu__link-text">Reports</span><i class="kt-menu__hor-arrow la la-angle-down"></i><i class="kt-menu__ver-arrow la la-angle-right"></i></a> --}}
                                        <div class="kt-menu__submenu kt-menu__submenu--fixed kt-menu__submenu--left" style="width:1000px">
                                            <div class="kt-menu__subnav">
                                                <ul class="kt-menu__content">
                                                    <li class="kt-menu__item ">
                                                        <h3 class="kt-menu__heading kt-menu__toggle">
                                                            <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                            <span class="kt-menu__link-text">Finance Reports</span><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                        </h3>
                                                        <ul class="kt-menu__inner">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-map"></i><span class="kt-menu__link-text">Annual Reports</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-user"></i><span class="kt-menu__link-text">HR Reports</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-clipboard"></i><span class="kt-menu__link-text">IPO Reports</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-graphic-1"></i><span class="kt-menu__link-text">Finance Margins</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-graphic-2"></i><span class="kt-menu__link-text">Revenue Reports</span></a>
                                                            </li>
                                                        </ul>
                                                    </li>
                                                    <li class="kt-menu__item ">
                                                        <h3 class="kt-menu__heading kt-menu__toggle">
                                                            <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                            <span class="kt-menu__link-text">Project Reports</span><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                        </h3>
                                                        <ul class="kt-menu__inner">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Coca Cola CRM</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Delta Airlines Booking Site</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Malibu Accounting</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Vineseed Website Rewamp</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Zircon Mobile App</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--line"><span></span></i>
                                                                    <span class="kt-menu__link-text">Mercury CMS</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </li>
                                                    <li class="kt-menu__item ">
                                                        <h3 class="kt-menu__heading kt-menu__toggle">
                                                            <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                            <span class="kt-menu__link-text">HR Reports</span><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                        </h3>
                                                        <ul class="kt-menu__inner">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Staff Directory</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Client Directory</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Salary Reports</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Staff Payslips</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Corporate Expenses</span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                                    <span class="kt-menu__link-text">Project Expenses</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </li>
                                                    <li class="kt-menu__item ">
                                                        <h3 class="kt-menu__heading kt-menu__toggle">
                                                            <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                            <span class="kt-menu__link-text">Reporting Apps</span><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                        </h3>
                                                        <ul class="kt-menu__inner">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Report Adjusments</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Sources & Mediums</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Reporting Settings</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Conversions</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Report Flows</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><span class="kt-menu__link-text">Audit & Logs</span></a>
                                                            </li>
                                                        </ul>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="kt-menu__item kt-menu__item--submenu kt-menu__item--rel" data-ktmenu-submenu-toggle="click" aria-haspopup="true">
                                        <!-- <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><span class="kt-menu__link-text">Apps</span><i class="kt-menu__hor-arrow la la-angle-down"></i><i class="kt-menu__ver-arrow la la-angle-right"></i></a> -->
                                        <div class="kt-menu__submenu kt-menu__submenu--classic kt-menu__submenu--left">
                                            <ul class="kt-menu__subnav">
                                                <li class="kt-menu__item " aria-haspopup="true">
                                                    <a href="javascript:;" class="kt-menu__link ">
                                                        <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                        <span class="kt-menu__link-text">eCommerce</span>
                                                    </a>
                                                </li>
                                                <li class="kt-menu__item kt-menu__item--submenu" data-ktmenu-submenu-toggle="hover" aria-haspopup="true">
                                                    <a href="components_datatable_v1.html" class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                        <span class="kt-menu__link-text">Audience</span><i class="kt-menu__hor-arrow la la-angle-right"></i><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                    </a>
                                                    <div class="kt-menu__submenu kt-menu__submenu--classic kt-menu__submenu--right">
                                                        <ul class="kt-menu__subnav">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-users"></i><span class="kt-menu__link-text">Active Users</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-interface-1"></i><span class="kt-menu__link-text">User Explorer</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-lifebuoy"></i><span class="kt-menu__link-text">Users Flows</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-graphic-1"></i><span class="kt-menu__link-text">Market Segments</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-graphic"></i><span class="kt-menu__link-text">User Reports</span></a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>
                                                <li class="kt-menu__item " aria-haspopup="true">
                                                    <a href="javascript:;" class="kt-menu__link ">
                                                        <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                        <span class="kt-menu__link-text">Marketing</span>
                                                    </a>
                                                </li>
                                                <li class="kt-menu__item " aria-haspopup="true">
                                                    <a href="javascript:;" class="kt-menu__link ">
                                                        <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                        <span class="kt-menu__link-text">Campaigns</span>
                                                        <span class="kt-menu__link-badge"><span class="kt-badge kt-badge--success">3</span></span>
                                                    </a>
                                                </li>
                                                <li class="kt-menu__item kt-menu__item--submenu" data-ktmenu-submenu-toggle="hover" aria-haspopup="true">
                                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle">
                                                        <i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
                                                        <span class="kt-menu__link-text">Cloud Manager</span><i class="kt-menu__hor-arrow la la-angle-right"></i><i class="kt-menu__ver-arrow la la-angle-right"></i>
                                                    </a>
                                                    <div class="kt-menu__submenu kt-menu__submenu--classic kt-menu__submenu--right">
                                                        <ul class="kt-menu__subnav">
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link ">
                                                                    <i class="kt-menu__link-icon flaticon-add"></i><span class="kt-menu__link-text">File Upload</span>
                                                                    <span class="kt-menu__link-badge"><span class="kt-badge kt-badge--danger">3</span></span>
                                                                </a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-signs-1"></i><span class="kt-menu__link-text">File Attributes</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-folder"></i><span class="kt-menu__link-text">Folders</span></a>
                                                            </li>
                                                            <li class="kt-menu__item " aria-haspopup="true">
                                                                <a href="#" class="kt-menu__link "><i class="kt-menu__link-icon flaticon-cogwheel-2"></i><span class="kt-menu__link-text">System Settings</span></a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <!-- end:: Header Menu -->
                        <!-- begin:: Header Topbar -->
                        <div class="kt-header__topbar">
                            <!--begin: Search -->
                            <!-- <div class="kt-header__topbar-item kt-header__topbar-item--search dropdown">
                                <div class="kt-header__topbar-wrapper" data-toggle="dropdown" data-offset="10px,0px">
                                    <span class="kt-header__topbar-icon" ><i class="flaticon2-search-1"></i></span>
                                </div>
                                <div class="dropdown-menu dropdown-menu-fit dropdown-menu-right dropdown-menu-top-unround dropdown-menu-anim dropdown-menu-lg">
                                    <div class="kt-quick-search kt-quick-search--dropdown kt-quick-search--result-compact" id="kt_quick_search_dropdown">
                                        <form method="get" class="kt-quick-search__form">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="flaticon2-search-1"></i></span>
                                                </div>
                                                <input type="text" class="form-control kt-quick-search__input" placeholder="Search...">
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i class="la la-close kt-quick-search__close"></i></span>
                                                </div>
                                            </div>
                                        </form>
                                        <div class="kt-quick-search__wrapper kt-scroll" data-scroll="true" data-height="325" data-mobile-height="200"></div>
                                    </div>
                                </div>
                            </div> -->
                            <!--end: Search -->
                            <!--begin: Notifications -->
{{--                            <div class="kt-header__topbar-item">--}}
{{--                                    <div class="kt-header__topbar-wrapper"  data-toggle="kt-tooltip" title="Add New Policy">--}}
{{--                                        <span class="kt-header__topbar-icon"> <a href="{{  URL::to('admin/policy/create') }}"><i class="flaticon2-add-1"></i></a></span>--}}
{{--                                    </div>--}}
{{--                                </div> --}}


                           <div class="kt-header__topbar-item dropdown">

                                <div class="kt-header__topbar-wrapper" data-toggle="dropdown" data-offset="30px, 0px" aria-expanded="true">
                                    <span class="kt-header__topbar-icon"> <i class="flaticon2-bell-alarm-symbol"></i> <span class="kt-badge kt-badge--dot kt-badge--notify kt-badge--sm kt-badge--brand"></span> </span>
                                </div>

                                <div class="dropdown-menu dropdown-menu-fit dropdown-menu-right dropdown-menu-anim dropdown-menu-top-unround dropdown-menu-lg">
                                    <form id="appendForm">
                                        <div class="kt-head" style="background-image: url({{asset('media/misc/head_bg_sm.jpg')}})">
                                            <h3 class="kt-head__title">
                                                User Notifications
                                            </h3>
                                            <div class="kt-head__sub "><span class="kt-head__desc notification notificationCount" ></span></div>
                                        </div>

                                        <div class="kt-notification kt-margin-t-30 kt-margin-b-20 kt-scroll allNotification" data-scroll="true" data-height="270" data-mobile-height="220">

                                        </div>

                                    </form>
                                </div>
                            </div>
                            <!--end: Notifications -->
                            <!--begin: Quick Actions -->

                           {{--   <div class="kt-header__topbar-item">
                                <div class="kt-header__topbar-wrapper" id="kt_offcanvas_toolbar_quick_actions_toggler_btn">
                                    <span class="kt-header__topbar-icon"><i class="flaticon2-gear"></i></span>
                                </div>
                            </div>  --}}
                            <!--end: Quick Actions -->
                            <!--begin:: Languages -->
                            <div class="kt-header__topbar-item kt-header__topbar-item--langs">
                                <div class="kt-header__topbar-wrapper" data-toggle="dropdown" data-offset="10px,0px">
                                    <span class="kt-header__topbar-icon">
                                        <img class="" src="{{asset('media/flags/botswana-flag.png')}}" style="height:20px"alt="" />
                                    </span>
                                </div>
                                <div class="dropdown-menu dropdown-menu-fit dropdown-menu-right dropdown-menu-anim dropdown-menu-top-unround">
                                    <ul class="kt-nav kt-margin-t-10 kt-margin-b-10">
                                        <li class="kt-nav__item kt-nav__item--active">
                                            <a href="#" class="kt-nav__link">
                                                <span class="kt-nav__link-icon">
                                                    <img src="{{asset('media/flags/botswana-flag.png')}}" style="height:20px" alt="" />
                                                </span>
                                                <span class="kt-nav__link-text">Botswana</span>
                                            </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link">
                                                <span class="kt-nav__link-icon">
                                                    <img src="{{asset('media/flags/south-african-flag.png')}}" style="height:20px" alt="" />
                                                </span>
                                                <span class="kt-nav__link-text">South Africa</span>
                                            </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link">
                                                <span class="kt-nav__link-icon">
                                                    <img src="{{asset('media/flags/zimbabwean-flag.png')}}" style="height:20px" alt="" />
                                                </span>
                                                <span class="kt-nav__link-text">Zimbabwe</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <!--end:: Languages -->
                            <!--begin: User Bar -->
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
                                      {{--   <li class="kt-nav__item">
                                            <a  class="kt-nav__link">
                                                <span class="kt-nav__link-icon"><i class="flaticon2-browser-2"></i></span>
                                                <span class="kt-nav__link-text">My Tasks</span>
                                            </a>
                                        </li> --}}
                                       {{--  <li class="kt-nav__item">
                                            <a class="kt-nav__link">
                                                <span class="kt-nav__link-icon"><i class="flaticon2-mail"></i></span>
                                                <span class="kt-nav__link-text">Messages</span>
                                            </a>
                                        </li> --}}
                                       {{--  <li class="kt-nav__item">
                                            <a class="kt-nav__link">
                                                <span class="kt-nav__link-icon"><i class="flaticon2-gear"></i></span>
                                                <span class="kt-nav__link-text">Settings</span>
                                            </a>
                                        </li> --}}
                                        <li class="kt-nav__item kt-nav__item--custom kt-margin-t-15"> <a href="logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();style.display='block';" class="btn btn-outline-metal btn-hover-brand btn-upper btn-font-dark btn-sm btn-bold">Sign Out</a> </li>

                                       <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                           {{ csrf_field() }}
                                       </form>
                                    </ul>
                                </div>
                            </div>
                            <!--end: User Bar -->
                            <!--begin:: Quick Panel Toggler -->
                            <!-- commented for temporary purpose, can be used further -->
                          <!--  <div class="kt-header__topbar-item kt-header__topbar-item--quick-panel" data-toggle="kt-tooltip" title="Quick panel" data-placement="right">
                               <span class="kt-header__topbar-icon" id="kt_quick_panel_toggler_btn"> <i class="flaticon2-grids"></i> </span>
                           </div> -->
                            <!--end:: Quick Panel Toggler -->
                        </div>
                        <!-- end:: Header Topbar -->
                    </div>
                    <!-- end:: Header -->
                    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
                        {{--Notification Modal--}}
                        <div class="kt-section__content kt-section__content--border">
                            <!-- Button trigger modal -->
                            {{--<button type="button" class="btn btn-outline-brand" data-toggle="modal" data-target="#exampleModalTooltips"> Launch tooltips and popovers demo modal </button>--}}
                            <!-- Modal -->
                            <div class="modal fade" id="exampleModalTooltips" tabindex="-1" role="dialog" aria-labelledby="exampleModalTooltips" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="activityTitle"></h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                                        </div>
                                        <div class="modal-body col-12">
                                            <h5 id="activityDescription" style="margin-bottom: 5%">

                                            </h5>
                                            <span>Performed By : </span>
                                            <p id="performedBy" style="display:inline;font-weight:bold;"></p><br>

                                            <span>Performed On :</span>
                                            <p id="performedOn" style="display:inline;font-weight:bold;"></p>
                                            <hr>
                                           <span>
                                                Date: <p style="display:inline" id="date"> </p>
                                            </span>
                                            <span style="margin-left:15%">
                                                Time:  <p style="display:inline" id="time"> </p>
                                            </span>

                                            <span style="margin-left:15%">
                                                 Updated :  <p style="display:inline" id="updatetime"> </p>
                                            </span>
                                        </div>
                                        <div class="modal-footer col-12">
                                            <div class="col-7">
                                                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @include('admin.layouts.notifications')
                        @include('admin.layouts.loader')

