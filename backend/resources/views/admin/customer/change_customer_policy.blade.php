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
                Change Customer in Policy
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"> Customer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Change Customer in Policy</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="customerpolicyForm"  action="#" method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                    <h5 class="text-center"><b>Note : </b> Section A Find Customer and Select a customer in list and Section B  Enter Policy Number.</h5>
                    <hr>
                    <div class="row">
                   
                        <div class="col-6" style="border-right:  2px solid blue;">
                        <h5 class="text-center">Section A</h5>
                           <div class="row">
                               <div class="col-6 mb-2">
                                  <label for="example-text-input" class="form-label">Customer search by First Name / Last Name</label>
                               </div>
                               <div class="col-6 mb-2">
                                   <input type="text" onchange="myFunction51()" id="name" name="name" value="" placeholder="Customer search by First Name" class="form-control" minlength="4">
                               </div>
                               <div class="col-6 mb-2">
                                   <label for="example-text-input" class="form-label">Customer search by Email</label>
                               </div>
                               <div class="col-6 mb-2">
                                   <input type="text" onchange="myFunction52()" id="email" name="email" value="" placeholder="Customer search by Email" class="form-control" minlength="4">
                               </div>

                               <div class="col-6 mb-2">
                                  <label for="example-text-input" class="form-label">Customer search by Cellphone number </label>
                               </div>
                               <div class="col-6 mb-2">
                                  <input type="text" onchange="myFunction53()" id="cellphone" name="cellphone" value="" placeholder="Customer search by Cellphone" class="form-control" minlength="4" maxlength="10">
                               </div>
                               <div class="col-6 mb-2">
                                  <label for="example-text-input" class="form-label">Customer search by Omang/passport</label>
                               </div>
                               <div class="col-6 mb-2">
                                  <input type="text" onchange="myFunction54()" id="omangpassport" name="omangpassport" value="" placeholder="Customer search by Omang/passport" class="form-control" minlength="4">
                               </div>

                           </div>




                         
                        </div>
                        <div class="col-6">
                        <h5 class="text-center">Section B</h5>
                        <div class="form-group row">
                            <div class="col-3"><label for="example-text-input" class="form-label">Policy Number</label></div>
                            <div class="col-9">
                            <input type="text" onchange="policyid11()" id="policy_number" name="policy_number" value="" class="form-control" minlength="13"  placeholder="Policy Number" required>

                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6" style="border-right:  2px solid blue;">
                            <div  id="delete_confirm">

                                  <div class="modal-content" id="modal-content">


                                 </div>
                              </div>
                        </div>
                        <div class="col-6">
                        <div  id="delete_confirm1">

                              <div class="modal-content1" id="modal-content1">


                             </div>
                          </div>
                        </div>

                    </div>


                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" id="btn" value="Submit" class="btn btn-brand submit">Submit</button>
                                       <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                    </div>
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
    <div class="modal fade" id="delete_confirm_beneficiary" tabindex="-1" role="dialog"
        aria-labelledby="user_delete_confirm_title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-content_beneficiary">

            </div>
        </div>
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
<script>
    $(document).ready(function () {

   $("select").select2();
});
</script>
<script>

    $("#customerpolicyForm").validate({
        rules: {
            customer_id: "required",
            policy_number:{
                required:true,
                minlength:13,
            }

        },
        messages: {
            customer_id: "Select Customer Name",

            policy_number:{
                required:"Enter Policy Number",
                minlength:"Minimum length 13",
            }

        }
    });

