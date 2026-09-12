<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->

         @include('Admin.Layout.header')
         <link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">


    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">


                @include('Admin.Layout.sidebar')

                @include('Admin.Layout.topNav')



                        <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                        <div class="loading">Loading&#8230;</div>
                            <!--begin::Dashboard 4-->
                            <!--begin::Row-->
                            <div class="row">
								<div class="col-lg-12 col-xl-12 order-lg-1 order-xl-1">
                                       <!--begin::Portlet-->

                                     <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Search Installments
                                                </h3>
                                            </div>
                                        </div>

                                        <div class="kt-portlet__body">


                                           <div class="col-lg-12 newsearch1">
                                             <form class="navbar-form navbar-search" role="search">
                                                                     <div class="input-group">
                                                                     <button type="button" class="btn btn-outline-hover-secondary" disabled="disabled"><i class="fa fa-search"></i></button>
                                                                         <input type="text" class="form-control" name="query" id="query" placeholder="Search Installments">
                                                                         <div class="input-group-btn">
                                                                             <div class="col">
                                                                                 <!-- <div class="dropdown">
                                                                                     <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true">
                                                                                         Search Installments by
                                                                                     </button>
                                                                                     <div class="dropdown-menu" aria-labelledby="dropdownMenuButton" x-placement="bottom-start" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 38px, 0px);">
                                                                                         <a class="dropdown-item" href="#" data-toggle="kt-tooltip" title="" data-placement="right" data-skin="dark" data-container="body" data-original-title="Tooltip title">Client Name</a>
                                                                                         <a class="dropdown-item" href="#">Client ID</a>
                                                                                         <a class="dropdown-item" href="#" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Tooltip title">Location</a>
                                                                                     </div>
                                                                                 </div> -->
                                                                             </div>
                                                                         </div>
                                                                         <div class="input-group-btn">
                                                                           
                                                                         </div>
                                                                     </div>
                                                                 </form>
                                                                 </div>
                                        </div>

                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                                <!-- <div class="kt-widget-17__foot-toolbar"> <a href="#" class="btn btn-brand btn-sm btn-upper btn-bold">Search</a> </div> -->
                                                 <!-- <button type="submit" class="btn btn-brand btn-sm btn-upper btn-bold">Search</button> -->
                                         </div>
                                        </div>
                                    </div>

                                    <!--end::Portlet-->

                                    <!--end::Portlet-->
                                </div>

                                <div class="col-lg-12 col-xl-12 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--lg kt-portlet__head--noborder kt-portlet__head--break-sm">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Installments
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-wrapper">

                                               <!-- <div class="kt-form__group kt-form__group--inline kt-margin-r-10">
                                                        <div class="kt-form__label">Sort By:</div>
                                                        <div class="kt-form__control" style="width: 160px;">
                                                            <select class="form-control bootstrap-select" id="kt_form_status" title="Status">
                                                                <option value="0"></option>
                                                                <option value="1">Bank Name</option>
                                                                <option value="2">Bank Location Number</option>
                                                                <option value="3">Bank</option>

                                                            </select>
                                                        </div>
                                                    </div> -->

                                                    <button class="btn btn-secondary mr-4" type="button" id="kt_datatable_check_all">Select all rows</button>
                                                <button class="btn btn-secondary mr-4" type="button" id="kt_datatable_uncheck_all">Unselect all rows</button>

                                                    <div id="checkedActions" class="">
                                                    <!-- <input   id="checking" class="kt-checkbox kt-checkbox--single kt-checkbox--solid" type="checkbox"> -->
                                                        <button type="button" id="schedule" class="btn btn-outline-success active" title="Pay Selected Installments Now">Pay off Mutiple Installments</button>
                                                      <!-- part payment button for realpay      -->
                                                      <!-- <button id="payNow" type="button"  data-toggle="modal" data-target="#installmentsModal" class="btn btn-outline-primary active"title="Part Payment">Part Payment</button> -->
                                                    </div>

                                                     <!-- <button type="button" class="btn btn-outline-brand" > <a href="" class="kt-nav__link"><i class="flaticon2-add-circular-button"></i></a></button> -->
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fit">
                                            <!--Doc: For the datatable initialization refer to "recentOrdersInit" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                            <div class="kt-datatable" id="clientinstallmentDt"></div>
                                        </div>
                                    </div>


                                    <!--end::Portlet-->

                                </div>
                            </div>
                            <!--end::Row-->
                            <!--end::Dashboard 4-->
                        </div>
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
                    <div class="kt-footer__copyright " align="center"> 2019&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct in Partnership with : </a><a href="https://www.aig.com/individual" target="_blank"><img src="{{ asset('img/aig7.png') }}" alt="not found"></a> </div>
                        <div class="kt-footer__menu"> <a href="https://alphadirect.co.bw/aboutus.html" target="_blank" class="kt-footer__menu-link kt-link">About</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Team</a> <a href="https://alphadirect.co.bw/contact.html" target="_blank" class="kt-footer__menu-link kt-link">Contact</a> </div>
                    </div>
                    <!-- end:: Footer -->
                </div>
                <!-- end:: Wrapper -->
            </div>
            <!-- end:: Page -->
        </div>
        <!-- end:: Root -->
        <!-- begin:: Topbar Offcanvas Panels -->
        <!-- begin::Offcanvas Toolbar Quick Actions -->
        <div id="kt_offcanvas_toolbar_quick_actions" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__head">
                <h3 class="kt-offcanvas-panel__title">
                    Quick Actions
                </h3>
                <a href="#" class="kt-offcanvas-panel__close" id="kt_offcanvas_toolbar_quick_actions_close"><i class="flaticon2-delete"></i></a>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="kt-grid-nav-v2">
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-box"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Orders</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon-download-1"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Uploades</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-supermarket"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Products</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-avatar"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Customers</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-list"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Blog Posts</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-settings"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Settings</div>
                    </a>
                </div>
            </div>
        </div>
        <!-- end::Offcanvas Toolbar Quick Actions -->
        <!-- end:: Topbar Offcanvas Panels -->
        <!-- begin:: Quick Panel -->
        <div id="kt_quick_panel" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__nav">
                <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item active"> <a class="nav-link active" data-toggle="tab" href="#kt_quick_panel_tab_notifications" role="tab">Notifications</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_actions" role="tab">Actions</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_settings" role="tab">Settings</a> </li>
                </ul>
                <button class="kt-offcanvas-panel__close" id="kt_quick_panel_close_btn"><i class="flaticon2-delete"></i></button>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="tab-content">
                    <div class="tab-pane fade show kt-offcanvas-panel__content kt-scroll active" id="kt_quick_panel_tab_notifications" role="tabpanel">
                        <!--Begin::Timeline -->
                        <div class="kt-timeline">
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--success">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">02:30 PM</span>
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a>
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--danger">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span>
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a>
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--brand">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon2-box kt-font-brand"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Yestardey</span>
                                </div>
                                <a href="" class="kt-timeline__item-text"> FlyMore design mock-ups been uploadet by designers Bob, Naomi, Richard </a>
                                <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--warning">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-warning"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Aug 13,2018</span>
                                </div>
                                <a href="" class="kt-timeline__item-text">
                                    Meeting with Ken Digital Corp ot Unit14, 3 Edigor Buildings, George Street, Loondon
                                    <br>
                                    England, BA12FJ
                                </a>
                                <div class="kt-timeline__item-info"> Meeting, Customer </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--info">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-notepad kt-font-info"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">May 09, 2018</span>
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a>
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--accent">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon" > <i class="flaticon-bell kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span>
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a>
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                        </div>
                        <!--End::Timeline -->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_actions" role="tabpanel">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-success">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Recent Bills
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-focus">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Latest Orders
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-info">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Latest Invoices
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-warning">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        New Comments
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-brand">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Recent Posts
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_settings" role="tabpanel">
                        <form class="kt-form">
                            <div class="kt-heading kt-heading--space-sm">Notifications</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable notifications:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_1">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable audit log:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_2">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notify on new orders:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_2">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Orders</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable order tracking:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_3">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable orders reports:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_3">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Allow order status auto update:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_4">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Customers</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customer singup:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_5">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customers reporting:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_5">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notifiy on new customer registration:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_6">
                                            <span></span>
                                        </label>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Quick Panel -->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->

