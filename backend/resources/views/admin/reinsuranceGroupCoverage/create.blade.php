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
                    Create Reinsurance Group Coverage
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceGroupCoverage.index')}}" class="kt-subheader__breadcrumbs-link"> Reinsurance  Group Coverage </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="reinsuranceGroupCreate" action="{{ route('admin.reinsuranceGroupCoverage.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Group Code</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="group_code" title="Group Code is required" placeholder="Enter Group Code">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Group Name</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="group_name" title="Group Name is required" placeholder="Enter Group Name">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" data-live-search="true" title="Please choose product"  id="product_id" name="product_id">
                                    @foreach($products as $product)
                                        <option  value="{{$product->id}}">{{$product->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" style="margin-top: 1%">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" checked="checked" name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        <p id="switchMsg" style="display:inline;float:left;margin-top: 10px;margin-left: 5px;color:cornflowerblue">Active</p>
                                    </label>
                                </span>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                       {{-- <h3 class="kt-heading kt-heading--md">
                            Coverages
                        </h3>--}}
                        <div class="row">
                            <table class="table table-striped m-table">
                                <tbody id="coverageDiv"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="submit" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.reinsuranceGroupCoverage.index') }}" >Cancel</a>
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
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>


<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#reinsuranceGroupCreate" ).validate({
// define validation rules
                rules: {
                    group_code: {
                        required: true
                    },
                    group_name:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("reinsuranceGroupCreate", -200);
                },

                submitHandler: function (form) {
                    $('#submit').hide();
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

<script>
    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Active";
            document.getElementById("switchMsg").style.color="cornflowerblue";

        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

    }
</script>
<script>
    $('#product_id').on('change', function (e, data) {
        var product_id = this.value;
        var selected = '';
        $.ajax({
            url: '{{ route('admin.reinsuranceGroupCoverage.product_coverage') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": product_id,
            },
            type: 'post',
            datatype: 'json',
            success: function (data) {

                var append = '';
                var coverage = '';
                if(data.coverage.length !=  0) {
                            coverage += '<tr>' +
                            '<th>COVERAGE NAME</th>' +
                            '<th>SI/PREMIUM</th>' +
                            '<th>RI LIMIT</th>' +
                            '<th>LIMIT</th>' +
                            '</tr>';
                        data.coverage.forEach(function ($cover) {
                            coverage += '<tr>' +
                                '<input type="hidden" name="coverage_id[]" value="' + $cover.coverage_id + '">' + $cover.coverage_id  +
                                '<td><input type="hidden" name="coverage_name[]" value="' + $cover.name + '">' + $cover.name + '</td>' +
                                '<td><select name="si_premium[]" required><option value="0">Select SI/PREMIUM</option><option value="1">SumInsured</option><option value="2">Premium</option></select></td>' +
                                '<td><select name="ri_limit[]" id="ri_limit" onchange="AlertVal(this,"'+$cover.id +'");"><option value="0">Select RI LIMIT</option><option value="1">SumInsured</option><option value="2">Skip</option><option value="3">Other</option></select></td>' +
                                '<td><input type="text" name="limit[]" id="limit_ "'+$cover.id+'"></td>' +
                                '</tr>';
                        });

                        $('#coverageDiv').empty();
                        $('#coverageDiv').append(coverage);
                    KTFormControls.init();
                    }
                else{
                    $('#coverageDiv').empty();
                    $('<p style="font-weight: bold;">No coverages are available</p>').appendTo('#coverageDiv');
                }
            }
        });
    });

    function AlertVal(e,id){
        var limit_id= e.options[e.selectedIndex].value;
        alert(limit_id);
        var input_id ='#limit_'+id;
        if(limit_id == 3 ){
            $(input_id).css('display','block');
        }
        else
        {
            $(input_id).css('display','none');
        }
    };
</script>



</body>
<!-- end::Body -->
</html>