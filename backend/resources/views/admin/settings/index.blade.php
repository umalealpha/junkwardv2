<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

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

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
   @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Settings
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Settings</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <div class="form-group row">
                            <div class="col-6">
                                <label for="activation_code" class="form-label">Info-Bib Settings</label>
                                <select name="info_bib" class="form-control" id="info_bib">
                                    <option value="option1">Option </option>
                                    <option value="option1">Option </option>
                                    <option value="option1">Option </option>
                                </select>
                                <span class="form-text text-muted"></span>
                            </div>
                            <div class="col-6">
                                    <label for="serial_code" class="form-label">VCS Setttings</label>
                                    <select name="vcs" class="form-control" id="vcs">
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                        </select>
                                    <span class="form-text text-muted"></span>
                            </div>
                            <div class="col-6">
                                    <label for="serial_code" class="form-label">Amazon SES settings</label>
                                    <select name="amazon_ses" class="form-control" id="amazon_ses">
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                            </div>
                            <div class="col-6">
                                    <label for="serial_code" class="form-label">Amazon AWS settings</label>
                                    <select name="amazon_aws" class="form-control" id="amazon_aws">
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                            <option value="option1">Option </option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                            </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Content -->
    </div>
     @endif
    <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>



</body>
<!-- end::Body -->
</html>