</script>
    <script>
        function myFunction51(){
         var name = $('#name').val();

        $.ajax({
            url: '{{ route('admin.customer.getlist') }}',
            type: 'POST',
            datatype: 'json',
            data: {
                "_token": "{{ csrf_token() }}",
                "name": name,
            },
            success: function(data) {

                var append = '<div class="row">'+
                             '<div class="col-6"><label for="example-text-input" class="form-label">Customer List</label></div>'+
                              '<div class="col-6"><select onchange="customerdetail()" name="customer_id" id="select_page" style="padding: 0.65rem 1rem  !important;" class="form-control customer_id" >'+
                                '<option class="form-control" value="" >-- Select Name and Email--</option>';
                                $.each(data.data1, function( index, value ) {


                                    append += '<option  class="form-control" value="'+value.id+'" >'+value.firstName+' '+value.lastName+' '+value.email+'</option>';
                                });

                                append += '</select></div>';

                        $('#modal-content').empty();
                        $('#modal-content').html(append);
            },
            });
       };
       function myFunction52(){
         var email = $('#email').val();

        $.ajax({
            url: '{{ route('admin.customer.getlist') }}',
            type: 'POST',
            datatype: 'json',
            data: {
                "_token": "{{ csrf_token() }}",
                "email": email,
            },
            success: function(data) {

                var append = '<div class="row">'+
                             '<div class="col-6"><label for="example-text-input" class="form-label">Customer List</label></div>'+
                              '<div class="col-6"><select onchange="customerdetail()" name="customer_id" id="select_page" style="padding: 0.65rem 1rem  !important;" class="form-control customer_id" >'+
                                '<option class="form-control" value="" >-- Select Name and Email--</option>';
                                $.each(data.data1, function( index, value ) {


                                    append += '<option  class="form-control" value="'+value.id+'" >'+value.firstName+' '+value.lastName+' '+value.email+'</option>';
                                });

                                append += '</select></div>';

                        $('#modal-content').empty();
                        $('#modal-content').html(append);
            },
            });
       };
       function myFunction53(){
         var cellphone = $('#cellphone').val();

        $.ajax({
            url: '{{ route('admin.customer.getlist') }}',
            type: 'POST',
            datatype: 'json',
            data: {
                "_token": "{{ csrf_token() }}",
                "cellphone": cellphone,
            },
            success: function(data) {

                var append = '<div class="row">'+
                             '<div class="col-6"><label for="example-text-input" class="form-label">Customer List</label></div>'+
                              '<div class="col-6"><select onchange="customerdetail()" name="customer_id" id="select_page" style="padding: 0.65rem 1rem  !important;" class="form-control customer_id" >'+
                                '<option class="form-control" value="" >-- Select Name and Email--</option>';
                                $.each(data.data1, function( index, value ) {


                                    append += '<option  class="form-control" value="'+value.id+'" >'+value.firstName+' '+value.lastName+' '+value.email+'</option>';
                                });

                                append += '</select></div>';

                        $('#modal-content').empty();
                        $('#modal-content').html(append);
            },
            });
       };
       function myFunction54(){
         var omangpassport = $('#omangpassport').val();

        $.ajax({
            url: '{{ route('admin.customer.getlist') }}',
            type: 'POST',
            datatype: 'json',
            data: {
                "_token": "{{ csrf_token() }}",
                "omangpassport": omangpassport,
            },
            success: function(data) {

                var append = '<div class="row">'+
                             '<div class="col-6"><label for="example-text-input" class="form-label">Customer List</label></div>'+
                              '<div class="col-6"><select onchange="customerdetail()" name="customer_id" id="select_page" style="padding: 0.65rem 1rem  !important;" class="form-control customer_id" >'+
                                '<option class="form-control" value="" >-- Select Name and Email--</option>';
                                $.each(data.data1, function( index, value ) {
                                   append += '<option  class="form-control" value="'+value.id+'" >'+value.firstName+' '+value.lastName+' '+value.email+'</option>';
                                });

                                append += '</select></div>';

                        $('#modal-content').empty();
                        $('#modal-content').html(append);
            },
            });
       };

    </script>
    <script>
          function policyid11(){
                var id =  $('#policy_number').val();

                $.ajax({
                    url: '{{ route('admin.policy.detail') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": id,
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function(data) {
                        var premium_freq1 = data.data1.premium_freq;
                        if (premium_freq1 == 1){ premium_freq = 'Monthly';}
                        if (premium_freq1 == 2){ premium_freq = '3 Installments';}
                        if (premium_freq1 == 3){ premium_freq = 'Yearly';}
                        if (premium_freq1 == null){ premium_freq = 'Monthly';}
                       var first_premium1 = data.data1.first_premium;
                       if (first_premium1 == null ){first_premium = '-';}else{first_premium = first_premium1;}
                       var policyNumber1 = data.data1.policyNumber;
                       if (policyNumber1 == null ){policyNumber = '-';}else{policyNumber = policyNumber1;}
                       var leadSource1 = data.data1.leadSource;
                       if (leadSource1 == null ){leadSource = '-';}else{leadSource = leadSource1;}
                       var sum_assured1 = data.data1.sum_assured;
                       if (sum_assured1 == null ){sum_assured = '-';}else{sum_assured = sum_assured1;}
                       var customer_id1 = data.data1.customer_id;
                       if (customer_id1 == null ){customer_id = '-';}else{customer_id = customer_id1;}
                       var serial_code1 = data.data1.serial_code;
                       if (serial_code1 == null ){serial_code = '-';}else{serial_code = serial_code1;}
                       var BillingStart1 = data.data1.BillingStart;
                       if (BillingStart1 == null ){BillingStart = '-';}else{BillingStart = BillingStart1;}
                       var premium1 = data.data1.premium;
                       if (premium1 == null ){premium = '-';}else{premium = premium1;}
                        var append = '<table class="table">'+
                       '<tr><th>Policy Number :</th><td>'+policyNumber+'</td></tr>'+
                       '<tr><th>Lead Source :</th><td>'+leadSource+'</td></tr>'+
                       '<tr><th>Sum Assured :</th><td>'+sum_assured+'</td></tr>'+
                       '<tr><th>Customer Id :</th><td>'+customer_id+'</td></tr>'+
                       '<tr><th>Serial Code :</th><td>'+serial_code+'</td></tr>'+
                       '<tr><th>Billing Start :</th><td>'+BillingStart+'</td></tr>'+
                       '<tr><th>Billing Start Date :</th><td>'+data.billingStartDate+'</td></tr>'+
                       '<tr><th>First Premium :</th><td>'+first_premium+'</td></tr>'+
                       '<tr><th>Premium :</th><td>'+premium+'</td></tr>'+
                       '<tr><th>Premium freq :</th><td>'+premium_freq+'</td></tr>'+

                       '</table>';

                        $('#modal-content1').empty();
                        $('#modal-content1').html(append);

                    },
                });
            };