<!-- modal pay now view -->

        <div class="modal fade bd-example-modal-lg" id="installmentsModal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                                            <div class="modal-content">

                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">
                                                                        Installment Payments
                                                                    </h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                                                                </div>

                                                                <div class="modal-body">
                                                                    <p>Specify The Amount Being Paid.</p>
                                                                    <form action="" method="POST" class="kt-form">
                                                                    
                                                                    </form>

                                                                     <!-- <button id="cancel" type="button"  class="btn btn-outline-primary active"title="Pay Installments Now">Pay Now</button> -->
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                                                                    <button type="button" class="btn btn-outline-brand" title="Pay Installments Now" >Pay</button>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>

<!-- modal pay now view -->

<!-- modal 2 -->
<!-- <div class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true"> -->
<div id="viewinstallmentModal" class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true" >
<div class="modal-dialog modal-xl">
<!-- modal content -->
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Installment action </h5>

</div>
    <div class="modal-body">

        <form action= "{{ \Config::get('values.graphite_url') }}/realpay/editInstallments" class="form-vertical" role="form" method="POST">



            <div class="form-group">

            <div class="row">
                <div class="col-md-6 form-group">
                <label for="name">Installment Changes :</label>

                    <select class="form-control" name="status">

                        <option value="A">Activate</option>
                        <option value="I">Cancel</option>
                        <option value="S">Clear</option>

                    </select>




                </div>
                </div>


                <div class="modal-footer">
                 <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                 <button type="button" class="btn btn-outline-brand">Save changes</button>

                </div>
