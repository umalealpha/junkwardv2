<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->

         @include('CustomerPortal.Layout.header')

         <link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">



    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">


                @include('CustomerPortal.Layout.sidebar')

                @include('CustomerPortal.Layout.topNav')



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
                                                    Search Transactions
                                                </h3>
                                            </div>
                                        </div>


                                        <div class="kt-portlet__body">


                                           <div class="col-lg-12 newsearch1">
                                             <form class="navbar-form navbar-search" role="search">
                                                                     <div class="input-group">
                                                                     <button type="button" class="btn btn-outline-hover-secondary" disabled="disabled"><i class="fa fa-search"></i></button>
                                                                         <input type="text" class="form-control" name="query" id="query" placeholder="Search Transactions Here">
                                                                         <div class="input-group-btn">
                                                                             <div class="col">
                                                                                 <!-- <div class="dropdown">
                                                                                     <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true">
                                                                                         Search Transactions by
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
{{--                                                 <button type="submit" class="btn btn-brand btn-sm btn-upper btn-bold">Search</button>
 --}}                                         </div>
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
                                                    Transactions
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-wrapper kt-form">
                                                    <div class="kt-form__group kt-form__group--inline kt-margin-r-10">
                                                        <div class="kt-form__label">Sort By:</div>
                                                        <div class="kt-form__control" style="width: 160px;">
                                                            <select class="form-control bootstrap-select" id="kt_form_status" title="Status">
                                                                <option value="0"></option>
                                                                <option value="1">Bank Name</option>
                                                                <option value="2">Bank Location Number</option>
                                                                <option value="3">Bank</option>

                                                            </select>
                                                        </div>
                                                    </div>
                                                     <!-- <button type="button" class="btn btn-outline-brand" > <a href="" class="kt-nav__link"><i class="flaticon2-add-circular-button"></i></a></button> -->
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fit">
                                            <!--Doc: For the datatable initialization refer to "recentOrdersInit" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                            <div class="kt-datatable" id="clientDt"></div>
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

<!-- modal 2 -->
<!-- <div class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true"> -->
<div id="viewModal" class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true" >
<div class="modal-dialog modal-xl">
<!-- modal content -->
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Policy Plan : Premium </h5>
<h4 class="modal-title">Policy View</h4>
</div>
    <div class="modal-body">

        <form action= "" class="form-horizontal" role="form" method="POST">



            <div class="form-group">

                <div class="row">
                <div class="col-md-3 form-group">
                <label for="name">First Name :</label>

                <h4 type="text" class="text-dark" for="name" id="firstNameView"></h4>


                </div>
                <div class="col-md-3 form-group">
                <label  for="name">Last Name :</label>

                <h4 type="text" class="text-dark" for="name" id="lastNameView"></h4>

                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Omang :</label>


                <h4 type="text" class="text-dark" for="name" id="omangView"></h4>
                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Cellphone :</label>


                <h4 type="text" class="text-dark" for="name" id="cellphoneView"></h4>
                </div>
                </div>
                <div class="row">

                <div class="col-md-3 form-gSroup">
                <label  for="name">License Plate :</label>


                <h4 type="text" class="text-dark" for="name" id="licensePlateView"></h4>
                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Date Of Birth :</label>


                <h4 type="text" class="text-dark" for="name" id="dobView"></h4>

                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Billing Method :</label>

                <h4 type="text" class="text-dark" for="name" id="billingView"></h4>

                </div>
                    </div>
                    <div class="row">
                    <div class="col-md-3 form-group">
                <label  for="name">Billing Cell :</label>

                <h4 type="text" class="text-dark" for="name" id="billingCellView"></h4>
                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Bank Name :</label>

                <h4 type="text" class="text-dark" for="name" id="bankNameView"></h4>
                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Branch Code :</label>

                <h4 type="text" class="text-dark" for="name" id="branchCodeView"></h4>
                </div>

                <div class="col-md-3 form-group">
                <label  for="name">Account Number :</label>

                <h4 type="text" class="text-dark" for="name" id="accountNumberView"></h4>
                </div>
                </div>
                </div>

                <div class="modal-footer">
                 <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>

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
       @include('CustomerPortal.Layout.scripts')


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
        var urlValue = '{{ \Config::get('values.graphite_url') }}'
var datatable = $('#clientDt').KTDatatable({
  // datasource definition
  data: {
    type: 'remote',
    source: {
      read: {
        method:'GET',
        url: urlValue+'realpay/getTransactionsReport',
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
    serverSorting: true,

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
    {
      field: 'clientName',
      title: 'Client Name',
      template: function(row, index, datatable) {
        return row['ns0:clientName'];
      },
    },
    {
      field: 'clientNumber',
      title: 'Client Number',
      template: function(row, index, datatable) {
        return row['ns0:clientNumber'];
      },
    },
    {
      field: 'installmentDate',
      title: 'Next Billing Date',
      template: function(row, index, datatable) {
        return row['ns0:installmentDate'];
      },
    },
    {
      field: 'installmentAmount',
      title: 'Billing Amount',
      template: function(row, index, datatable) {
        return row['ns0:installmentAmount'];
      },
    },
    {
      field: 'contractNumber',
      title: 'Contract Number',
      template: function(row, index, datatable) {

        return row['ns0:contractNumber'];
      },
    },

    {
      field: 'status',
      title: 'Status',
      template: function(row, index, datatable) {
        return row['ns0:status'];
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

    {
      field: 'Actions',
      title: 'Actions',
      sortable: true,
      width: 150,
      overflow: 'visible',
      textAlign: 'center',
      template: function(row, index, datatable) {
        var dropup = (datatable.getPageSize() - index) <= 4 ? 'dropup' : '';

        let currentEditData;
        $(".showEditModal").on("click", function() {
            console.log("Click");
            currentEditData = JSON.parse($(this).data("data")+"\"}");
            //
            $("#id").val(currentEditData.id);
            $("#firstName").val(currentEditData.firstName);
            $("#lastName").val(currentEditData.lastName);
            $("#cellphone").val(currentEditData.cellphone);
            $("#dob").val(currentEditData.dob);
            $("#licensePlate").val(currentEditData.licensePlate);
            $("#omang").val(currentEditData.omang);
            $("#billing").val(currentEditData.billing);
            $("#billingCell").val(currentEditData.billingCell);
            $("#bankName").val(currentEditData.bankName);
            $("#branchCode").val(currentEditData.branchCode);
            $("#accountNumber").val(currentEditData.accountNumber);
        });

        $(".showModal").on(".hidden.bs.modal", ()=>{
            currentEditData = null;
        });
        // href="processClaim/'+row.id+'"
    //  return '<a  type="checkbox" class="btn btn-hover-brand btn-icon btn-pill" title=""><i class="la la-folder-open-o"></i></button>';
    //   return '<input  type="checkbox" checked data-toggle="toggle" title="Activate Bank"><i class="la la-folder-open-o"></i>';

    return '<div class="dropdown ' + dropup + '">\
                        <a href="#" class="btn btn-hover-brand btn-icon btn-pill" data-toggle="dropdown">\
                            <i class="la la-ellipsis-h"></i>\
                        </a>\
                        <div class="dropdown-menu dropdown-menu-right">\
                            <a class="dropdown-item" href=""><i class="la la-folder-open"></i>Edit Bank Image</a>\
                        </div>\
                    </div>\
                    ';

      },
    }],
});

        $(".showViewModal").on("click", function() {
        });

</script>


    </body>
    <!-- end::Body -->
</html>
