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
                    Edit Claim Question
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Claim Question</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="productFactorMainEdit" action="{{ route('policy.question.update', $policyQuestion->id) }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">


                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Risk Type</label>
                            <div class="col-9">
                                {!! Form::select('risk_type', $riskTypes, $claimQuestion->risk_type, ['class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Coverage</label>
                            <div class="col-9">
                                {!! Form::select('coverage', $coverages, $claimQuestion->coverage, ['class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Question </label>
                            <div class="col-9">
                                <input type="text" class="form-control" placeholder="Enter Question"
                                       name="question" value="{{ $claimQuestion->question }}">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Type</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="productFactorType"
                                        title="Please choose factor main" data-live-search="true"
                                        name="type">
                                    @foreach($lookup as $record)
                                        <option value="{{$record->value}}" @if($claimQuestion->response_type == $record->value) selected @endif>{{$record->value}}</option>
                                    @endforeach
                                    </select>
                            </div>
                        </div>

                        {{--Add multiple values--}}
                        <div class=" row">
                            <label class="col-lg-3 col-form-label"></label>
                            <div class="col-lg-3">
                                <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Value</label>
                            </div>
                            <div class="col-lg-3">
                                <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Factor</label>
                            </div>
                        </div>
                        @foreach($clValues as $clValue)
                            <div class="kt-repeater__data-set">
                            <div data-repeater-item class="kt-repeater__item">
                            <div class="kt-repeater__close kt-repeater__close--align-right form-group" style="height: 0px;text-align: right;margin-bottom: 0px;position: relative;right: 10%">
                                <a href="{{route('claim.question.factorValueDelete',$factorValue->id)}}" class="btn btn-danger"> <i class="la la-close"></i> Close </a>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-3 col-form-label"></label>
                                <div class="col-lg-3">
                                    <input type="text" name="factor_type_values_names[{{$clValue->id}}]" class="form-control" value="{{$clValue->name}}" placeholder="Enter Value">
                                </div>
                                <div class="col-lg-3">
                                    <input type="number" name="factor_type_factor_names[{{$clValue->id}}]" class="form-control" value="{{$clValue->factor}}" placeholder="Enter Factor">
                                </div>
                            </div>

                            </div>
                                </div>

                        @endforeach
                        <div class="kt-repeater" id="multipleValues" >
                        <div class="kt-repeater__data-set">
                            <div data-repeater-list="factorProductValues">
                                <div data-repeater-item class="kt-repeater__item">
                                    <div class="kt-repeater__close kt-repeater__close--align-right form-group" style="right: 10%;">
                                        <button data-repeater-delete="" class="btn btn-danger"> <i class="la la-close"></i> Close </button>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-lg-3 col-form-label"></label>
                                        <div class="col-lg-3">
                                            <input type="text" name="factor_type_values" class="form-control" placeholder="Enter Value">
                                        </div>
                                        <div class="col-lg-3">
                                            <input type="number" name="factor_type_factor" class="form-control" placeholder="Enter Fatcor">
                                        </div>
                                    </div>

                                   {{-- <div class="kt-separator kt-separator--border-dashed"></div>
                                    <div class="kt-separator kt-separator--height-sm"></div>--}}
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
                                        <input id="switchValue" type="checkbox" @if($policyQuestion->status) checked="checked" @endif name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        @if($policyQuestion->status)
                                            <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>
                                        @else
                                            <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:#ff4d4d">Inactive</p>
                                        @endif
                                    </label>
                                </span>
                            </div>
                        </div>



                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    @can('claim-question-edit')
                                    <button type="submit" value="Submit" class="btn btn-brand">Update</button>
                                    @endcan
                                    <a class="btn btn-secondary" href="{{ route('policy.question.display') }}" >Cancel</a>
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
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>


<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

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
<!-- end::Body -->
</html>