</form>
</div>
</div>
</div>


</div>
<!-- /modal 2 -->




        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            var KTAppOptions = {

    "colors": {

        "state": {

            "brand": "#5d78ff",

            "metal": "#c4c5d6",

            "light": "#ffffff",

            "accent": "#00c5dc",

            "primary": "#5867dd",

            "success": "#34bfa3",

            "info": "#36a3f7",

            "warning": "#ffb822",

            "danger": "#fd3995",

            "focus": "#9816f4"
        },

        "base": {

            "label": [

                "#c5cbe3",

                "#a1a8c3",

                "#3d4465",

                "#3e4466"
            ],

            "shape": [

                "#f0f3ff",

                "#d9dffa",

                "#afb4d4",

                "#646c9a"
            ]
        }
    }
};
        </script>
        <!-- end::Global Config -->
       @include('Admin.Layout.scripts')


<script>

   @if(Session::has('policySaved'))
    Toastify({
      text: "{{ Session::get('policySaved') }}",
      duration: 4000,
      newWindow: true,
      gravity: "top", // `top` or `bottom`
      positionRight: true, // `true` or `false`
      backgroundColor: "#00C851",
    }).showToast();

  @endif

</script>




<script>

let dataSet;


function isPaying() {
    console.log("aa");
    // if(checked.length>0){
    //     $("#checkbutton").removeAttr("disabled");
    // } else {
    //     $("#checkbutton").attr("disabled");
    // }
}

        function viewData(elem) {
            console.log($(elem).data("data"));
            currentEditData = JSON.parse($(elem).data("data")+"\"}");
            //
            $("#firstNameView").text(currentEditData.firstName);
            $("#lastNameView").text(currentEditData.lastName);
            $("#cellphoneView").text(currentEditData.cellphone);
            $("#dobView").text(currentEditData.dob);
            $("#licensePlateView").text(currentEditData.licensePlate);
            $("#omangView").text(currentEditData.omang);
            $("#billingView").text(currentEditData.billing);
            $("#billingCellView").text(currentEditData.billingCell);
            $("#bankNameView").text(currentEditData.bankName);
            $("#branchCodeView").text(currentEditData.branchCode);
            $("#accountNumberView").text(currentEditData.accountNumber);
        }


