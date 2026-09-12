<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
                    View User
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}" class="kt-subheader__breadcrumbs-link"> User </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->

            <div class="kt-portlet">
                <!--begin::Form-->
                <div class="kt-portlet__body" style="padding-top:0px" >
                    <div class="row">
                        <div class="kt-portlet kt-portlet--tabs">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">Profile Account
                                        @if(auth()->user()->hasRole('Super Admin'))

                                            @if($user->active == null)
                                                <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Account Not-Active</span>
                                            @elseif($user->active == 1)
                                                <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Account Active</span>
                                            @elseif($user->active == 2)
                                                <span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Account Suspended</span>
                                            @endif

                                        @endif
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#kt_portlet_base_demo_3_1_personal_info" role="tab">Personal Info</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_5_change_profile_picture" role="tab">Profile Picture</a>
                                        </li>
                                        {{--  <li class="nav-item">
                                             <a class="nav-link" data-toggle="tab" href="#kt_portlet_base_demo_3_3_claims_content" role="tab">General Interests and Skills </a>
                                         </li> --}}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3 pd-0">
                        <div class="kt-portlet__body">
                            <div class="kt-user-card__wrapper text-center">
                                <div class="kt-user-card__pic">
                                    @if($user->profile->profile_photo != null)
                                        @if (\AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo))
                                            <img class="mt-5 rounded border border-light" src="{{\AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo) }}" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" >
                                        @else
                                            <img class="mt-5 rounded border border-light" src="{{ $user->profile->profile_photo }}" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" >
                                        @endif

                                    @else
                                        <img alt="Pic" style="height:50%;width:50%;border-radius: 50%!important;margin: 0 auto;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" />
                                    @endif
                                </div>
                                <div class="kt-user-card__details mt-4">
                                    <h3>{{ $user->firstName }}  {{ $user->lastName }}</h3>
                                    <h4 class="mt-2 kt-font-info"> {{ $user->profile->work_position }}</h4>
                                    <h5><i class="fas fa-envelope"></i> {{ $user->email }}</h5>
                                    <h5><i class="mt-2  fas fa-mobile-alt"></i> {{ $user->profile->cellphone }}</h5>
                                    <h5><i class="fas fa-phone"></i> {{ $user->profile->work_phone }}</h5>
                                </div>
                                <div class="kt-user-card__footer mt-5">
                                    <h6>About {{ $user->firstName }} {{ $user->lastName }}</h6>
                                    <blockquote class="blockquote mb-0">
                                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante.</p>
                                        <footer class="blockquote-footer">Someone famous said this <cite title="Source Title">www.famous.person</cite></footer>
                                    </blockquote>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="col-9">
                        <div class="kt-portlet__body" style="padding-top:0px" >
                            <div class="tab-content">
                                <div class="tab-pane active" id="kt_portlet_base_demo_3_1_personal_info" role="tabpanel">
                                    <div class="kt-portlet" id="labelDiv">
                                            <div class="kt-portlet__body">

                                            <table class="table table-striped m-table">
                                                 <tbody>
                                                 <tr>
                                                     <th>First Name</th>
                                                     <td>{{ $user->firstName }}</td>
                                                     @if ($errors->has('first_name'))
                                                        <td class="text text-danger">
                                                            {{ $errors->first('first_name') }}</td>
                                                     @endif
                                                 </tr>
                                                 <tr>
                                                     <th>Last Name</th>
                                                     <td>{{ $user->lastName }}</td>
                                                     @if ($errors->has('last_name'))
                                                         <td class="text text-danger">
                                                             {{ $errors->first('last_name') }}</td>
                                                     @endif
                                                 </tr>
                                                 <tr>
                                                     <th>Email</th>
                                                     <td>{{ $user->email }}</td>
                                                     @if ($errors->has('email'))
                                                         <td class="text text-danger">
                                                             {{ $errors->first('email') }}</td>
                                                     @endif
                                                 </tr>
                                                 <tr>
                                                     <th>Date of Birth</th>
                                                     <td>{{ $userProfile->dob }}</td>
                                                     @if ($errors->has('dob'))
                                                         <td class="text text-danger">
                                                             {{ $errors->first('dob') }}</td>
                                                     @endif
                                                 </tr>
                                                 @if(auth()->user()->hasRole('Super Admin') === true)
                                                     <tr>
                                                         <th>Date of Birth</th>
                                                         <td>{{ $userProfile->dob }}</td>
                                                         @if ($errors->has('dob'))
                                                             <td class="text text-danger">
                                                                 {{ $errors->first('dob') }}</td>
                                                         @endif
                                                     </tr>
                                                 @endif

                                                 <tr>
                                                     @if(auth()->user()->hasRole('Super Admin') === true)
                                                         <th>Department</th>
                                                         @if($depts != null)
                                                             @foreach($depts as $dept)
                                                                 @if($userProfile->department_id == $dept->id)
                                                                     <td>{{ $dept->name }}</td>
                                                                 @endif
                                                             @endforeach
                                                         @else

                                                             <td class="text text-danger">
                                                                 No department mentioned</td>
                                                         @endif
                                                     @endif
                                                 </tr>

                                                 <tr>
                                                     @if($roles != null)
                                                         @foreach($roles as $role)
                                                             @if( $role->name == $userRole)
                                                                 <th>Role</th>
                                                                 <td>{{$role->name}}</td>
                                                             @endif
                                                         @endforeach
                                                     @else
                                                         <td>Role not assigned.</td>
                                                     @endif
                                                 </tr>
                                                 <tr>
                                                     <th>Account Type</th>
                                                     @if($user->active == '')
                                                         <td>Account not active</td>
                                                           @elseif($user->active == 1)
                                                         <td>Active</td>
                                                           @elseif($user->active == 2)
                                                         <td>Suspended</td>
                                                           @endif
                                                 </tr>

                                                 <tr>
                                                     <th>Omang</th>
                                                             @if($userProfile->omang != null)
                                                                 <td>{{  $userProfile->omang }}</td>
                                                             @else
                                                                 <td>Omang Id not mentioned.</td>
                                                             @endif
                                                             @if ($errors->has('omang'))
                                                                 <td class="col-12 text text-danger">
                                                                                        {{ $errors->first('omang') }}
                                                                                    </td>
                                                             @endif

                                                 </tr>
                                                 <tr>
                                                     <th>Address</th>
                                                     <td>{{ $userProfile->address }}</td>
                                                 </tr>
                                                 <tr>
                                                     <th>Passport</th>
                                                     <td>{{ $userProfile->passport }}</td>
                                                 </tr>
                                                 <tr>
                                                     <th>Mobile No.</th>
                                                     <td>{{  $userProfile->cellphone }}</td>
                                                 </tr>
                                                 <tr>
                                                     <th>Passport</th>
                                                     <td>{{  $userProfile->passport }}</td>
                                                 </tr>
                                                 </tbody>
                                             </table>

                                            </div>
                                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                                <div class="kt-form__actions">
                                                    <div class="row">
                                                        <div class="col-3"></div>
                                                        <div class="col-9">
                                                            <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Back</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="kt_portlet_base_demo_3_5_change_profile_picture" role="tabpanel">
                                    <form id="userEdit" action="{{ route('updateProfilePicture', $user->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet__body">
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label"></label>
                                                <div class="col-9">
                                                    <div class="kt-avatar kt-avatar--circle" id="profile_picture">
                                                        @if(\AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo))
                                                            <div class="kt-avatar__holder" style="background-image:url({{  \AlphaDirect\Helper::getCloudFrontURL($user->profile->profile_photo) }})"></div>
                                                        @else
                                                            <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        @endif
                                                    </div>
                                                    <span class="col-12 text text-danger">
                                                                            @if ($errors->has('profile_picture'))
                                                            <span class="col-12 text text-danger">
                                                                                    {{ $errors->first('profile_picture') }}
                                                                                </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9">
                                                        <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Back</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                </div>
                                <div class="tab-pane" id="kt_portlet_base_demo_3_2_change_password" role="tabpanel">
                                    <form id="userEdit" action="{{ route('updatePassword', $user->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                        <div class="kt-portlet__body" style="padding-top:0px !important">
                                            <p class="text text-warning">
                                                <i class="kt-menu__link-icon flaticon-warning"></i> If you don't want to change password... please leave them empty
                                            </p>

                                            {{--    <div class="form-group row">
                                                       <label for="example-text-input" class="col-3 col-form-label">Old Password</label>
                                                       <div class="col-9">
                                                           <input type="password" class="form-control" name="old_password"   placeholder="Enter previous password">
                                                           <span class="form-text text-muted"></span>
                                                           @if ($errors->has('old_password'))
                                                           <span class="col error">
                                                               <p class="text text-danger">{{ $errors->first('old_password') }}</p>
                                                           </span>
                                                           @endif
                                                       </div>
                                                   </div> --}}

                                            <div class="form-group row">
                                                <label for="password" class="col-3 col-form-label">Password</label>
                                                <div class="col-9">
                                                    <input type="password" id="password"  class="form-control" name="password"  autocomplete="off"  placeholder="Enter password">
                                                    <span class="form-text text-muted"></span>
                                                    @if ($errors->has('password'))
                                                        <span class="col error">
                                                                                        <p class="text text-danger">{{ $errors->first('password') }}</p>
                                                                                    </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label">Confirm Password</label>
                                                <div class="col-9">
                                                    <input type="password" class="form-control" name="confirm_password"  placeholder="Confirm password" autocomplete="off">
                                                    <span class="form-text text-muted"></span>
                                                    @if ($errors->has('confirm_password'))
                                                        <span class="col error">
                                                                                        <p class="text text-danger">{{ $errors->first('confirm_password') }}</p>
                                                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9">
                                                        @can('user-edit')
                                                            <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>
                                                        @endcan
                                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                        <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
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
    <!-- begin:: Footer -->
