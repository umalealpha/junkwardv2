<!DOCTYPE html>
<html lang="en" >
  @include('admin.layouts.header')
  <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
  <link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
  <link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
      <!-- begin::Body -->
  <style>
      .kt-widget-16 .kt-widget-16__item {
          margin-bottom: 0;
      }
    #days {
    font-size: 17px;
    color: #2138bd;
    }
    #hours {
    font-size: 17px;
    color: #646c9a;
    }
    #minutes {
    font-size: 17px;
    color: #69719c;
    }
    #seconds {
    font-size: 10px;
    color: #589ccd;
    }
  </style>

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
               @include('admin.layouts.sidebar')
               @include('admin.layouts.topNav')
            </div>
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                       @if (auth()->user()->password == null)
                        Welcome {{ Auth::user()->firstName }}
                        @endif
                    </h3>
                </div>

            </div>
            <!-- end:: Subheader -->

                        <!--If Password default -->
                        @if (auth()->user()->password == null)
                        <!-- begin:: Content -->
                        @include('includes.reset')
                        @else
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                            <!--begin::Dashboard 4-->
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--noborder">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Policy Chart

                                                </h3>
                                            </div>
                                        </div>


                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-21">
                                                <div class="kt-widget-21__title">
                                                    <div class="kt-widget-21__label"><?php echo $totalPoliciesCount; ?></div>
                                                    <img src="../assets/media/misc/iconbox_bg.png" class="kt-widget-21__bg" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-21__data">
                                                    <!--Doc: For the chart legend bullet colors can be changed with state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-21__legends">
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-brand"></i> <span>Activated</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-shape-bg-color-4"></i> <span>Deativated</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-shape-bg-color-3"></i> <span>Cancel</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-danger"></i> <span>Expired</span> </div>
                                                    </div>
                                                    <div class="kt-widget-21__chart">
                                                        <div class="kt-widget-21__stat label">
                                                            <?php
                                                                if($totalPoliciesCount > 0){
                                                                    $count = ($activePoliciesCount/$totalPoliciesCount) *100;
                                                                    echo floor(number_format($count ,2));
                                                                }else{
                                                                    echo floor(number_format(0));
                                                                }
                                                            ?> %
                                                        </div>
                                                        <!--Doc: For the chart initialization refer to "widgetTechnologiesChart" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                                        <canvas id="kt_widget_technologies_chart" style="height: 110px; width: 110px;"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--noborder">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                   Claim Status
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-21">
                                                <div class="kt-widget-21__title">
                                                    <div class="kt-widget-21__label"><?php echo $totalClaimCount ?></div>
                                                    <img src="{{asset('media/misc/iconbox_bg.png')}}" class="kt-widget-21__bg" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-21__data">
                                                    <!--Doc: For the chart legend bullet colors can be changed with state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-21__legends">
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-brand"></i> <span>Approved</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-shape-bg-color-4"></i> <span>Rejected</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-shape-bg-color-3"></i> <span>Pending</span> </div>
                                                    </div>
                                                    <div class="kt-widget-21__chart">
                                                        <div class="kt-widget-21__stat">
                                                            <?php
                                                                if($totalClaimCount > 0){
                                                                    $claimCount = ($approvedClaimCount/$totalClaimCount) *100;
                                                                    echo number_format($claimCount ,0);
                                                                }else{
                                                                    echo number_format(0);
                                                                }
                                                            ?>
                                                            %</div>
                                                        <!--Doc: For the chart initialization refer to "widgetTechnologiesChart2" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                                        <canvas id="kt_widget_technologies_chart_2" style="height: 110px; width: 110px;"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>

                                <div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--noborder">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    @if($currentSalesTarget != 0)
                                                        <span class="col-12">Sales Target : <?php echo $target_no ?> </span>
                                                        <span class=""> Date : <?php echo $deadline_date ?></span>
                                                    @else
                                                    <span class="col-12">Sales Target and Deadline not set</span>
                                                    @endif
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-21">
                                                <div class="kt-widget-21__title">
                                                    @if($currentSalesTarget != 0)
                                                        <div class="kt-widget-21__label"><?php echo $currentSalesTarget ?></div>                                                    <img src="{{asset('media/misc/iconbox_bg.png')}}" class="kt-widget-21__bg" alt="bg"/>
                                                    @else
                                                         <div class="kt-widget-21__label">0</div> <img src="{{asset('media/misc/iconbox_bg.png')}}" class="kt-widget-21__bg" alt="bg"/>
                                                    @endif
                                                </div>
                                                <div class="kt-widget-21__data">
                                                    <!--Doc: For the chart legend bullet colors can be changed with state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-21__legends">
                                                        @if($currentSalesTarget != 0)
                                                            <div id="timer">
                                                                <div id="days"></div>
                                                                <div id="hours"></div>
                                                                <div id="minutes"></div>
                                                                <div id="seconds"></div>
                                                            </div>
                                                        @else
                                                            {{-- Permissin to access 'set target' button--}}
                                                            @if(auth::user()->hasPermissionTo('sales-target-Allow to set'))
                                                            <div class="kt-widget-21__legends">
                                                            <a href="{{ route( 'setSalesTarget')}}" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" data-placement="top" data-original-title="Add New Sales Target">Set Sales Target Here
                                                            </a>
                                                            </div>
                                                             @endif
                                                             @endif
                                                    </div>
                                                     <div class="kt-widget-21__chart">
                                                        <div class="kt-widget-21__stat"></div>
                                                        <!--Doc: For the chart initialization refer to "widgetTechnologiesChart2" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                                       {{-- <canvasid="kt_widget_claim_type_chart"style="height:110px;width:110px;"></canvas> --}}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>




                                {{-- top policies section--}}
                                <div class="col-lg-6 col-xl-6 order-lg-2 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Latest Policies
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-16">
                                                @foreach($policyLatest as $policy)
                                                <div class="kt-widget-16__item kt-widget-16__item--info">
                                                    <div class="kt-widget-16__labels">
                                                        <a href="{{ route('admin.policy.edit',$policy->id) }}">
                                                            <div class="kt-widget-16__title">{{ $policy->policyNumber }}</div>
                                                        </a>
                                                        <div class="kt-widget-16__desc">{{ ucwords($policy->firstName) }} {{ ucwords($policy->lastName) }}</div>

                                                    </div>

                                                    <div class="kt-widget-16__data">
                                                        <div class="kt-widget-16__numbers">
                                                            @if($policy->status == 1)
                                                                <div class="kt-widget-16__numbers-change">Activated</div>
                                                            @elseif($policy->status == 0)
                                                               <div class="kt-widget-16__numbers-change">Deactivated</div>
                                                            @elseif($policy->status == 3)
                                                               <div class="kt-widget-16__numbers-change">Expired</div>
                                                            @else
                                                                <div class="kt-widget-16__numbers-change">Cancel</div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                </div>
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <div class="col-lg-6 col-xl-4 order-lg-2 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Latest Claims
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-16">
                                                @foreach($ClaimLatest as $claim)
                                                <div class="kt-widget-16__item kt-widget-16__item--info">
                                                    <div class="kt-widget-16__labels">
                                                        <a href="{{ route('admin.claims.show', $claim->id) }}">
                                                            <div class="kt-widget-16__title">{{ $claim->claim_number }}</div>
                                                        </a>
                                                        <div class="kt-widget-16__desc">{{ ucwords(strtolower($claim->firstName)) }} {{ ucwords(strtolower($claim->lastName)) }}</div>
                                                    </div>
                                                    <div class="kt-widget-16__data">
                                                        <div class="kt-widget-16__numbers">
                                                            <div class="kt-widget-16__numbers-change">{{ $claim->status }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>

                                <div class="col-lg-12 col-xl-8 order-lg-2 order-xl-1">
                                    <!--begin::Portlet-->

                                    <!--end::Portlet-->
                                </div>
                            </div>
                            <!--end::Row-->
                            <!--end::Dashboard 4-->
                        </div>
                        @endif
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                   @include('includes.footer')
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

        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script src="{{asset('assets/vendors/general/chart.js/dist/Chart.bundle.js')}}" type="text/javascript"></script>

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


     @include('admin.layouts.scripts')

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
var datatable = $('#policies').KTDatatable({
  // datasource definition
  data: {
    type: 'remote',
    source: {
      read: {
        method:'GET',
        url: '{{Route('allPolicies')}}',
        map: function(raw) {
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
    input: $('#firstname'),


  },


  // columns definition
  columns: [
    {
      field: 'policyNumber',
      title: 'Policy Number',
      template: function(row, index, datatable) {
        return '<a href="viewPolicy/'+row.policyID+'">MIS-2019-'+row.policyNumber+'</a>';
      },
    },
    {
      field: 'customerName',
      title: 'Customer Name',
      template: function(row, index, datatable) {
        return '<a href="viewCustomer/'+row.id+'">'+row.firstName +' '+row.lastName+'</a>';

      },
    },
    {
      field: 'cellphone',
      title: 'Cellphone',
      template: function(row, index, datatable) {
        return row.cellphone;
      },
    },
      {
      field: 'licensePlate',
      title: 'Motor Reg. No ',
      template: function(row, index, datatable) {
        return row.vehiclePlate;
      },
    },
     {
      field: 'kyc',
      title: 'KYC',
      template: function(row, index, datatable) {

          let completed = 'Completed';
          let notCompleted = 'Imcomplete';

            if(row.isActive == 1){

              return '<i class="la la-check" data-toggle="tooltip" data-placement ="top" title = "Documents have been succefully submitted " style="color:#00C851;font-size:22px;"></i>';
          } else{
              return '<i class="la la-close" data-toggle="tooltip" data-placement ="top" title = "Documents have not been submitted" style="color:#CC0000;font-size:22px;"></i>';

          }
      },
    },

    {
      field: 'plan',
      title: 'Policy Plan',
      template: function(row, index, datatable) {
            let policyPlan = 'Microinsurance'

        return policyPlan;
      },
    },
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

      return '<div class="dropdown ' + dropup + '">\
                        <a href="#" class="btn btn-hover-brand btn-icon btn-pill" data-toggle="dropdown">\
                            <i class="la la-ellipsis-h"></i>\
                        </a>\
                        <div class="dropdown-menu dropdown-menu-right">\
                            <a class="dropdown-item" href="viewPolicy/'+row.policyID+'"><i class="la la-folder-open"></i>View Policy Details</a>\
                            @if(Auth::user()->hasRole('Claims Agent'))
                            <a class="dropdown-item" href="agentMakeClaim/'+row.vehiclePlate+'"><i class="la la-cogs"></i>Process Claim</a>\
                            @endif
                            <a class="dropdown-item" href="{{Route('generatePDF')}}"><i class="la la-file-pdf-o"></i> Generate PDF</a>\
                        </div>\
                    </div>\
                    ';
      },
    }],
});

        $(".showViewModal").on("click", function() {
        });


</script>


     <script>
  @if(Session::has('userNotification'))
    Toastify({
      text: "{{ Session::get('userNotification') }}",
      duration: 4000,
      newWindow: true,
      gravity: "top", // `top` or `bottom`
      positionRight: true, // `true` or `false`
      backgroundColor: "#00C851",
    }).showToast();

  @endif

    @if(Session::has('sessionExpired'))
    Toastify({
    text: "{{ Session::get('sessionExpired') }}",
    duration: 4000,
    newWindow: true,
    gravity: "top", // `top` or `bottom`
    positionRight: true, // `true` or `false`
    backgroundColor: "#CC0000",
    }).showToast();
    @endif

</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>



"use strict";
var KTDashboard = function() {
    var activePoliciesCount ='<?php echo $activePoliciesCount; ?>';
    var deactivePoliciesCount ='<?php echo $deactivePoliciesCount; ?>';
    var cancelPoliciesCount ='<?php echo $cancelPoliciesCount; ?>';
    var expiredPoliciesCount ='<?php echo $expiredPoliciesCount; ?>';

    var approvedClaimCount ='<?php echo $approvedClaimCount; ?>';
    var rejectedClaimCount ='<?php echo $rejectedClaimCount; ?>';
    var pendingClaimCount ='<?php echo $pendingClaimCount; ?>';

    var lifeClaimCount ='<?php echo $lifeClaimCount; ?>';
    var glassClaimCount ='<?php echo $glassClaimCount; ?>';
    var motorClaimCount ='<?php echo $motorClaimCount; ?>';

    var KTDatatablesDataSourceAjaxServer = function () {

        var initTable1 = function () {
            var table = $('#policy_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,

                serverFiltering: false,
                serverSide: true,
                ajax: '{!! route('policydata') !!}',
                order: [0, 'DESC'],
                columns: [
                    {data: 'policyNumber'},
                    {data: 'name'},
                    {data: 'product_name'},
                    {data: 'status'},
                    {data: 'created_at'},
                ],
            });
        };
    }

    var widgetTechnologiesChart = function() {
        if ($('#kt_widget_technologies_chart').length == 0) {
            return;
        }

        var randomScalingFactor = function() {
            return Math.round(Math.random() * 100);
        };

        var config = {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        activePoliciesCount,deactivePoliciesCount,cancelPoliciesCount,expiredPoliciesCount
                    ],
                    backgroundColor: [
                        KTApp.getStateColor('brand'),
                        KTApp.getBaseColor('shape', 4),
                        KTApp.getBaseColor('shape', 3),
                        KTApp.getStateColor('danger')
                    ]
                }],
                labels: [
                    'Activated',
                    'Deativated',
                    'Cancel',
                    'Expired'
                ]
            },
            options: {
                cutoutPercentage: 75,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false,
                    position: 'top',
                },
                title: {
                    display: false,
                    text: 'Technology'
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                tooltips: {
                    enabled: true,
                    intersect: false,
                    mode: 'nearest',
                    bodySpacing: 5,
                    yPadding: 10,
                    xPadding: 10,
                    caretPadding: 0,
                    displayColors: false,
                    backgroundColor: KTApp.getStateColor('brand'),
                    titleFontColor: '#ffffff',
                    cornerRadius: 4,
                    footerSpacing: 0,
                    titleSpacing: 0
                }
            }
        };

        var ctx = document.getElementById('kt_widget_technologies_chart').getContext('2d');
        var myDoughnut = new Chart(ctx, config);
    }

    // for claim status
    var ClaimStatus = function() {
        if ($('#kt_widget_technologies_chart_2').length == 0) {
            return;
        }

        var randomScalingFactor = function() {
            return Math.round(Math.random() * 100);
        };

        var config = {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        approvedClaimCount,rejectedClaimCount,pendingClaimCount
                    ],
                    backgroundColor: [
                        KTApp.getStateColor('brand'),
                        KTApp.getBaseColor('shape', 4),
                        KTApp.getBaseColor('shape', 3)

                    ]
                }],
                labels: [
                    'Approved',
                    'Rejected',
                    'Pending'

                ]
            },
            options: {
                cutoutPercentage: 75,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false,
                    position: 'top',
                },
                title: {
                    display: false,
                    text: 'Technology'
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                tooltips: {
                    enabled: true,
                    intersect: false,
                    mode: 'nearest',
                    bodySpacing: 5,
                    yPadding: 10,
                    xPadding: 10,
                    caretPadding: 0,
                    displayColors: false,
                    backgroundColor: KTApp.getStateColor('brand'),
                    titleFontColor: '#ffffff',
                    cornerRadius: 4,
                    footerSpacing: 0,
                    titleSpacing: 0
                }
            }
        };
        var ctx = document.getElementById('kt_widget_technologies_chart_2').getContext('2d');
        var myDoughnut = new Chart(ctx, config);
    }

    // for claim subtype
    var ClaimType = function() {
        if ($('#kt_widget_claim_type_chart').length == 0) {
            return;
        }

        var randomScalingFactor = function() {
            return Math.round(Math.random() * 100);
        };

        var config = {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        lifeClaimCount,glassClaimCount,motorClaimCount
                    ],
                    backgroundColor: [
                        KTApp.getStateColor('brand'),
                        KTApp.getBaseColor('shape', 4),
                        KTApp.getBaseColor('shape', 3)

                    ]
                }],
                labels: [
                    'Life',
                    'Glass',
                    'Motor Accident'
                ]
            },
            options: {
                cutoutPercentage: 75,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false,
                    position: 'top',
                },
                title: {
                    display: false,
                    text: 'Technology'
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                tooltips: {
                    enabled: true,
                    intersect: false,
                    mode: 'nearest',
                    bodySpacing: 5,
                    yPadding: 10,
                    xPadding: 10,
                    caretPadding: 0,
                    displayColors: false,
                    backgroundColor: KTApp.getStateColor('brand'),
                    titleFontColor: '#ffffff',
                    cornerRadius: 4,
                    footerSpacing: 0,
                    titleSpacing: 0
                }
            }
        };
        var ctx = document.getElementById('kt_widget_claim_type_chart').getContext('2d');
        var myDoughnut = new Chart(ctx, config);
    };


    return {
        //main function to initiate the module
        init: function () {
            KTDatatablesDataSourceAjaxServer();
            widgetTechnologiesChart();
            ClaimStatus();
            ClaimType();
        }
    };
}();

    jQuery(document).ready(function() {
        KTDashboard.init();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
        var KTBootstrapDatepicker = function () {
                var arrows;
                if (KTUtil.isRTL()) {
                    arrows = {
                        leftArrow: '<i class="la la-angle-right"></i>',
                        rightArrow: '<i class="la la-angle-left"></i>'
                    }
                } else {
                    arrows = {
                        leftArrow: '<i class="la la-angle-left"></i>',
                        rightArrow: '<i class="la la-angle-right"></i>'
                    }
                }
                // Private functions
                var demos = function () {
                    // minimum setup
                    $('.kt_datepicker_1').datepicker({
                        rtl: KTUtil.isRTL(),
                        todayHighlight: true,
                        orientation: "bottom left",
                        templates: arrows,
                        format: 'yyyy-mm-dd'
                    });
                }
                return {
                    // public functions
                    init: function() {
                        demos();
                    }
                };
            }();
            jQuery(document).ready(function() {
                KTBootstrapDatepicker.init();
            });
    });

    </script>
    <script>

        function makeTimer() {

        //		var endTime = new Date("29 April 2018 9:56:00 GMT+01:00");
                let deadlineDate ='<?php echo $deadline_date; ?>';

                if(deadlineDate != null){
                    var endTime = new Date(deadlineDate);
                    endTime = (Date.parse(endTime) / 1000);


                    var now = new Date();
                    now = (Date.parse(now) / 1000);

                    var timeLeft = endTime - now;

                    var days = Math.floor(timeLeft / 86400);
                    var hours = Math.floor((timeLeft - (days * 86400)) / 3600);
                    var minutes = Math.floor((timeLeft - (days * 86400) - (hours * 3600 )) / 60);
                    var seconds = Math.floor((timeLeft - (days * 86400) - (hours * 3600) - (minutes * 60)));

                    if (hours < "10") { hours = "0" + hours; }
                    if (minutes < "10") { minutes = "0" + minutes; }
                    if (seconds < "10") { seconds = "0" + seconds; }

                    $("#days").html(days + "<span>Days</span>");
                    $("#hours").html(hours + "<span>Hours</span>");
                    $("#minutes").html(minutes + "<span>Minutes</span>");
                    $("#seconds").html(seconds + "<span>Seconds</span>");
                }



        }

            setInterval(function() { makeTimer(); }, 1000);
    </script>



    </body>
    <!-- end::Body -->
</html>
