<!DOCTYPE html>
<!-- 
Theme: Keen - The Ultimate Bootstrap Admin Theme
Author: KeenThemes
Website: http://www.keenthemes.com/
Contact: support@keenthemes.com
Follow: www.twitter.com/keenthemes
Dribbble: www.dribbble.com/keenthemes
Like: www.facebook.com/keenthemes
License: You must have a valid license purchased only from https://themes.getbootstrap.com/product/keen-the-ultimate-bootstrap-admin-theme/ in order to legally use the theme for your project.
-->
<html lang="en" >

  @include('Admin.Layout.header')
      <!-- begin::Body -->
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


           @include('Admin.Layout.sidebar')
           @include('Admin.Layout.topNav')



               
                        <!-- begin:: Content -->
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
                                                    Gross Written Premiums
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-toolbar-wrapper">
                                                    <div class="dropdown dropdown-inline">
                                                        <button type="button" class="btn btn-clean btn-sm btn-icon btn-icon-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon-more-1"></i> </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
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
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-19">
                                                <div class="kt-widget-19__title">
                                                    <div class="kt-widget-19__label"><small>BWP</small>64M</div>
                                                    <img class="kt-widget-19__bg" src="{{asset('media/misc/iconbox_bg.png')}}" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-19__data">
                                                    <!--Doc: For the chart bars you can use state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-19__chart">
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-45 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="45"></div>
                                                        </div>
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-95 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="95"></div>
                                                        </div>
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-63 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="63"></div>
                                                        </div>
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-11 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="11"></div>
                                                        </div>
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-46 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="46"></div>
                                                        </div>
                                                        <div class="kt-widget-19__bar">
                                                            <div class="kt-widget-19__bar-88 kt-bg-success" data-toggle="kt-tooltip" data-skin="brand" data-placement="top" title="88"></div>
                                                        </div>
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
                                                    Commercial / Personal / Micro Line
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-toolbar-wrapper">
                                                    <div class="dropdown dropdown-inline">
                                                        <button type="button" class="btn btn-clean btn-sm btn-icon btn-icon-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon-more-1"></i> </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
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
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-21">
                                                <div class="kt-widget-21__title">
                                                    <div class="kt-widget-21__label">9.3M</div>
                                                    <img src="{{asset('media/misc/iconbox_bg.png')}}" class="kt-widget-21__bg" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-21__data">
                                                    <!--Doc: For the chart legend bullet colors can be changed with state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-21__legends">
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-success"></i> <span>Commercial</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-warning"></i> <span>Personal</span> </div>
                                                        <div class="kt-widget-21__legend"> <i class="kt-bg-brand"></i> <span>Micro</span> </div>
                                                    </div>
                                                    <div class="kt-widget-21__chart">
                                                        <div class="kt-widget-21__stat">+37%</div>
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
                                                    Total Premiums
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-toolbar-wrapper">
                                                    <div class="dropdown dropdown-inline">
                                                        <button type="button" class="btn btn-clean btn-sm btn-icon btn-icon-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon-more-1"></i> </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
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
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-20">
                                                <div class="kt-widget-20__title">
                                                    <div class="kt-widget-20__label">17M</div>
                                                    <img class="kt-widget-20__bg" src="{{asset('media/misc/iconbox_bg.png')}}" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-20__data">
                                                    <div class="kt-widget-20__chart">
                                                        <!--Doc: For the chart initialization refer to "widgetTotalOrdersChart2" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                                        <canvas id="kt_widget_total_orders_chart_2"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <div class="col-lg-6 col-xl-4 order-lg-2 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid-half kt-widget-13">
                                        <div class="kt-portlet__body">
                                            <div id="kt-widget-slider-13-2" class="kt-slider carousel slide" data-ride="carousel" data-interval="4000">
                                                <div class="kt-slider__head">
                                                    <div class="kt-slider__label">Pending Claims</div>
                                                    <div class="kt-slider__nav">
                                                        <a class="kt-slider__nav-prev carousel-control-prev" href="#kt-widget-slider-13-2" role="button" data-slide="prev"> <i class="fa fa-angle-left"></i> </a>
                                                        <a class="kt-slider__nav-next carousel-control-next" href="#kt-widget-slider-13-2" role="button" data-slide="next"> <i class="fa fa-angle-right"></i> </a>
                                                    </div>
                                                </div>
                                                <div class="carousel-inner">
                                                    <div class="carousel-item active kt-slider__body">
                                                        <div class="kt-widget-13">
                                                            <div class="kt-widget-13__body">
                                                                <a class="kt-widget-13__title" href="#">Toyota Hilux 2017</a> 
                                                                <div class="kt-widget-13__desc">Awaiting Supplier to upload their quote  </div>
                                                            </div>
                                                            <div class="kt-widget-13__foot">
                                                                <div class="kt-widget-13__progress">
                                                                    <div class="kt-widget-13__progress-info">
                                                                        <div class="kt-widget-13__progress-status"> Progress </div>
                                                                        <div class="kt-widget-13__progress-value">78%</div>
                                                                    </div>
                                                                    <div class="progress">
                                                                        <div class="progress-bar kt-bg-brand" role="progressbar" style="width: 78%" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="carousel-item kt-slider__body">
                                                        <div class="kt-widget-13">
                                                            <div class="kt-widget-13__body">
                                                                <a class="kt-widget-13__title" href="#">Audi RS 7 2018</a> 
                                                                <div class="kt-widget-13__desc"> Recent Claim waititng for Mpho to authorize. </div>
                                                            </div>
                                                            <div class="kt-widget-13__foot">
                                                                <div class="kt-widget-13__progress">
                                                                    <div class="kt-widget-13__progress-info">
                                                                        <div class="kt-widget-13__progress-status"> Progress </div>
                                                                        <div class="kt-widget-13__progress-value">55%</div>
                                                                    </div>
                                                                    <div class="progress">
                                                                        <div class="progress-bar kt-bg-brand" role="progressbar" style="width: 55%" aria-valuenow="55" aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="carousel-item kt-slider__body">
                                                        <div class="kt-widget-13">
                                                            <div class="kt-widget-13__body">
                                                                <a class="kt-widget-13__title" href="#">Audi A5 2015</a> 
                                                                <div class="kt-widget-13__desc"> New Glass Claim. Damage Extent: Shattered </div>
                                                            </div>
                                                            <div class="kt-widget-13__foot">
                                                                <div class="kt-widget-13__progress">
                                                                    <div class="kt-widget-13__progress-info">
                                                                        <div class="kt-widget-13__progress-status"> Progress </div>
                                                                        <div class="kt-widget-13__progress-value">24%</div>
                                                                    </div>
                                                                    <div class="progress">
                                                                        <div class="progress-bar kt-bg-brand" role="progressbar" style="width: 24%" aria-valuenow="24" aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid-half kt-widget-12">
                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-12__body">
                                                <div class="kt-widget-12__head">
                                                    <div class="kt-widget-12__date kt-widget-12__date--warning"> <span class="kt-widget-12__day">24</span> <span class="kt-widget-12__month">Apr</span> </div>
                                                    <div class="kt-widget-12__label">
                                                        <h3 class="kt-widget-12__title">
                                                            Mpho's Birthday today
                                                        </h3>
                                                        <span class="kt-widget-12__desc">Claims Department</span> 
                                                    </div>
                                                </div>
                                                <div class="kt-widget-12__info"> She's been doing great, wish her a happy birthday!  </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-portlet__foot-wrapper">
                                                <div class="kt-portlet__foot-info">
                                                    <div class="kt-widget-12__members">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                
                                <div class="col-lg-12 col-xl-8 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--lg kt-portlet__head--noborder kt-portlet__head--break-sm">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    New Policies 
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-wrapper kt-form">
                                                    <div class="kt-form__group kt-form__group--inline kt-margin-r-10">
                                                        
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fit">
                                            <!--Doc: For the datatable initialization refer to "recentOrdersInit" function in "src\theme\app\scripts\custom\dashboard.js" -->
                                            <div class="kt-datatable" id="policies"></div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div> 
                                <div class="col-lg-6 col-xl-4 order-lg-2 order-xl-1">
                                    <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    New Claims 
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-bold" role="tablist">
                                                    <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#kt_portlet_tabs_1_1_1_content" role="tab"> Today </a> </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="tab-content">
                                                <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                                        <!--Begin::Timeline -->
                                                        <div class="kt-timeline">
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--success">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-success"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">9:30 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text"> An instant insurance product has been activated  </a> 
                                                                <div class="kt-timeline__item-info"> Cover - P80,000.00 </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--danger">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon" > <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">12:20 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text"> Policy MIS-2019-596235 deactivated </a> 
                                                                <div class="kt-timeline__item-info"> Wreckless Drving  </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            
                                                            
                                                        </div>
                                                        <!--End::Timeline 1 -->
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="kt_portlet_tabs_1_1_2_content" role="tabpanel">
                                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                                        <!--Begin::Timeline -->
                                                        <div class="kt-timeline">
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--info">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-psd kt-font-info"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    New secyrity alert by Firewall &amp; order to take
                                                                    <br>
                                                                    aktion on User Preferences 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--success">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-success"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--accent">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-shopping-basket kt-font-success"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    New secyrity alert by Firewall &amp; order to take
                                                                    <br>
                                                                    aktion on User references 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--warning">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-rotate kt-font-warning"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">May 09, 2018</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--brand">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-paper-plane-1 kt-font-brand"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Aug 13,2018</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    Meeting with Ken Digital Corp ot Unit14, 3
                                                                    <br>
                                                                    Edigor Buildings, George Street, Loondon
                                                                    <br>
                                                                    England, BA12FJ 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Meeting, Customer </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--danger">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-danger"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    FlyMore design mock-ups been uploadet by
                                                                    <br>
                                                                    designers Bob, Naomi, Richard 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--warning">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-security kt-font-warning"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    FlyMore design mock-ups been uploadet by
                                                                    <br>
                                                                    designers Bob, Naomi, Richard 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--brand">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-price-tag kt-font-brand"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                        </div>
                                                        <!--End::Timeline 1 -->
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="kt_portlet_tabs_1_1_3_content" role="tabpanel">
                                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                                        <!--Begin::Timeline -->
                                                        <div class="kt-timeline">
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--brand">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-medal kt-font-brand"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Aug 13,2018</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    Meeting with Ken Digital Corp ot Unit14, 3
                                                                    <br>
                                                                    Edigor Buildings, George Street, Loondon
                                                                    <br>
                                                                    England, BA12FJ 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Meeting, Customer </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--danger">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    FlyMore design mock-ups been uploadet by
                                                                    <br>
                                                                    designers Bob, Naomi, Richard 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--info">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon2-box kt-font-info"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    New secyrity alert by Firewall &amp; order to take
                                                                    <br>
                                                                    aktion on User Preferences 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--success">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-success"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--accent">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-envelope kt-font-success"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    New secyrity alert by Firewall &amp; order to take
                                                                    <br>
                                                                    aktion on User references 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--warning">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-rotate kt-font-warning"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">May 09, 2018</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--info">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-info"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    FlyMore design mock-ups been uploadet by
                                                                    <br>
                                                                    designers Bob, Naomi, Richard 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                            <!--Begin::Item -->
                                                            <div class="kt-timeline__item kt-timeline__item--brand">
                                                                <div class="kt-timeline__item-section">
                                                                    <div class="kt-timeline__item-section-border">
                                                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-download-1 kt-font-brand"></i> </div>
                                                                    </div>
                                                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                                                </div>
                                                                <a href="" class="kt-timeline__item-text">
                                                                    KeenThemes created new layout whith tens of
                                                                    <br>
                                                                    new options for Keen Admin panel 
                                                                </a>
                                                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                                                            </div>
                                                            <!--End::Item -->
                                                        </div>
                                                        <!--End::Timeline 1 -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-12 col-xl-8 order-lg-2 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Recent Transactions
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-toolbar-wrapper">
                                                    <div class="dropdown dropdown-inline">
                                                        <button type="button" class="btn btn-clean btn-sm btn-icon btn-icon-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="flaticon-more-1"></i> </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
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
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="kt-widget-17">
                                                
                                                
                                                
                                                <div class="kt-widget-17__item">
                                                    <div class="kt-widget-17__product">
                                                        <div class="kt-widget-17__thumb">
                                                            <a href="#">
                                                                <img src="{{asset('images/autoglass.png')}}" class="kt-widget-17__image" alt="" title="" />
                                                            </a>
                                                        </div>
                                                        <div class="kt-widget-17__product-desc">
                                                            <a href="#">
                                                                <div class="kt-widget-17__title"> Auto-glass </div>
                                                            </a>
                                                            <div class="kt-widget-17__sku"> Invoice: 08451345239 <span> AUDI RS7 - Glass Claims</span></div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-widget-17__prices">
                                                        <div class="kt-widget-17__total"> P327.00 </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="kt-widget-17__item">
                                                    <div class="kt-widget-17__product">
                                                        <div class="kt-widget-17__thumb">
                                                            <a href="#">
                                                                <img src="{{asset('images/PG-Flat-full-logo.png')}}" class="kt-widget-17__image" alt="" title="" />
                                                            </a>
                                                        </div>
                                                        <div class="kt-widget-17__product-desc">
                                                            <a href="#">
                                                                <div class="kt-widget-17__title"> PG Glass </div>
                                                            </a>
                                                            <div class="kt-widget-17__sku"> Invoice: 08451345239 <span> AUDI A5 - Glass Claims</span></div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-widget-17__prices">
                                                        <div class="kt-widget-17__total"> P780.00 </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--md">
                                            <div class="kt-widget-17__foot">
                                                <div class="kt-widget-17__foot-info"></div>
                                            </div>
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
                        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
                        <div class="kt-footer__menu"> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">About</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Team</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Contact</a> </div>
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


     @include('Admin.Layout.scripts')

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
    },/*{
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
    }, */{
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

    </body>
    <!-- end::Body -->
</html>