// Conrfirmation Swal for print outs
$(document).ready(function() {
            // var table = $('#clientinstallmentDt').DataTable();
            $("#clientinstallmentDt").on("click", "tr .printReciept", 
            function (e) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are about to print a Reciept",
                    type: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Print This Reciept'
                }).then((result) => {
                    if (result.value) {
                        
                        Swal.fire({
                            title:'Printed',
                            text:'Reciept Printed',
                            type:'success'
                        }) .then((result) => {
                            $(this).parent().submit();
                            
                        });
                    }
                });
        });
        });

        $(document).ready(function() {
            // var table = $('#clientinstallmentDt').DataTable();
            $("#clientinstallmentDt").on("click", "tr .printInvoice", 
            function (e) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are about to print a Invoice",
                    type: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Print This Invoice'
                }).then((result) => {
                    if (result.value) {
                        
                        Swal.fire({
                            title:'Printed',
                            text:'Invoice Printed',
                            type:'success'
                        }) .then((result) => {
                            $(this).parent().submit();
                          
                        });
                    }
                });
        });
        });

        var urlValue = '{{ \Config::get('values.graphite_url') }}'

        $.urlParam = function(name){
                var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                if (results==null) {
                    return null;
                }
                return decodeURI(results[1]) || 0;
            } 

