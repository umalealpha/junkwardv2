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
                        Edit Rule
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.rules.index')}}" class="kt-subheader__breadcrumbs-link"> Rules </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="ruleEdit" action="{{ route('admin.rules.update',$rule->id) }}"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-1 col-form-label">Product:</label>
                                <div class="col-4">
                                    @if($products && $products->name != null)

                                        <span class="form-control">{{$products->name}}</span>
                                    @else
                                        <span class="form-control">-</span>
                                    @endif
                                </div>
                                <div class="col-1"></div>
                                <label  class="col-2 col-form-label">Account Action:</label>
                                <div class="col-4">
                                    <span class="form-control">{{ $actions->code }}</span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-2 col-form-label">Transaction Type:</label>
                                <div class="col-3">
                                    @if($transaction_type && $transaction_type->name != null)
                                        <span class="form-control">{{$transaction_type->name}}</span>
                                    @else
                                        <span class="form-control">-</span>
                                    @endif
                                </div>
                                <div class="col-1"></div>
                                <label  class="col-2 col-form-label">Transaction Sub Type:</label>
                                <div class="col-4">
                                    @if($transaction_subtype && $transaction_subtype->name != null)
                                        <span class="form-control">{{$transaction_subtype->name}}</span>
                                    @else
                                        <span class="form-control">-</span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Amount Type:</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose amount type " data-live-search="true" name="amount_type" >
                                        @foreach($amount_types as $amount_type)
                                            <option value="{{$amount_type->id}}" @if($amount_type->id == $rule->amount_type) selected
                                                    @endif>{{$amount_type->value}}</option>
                                        @endforeach
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Account Name:</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose account name " data-live-search="true" name="account_name" >
                                        @foreach($accountNames as $accountName)
                                            <option value="{{$accountName->id}}" @if($accountName->id == $rule->account_id) selected
                                                    @endif>{{$accountName->account_name}} - {{$accountName->account_num}}</option>
                                        @endforeach
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Entry Type:</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker entry_type" title="Please choose entry type"  id="entry_type" name="entry_type">
                                        <option value="credit" @if($rule->entry_type == 'credit') selected @endif>Credit</option>
                                        <option value="debit" @if($rule->entry_type == 'debit') selected @endif>Debit</option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    @can('rule-edit')
                                        <div class="col-9">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ route('admin.rules.index') }}" >Cancel</a>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </form>
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