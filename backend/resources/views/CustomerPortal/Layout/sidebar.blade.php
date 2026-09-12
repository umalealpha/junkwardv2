
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a href="index.html">
            <img alt="Logo" src="assets/media/logos/logo-3.png"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span>
            </span>
        </button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
     <!-- begin:: Aside -->
                <button class="kt-aside-close " id="kt_aside_close_btn"><i class="la la-close"></i></button>
                <div class="kt-aside kt-aside--fixed kt-grid__item kt-grid kt-grid--desktop kt-grid--hor-desktop" id="kt_aside">
                    <!-- begin::Aside Brand -->
                    <div class="kt-aside__brand kt-grid__item " id="kt_aside_brand">
                        <div class="kt-aside__brand-logo">
                            <a>
                                <img alt="Logo" src="{{asset('images/logo.png')}}"/>
                            </a>
                        </div>
                        <div class="kt-aside__brand-tools">
                            <button class="kt-aside__brand-aside-toggler kt-aside__brand-aside-toggler--left" id="kt_aside_toggler"><span></span></button>
                        </div>
                    </div>
                    <!-- end:: Aside Brand -->
                    <!-- begin:: Aside Menu -->
                    <div class="kt-aside-menu-wrapper kt-grid__item kt-grid__item--fluid" id="kt_aside_menu_wrapper">
                        <div id="kt_aside_menu" class="kt-aside-menu " data-ktmenu-vertical="1" data-ktmenu-scroll="1" data-ktmenu-dropdown-timeout="500" >
                            <ul class="kt-menu__nav ">
                            
                                <li class="kt-menu__item kt-menu__item--submenu  {{ request()->is('customers/dashboard') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('MyDashboard')}}" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon-home-2"></i><span class="kt-menu__link-text">My Dashboard</span></a>
                                </li>
                                
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('customers/portalpolices') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('customers/policyDetail/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('MyPolicies')}}" class="kt-menu__link kt-menu__toggle ">
                                                    <i class="kt-menu__link-icon flaticon-file-2"></i>
                                        <span class="kt-menu__link-text">My Policies</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu  {{ request()->is('customers/portalclaims') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('agents/processClaim/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('MyClaims')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-drop"></i>
                                        <span class="kt-menu__link-text">My Claims</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu  {{ request()->is('customers/portalProfile') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('agents/processClaim/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                <a href="{{Route('MyProfile')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-user-settings"></i>
                                        <span class="kt-menu__link-text">My Profile</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('customers/MyInstallments') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('customers/MyTransactions') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon-cogwheel"></i><span class="kt-menu__link-text" title="Manage Your Account">Manage Account</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">

                                            <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('MyInstallments')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-piggy-bank"><span></span></i>
                                                    <span class="kt-menu__link-text">View Installemts</span>
                                                </a>
                                            </li>
                                            
                                            <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('MyTransactions')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-graphic"><span></span></i>
                                                    <span class="kt-menu__link-text">View Transactions</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>

                            
                               
                            </ul>
                        </div>
                    </div>
                    <!-- end:: Aside Menu -->
                    <!-- begin:: Aside Footer -->
                    <div class="kt-aside__footer kt-grid__item" id="kt_aside_footer">
                        <div class="kt-aside__footer-nav">
                            <div class="kt-aside__footer-item">
                                <a href="#" class="btn btn-icon"><i class="flaticon2-gear"></i></a>
                            </div>
                            <div class="kt-aside__footer-item">
                                <a href="#" class="btn btn-icon"><i class="flaticon2-cube"></i></a>
                            </div>
                            <div class="kt-aside__footer-item">
                                <a href="#" class="btn btn-icon"><i class="flaticon2-bell-alarm-symbol"></i></a>
                            </div>
                            <div class="kt-aside__footer-item">
                                <button type="button" class="btn btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon2-add"></i> </button>
                                <div class="dropdown-menu dropdown-menu-left">
                                    <ul class="kt-nav">
                                        <li class="kt-nav__section kt-nav__section--first"> <span class="kt-nav__section-text">Export Tools</span> </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-print"></i> <span class="kt-nav__link-text">Print</span> </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-copy"></i> <span class="kt-nav__link-text">Copy</span> </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-file-excel-o"></i> <span class="kt-nav__link-text">Excel</span> </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-file-text-o"></i> <span class="kt-nav__link-text">CSV</span> </a>
                                        </li>
                                        <li class="kt-nav__item">
                                            <a href="#" class="kt-nav__link"> <i class="kt-nav__link-icon la la-file-pdf-o"></i> <span class="kt-nav__link-text">PDF</span> </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="kt-aside__footer-item">
                                <a href="#" class="btn btn-icon"><i class="flaticon2-calendar-2"></i></a>
                            </div>
                        </div>
                    </div>
                    <!-- end:: Aside Footer-->
                </div>
                <!-- end:: Aside -->