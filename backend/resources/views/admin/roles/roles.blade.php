<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

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
                 Role
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{!! route('admin.roles.index') !!}" class="kt-subheader__breadcrumbs-link"> Role </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Role</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="batchForm" action="{{ route('admin.roles.rolesStoreUpdate') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="col-xs-6 col-sm-6 col-md-6">
                            <div class="form-group">
                                <strong>Role Name:</strong>
                               
                                 <select class="form-control kt_selectpicker" id="Roles"
                                        name="Roles" data-live-search="true"
                                        title="Select Role" required>
                                        <option value="">Select Role</option>

                                        @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name}}</option>
                                        @endforeach
                                </select>
                               
                            </div>
                        </div>

                        <div class="col-xs-12 col-sm-12 col-md-12">

                            <div class="form-group">
                                <strong>Role Under Roles:</strong>
                                <br>
                                <label class="kt-checkbox kt-checkbox--brand selectSpan">
                                        <input type="checkbox" name="selectAll" id="selectAll" value="0">
                                     Select All
                                    <span></span>
                                </label>
                               <div id="underRoles"></div>
                            </div>
                        </div>


                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="btn"  class="btn btn-brand" onclick="loadingButton()">Submit</button>
                                    <button type="button" id="loadingBtn" class="btn btn-brand" style="display:none"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...</button>
                                    <a class="btn btn-secondary" href="{{ route('admin.roles.index') }}" >Cancel</a>
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
<script>
    $('.roleName').keyup(function(){
        var role = $(this).val();
        if (role == "Super Admin" || role == "superadmin" || role == "admin"){
            $('.selectSpan').attr('hidden',false);
        }
        else{
            $('.selectSpan').attr('hidden',true);
        }

    });

    function loadingButton(){
        document.getElementById("btn").style.display = "none";
        document.getElementById("loadingBtn").style.display = "block";
    }
    $("#Roles").change(function(){
        var value = $('#Roles').val();
     
        if (value == ''){
       
        }
        else{
         $.ajax({
                    url: '{{ route("admin.roles.roleUnderRolesData") }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": value
                    },
                    type: 'post',
                    datatype: 'json',
                    beforeSend: function() {
                                $("#loader").show();
                            },
                    success: function(data) {
                            $(".selectSpan").show();
                            $("#underRoles").html('');
                            $("#underRoles").append('');
                              
                            $.each(data.roles, function (key,val) {
                              var  inputs = '<input  type="checkbox" name="roleName[]" value="'+val.id+'"';
                              $.each(data.data, function (x,y) {
                                 if( y == val.id ){ inputs += "checked";}
                              });
                              
                               inputs += '> '+val.name+'</label> <br/>';
                               $("#underRoles").append(inputs);
                            });

                           
                        
                        
                    },
                    complete:function(data){
                                $("#loader").hide();
                            },
                });
            
        }

    });
</script>
<script>
$(".selectSpan").hide();
    $("#selectAll").change(function(){
       var value = $('#selectAll').val();
       if (value == 0){
           $("input:checkbox").prop('checked', true);
           $('#selectAll').attr("value","1");
       }
       else{
           $("input:checkbox").prop('checked', false);
           $('#selectAll').attr("value","0");
       }

    });

</script>
</body>
<!-- end::Body -->
</html>
