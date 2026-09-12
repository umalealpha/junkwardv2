<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
                Premium Calculate
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard</a> 
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <div class="row">
                <div class="col-6">
                <!--begin::Form-->
                <form id="cron"    class="">
                    <!-- CSRF Token -->
                 
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-4 col-form-label">Premium (Amount in P)</label>
                            <div class="col-8">
                                <input type="text" class="form-control" id="Premium" name="cron_name" value=""  placeholder="Enter Amount">

                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-4"><label for="example-text-input" class="form-label">Premium Type  <span class="red-star">*</span></label></div>
                            <div class="col-8">
                                <select name="Premium_type" id="Premium_type" class="form-control Premium_type" >
                              <!--      <option value="" >-- Select Premium Type --</option> -->
                                    <option value="Monthly" >Monthly Premium</option>
                                    <option value="ThreeInstallment" >Three Installment Premium</option>
                                    <option value="Annual" >Annual Premium</option>
                                </select>
                            </div>
                        </div>
                      
                      
                    

                              
                        

                        </div>



               <!--     <div class="kt-portlet__body">
                 

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                    <button  id="Calculate"  class="btn btn-brand">Calculate</button> 
                                         <a class="btn btn-secondary" href="{{Route('admin-dashboard')}}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> -->
                </form>
                </div>
                <div class="col-6" id="result" style="display: none;">
                <h4 class="kt-subheader__title text-center mt-2 mb-1"> Result</h4>
                <hr>
                        <div class="row mb-2">
                                <div class="col-6">
                                <h5 class="kt-subheader__title"> Annual Premium :</h5>
                                </div>
                                <div class="col-6" >
                                <h5 class="kt-subheader__title" id="Annualx"></h5>
                                </div>
                        </div>
                        <div class="row mb-1">
                                <div class="col-6">
                                <h5 class="kt-subheader__title">Three Installment Premium :</h5>
                                </div>
                                <div class="col-6" >
                                <h5 class="kt-subheader__title" id="ThreeInstallmentx"></h5>
                                </div>
                        </div>
                        <div class="row mb-1">
                                <div class="col-6">
                                <h5 class="kt-subheader__title">Monthly Premium :</h5>
                                </div>
                                <div class="col-6" >
                                <h5 class="kt-subheader__title" id="Monthlyx"></h5>
                                </div>
                        </div>
                        <hr>
                        
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
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>




<script type="text/javascript">
    $(document).ready(function(){
        $('#Premium').on('change', function () {
            if($('#cron').valid()){
                $('#result').show();
                proses();
            }else{
                $('#result').hide(); 
            }
           
       });
       $('#Premium_type').on('change', function () {
        if($('#cron').valid()){
            $('#result').show();
               proses();
         }else{
                $('#result').hide(); 
            }
      
        });
        $('#Calculate').on('click', function () {
        if($('#cron').valid()){
            $('#result').show();
               proses();
         }else{
                $('#result').hide(); 
            }
      
        });
        function proses() {
            var    Premium =   $('#Premium').val();
            var    Premium_type =   $('#Premium_type').val();
            if(Premium_type === 'Monthly' ){
                var monthlyPremium =   Premium ;
            }
            if(Premium_type === 'ThreeInstallment' ){
                var monthlyPremium =  1.08 * Premium / 4;
            }
            if(Premium_type === 'Annual' ){
                var monthlyPremium =  1.08 * Premium / 12;
            }
              const result = calculatePremiums(monthlyPremium);
             print(result);
        }
      function print(result) {
        $('#Annualx').text(`P ${result.yearlyPremium}`);
        $('#ThreeInstallmentx').text(`P ${result.threeInstallmentPremium}`);
        $('#Monthlyx').text(`P ${result.monthlyInstallment}`);
      }


      function calculatePremiums(monthlyPremium) {

            const numberOfInstallments = 12;
            const yearlyPremium = monthlyPremium * numberOfInstallments;
            const monthlyInstallment = yearlyPremium / numberOfInstallments;
            const threeInstallmentPremium = monthlyInstallment * 4;

            return {
                monthlyInstallment: monthlyInstallment.toFixed(2),
                yearlyPremium: (yearlyPremium / 1.08).toFixed(2),
                threeInstallmentPremium: (threeInstallmentPremium / 1.08).toFixed(2),
            };
       }

 });
</script>
<script>
    "use strict";
    // Class definition
 

    var KTFormControls = function () {

  

        var demo1 = function () {
            $( "#cron" ).validate({

                rules: {
                    cron_name: {
                        required: true,
                        number:true
                    }
                   
                   
                   
                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("customerEdit", -200);
                    $('#btn').show();
                },
                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show()
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
       


</body>
<!-- end::Body -->
</html>
