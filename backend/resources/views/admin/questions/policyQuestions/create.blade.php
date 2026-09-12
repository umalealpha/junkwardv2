<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->

<body  class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
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
                    Create New Question
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Administration </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Policy Questions</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
            <form id="typeForm" action="{{ route('policy.question.save') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                       <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                    <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Select Risk</label>

                            <div class="col-9">
                                <select class="form-control" title="Please choose Risk"
                                        data-live-search="true"
                                        id="risk_type"  name="risk_type" required>

                                    @foreach($riskTypes as $risk)
                                        <option value="{{$risk->id}}">{{$risk->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Select Coverage Name</label>

                            <div class="col-9">
                                <select class="form-control" title="Please choose Risk"
                                        data-live-search="true"
                                        id="coverage"  name="coverage" required>

                                    @foreach($coverages as $coverage)
                                        <option value="{{$coverage->id}}">{{$coverage->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Question</label>
                            <div class="col-9 ">
                            <input  class="form-control" name="question"  placeholder="Question" title="Question is required" required>

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Response Type</label>
                            <div class="col-9">
                                <select class="form-control " id="productFactorType"
                                        title="Please choose factor main" data-live-search="true"
                                          name="response_type">
                                    @if($lookup->count() > 0)
                                        @foreach($lookup as $lookups)
                                            <option value="{{$lookups->value}}">{{$lookups->value}}</option>
                                        @endforeach
                                    @else
                                        <option value="">No records found</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="kt-repeater" id="multipleValues" style="display: none">
                            <div class=" row">
                                <label class="col-lg-3 col-form-label"></label>
                                <div class="col-lg-3">
                                    <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Value</label>
                                </div>
                                <div class="col-lg-3">
                                    <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Factor</label>
                                </div>
                            </div>
                            <div class="kt-repeater__data-set">
                                <div data-repeater-list="factorProductValues">
                                    <div data-repeater-item class="kt-repeater__item">
                                        <div class="kt-repeater__close kt-repeater__close--align-right form-group" style="right: 10%;">
                                            <button data-repeater-delete="" class="btn btn-danger"> <i class="la la-close"></i> Close </button>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-lg-3 col-form-label"></label>
                                            <div class="col-lg-3">
                                                <input type="text" name="factor_type_values" class="form-control" placeholder="Enter Value" required>
                                            </div>
                                            <div class="col-lg-3">
                                                <input type="number" name="factor_type_factor" class="form-control"
                                                       placeholder="Enter Factor" required>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="kt-repeater__add-data">
                                <span data-repeater-create="" class="btn btn-info btn-sm" style="margin-left: 26%;"> <i class="la la-plus"></i> Add </span>
                            </div>
                        </div>

                        <br>
                        <br>
                        {{--End multiple values--}}
                        <div class="form-group row">
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
            </div>
        </div> 
   
    <!-- begin:: footer -->
    @include('includes.footer') 
    <!-- end::footer -->
    </div>
    @include('admin.layouts.scripts')
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script>

    $('#productFactorType').on('change',function(){
        if($(this).val() !=1){
           $('#multipleValues').show();
        }else {
            $('#multipleValues').hide();
        }
    });
</script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

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


</body>
</html>
