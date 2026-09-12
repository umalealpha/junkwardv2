<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
               
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
                    Agent Pin Settings
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>Config
                    <span class="kt-subheader__breadcrumbs-separator"></span>Agent Pin Settings
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!--If Password default, show edit details -->
    {{--    @if (Auth::user()->default_password == "111111")
       @include('includes.reset')
       @else --}}
    <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form  action="{{ route('admin.agentpin.storeSetting') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        
                       
                        
                       
                      
                        <div class="form-group row">
                            <label  class="col-3 col-form-label" >Apply Agent Pin Function :</label>
                            <div class="col-9 ">
                            <span class="kt-switch">
                                        <label>
                                            <input id="switchValue12" type="checkbox" @if(isset($data))@if($data == 1) checked @endif @endif name="agentpinstatus"
                                                value="1" onchange="statusMsg12()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                @if(isset($data)) 
                                                @if($data == 1)    <h4 id="switchMsg12"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                 Yes</h4>
                                                 @else
                                                 <h4 id="switchMsg12" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:red;">No</h4>
                                                 @endif
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
                                    <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.accounts.index') }}" >Cancel</a>
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>



<script>
   

    function statusMsg12(){
        var isChecked=document.getElementById("switchValue12").checked;
        if (isChecked){
            document.getElementById("switchMsg12").innerHTML="Yes";
            document.getElementById("switchMsg12").style.color="cornflowerblue";
        }
        else {
            document.getElementById("switchMsg12").innerHTML="No";
            document.getElementById("switchMsg12").style.color="#ff4d4d";
        }

    }
    </script>

</body>
<!-- end::Body -->
</html>