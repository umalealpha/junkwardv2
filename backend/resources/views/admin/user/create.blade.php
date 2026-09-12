<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />

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

        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Create User
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}"
                            class="kt-subheader__breadcrumbs-link"> User </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="userCreate" action="{{ route('admin.user.store') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">First Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="first_name"
                                        placeholder="Enter first name" value="{{ old('first_name') }}">
                                    <span class="form-text text-muted"></span>
                                    @if ($errors->has('first_name'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('first_name') }}</p>
                                    </span>
                                    @endif

                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Last Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="last_name" id="last_name"
                                        placeholder="Enter last name" value="{{ old('last_name') }}">
                                    <span class="form-text text-muted"></span>
                                    @if ($errors->has('last_name'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('last_name') }}</p>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Email</label>
                                <div class="col-9">
                                    <input type="text" class="form-control emailField" id="cutomer_email" name="cutomer_email"
                                        placeholder="Enter email" value="{{ old('email') }}">
                                    <span class="form-text text-muted"></span>
                                    <span id="emailErrorMsg" style="color: red;display: none">Email already
                                        exists.</span>
                                    @if ($errors->has('email'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('email') }}</p>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Password</label>
                                <div class="col-5">
                                    <input type="text" class="form-control" name="password" id="password"
                                        placeholder="Enter password" autocomplete="off">
                                    @if ($errors->has('password'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('password') }}</p>
                                    </span>
                                    @endif
                                    <span class="text text-warning passMsg"
                                        style="color: red !important;display:none;">Password and confirm password should
                                        be same.</span>
                                </div>
                                <div class="col-2">
                                    <span class="btn btn-brand" style="height: 40px;" id="passwordSuggestion">Suggestions</span>
                                </div>
{{--                                <div class="col-2">--}}
{{--                                    <span class="btn btn-brand" style="height: 40px;" id="clearPassword">Clear</span>--}}
{{--                                </div>--}}
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Confirm Password</label>
                                <div class="col-5">
                                    <input type="text" class="form-control" autocomplete="off" name="confirmPass"
                                           id="confirm_password" placeholder="Re-Enter password">
                                    <span class="text text-warning cpassMsg"
                                          style="color: red !important;display:none;">Password and confirm password should
                                        be same.</span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-date-input" class="col-3 col-form-label">Date of Birth</label>
                                <div class="col-9">
                                    <input class="form-control kt_datepicker_1 restrictDate" type="text"
                                         name="dob" value="{{ old('dob') }}">
                                </div>
                            </div>

                            @if(auth()->user()->hasRole('Super Admin')|| auth()->user()->can('access-all users'))
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Agency</label>

                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please select agency"
                                            data-live-search="true" name="agency">
                                        @foreach($agencies as $key=>$agency)
                                            <option value="{{$agency->id}}">{{$agency->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                                @else
                                <input type="hidden" name="agency" value="{{ Auth::user()->agency_id }}" />
                            @endif

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Department</label>

                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please select department"
                                        data-live-search="true" name="dept">
                                        @foreach($depts as $dept)
                                        <option value="{{$dept->id}}">{{$dept->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Role</label>
                                <div class="col-9">
                                    <select class="form-control selectpicker kt_selectpicker" title="Please select role"
                                        data-live-search="true" multiple name="role[]">

                                        @foreach($roles as $role)
                                            
                                                <option value="{{$role->name}}">{{$role->name}}</option>
                                           
                                        @endforeach
                                    </select>
                                    @if ($errors->has('role'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('role') }}</p>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Omang</label>
                                <div class="col-9">
                                    <input type="text" class="form-control omang" name="omang" placeholder="Enter Omang"
                                        onkeypress="return isNumberKey(event)" pattern="[0-9]{9}" maxlength="9" value="{{ old('omang') }}">
                                    <span class="form-text text-muted"></span>
                                    <p class="OmangError" style="color:#e61c30;display:none;">Please Input numbers only
                                    </p>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Address</label>
                                <div class="col-9">
                                    <input type="textarea" class="form-control" name="address"
                                        placeholder="Enter address" value="{{ old('address') }}">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Passport</label>
                                <div class="col-9">
                                    <input type="textarea" class="form-control" name="passport"
                                        placeholder="Enter passport number" value="{{ old('passport') }}">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Mobile No.</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="mobile"
                                        placeholder="Enter mobile no." value="{{ old('mobile') }}">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-3 col-form-label">Gender</label>
                                <div class="col-9">
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="1">Male <span></span>
                                        </label>
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="0">Female <span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Commission</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="switchValue" type="checkbox" checked="checked" name="commission"
                                                value="1" onchange="statusMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="switchMsg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                        </label>
                                    </span>
                                </div>
                            </div>

                            @if(auth()->user()->hasRole('Super Admin')|| auth()->user()->can('access-Graphite Login Option'))
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Allow Graphite Login</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="loginValue" type="checkbox" checked="checked" name="graphite_login"
                                                   value="1" onchange="loginMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="loginMsg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                        </label>
                                    </span>
                                </div>
                            </div>
                            @endif

                            @if(auth()->user()->hasRole('Super Admin')|| auth()->user()->can('access-Reports Login Option'))
                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Allow Reporting Dashboard Login</label>
                                    <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="reportValue" type="checkbox" checked="checked" name="report_login"
                                                   value="1" onchange="reportMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="reportMsg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
                                        </label>
                                    </span>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Allow bypass 500k</label>
                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="bypassValue" type="checkbox" checked="checked" name="bypass_500k"
                                                   value="1" onchange="bypass()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="bypassMsg"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                Yes</h4>
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
                                        <button type="submit" value="Submit" id="subbtn"
                                            class="btn btn-brand">Submit</button>
                                        {{--  <button class="btn btn-brand" type="button" id="LoadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button> --}}
                                        <a class="btn btn-secondary" href="{{ route('admin.user.index') }}">Cancel</a>
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

    <!-- end:: Root -->


    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->

    @include('admin.layouts.scripts')
    <script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
        type="text/javascript"></script>


    <script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>

    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>


    <script>
        "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#userCreate" ).validate({
// define validation rules
                rules: {
                    first_name: {
                        required: true
                    },
                    last_name: {
                        required: true
                    },
                    cutomer_email: {
                        required: false,
                        cutomer_email:true,
                    },
                    password: {
                        required: true
                    },
                    confirmPass: {
                        required: true,
                        equalTo:'#password',
                    },
                    role:{
                        required:true,
                    },
                    passport: {
                        required: /* '#omang:blank', */ function () {
                                return $('input[name="omang"]').val().length == 0;
                            }
                    },
                     omang: {
                     required: /* '#passport:blank', */ function () {
                                return $('input[name="passport"]').val().length == 0;
                            }
                     },
                    /*   confirmPass: {
                     required: true,
                     },
                     dept: {
                     required: true
                     },
                     / address: {
                     required: true
                     },
                     omang: {
                     required: true
                     },
                     mobile: {
                     required: true
                     },  */

                },
                messages:{
                    confirmPass:{
                        equalTo : "Confirm password and Password should match"
                    },
                    omang: {
                        required: "Please provide omang Id or passport."
                    },
                    passport:{
                        required: "Please provide omang Id or passport."
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("userCreate", -200);
                },

                submitHandler: function (form) {
                    /* $('#btn').hide();
                     $('#loadBtn').show();*/
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
        $('.emailField').val('');
    });
    jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');
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
                templates: arrows,
                /* todayHighlight: true,
                minDate: new Date(1900,1-1,1),  */
                maxDate: '-18Y',
                orientation: "bottom left",
                endDate: '-18y',
                startDate: '-110y',
                format: "yyyy-mm-dd",
                changeMonth: true,
                changeYear: true,
                defaultDate: null,
                yearRange: "-100:+0",
                autoclose:true

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

    $('#userCreate').submit(function(){
        var pass = $('#password').val();
        var cpass = $('#confirm_password').val();
        /*    if( pass && cpass != null){
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

         } */
    });

    $('#password,#confirm_password').click(function() {
        $('span.passMsg').hide();
        $('span.cpassMsg').hide();
    });

    function isNumberKey(evt)
    {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31
                && (charCode < 48 || charCode > 57)){
            $('.OmangError').show();
            return false;
        }
        return true;
    }
    $('.restrictDate').datepicker({
        format: "yyyy-mm-dd",
        autoclose: true,
        orientation: "top",
        endDate: "today"

    });

    $(document).ready(function() {
        var ajaxRequest;
        $('.emailField').on('keyup',function() {
            var email = $(this).val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.user.checkEmail') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "email": email,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.count > 0) {
                            $('#emailErrorMsg').css('display','block');
                            $("#subbtn").attr("disabled", true);
                        } else {
                            $('#emailErrorMsg').css('display','none');
                            $("#subbtn").attr("disabled", false);
                        }
                    }
                });
            }, 200);
        });

        // $('#clearPassword').on('click',function() {
        //     $('#password').val('');
        //     $('#confirm_password').val('');
        // });

        $('#passwordSuggestion').on('click',function() {

            $('#password').val('');
            $('#confirm_password').val('');

            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.user.generatePassword') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        $('#password').val(data);
                        $('#confirm_password').val(data);
                    }
                });
            }, 200);
        });
    });
    </script>
