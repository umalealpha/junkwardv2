<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
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

    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Add Discount/Surcharge Quote: #{{ $policyNumber }}
                    </h3>
                </div>
            </div>
            <!-- end:: Subheader -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div style="margin-bottom:2%" id="rerate_div">
                            <form id="updatePremiumDiscSurc" action="{{ URL::to('admin/policy/addDiscountSurcharge',$policyNumber) }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <table class="table table-striped table-bordered table-hover table-checkable">
                                <thead>
                                <tr>
                                    <th>Please select type:</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="type" title="Please select type" data-live-search="true" id="type">
                                            <option value="discount">Discount</option>
                                            <option value="surcharge">Surcharge</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Please select value type:</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="value_type" title="Please select value type" data-live-search="true" id="value_type">
                                            <option value="1">Flat value</option>
                                            <option value="2">Percent(%) value</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Value:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control" name="value" title="Enter the value" placeholder="Enter value"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Reason:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control" name="reason" title="Please provide the reason" placeholder="Please provide reason"/>
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-5"></div>
                                    <div class="col-7">
                                        <button type="submit" class="btn btn-info">Submit</button>
                                        <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    </div>
                </div>
            </div>
        </div>
</div>
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
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

</body>
<!-- end::Body -->
</html>