var datatable = $('#clientinstallmentDt').KTDatatable({
  // datasource definition
  data: {
    type: 'remote',
    source: {
      read: {
        method:'GET',
        url: $urlValue+'/realpay/getInstallments?contractNumber='+$.urlParam("id"),
        map: function(raw) {
            $('.loading').css("display","none");
          // sample data mapping
           dataSet = raw;
          if (typeof raw.data !== 'undefined') {
            dataSet = raw.data;
          }
          return dataSet;
        },
      },
    },
    pageSize: 10,
    serverPaging: true,
    serverFiltering: false,
    // serverSorting: true,

  },

  // layout definition
  layout: {
    scroll: false,
    footer: false,
  },

  // column sorting
  sortable: true,

  pagination: true,

  search: {
    input: $('#query'),


  },


  // columns definition
  columns: [
    // {
    //     field: 'checkbox',
    //     title: '#',
    //     sortable: false,
    //     width: 30,
    //     type: 'number',
    //     selector: { class: 'kt-checkbox--solid', id: "actionCheck" },
    //     textAlign: 'center',
	// },

    {
        field: 'CheckBox',
        title: '#',
        sortable: false,
        width: 30,      
        template: function(row, index, datatable) {

            if(row['ns0:status'] == 'I' ){

            return '<i class="flaticon2-accept"></i>';

            }

            if(row['ns0:status'] == 'A' ){
                return '<input id="checking" class="checkBoxClass kt-checkbox kt-checkbox--single kt-checkbox--solid" type="checkbox" data-date="'+row['ns0:actionDate']+'" data-id="'+row['ns0:installmentReferenceNumber']+'" data-amount="'+row['ns0:totalInstallmentAmount']+'" >';
            }
      },
    },

    {
      field: 'actionDate',
      title: 'Billing Date',
      template: function(row, index, datatable) {
        return row['ns0:actionDate'];
      },
    },

    {
      field: 'totalInstallmentAmount',
      title: 'Total Billing Amount',
      template: function(row, index, datatable) {
        return row['ns0:totalInstallmentAmount'];
      },
    },


    {
      field: 'status',
      title: 'Status',
      template: function(row, index, datatable) {
          let future="SCHEDULED";
          let cancelled="PAID";
          let successfull="SUCCESSFUL";

        if(row['ns0:status'] == 'A' ){
        return future;
        }

        if(row['ns0:status'] == 'I' ){
        return cancelled;
        }

        if(row['ns0:status'] == 'S' ){
        return successfull;
        }
      },
    },

    {
        width: 150,
      field: 'activate',
      title: 'Actions',
      sortable: false,
      template: function(row, index, datatable) {

        if(row['ns0:status'] == 'A'){

            $('#esetForm').click(function(e){
    
    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to pay for a Clients Installment!",
                type: 'question',
                
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay For Installment!'
                }).then((result) => {
                if (result.value) {
                    document.getElementById('kt_form').submit();
                    
                    Swal.fire({
                    title:'PAID',
                    text:'Installment Paid',
                    type:'success'
                    }) .then((result) => {

                        window.location.reload();
                    })
                    
                }
                    })
                    var urlValue = '{{ \Config::get('values.graphite_url') }}'

       });
         return  '<form method="POST" id="kt_form" class="kt-form" action="{{ \Config::get('values.graphite_url') }}/realpay/editInstallment">\
                                <input type="hidden" name="status" value="I">\
                                <input type="hidden" name="installmentReferenceNumber" value="'+row['ns0:installmentReferenceNumber']+'">\
                                <input type="hidden" name="red" value="/admin/ClientsContracts?id='+$.urlParam("id")+'"/>\
                                <button id="esetForm" class="btn btn-outline-primary active" type="button">Pay</button>\
                  </form>'
            }

        if(row['ns0:status'] == 'I'){
            return  '<button type="button" class="btn btn-outline-success" disabled="disabled">Paid</button>'
        }

      },
    },

    {
        width: 150,
      field: 'printouts',
      title: 'Print Outs',
      sortable: false, 
      template: function(row, index, datatable) {
    

        if(row['ns0:status'] == 'A'){

         return  '<form method="POST" id="rpInvoice" class="kt-form" action="{{Route('printInvoice')}}">\
                             {{csrf_field()}}\
                                <input type="hidden" name="status" value="I">\
                                <input type="hidden" name="date" value="'+row['ns0:actionDate']+'">\
                                <input type="hidden" name="amount" value="'+row['ns0:totalInstallmentAmount']+'">\
                                <button  class="btn btn-outline-success active printInvoice" type="button">Print Invoice</button>\
                  </form>'
            }

        if(row['ns0:status'] == 'I'){


            return  '<form method="POST" id="rpReciept" class="kt-form" action="{{Route('printReciept')}}">\
            {{csrf_field()}}\
                    <input type="hidden" name="date" value="'+row['ns0:actionDate']+'">\
                    <input type="hidden" name="amount" value="'+row['ns0:totalInstallmentAmount']+'">\
                <button  type="button" class="btn btn-outline-warning active printReciept">Print Receipts</button>\
                </form>'
        }

      },
    },


    /*{
      field: 'status',
      title: 'Status',
      // callback function support for column rendering
      template: function(row) {
        var status = {
          1: {'title': 'Pending', 'class': 'kt-badge--brand'},
          2: {'title': 'Delivered', 'class': ' kt-badge--metal'},
          3: {'title': 'Canceled', 'class': ' kt-badge--primary'},
          4: {'title': 'Success', 'class': ' kt-badge--success'},
          5: {'title': 'Info', 'class': ' kt-badge--info'},
          6: {'title': 'Danger', 'class': ' kt-badge--danger'},
          7: {'title': 'Warning', 'class': ' kt-badge--warning'},
        };


        return '<span class="kt-badge ' + status[row.status].class + ' kt-badge--inline kt-badge--pill">' + status[row.status].title + '</span>';
      },
    }, {
      field: 'type',
      title: 'Type',
      // callback function support for column rendering
      template: function(row) {
        var status = {
          1: {'title': 'Online', 'state': 'danger'},
          2: {'title': 'Retail', 'state': 'primary'},
          3: {'title': 'Direct', 'state': 'accent'},
        };
        return '<span class="kt-badge kt-badge--' + status[row.type].state + ' kt-badge--dot"></span>&nbsp;<span class="kt-font-bold kt-font-' + status[row.type].state + '">' +
            status[row.type].title + '</span>';
      },
    }, */

    // {
    //   field: 'Actions',
    //   title: 'Actions',
    //   sortable: true,
    //   width: 150,
    //   overflow: 'visible',
    //   textAlign: 'center',
    //   template: function(row, index, datatable) {
    //     var dropup = (datatable.getPageSize() - index) <= 4 ? 'dropup' : '';

    //     let currentEditData;
    //     $(".showEditModal").on("click", function() {
    //         console.log("Click");
    //         currentEditData = JSON.parse($(this).data("data")+"\"}");

    //         $("#id").val(currentEditData.id);
    //         $("#firstName").val(currentEditData.firstName);
    //         $("#lastName").val(currentEditData.lastName);
    //         $("#cellphone").val(currentEditData.cellphone);
    //         $("#dob").val(currentEditData.dob);
    //         $("#licensePlate").val(currentEditData.licensePlate);
    //         $("#omang").val(currentEditData.omang);
    //         $("#billing").val(currentEditData.billing);
    //         $("#billingCell").val(currentEditData.billingCell);
    //         $("#bankName").val(currentEditData.bankName);
    //         $("#branchCode").val(currentEditData.branchCode);
    //         $("#accountNumber").val(currentEditData.accountNumber);
    //     });

    //     $(".showModal").on(".hidden.bs.modal", ()=>{
    //         currentEditData = null;
    //     });

    // return '<div class="dropdown ' + dropup + '">\
    //                     <a href="#" class="btn btn-hover-brand btn-icon btn-pill" data-toggle="dropdown">\
    //                         <i class="flaticon2-arrow-down"></i>\
    //                     </a>\
    //                     <div class="dropdown-menu dropdown-menu-right">\
    //                             <form class="dropdown-item" method="POST" action="{{ \Config::get('values.graphite_url') }}/realpay/editInstallment">\
    //                                 <input type="hidden" name="status" value="I">\
    //                                 <input type="hidden" name="installmentReferenceNumber" value="'+row['ns0:installmentReferenceNumber']+'"/>\
    //                                 <input type="hidden" name="red" value="/admin/ClientsContracts?id='+$.urlParam("id")+'"/>\
    //                                 <input type="submit" value="Cancel" class="dropdown-item"/>\
    //                             </form>\
    //                     </div>\
    //                 </div>\
    //                 ';

    //   },
    // }

],
});