@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->

<!-- end:: Page -->

<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {

        // Private functions
        jQuery.validator.addMethod("uploadFile", function (val, element) {
            var size = element.files[0].size;
            console.log(size);
            if (size > 1048576)// checks the file more than 1 MB
            {

                return false;
            } else {

                return true;
            }
        }, "File size too large, should be less than 1MB");


        var demo1 = function () {
            $( "#userEdit" ).validate({
                // define validation rules
                rules: {
                    first_name: {
                        required: true,
                    },
                    last_name: {
                        required: true,
                    },
                    email: {
                        required: true,
                        email:true
                    },
                    cellphone: {
                        required: true,
                    },
                    omang: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    passport: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    profile_picture:{
                        // required: true,
                        extension:'jpe?g,png',
                        uploadFile:true,

                    },
                    password: {
                        minlength:6,
                        maxlength:12,
                    },
                    confirm_password:{
                        minlength : 6,
                        maxlength:12,
                        equalTo : "#password"
                    },

                },
                groups: {
                    validateGroup1: "omang passport",
                },
                messages:{
                    first_name: {
                        required: "Enter first Name",
                    },
                    last_name: {
                        required: "Enter Last Name",
                    },
                    email: {
                        required: "Enter email",
                        email:true
                    },

                    cellphone: {
                        required: "Enter phone number",
                    },
                    omang: {
                        require_from_group: "Please provide either your Omang Id or Passport",

                    },
                    passport: {
                        require_from_group: "Please provide either your Omang Id or Passport",

                    },
                    profile_picture:{
                        extension:"Image format not supported",
                    },
                    password: {
                        minlength:"Password should at least be 6 characters long",
                        maxlength:"Password should not be more than 12 characters long",
                    },
                    confirm_password:{
                        minlength : "Confirm Password should at least be 6 characters long",
                        equalTo : "Confirm password and Password should match"
                    },
                },

                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("#userEdit", -200);
                },

                submitHandler: function (form) {
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

    // Avatar Class definition
    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('profile_picture');
            }
        };
    }();
    jQuery(document).ready(function() {
        KTFormControls.init();
        KTAvatarDemo.init();
    });

</script>

<script>
    var KTBootstrapDatepicker = function () {
        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function () {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });

    $('#userEdit').submit(function(){
        var pass = $('#password').val();
        var cpass = $('#confirm_password').val();
        if( pass && cpass != null){
            if(pass != cpass){
                $('span.passMsg').show();
                $('span.cpassMsg').show();
                $("button#subbtn").css("display", "inline");
                $("button#LoadBtn").css("display", "none");
                $('html, body').animate({
                    scrollTop: $("#last_name").offset().top
                }, 500);
                return false;
            }
            else{
                $("button#subbtn").css("display", "none");
                $("button#LoadBtn").css("display", "inline");
                return true;
            }
        }
    });

    $('#password,#confirm_password').click(function() {
        $('span.passMsg').hide();
        $('span.cpassMsg').hide();
    });

</script>

</body>
<!-- end::Body -->
</html>
