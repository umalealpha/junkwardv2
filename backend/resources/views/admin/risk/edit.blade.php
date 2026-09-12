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
                    Edit Risk
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Administration </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Risk</a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Update</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
            <!--begin::Form-->
            <form id="typeForm" action="{{ route('risk.update',$riskTypes->id) }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Region</label>

                            <div class="col-9">
                                <select class="form-control kt_selectpicker" title="Please choose region"
                                        data-live-search="true"
                                        id="region"  name="region" required>
                                    @foreach($regions as $region)
                                        <option value="{{$region->id}}"  @if($riskTypes->regionRisk == NULL)  @elseif($riskTypes->regionRisk->name == $region->name) selected @endif>{{$region->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Risk Name:</label>
                            <div class="col-9">
                                <input  class="form-control" name="name" value="{{$riskTypes->name}}"  placeholder="Enter Risk Name" title="Risk type name is required">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Limit:</label>
                            <div class="col-9">
                                <input  class="form-control" name="limit" value="{{$riskTypes->limit}}"  style="margin-left: 2px;"  placeholder="Enter Risk Limit" title="Limit is required">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Active</label>

                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" @if($riskTypes->active) checked="checked" @endif name="active" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        @if($riskTypes->active)
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
                                <div class="col-9" style="margin-left: 275px;" >
                                    <button type="submit" value="Submit" class="btn btn-brand">Submit</button>
                                    <a class="btn btn-secondary" href="{{ route('risk.display') }}" >Cancel</a>
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

</body>
</html>
