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
                    Create Rule
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.rules.index')}}" class="kt-subheader__breadcrumbs-link"> Rules </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="ruleCreate" action="{{ route('admin.rules.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-1 col-form-label">Product:</label>
                            <div class="col-4">
                                <select class="form-control kt_selectpicker"
                                        title="Please choose product" data-live-search="true"
                                        name="product_name">
                                    @foreach($products as $product)
                                        <option value="{{$product->id}}">{{$product->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-1"></div>
                            <label  class="col-2 col-form-label">Account Action:</label>
                            <div class="col-4">
                                <select class="form-control kt_selectpicker"
                                        title="Please choose action type" data-live-search="true" name="action_code" >
                                    @foreach($actions as $action)
                                        <option value="{{$action->id}}">{{$action->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-1 col-form-label">Transaction Type:</label>
                            <div class="col-4">
                                <select class="form-control kt_selectpicker"
                                        title="Please choose transaction type" data-live-search="true"
                                        name="transaction_type" id="transaction_type">
                                    @foreach($trans_types as $trans_type)
                                        <option value="{{$trans_type->id}}">{{$trans_type->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-1"></div>
                            <label  class="col-2 col-form-label">Transaction Sub Type:</label>
                            <div class="col-4">
                                <select class="form-control kt_selectpicker"
                                        title="Please choose transaction sub type" data-live-search="true" name="transaction_subType" id="transaction_sub_type">

                                </select>
                            </div>
                        </div>
                        {{--New table Starts--}}
                        <table class="block block-rounded block-bordered table table-bordered" id="ruleDiv" >
                            <thead style="text-align: center;">
                            <th >Amount Type</th>
                            <th >Account Name:</th>
                            <th >Entry Type</th>
                            <th >Action</th>
                            </thead>
                            <tbody id="product_table_body table-data">
                            <thead>
                            <tr style="height: auto!important;" class="fieldGroup">
                                <td>
                                    <select class="form-control kt_selectpicker" title="Please choose action " data-live-search="true" name="amount_type[]" >
                                        @foreach($amount_types as $amount_type)
                                            <option value="{{$amount_type->id}}">{{$amount_type->value}}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-control kt_selectpicker" title="Please choose account name" data-live-search="true" name="account_name[]">
                                        @foreach($accountNames as $accountName)
                                            <option value="{{$accountName->id}}">{{$accountName->account_name}} - {{$accountName->account_num}}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-control kt_selectpicker entry_type" title="Please choose entry type"  id="entry_type" name="entry_type[]">
                                        <option value="credit">Credit</option>
                                        <option value="debit">Debit</option>
                                    </select>
                                </td>
                                <td>
                                </td>
                            </tr>
                            </thead>
                        </table>
                        <a style="height: 40px;width: 80px;margin-left: 45%" href="javascript:void(0)" class="btn btn-brand addMore"><span class="glyphicon glyphicon glyphicon-plus" aria-hidden="true"></span> Add</a>
                        <span style="text-align:center;margin-top:3%;color:red;display:none;font-size:15px" id="noteId" ><text style="font-weight: bold">Error:</text> &nbsp Atleast 1 Credit entry and 1 Debit entry is mandatory.</span>
                        <span style="text-align:center;margin-top:3%;color:red;display:none;font-size:15px" id="maxMsg" ><text style="font-weight: bold">Note:</text> &nbsp Maximum 11 groups are allowed.</span>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9" style="margin-left:41% ">
                                    <button type="submit" value="Submit" id="sbtBtn" class="btn btn-brand " >Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.rules.index') }}" >Cancel</a>
                                </div>
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>


<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#ruleCreate" ).validate({
// define validation rules
                rules: {
                    product_name: {
                        required: true
                    },
                    action_code:{
                        required:true
                    },
                    amount_type:{
                        required:true
                    },
                    account_name:{
                        required:true
                    },

                    entry_type:{
                        required:true
                    },
                    transaction_type:{
                        required:true
                    },
                    transaction_subType:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("ruleCreate", -200);
                },

                submitHandler: function (form) {
                    $('#sbtBtn').hide();
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

    $(document).ready(function(){
//group add limit
        var maxGroup = 11;

//add more fields group
        $(".addMore").click(function(){
            if($('body').find('.fieldGroup').length < maxGroup){
                var fieldHTML = '<tr style="height: auto!important;" class="fieldGroup">' +
                    '<td>' +
                    '<select class="form-control kt_selectpicker" title="Please choose action " data-live-search="true" name="amount_type[]" >' +
                    '@foreach($amount_types as $amount_type)' +
                    '<option value="{{$amount_type->id}}">{{$amount_type->value}}</option>' +
                    ' @endforeach'+
                    '</select>' +
                    '</td>'+
                    '<td>'+
                    ' <select class="form-control kt_selectpicker"  title="Please choose account name" data-live-search="true" name="account_name[]">'+
                    '@foreach($accountNames as $accountName)'+
                    '<option value="{{$accountName->id}}">{{$accountName->account_name}} - {{$accountName->account_num}}</option>'+
                    '@endforeach'+
                    '</select>'+
                    '</td>'+
                    '<td>'+
                    ' <select class="form-control kt_selectpicker entry_type" title="Please choose account name" name="entry_type[]">'+
                    '<option value="credit">Credit</option>'+
                    '<option value="debit">Debit</option>'+
                    '</select>'+
                    '</td>'+
                    '<td style="text-align: center;">'+
                    '<a href="javascript:void(0)" class="btn btn-danger remove"><span class="glyphicon glyphicon glyphicon-plus" aria-hidden="true"></span>Remove</a>'+
                    '</td>'+
                    '</tr>';
                $('body').find('.fieldGroup:last').after(fieldHTML);
            }else{
                $('#maxMsg').show();
                $(".addMore").hide();

            }
        });

//remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
            if($('body').find('.fieldGroup').length < maxGroup){
                $('#maxMsg').hide();
                $(".addMore").show();
                $("#sbtBtn").prop("disabled", false);
            }
        });
    });

    $('#sbtBtn').click(function(){
        var selectedEntries = [];
        $('select[name="entry_type[]"] option:selected').each(function() {
            selectedEntries.push($(this).val());
        });
        if(jQuery.inArray("debit", selectedEntries) !== -1 && jQuery.inArray("credit", selectedEntries) !== -1){
            $("#sbtBtn").prop("disabled",false);
            $("#noteId").hide();
        }
        else{
            $("#sbtBtn").prop("disabled",true);
            $("#noteId").show();
        }
    });

    $(document).on('change', '.entry_type', function(){
        $("#sbtBtn").prop("disabled",false);
        $("#noteId").hide();
    });

    $('.addMore').change( function(){
        $("#sbtBtn").prop("disabled",false);
        $("#noteId").hide();
    });

    $(document).ready(function(){
        var ajaxRequest;
        $('#transaction_type').on('change', function(){
            var trans_type = $(this).val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.rules.getTransSubtype') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "trans_type": trans_type,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            $('#transaction_sub_type').empty();
                            $.each(data.count, function(key, subtype){
                                $('#transaction_sub_type').append('<option value="' + subtype.id + '">' + subtype.name + '</option>');
                            });
                        } else {
                            $('#transaction_sub_type').empty();
                        }
                        $("#transaction_sub_type").selectpicker('refresh');
                    }
                });
            }, 200);

        });
    });

</script>

</body>
<!-- end::Body -->
</html>