<script>
    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Yes";
            document.getElementById("switchMsg").style.color="cornflowerblue";
            document.getElementById("switchValue").value = 1;
        }
        else {
            document.getElementById("switchMsg").innerHTML="No";
            document.getElementById("switchMsg").style.color="#ff4d4d";
            document.getElementById("switchValue").value = 0;
        }

    }

    function bypass(){
        var isChecked=document.getElementById("bypassValue").checked;
        if (isChecked){
            document.getElementById("bypassMsg").innerHTML="Yes";
            document.getElementById("bypassMsg").style.color="cornflowerblue";
            document.getElementById("bypassValue").value = 1;
        }
        else {
            document.getElementById("bypassMsg").innerHTML="No";
            document.getElementById("bypassMsg").style.color="#ff4d4d";
            document.getElementById("bypassValue").value = 0;
        }

    }
    function loginMsg(){
        var isChecked=document.getElementById("loginValue").checked;
        if (isChecked){
            document.getElementById("loginMsg").innerHTML="Yes";
            document.getElementById("loginMsg").style.color="cornflowerblue";
            document.getElementById("loginValue").value = 1;
        }
        else {
            document.getElementById("loginMsg").innerHTML="No";
            document.getElementById("loginMsg").style.color="#ff4d4d";
            document.getElementById("loginValue").value = 0;
        }

    }
    function reportMsg(){
        var isChecked=document.getElementById("reportValue").checked;
        if (isChecked){
            document.getElementById("reportMsg").innerHTML="Yes";
            document.getElementById("reportMsg").style.color="cornflowerblue";
            document.getElementById("reportValue").value = 1;
        }
        else {
            document.getElementById("reportMsg").innerHTML="No";
            document.getElementById("reportMsg").style.color="#ff4d4d";
            document.getElementById("reportValue").value = 0;
        }
    }
</script>
</body>
<!-- end::Body -->

</html>