$('#esetForm').click(function(e){
    
    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to pay for a Clients Installment!",
                type: 'question',
                
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay For Installment!'
                }).then((result) => {
                if (result.value) {
                    document.getElementById('kt_form').submit();
                    
                    Swal.fire({
                    title:'PAID',
                    text:'Installment Paid',
                    type:'success'
                    }) .then((result) => {

                        
                        window.location.reload();
                    })
                    
                }
                    })


       });










$('#schedule').on('click', function() {
    var urlValue = '{{ \Config::get('values.graphite_url') }}'
    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to pay for multiple Installment!",
                type: 'question',
                
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay these Installment!'
                }).then((result) => {
                    if (result.value) {
                    $('.loading').css("display","");

                                var arr =  [];
                                var checkedArray =
                                Array.of(
                                    $(".kt-datatable__body")
                                    .find(":checked")
                                );

                                for(var i = 0; i < checkedArray[0].length; i++) {
                                    arr.push($(checkedArray[0][i]).data("id"));   
                                }

                                $.ajax({
                                    type: "POST",
                                    url: urlValue+"realpay/editInstallment/m",
                                    data: {"refs": arr, "action": "I"},
                                    dataType: 'text',
                                    success: function(){
                                        console.log("Success");
                                        document.location.reload();
                                    },
                                    fail: ()=>{
                                        console.log("Failed");
                                        $('.loading').css("display","none");
                                        
                                    }
                                });

                                Swal.fire({
                    title:'PAID',
                    text:'Selected Installments Paid',
                    type:'success'
                    }) .then((result) => {

                        
                        window.location.reload();
                    })

                    } 
                    })

   
    
 });
 var urlValue = '{{ \Config::get('values.graphite_url') }}'
