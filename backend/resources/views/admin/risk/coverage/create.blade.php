<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body
class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">

    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
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
         <!-- check if is first time login -->


         <!-- begin:: Subheader -->
         <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Coverage Create
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Administration </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Coverage</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
            <form id="typeForm" action="{{ route('coverage.save') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">

                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Risk Name</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="riskTypeDropDown" title="Please Choose Risk"
                                        data-live-search="true"
                                        name="risk_type" required>
                                    @foreach($riskTypes as $risk)
                                        <option value="{{$risk->id}}">{{$risk->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                    </div><p></p>
                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Coverage Name:</label>
                            <div class="col-9">
                                <input  class="form-control" name="name"  placeholder="Enter Coverage Name" title="Coverage name is required" required>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Limit:</label>
                            <div class="col-9">
                                <div class='displayRiskLimit'>
                                <input class="form-control" id="currentLimit"  name="limit"  style="margin-left: 2px;"   placeholder="Enter Coverage Limit" title="Limit is required" required>
                                </div>

                            </div>

                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label"></label>
                            <div class="col-9">
                            <p id="sumError" style="display:none;color:red"> Coverage Limit value should be less than or Equal to The parent Risk Value Limit Of <span id="sum_value"></span></p>
                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" checked="checked" name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>

                                    </label>
                                </span>
                            </div>
                        </div>

                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9" style="margin-left: 275px;" >
                                    <button type="submit" value="Submit" class="btn btn-brand">Submit</button>
                                    <a class="btn btn-secondary" href="{{ route('risk.display') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                @if ($errors->count() > 0)
                    <span class='help-block'>
                        <strong>{{ "Some input field is not properly filled" }}</strong>
                    </span>
                @endif

                <!--end::Form-->
            </div>
            <!--end::Portlet-->

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
$(document).ready(function(){
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $("#riskTypeDropDown").change(function(){

        $.ajax({
            /* the route pointing to the post function */
            url: '{{Route('risklimit.data')}}',
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            data: {
                    _token: CSRF_TOKEN,
                    id:$('#riskTypeDropDown option:selected').val()
            },
            dataType: 'JSON',
            /* remind that 'data' is the response of the AjaxController */
            success: function (data) {
                if (data) {
                console.log(data.limit);
                $.each(data.limit, function(key, value){

                   // $('.displayRiskLimit').append('<input class="form-control" id="currentLimit" value="' + value.limit + '"  name="limit"  style="margin-left: 2px;"   placeholder="Enter Coverage Limit" title="Limit is required" required> ');
                   $("#currentLimit").val(value.limit);
                });

                }else{


                }
            },
        });

    });


});
</script>


<script>
$(document).ready(function(){
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
        $("#currentLimit").change(function() {

            var coverageValue = $(this).val();
            var risk_id = $('#riskTypeDropDown option:selected').val()

            $.ajax({

                url: '{{ route('coverage.checkRiskLimit') }}',
                 type: 'GET',
                 data: {
                    _token: CSRF_TOKEN,
                     risk_id: risk_id,
                    coverageValue: coverageValue,

                },
                dataType: 'JSON',
                 success: function (data) {
                    if(data.error) {
                        $('#sum_value').html(data.limit);
                            $('#sumError').css('display','block');
                            $('#submit').attr("disabled",true);
                    } else {
                        $('#sumError').css('display','none');
                            $('#submit').attr("disabled",false);
                    }
                },
            });
    });
});
</script>
</body>
</html>
