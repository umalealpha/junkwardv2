<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
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
                                <li class="kt-menu__item kt-menu__item--submenu  {{ request()->is('admin/dashboard') ?  : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('admin-dashboard')}}" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-graphic"></i><span class="kt-menu__link-text">Dashboards</span></a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu  {{ request()->is('admin/policyList') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('admin-policyList')}}" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-drop"></i><span class="kt-menu__link-text" title="View, edit or create Policies">Policies</span></a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/Claims') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('admin-allClaims')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-interface-3"></i>
                                        <span class="kt-menu__link-text" title="View or create Claims">Claims</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/addSupplier') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('addSupplier')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-cube-1"></i><span class="kt-menu__link-text" title="View or Add Suppliers">Suppliers</span>
                                    </a>
                                </li>


                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/addCompany') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('addCompany')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon-home"></i><span class="kt-menu__link-text" title="View or Add Companies">Companies</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/PolicyManagement') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                <a href="{{Route('admin-policyManagement')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-contract"></i> <span class="kt-menu__link-text" title="View or Manage Policy Plans">Policy Plan Management</span>
                                    </a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/addTreaty') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('addTreatyView')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-copy"></i><span class="kt-menu__link-text" title="View Reinsurance Treaties">Reinsurance Treaties</span>
                                    </a>
                                </li>


								<li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/addStaff') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-browser-2"></i><span class="kt-menu__link-text" title="View or add Staff Member">Staff Management</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>

                                        <ul class="kt-menu__subnav">


                                            <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('addStaff')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-user-add"><span></span></i>
                                                    <span class="kt-menu__link-text" >Add Users</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewCustomers') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/viewCustomer/*') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="{{Route('customersView')}}" class="kt-menu__link kt-menu__toggle">
                                        <i class="kt-menu__link-icon flaticon2-avatar"></i><span class="kt-menu__link-text" title="View Customers">Customers</span>
                                    </a>
                                </li>
                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-pie-chart-4"></i><span class="kt-menu__link-text" title="View Reports">Reports</span></a>
                                </li>
                                <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-copy"></i><span class="kt-menu__link-text" title="View Activity Log">Activity Log</span></a>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewEmailBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/viewSMSBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon2-laptop"></i><span class="kt-menu__link-text" title="View or Edit RealPay Billing of Customers">Administration</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewEmailBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('EmailBroadView')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-new-email"><span></span></i>
                                                    <span class="kt-menu__link-text">Email Broadcasting</span>
                                                </a>
                                            </li>
                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewSMSBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('SMSBroadView')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon2-phone"><span></span></i>
                                                    <span class="kt-menu__link-text">SMS Broadcasting</span>
                                                </a>
                                            </li>

                                            <li class="kt-menu__item kt-menu__item--submenu" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon-settings"></i><span class="kt-menu__link-text" title="View or Edit RealPay Billing of Customers">Config</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                                <div class="kt-menu__submenu ">
                                                    <span class="kt-menu__arrow"></span>
                                                    <ul class="kt-menu__subnav">
                                                        <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewEmailBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                            <a href="{{Route('EmailBroadView')}}" class="kt-menu__link kt-menu__toggle">
                                                                <i class="kt-menu__link-icon flaticon-car"><span></span></i>
                                                                <span class="kt-menu__link-text">UBI Settings</span>
                                                            </a>
                                                        </li>
                                                        <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/viewSMSBroad') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                            <a href="{{Route('SMSBroadView')}}" class="kt-menu__link kt-menu__toggle">
                                                                <i class="kt-menu__link-icon flaticon-clipboard"><span></span></i>
                                                                <span class="kt-menu__link-text">Life Settings</span>
                                                            </a>
                                                        </li>
                                                    </ul>

                                            </li>
                                        </ul>
                                    </div>
                                </li>

                                <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllBanks') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllClients') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllContracts') ? 'kt-menu__item--open  kt-menu__item--here' : '' }} {{ request()->is('admin/AllTransactions') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                    <a href="javascript:;" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-icon flaticon-coins"></i><span class="kt-menu__link-text" title="View or Edit RealPay Billing of Customers">Billing</span><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
                                    <div class="kt-menu__submenu ">
                                        <span class="kt-menu__arrow"></span>
                                        <ul class="kt-menu__subnav">


                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllBanks') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('AllBanks')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon flaticon-piggy-bank"><span></span></i>
                                                    <span class="kt-menu__link-text">View Banks</span>
                                                </a>
                                            </li>


                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllClients') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('AllClients')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon la la-users"><span></span></i>
                                                    <span class="kt-menu__link-text">View Clients</span>
                                                </a>
                                            </li>

                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllContracts') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('AllContracts')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon la flaticon-file-2"><span></span></i>
                                                    <span class="kt-menu__link-text">Contracts</span>
                                                </a>
                                            </li>

                                            <li class="kt-menu__item kt-menu__item--submenu {{ request()->is('admin/AllTransactions') ? 'kt-menu__item--open  kt-menu__item--here' : '' }}" aria-haspopup="true" data-ktmenu-submenu-toggle="hover">
                                                <a href="{{Route('AllTransactions')}}" class="kt-menu__link kt-menu__toggle">
                                                    <i class="kt-menu__link-icon la flaticon2-open-text-book"><span></span></i>
                                                    <span class="kt-menu__link-text">Transactions</span>
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