$('#payNow').on('click', function() {
    // $('#payNow').addClass("disabled"); 
    
    let arr =  [];
    let total = 0;
    let checkedArray = Array.of(
        $(".kt-datatable__body")
        .find(":checked")
    );
    $(".modal-body").empty();
    for(let i = 0; i < checkedArray[0].length; i++) {
        let obj ={};
        total += Number($(checkedArray[0][i]).data("amount"));
        obj[$(checkedArray[0][i]).data("id")] = $(checkedArray[0][i]).data("amount");
        obj[$(checkedArray[0][i]).data("id")] = $(checkedArray[0][i]).data("date");
      
        arr.push(obj);
        $(".modal-body").append("<h6><label>Price of Installemt:</label></h6>");
        $(".modal-body").append("<input class='form-control' name='"+$(checkedArray[0][i]).data("id")+"' value='"+$(checkedArray[0][i]).data("amount")+"'>");
        $(".modal-body").append("<h6><label>Date of Installemt:</label></h6>");
        $(".modal-body").append("<input class='form-control' name='"+$(checkedArray[0][i]).data("id")+"' value='"+$(checkedArray[0][i]).data("date")+"'>");
        
    }
    // console.log(total);
    // $(".modal-body").append("<h1 class ='text-right'>Amount is  is: P"+total+"</h1>");
    // $(".modal-body").append("<h1 class ='text-right'>Total Amount is  is: P </h1>");
    $(".modal-body").append("<h1 class ='text-right'>Total Amount is  is: P"+total+"</h1>");
    // $(".modal-body").append("<p align='right'><button type='button' class='btn btn-warning button-right'>Calculate</button></p>");
});


//     $('.loading').css("display","");
//     if(!$('#payNow').hasClass("disabled")) {
//         $('#payNow').addClass("disabled");
//         let arr =  [];
//         let checkedArray =
//         Array.of(
//             $(".kt-datatable__body")
//             .find(":checked")
//         );

//         for(let i = 0; i < checkedArray[0].length; i++) {
//             arr.push($(checkedArray[0][i]).data("id"));
//         }
            
//         $.ajax({
//             type: "POST",
//             url: urlValue+"realpay/editInstallment/m",
//             data: {
//                 "refs": arr,
//                 "action": "I"
//             },
//             success: ()=>{
//                 window.location = "/admin/ClientsContracts?id="+$.urlParam("id")
//             },
//             fail: ()=>{
//                 $('.loading').css("display","none");
//                 $('#payNow').removeClass("disabled");
//             }
//         });
//     }
// });

// function completePay() {
//     $('.loading').css("display","");
//     if(!$('#cancel').hasClass("disabled")) {
//         $('#cancel').addClass("disabled");
//         let arr =  [];
//         let checkedArray =
//         Array.of(
//             $(".kt-datatable__body")
//             .find(":checked")
//         );

//         for(let i = 0; i < checkedArray[0].length; i++) {
//             arr.push($(checkedArray[0][i]).data("id"));
//         }

//         $.ajax({
//             type: "POST",
//             url: urlValue+"realpay/editInstallment/m",
//             data: {
//                 "refs": arr,
//                 "action": "I"
//             },
//             success: ()=>{
//                 window.location = "/admin/ClientsContracts?id="+$.urlParam("id")
//             },
//             fail: ()=>{
//                 $('.loading').css("display","none");
//                 $('#cancel').removeClass("disabled");
//             }
//         });
//     }
// }

$('#kt_datatable_check_all').on('click', function() {
			// datatable.setActiveAll(true);

            $(".checkBoxClass").prop('checked', true);
        });



		$('#kt_datatable_uncheck_all').on('click', function() {
			// datatable.setActiveAll(false);
			$(".checkBoxClass").prop('checked', false);
        });

        // $(document).ready(()=>{
        //     $(".checkBoxClass").on("click", ()=>{
        //     })
        // })

//         function getChecked() {

//                         var checker = document.getElementById('checking');
//                         var sendbtn = document.getElementById('checkbutton');
//                         // when unchecked or checked, run the function
//                         // checker.onchange = function(){

//                         // }
//   }


</script>



    </body>
    <!-- end::Body -->
</html>
