<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />

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
                    Claim
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/policy') }}" class="kt-subheader__breadcrumbs-link"> Policy </a> <span class="kt-subheader__breadcrumbs-separator"></span>Process Claim<span class="kt-subheader__breadcrumbs-separator">
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="row">
                <div class="col-md-12">
                    <div class="kt-portlet">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        @if($type == 'Glass')
                                            Process Vehicle Claim
                                        @elseif($type == 'Life')
                                            Process Life Claim
                                        @elseif($type == 'cellphone')
                                            Process Cellphone Claim
                                        @elseif($type == 'key_loss')
                                            Process Loss Of Key Claim
                                        @elseif($type == 'Legal')
                                            Process Legal Claim
                                         @else
                                            Process Motor Vehicle Accident Claim
                                        @endif
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-widget-4">
                                    <form id="storeClaim" action="{{ route('admin.policy.storeClaim') }}"
                                          method="POST" enctype="multipart/form-data" class="kt-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                    <input type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                    <input type="hidden" name="type" value="{!! $type !!}">

                                    @if($type == 'Glass')
                                        @include('admin.policy.vehicle')
                                    @elseif($type == 'Life')
                                        @include('admin.policy.life')
                                    @elseif($type == 'key_loss')
                                            @include('admin.policy.key_loss')
                                    @elseif($type == 'cellphone')
                                       @include('admin.policy.cellphone')
                                    @elseif($type == 'Legal')
                                       @include('admin.policy.legal')
                                    @else
                                        @include('admin.policy.accident')
                                    @endif
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                </div>
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
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<!--end:: Global Optional Vendors -->

<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-timepicker.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>

<script>
    $(".legaloption").on("change",function(){
        var legal = this.value;

        if(legal == "1"){
            $(".ownlawyer").show();
            $('.representingMember').addClass("required");
            $('.lawyerTarrif').addClass("required");
        }else{
            $(".ownlawyer").hide();
            $('.representingMember').removeClass("required");
            $('.lawyerTarrif').removeClass("required");
        } 
    });