</script>
<script>
          function customerdetail(){
                var id =  $('#select_page option:selected').val();

                $.ajax({
                    url: '{{ route('admin.customer.detail') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": id,
                    },
                    type: 'post',
                    datatype: 'json',

                    success: function(data) {
                    var dlnumber1 = data.data2.driving_license_number;
                    if(dlnumber1 == null){ dlnumber = '-'; }else{ dlnumber = dlnumber1;}
                    var address1 = data.data2.address;
                    if(address1 == null){ address = '-'; }else{ address = address1;}
                    var city1 = data.city;
                    if(city1 == null){ city = '-'; }else{ city = city1;}
                    var middleName1 = data.data2.middleName;
                    if(middleName1 == null){ middleName = ''; }else{ middleName = middleName1;}
                    var omang1 = data.data2.omang;
                    if(omang1 == null){ omang = '-'; }else{ omang = omang1;}
                    var passport1 = data.data2.passport;
                    if(passport1 == null){ passport = ''; }else{ passport = passport1;}
                    var gender1 = data.data2.gender;
                    if(gender1 == null){ gender = '-'; }else{
                        gender = gender1;
                        if(gender == 1 ){gender = 'Male';}else{gender = 'Female';}

                        }
                      console.log(data);
                        var append = '<table class="table">'+
                       '<tr><th>Name :</th><td>'+data.data1.firstName+' '+middleName+' '+data.data1.lastName+'</td></tr>'+
                       '<tr><th>Gender :</th><td>'+gender+'</td></tr>'+
                       '<tr><th>Cellphone :</th><td>'+data.data1.cellphone+'</td></tr>'+
                       '<tr><th>Email :</th><td>'+data.data1.email+'</td></tr>'+
                       '<tr><th>Dob :</th><td>'+data.dob+'</td></tr>'+
                       '<tr><th>Driving license number :</th><td>'+dlnumber+'</td></tr>'+
                       '<tr><th>Omang :</th><td>'+omang+'</td></tr>'+
                       '<tr><th>Passport :</th><td>'+passport+'</td></tr>'+
                       '<tr><th>Address :</th><td>'+address+'<input type="hidden" id="customer_id1" name="customer_id1" value="'+data.data1.id+'"></td></tr>'+
                       '<tr><th>City :</th><td>'+city+'</td></tr>'+
                       '</table>';

                        $('#modal-content').empty();
                        $('#modal-content').html(append);

                    },
                });
            };

</script>
<script>
     $("#customerpolicyForm").on("click", "button.submit", function(event) {
                event.preventDefault();
                if($('#policy_number').val() != ''){
                var customer_id =  $('#customer_id1').val();
                var policy_number =  $('#policy_number').val();
if(customer_id != null && policy_number != null){
                $.ajax({
                    url: '{{ route('admin.customer.chenge_customer_policy_store_conform') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": policy_number
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function(data) {
                        $('#modal-content_beneficiary').append(append);
                        var append =
                            '<form action="{{ route('admin.customer.chenge_customer_policy_store') }}" method="POST" enctype="multipart/form-data" class="kt-form">'+
                            '<input type="hidden" name="_token" value="{{ csrf_token() }}" />'+
                            '<input type="hidden" name="customer_id" value="'+customer_id+'" />'+
                            '<input type="hidden" name="policy_number" value="'+policy_number+'" />'+
                            '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Change Customer in Policy </h5>' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                            '</div> ' +
                            '<div class="modal-body"> ' +

                            '<p>' + data.body + '</p> ' +
                            '</div> ' +
                            '<div class="modal-footer"> ' +
                            '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
                        $('#modal-content3').html(append);
                        if (data.status == 'success') {

                            append += '<button type="submit" class="btn btn-brand">Submit</button></div>';
                        }
                        append += '</div></form>';
                        $('#modal-content_beneficiary').empty();
                        $('#modal-content_beneficiary').html(append);
                        $('#delete_confirm_beneficiary').modal('show');
                    },
                });
            }else{
                alert('Please Select Customer in Customar list And Enter a valid Policy Number');
            }
                };
            });
</script>

</body>
<!-- end::Body -->
</html>
