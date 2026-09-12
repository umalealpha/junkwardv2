<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Calculate Premium
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Account </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!--If Password default, show edit details -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                    <input type="hidden" name="quoteNumber" id="quoteNumber" value="{{ $quoteNumber }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Quote Number:</label>
                            <div class="col-9">
                                <input  class="form-control" value="{{ $quoteNumber }}" disabled>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Difference in days:</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" title="Please select difference in days" data-live-search="true" id="days" name="days" required>
                                   <option value="">Please select difference in days</option>
                                   @for($i = 1;$i<=31;$i++)
                                       <option value="{{ $i }}">{{ $i }}</option>
                                   @endfor
                               </select>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row" id="response">
                            <label  class="col-3 col-form-label"></label>
                            <div class="col-9">
                                <div class="row">
                                    <label  class="col-3 col-form-label">Per Day premium:</label>
                                    <div class="col-9">
                                        <label  class="col-3 col-form-label" id="perDayValue"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                    <label  class="col-3 col-form-label">Till Date:</label>
                                    <div class="col-9">
                                        <label  class="col-3 col-form-label" id="datePremium"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                    <label  class="col-3 col-form-label">Premium for <label class="col-form-label" id="daysP"></label> days:</label>
                                    <div class="col-9">
                                        <label  class="col-3 col-form-label" id="tilldate"></label>
                                        <span class="form-text text-muted"></span>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button id="submitbtn" class="btn btn-brand">Calculate</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <!--end::Form-->
            </div>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
        {{--  @endif --}}
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

<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>

<script>
    $(document).ready(function() {
        $('#response').slideUp();

        $('#days').on('change', function () {
            $('#response').slideUp();
        });

        $('#submitbtn').on('click', function () {
            var quoteNumber = $('#quoteNumber').val();
            ajaxRequest = setTimeout(function (sn) {
                $.ajax({
                    url: '{{ route('quote.calculatePerDayPremium') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "quoteNumber": quoteNumber,
                        "day": $('#days').val(),
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function (data) {
                        var datePremium = data.premiumDate
                        $('#response').slideDown();
                        $('#perDayValue').html('P'+data.perDayPremium);
                        $('#tilldate').html('P'+data.tilldate);
                        $('#datePremium').text(datePremium);
                        $('#daysP').html($('#days').val());
                    }
                });
            }, 200);
        });
    });
</script>

</body>
<!-- end::Body -->
</html>