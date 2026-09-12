<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{ asset('images/logo.png') }}" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                    class="flaticon-more"></i></button>
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
                        Percentage setting
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a
                            href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link"> Dashboard </a>
                        {{-- <span class="kt-subheader__breadcrumbs-separator"></span>Config --}}
                        <span class="kt-subheader__breadcrumbs-separator"></span>Percentage Setting
                    </div>
                </div>
            </div>

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <h3 class="mt-4 ml-4">Percentage Setting</h3>
                    <form id="settinCreate" method="POST"
                        action="{{ route('admin.customerCashback.settingpercentage') }}" enctype="multipart/form-data"
                        class="kt-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Enter Percentage</label>
                                <div class="col-9">
                                    <input maxlength="4" class="form-control" name="name" @if ($data) value="{{ $data['value'] }}" @else value='' @endif
                                        title="" placeholder="">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" value="Submit" id="submitbtn"
                                            class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none">
                                            <span class="spinner-border spinner-border-sm" role="status"
                                                aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin-dashboard') }}">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
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

    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript">
    </script>
    <script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript">
    </script>

    <script>
        $(function() {
            $("input[name='hours'],input[name='days']").on('input', function(e) {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });
        });
    </script>

    <script>
        "use strict";
        // Class definition

        var KTFormControls = function() {
            // Private functions

            var demo1 = function() {
                $("#settinCreate").validate({
                    // define validation rules
                    rules: {
                        days: {
                            required: true
                        },
                        hours: {
                            required: true
                        },
                        limit: {
                            required: true
                        },
                    },

                    //display error alert on form submit
                    invalidHandler: function(event, validator) {

                        $('#submit').hide();
                        KTUtil.scrollTo("settinCreate", -200);
                    },

                    submitHandler: function(form) {
                        $('#submitbtn').hide();
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
