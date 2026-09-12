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
    <!--If Password default, show edit details -->
    @if (Auth::user()->default_password == "111111")
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        View Rule
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.rules.index')}}" class="kt-subheader__breadcrumbs-link"> Rules </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                        <div class="kt-portlet__body">
                            <table class="table table-striped m-table">
                                <tbody>
                                <tr>
                                    <th>Product</th>
                                    @if($products && $products->name != null)
                                            <td>{!! $products->name !!}</td>
                                        @else
                                            <td>-</td>
                                        @endif
                                </tr>
                                <tr>
                                    <th>Account Action</th>
                                    <td>{{ $actions->code }}</td>

                                </tr>
                                <tr>
                                    <th>Transaction Type</th>
                                    @if($transaction_type && $transaction_type->name != null)
                                        <td>{{$transaction_type->name}}</td>
                                    @else
                                        <td>-</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Transaction Sub Type</th>
                                    @if($transaction_subtype && $transaction_subtype->name != null)
                                        <td>{{$transaction_subtype->name}}</td>
                                    @else
                                        <td>-</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Amount Type</th>
                                    @if($amount_types != null)
                                        @foreach($amount_types as $amount_type)
                                            @if($amount_type->id == $rule->amount_type)
                                    <td>{{$amount_type->value}}</td>
                                            @endif
                                        @endforeach
                                    @else
                                        <td>Account Name and Number not found.</td>
                                    @endif
                                </tr>

                                <tr>
                                    <th>Account Name</th>
                                    @if($accountNames != null)
                                        @foreach($accountNames as $accountName)
                                            @if($accountName->id == $rule->account_id)
                                        <td>{{$accountName->account_name}} - {{$accountName->account_num}}</td>
                                            @endif
                                        @endforeach
                                    @else
                                    <td>Account Name and Number not found.</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Entry Type</th>
                                    @if($rule->entry_type == 'credit')
                                    <td>Credit</td>
                                        @elseif($rule->entry_type == 'debit')
                                    <td>Debit</td>
                                        @endif
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>

                                        <div class="col-9">

                                            <a class="btn btn-secondary" href="{{ route('admin.rules.index') }}" >Back</a>
                                        </div>

                                </div>
                            </div>
                        </div>
                    <!--end::Form-->
                </div>
                <!--end::Portlet-->
            </div>
            <!-- end:: Content -->
        </div>
@endif
<!-- begin:: Footer -->
    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
    </div>
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#ruleEdit" ).validate({
// define validation rules
                rules: {
                    account_name: {
                        required: true
                    },
                    account_num:{
                        required:true
                    },
                    entry_type:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("ruleEdit", -200);
                },

                submitHandler: function (form) {
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