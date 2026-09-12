<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
    <!-- check if is first time login -->

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    View Supplier
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ URL::to('admin/supplier')  }}" class="kt-subheader__breadcrumbs-link"> Supplier </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                    <div class="kt-portlet__body">
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Supplier Name</th>
                                <td>{{$supplier->supplierName}}</td>

                            </tr>
                            <tr>
                                <th>Supplier Type</th>
                                @if($supplierTypes != null)
                                    @foreach($supplierTypes as $supplierType)
                                        @if( $supplier->supplierType  == $supplierType->value)
                                            <td>{{ $supplierType->value }}</td>
                                        @endif
                                    @endforeach
                                @else
                                    <td>No data found.</td>
                            @endif
                            </tr>
                            <tr>
                                <th> Account Number</th>
                                <td>{!! $supplier->account_no !!}</td>
                            </tr>
                            <tr>
                                <th>VAT Number:</th>
                                <td>{!! $supplier->vat_no !!}</td>
                            </tr>
                            <tr>
                                <th>Contact Number</th>
                                <td >{!! $supplier->telephone !!}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td >{!! $supplier->telephone !!}</td>
                            </tr>
                            <tr>
                                <th>Contact Number</th>
                                <td>{!! $supplier->telephone !!}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{!! $supplier->email !!}</td>
                            </tr>
                            <tr>
                                <th>Location</th>
                                <td>{!! $supplier->supplierLocation !!}</td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                        <td>{!! $supplier->address !!}</td>

                            </tr>
                            </tbody>
                        </table>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">

                                    <a class="btn btn-secondary" href="{{ route('admin.supplier.index') }}" >Back</a>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            <!--end::Portlet-->
        </div>
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



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#supplierForm" ).validate({
                // define validation rules
                rules: {
                    sname: {
                        required: true
                    },
                    stype: {
                        required: true
                    },
                    vat: {
                        required: true
                    },
                    snumber: {
                        required: true
                    },
                    semail: {
                        required: true,
                        email:true
                    },
                    slocation: {
                        required: true
                    },
                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("supplierForm", -200);
                },

                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show();
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
            // public functions
            init: function() {
                demo1();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTFormControls.init();
    });
</script>


</body>
<!-- end::Body -->
</html>