</script>

    @if($type == 'Life')
        <script>
            "use strict";
            // Class definition
            var KTFormControls = function () {
               // Private functions
                jQuery.validator.addMethod("future", function(value, element) {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");

                var demo1 = function () {
                    $( "#storeClaim" ).validate({
                        ignore: [],
                        // define validation rules
                        rules: {
                            date_of_death: {
                                required: true,
                                future: true
                            },
                            description: {
                                required: true
                            },

                        },
                        messages: {
                            date_of_death: {
                                required: "Please enter Date of Death",
                                future: "Please enter only past dates"
                            },
                            description: "Please enter Description",
                        },
                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },
                        submitHandler: function (form) {
                            form.submit(); // submit the form
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
        </script>
    @endif

    @if($type == 'Legal')
    <script>
        "use strict";
        // Class definition
        var KTFormControls = function () {
           // Private functions
            jQuery.validator.addMethod("future", function(value, element) {
                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
            }, "Please enter only past dates");

            var demo1 = function () {
                $( "#storeClaim" ).validate({
                    ignore: [],
                    // define validation rules
                    rules: {
                        legal_firm: {
                            required: true,
                        },
                        declaration:{
                           required:true,
                        },
                        nofalseinfo:{
                            required:true,
                        },
                        signature:{
                            required:true,
                        },
                        tariffs_1:{
                            required:true,
                        },
                        tariffs_2:{
                            required:true,
                        },
                       
                    },
                    messages: {
                        declaration:{
                            required:"You must agree to declaration",
                        },
                        legal_firm: {
                            required: "Please enter Date of Death",
                        },
                        nofalseinfo:{
                            required:"You must agree to terms",
                        },
                        signature:{
                            required:"You must agree to terms",
                        },
                        tariffs_1:{
                            required:"You must agree to terms",
                        },
                        tariffs_2:{
                            required:"You must agree to terms",
                        },
                       
                    },
                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },
                    submitHandler: function (form) {
                        form.submit(); // submit the form
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
    </script>
@endif

    @if($type == 'Glass')
        <script>
            "use strict";
            // Class definition
            var KTFormControls = function () {
    // Private functions
                jQuery.validator.addMethod("future", function(value, element) {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");
                //Vehicle Registration
                jQuery.validator.addMethod("license", function(value, element) {
                    return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
                }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');
                var demo1 = function () {
                    $( "#storeClaim" ).validate({
                        ignore: [],
                        // define validation rules
                        rules: {
                            incidentDate: {
                                required: true,
                                future: true
                            },
                            cause: {
                                required: true
                            },

                        },
                        messages: {
                            incidentDate: {
                                required: "Please enter Date of Damage",
                                future: "Please enter only past dates"
                            },
                            cause: "Please enter Description",
                        },
                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },
                        submitHandler: function (form) {
                            form.submit(); // submit the form
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
        </script>
    @endif

    @if($type == 'cellphone')
        <script>
            "use strict";
            // Class definition
            var KTFormControls = function () {
    // Private functions
                jQuery.validator.addMethod("future", function(value, element) {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");

                var demo1 = function () {
                    $( "#storeClaim" ).validate({
                        ignore: [],
                        // define validation rules
                        rules: {
                            damage_extent: {
                                required: true
                            },
                            lossDate: {
                                required: true,
                                future: true
                            },
                            driver_dob: {
                                required: true,
                                future: true
                            },
                            cell_phone_make: {
                                required: true
                            },
                            descriptionofLoss: {
                                required: true
                            },
                            police_station: {
                                required: true
                            },
                            case_number: {
                                required: true
                            },
                            contact_number: {
                                required: true
                            },
                            date_reported: {
                                required: true,
                                future: true
                            },
                            ITC_reference_number: {
                                required: true
                            },
                            date_reported_to_alpha: {
                                required: true,
                                future: true
                            },
                        },
                        messages: {
                            date_of_death: {
                                required: "Please enter Date of Death",
                                future: "Please enter only past dates"
                            },
                            description: "Please enter Description",
                        },
                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },
                        submitHandler: function (form) {
                            form.submit(); // submit the form
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
        </script>
    @endif

    @if($type == 'Accident')
        <script>
            "use strict";
            // Class definition
            var KTFormControls = function ()
            {
            // Private functions
                jQuery.validator.addMethod("future", function(value, element)
                {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");
                //Vehicle Registration
                jQuery.validator.addMethod("license", function(value, element)
                {
                    return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
                }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');

                var demo2 = function ()
                {
                    $( "#storeClaim" ).validate({
                        // define validation rules
                        ignore: [],
                        ignore: ":hidden",
                        rules: {
                            claim_number: {
                                required: true
                            },
                            location: {
                                required: true
                            },
                            claim_sub_status: {
                                required: true
                            },
                            claim_status: {
                                required: true
                            },
                            paid_amount: {
                                required: true
                            },
                            reserve_amount: {
                                required: true
                            },
                            first_visit: {
                                required: true,
                                future:true
                            },
                            claim_allocated_on: {
                                required: true,
                                future:true
                            },
                            driver_dob:{
                                future:true
                            },
                            claim_allocated_to: {
                                required: true
                            },
                            coverages: {
                                required: true
                            },
                            co_attorney_assigned_date: {
                                required: false,
                                future:true
                            },
                            attorney_assigned_date: {
                                required: false,
                                future:true
                            },
                            co_attorney: {
                                required: true
                            },
                            primary_attorney: {
                                required: true
                            },
                            event_name: {
                                required: true
                            },
                            representative: {
                                required: true
                            },
                            incident_date: {
                                required: true,
                                future:true
                            },
                            loss_type: {
                                required: true
                            },
                            claim_type: {
                                required: true
                            },
                            claim_sub_type: {
                                required: true
                            },
                            reported_by: {
                                required: true
                            },
                            loss_description: {
                                required: true
                            },
                            date_of_accident:{
                                required: true,
                                future:true
                            },
                            place_of_accident:{
                                required: true
                            },
                            time_of_accident:{
                                required: true
                            },
                            recovery_address:{
                                required: true
                            },
                            recovery_email:{
                                required: true
                            },
                        },

                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },

                        submitHandler: function (form) {
                            $('#saveBtn').hide();
                            $('#loadBtn').show();
                            form[0].submit(); // submit the form
                        }
                    });
                }
                return {
                    // public functions
                    init: function() {
                        demo2();
                    }
                };

            }();
        </script>
    @endif

    @if($type == 'key_loss')
    <script>
        "use strict";
        // Class definition
        var KTFormControls = function () {
            // Private functions
            jQuery.validator.addMethod("future", function(value, element) {
                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
            }, "Please enter only past dates");
            //Vehicle Registration
            jQuery.validator.addMethod("license", function(value, element) {
                return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
            }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');
            var demo1 = function () {
                $( "#storeClaim" ).validate({
                    ignore: [],
                    // define validation rules
                    rules: {
                        lossDate: {
                            required: true,
                            future: true
                        },
                        cause: {
                            required: true
                        },

                    },
                    messages: {
                        lossDate: {
                            required: "Please enter Date of Damage",
                            future: "Please enter only past dates"
                        },
                        cause: "Please enter Description",
                    },
                    //display error alert on form submit
                    invalidHandler: function(event, validator) {
                        $('html, body').animate({
                            scrollTop: $(validator.errorList[0].element).offset().top - 200
                        }, 1000);
                    },
                    submitHandler: function (form) {
                        form.submit(); // submit the form
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
    </script>
@endif


<script>

    // $('#reason').on('change',function(){
    //     if($this.val()){
    //         var reason = this.options[this.selectedIndex].text;
    //         $(this).selectpicker('refresh');
    //         $('#dateOfLoss').text('Date of '+reason);
    //     }else{
    //         alert('hey');
    //     }
    // });

    "use strict";
    // Class definition
    var KTBootstrapDatepicker = function ()
    {
        var arrows;
        if (KTUtil.isRTL())
        {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        }
        else
        {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function ()
        {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });

            $('.child_dob').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                // templates: arrows,
                format: 'dd-mm-yyyy',
                endDate: "today",
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();
    // @if(session()->has('message'))
    // Toastify({
    //     text: "{{session()->get('message')}}",
    //     duration: 4000,
    //     newWindow: true,
    //     gravity: "top", // `top` or `bottom`
    //     positionRight: true, // `true` or `false`
    //     backgroundColor: "#00C851",
    // }).showToast();


    // @endif
</script>
<script>
    // Avatar Class definition
    var KTAvatarDemo = function() {
        return {
// Init demos
            init: function() {
                var avatar1 = new KTAvatar('incidentFront');
                var avatar2 = new KTAvatar('incidentBack');
                var avatar3 = new KTAvatar('incidentRight');
                var avatar4 = new KTAvatar('incidentLeft');
                var death_certificate = new KTAvatar('death_certificate');
                var avatar6 = new KTAvatar('driving_license');
                var avatar7 = new KTAvatar('omang_pic');
                var avatar8 = new KTAvatar('proof_residence');
                var avatar9 = new KTAvatar('proof_income');
                var avatar10 = new KTAvatar('passport_pic');
                var avatar11 = new KTAvatar('police_affidavit');
                var avatar12 = new KTAvatar('quote_1');
                var avatar13 = new KTAvatar('quote_2');
                var avatar14 = new KTAvatar('cell_phone_front');
                var avatar15 = new KTAvatar('cell_phone_back');
                var avatar16 = new KTAvatar('cell_phone_left');
                var avatar17 = new KTAvatar('cell_phone_right');
                var avatar18 = new KTAvatar('cell_phone_top');
                var avatar19 = new KTAvatar('cell_phone_bottom');

            }
        };
    }();

</script>

<script>
    function vehicleMsg()
    {
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#sup').delay(100).slideDown(500);
            $('#sup').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#sup').delay(100).slideUp(500);
            $('#sup').rules('remove',  'required');
        }
    }
</script>

<script>
    function isRecoveryInvolved(){
        var isChecked=document.getElementById("recovery_involved").checked;
        if (isChecked)
        {
            $('#recovery_involved_div').delay(100).slideDown(500);
        }
        else
        {
            $('#recovery_involved_div').delay(100).slideUp(500);
        }
    }

    $(document).ready(function (){
            $(document).on('click', '.thirdPartyInsured', function () {
        // var isChecked=document.getElementsByClassName("thirdPartyInsured").checked;
        // if ("input:checkbox[class=thirdPartyInsured]:checked")
            var isChecked=$(this).is(":checked");
            // var thisVal = $(this);
            if (isChecked == true) {
                $(this).parent().find('.tpInsuredMsg').html("Yes");
                $(this).parent().find('.tpInsuredMsg').css('color','cornflowerblue');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).$(".tpInsuredMsg").html("Yes");
            } else {
                $(this).parent().find('.tpInsuredMsg').html("No");
                $(this).parent().find('.tpInsuredMsg').css('color','#ff4d4d');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideUp(500);
                // $(this).$(".tpInsuredMsg").html("No");
            }
        });
    });

    function thirdPartyMsg()
    {
        var isChecked=document.getElementById("thirdPartyValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#details').delay(100).slideDown(500);
            $('#members_section').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#details').delay(100).slideUp(500);
            $('#members_section').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }
    }

    jQuery(document).ready(function()
    {
        KTFormControls.init();
        KTBootstrapDatepicker.init();

        // Class initialization on page load
        KTAvatarDemo.init();
        $('#addBeneficiary').click(function ()
        {
            KTBootstrapDatepicker.init();
            $('#addBeneficiaryDiv').toggle();
            $('#addBeneficiary').toggle();
        });
        $(".col-12").on('click', '.kt-repeater__add-data', function()
        {
            KTBootstrapDatepicker.init();
        });
    });

</script>
<script>
    function checkAttorney()
    {
        var attorneyChecked=document.getElementById("attorneyValue").checked;
        if (attorneyChecked)
        {
            $('#attorneyDiv').delay(100).slideDown(500);
        }
        else
        {
            $('#attorneyDiv').delay(100).slideUp(500);
        }

    }
</script>

<script>
    {{-- script for vehicle make dropdown ends--}}
       $(document).ready(function()
    {
        var  ajaxRequest;
        $(".addOtherDetails").on("click",function(){
            $('.make').selectpicker("refresh");
        });
    });
    /* script for vehicle make dropdown ends*/

    /*script for vehicle model dropdown starts*/
    $(document).ready(function()
    {
        var ajaxRequest;
        $('#details').on('change','.make', function()
        {
            var make = $(this).val();
            var append = '';
            var $t = $(this);
            ajaxRequest = setTimeout(function(sn)
            {
                $.ajax({
                    url: '{{ route('admin.policy.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data)
                    {
                        if (data)
                        {
                            console.log(data);
                            append += '<div class="form-group">';
                            append += '<label>Model </label>';
                            append += '<select class="form-control kt_selectpicker model" name="model[]" data-live-search="true" title="Please choose model">';
                            $.each(data.count, function(key, modal)
                            {
                                append += '<option value="' + modal + '">' + modal + '</option>';

                            });
                            append += '</select></div>';
                            $t.parent().parent().next().children().replaceWith(append);
                        }

                        else
                        {
                            append += '<h3>No data is available</h3>';
                        }
                    }
                });
            }, 200);
        });
    });

</script>


<script>
    /* validation for checking reserve amount less than sum assured */
    function delay(callback, ms)
    {
        var timer = 0;
        return function()
        {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function ()
            {
                callback.apply(context, args);
            }, ms || 0);
        };
    }

    $(document).ready(function()
    {
        var ajaxRequest;
        $('#reserve_amount').keyup(delay(function (e)
        {
            var reserve_amount = $('#reserve_amount').val();
            var policyId = $('#policyId').val();
            ajaxRequest = setTimeout(function(sn)
            {
                $.ajax({
                    url: '{{ route('admin.policy.checkreserve_amount') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "reserve_amount": reserve_amount,
                        "policyId":policyId
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if( data.error == 0)
                        {
                            $( "#saveBtn" ).prop( "disabled", false );
                            $('#reserveMsg').css('display', 'none');
                        }
                        else
                        {
                            var sum_assured = data.sum_assured;
                            if(data.sum_assured == null){
                                sum_assured = 0;
                            }
                            $('#reserveMsg').html('Please enter reserve amount less than '+ ' '+sum_assured);
                            $('#reserveMsg').css('display', 'block');
                            $('#saveBtn').prop('disabled',true);
                        }
                    }
                });
            }, 200);
        },400));


        /*validation for paid amount should be less than reserve amount*/
        $("#paid_amount").keyup(function()
        {
            var reserve_amount = $('#reserve_amount').val();
            var paid_amount = $('#paid_amount').val();
            if(paid_amount < reserve_amount)
            {
                $( "#saveBtn" ).prop( "disabled", false );
                $('#paidmsg').css('display', 'none');
            }
            else
            {
                $( "#saveBtn" ).prop( "disabled", true );
                $('#paidmsg').html('Paid amount should be less than reserve amount');
                $('#paidmsg').css('display', 'block');
            }
        });



    });
</script>
</body>
<!-- end::Body -->
</html>
