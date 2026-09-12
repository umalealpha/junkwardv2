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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit Policy Document
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Documents </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
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
                <form id="docUpdate" action="{{ route('admin.documents.updateDoc',$document->id) }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Select Product:</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="product" name="product" title="Please choose product" data-live-search="true" id="product" required>
                                    <option value='0' @if($document->product_id == 0) selected  @endif>Not required</option>
                                    <option value='-1' @if($document->product_id == -1) selected  @endif>All Products</option>
                                    @foreach($products as $product)
                                        <option value="{!! $product->id !!}" @if($product->id == $document->product_id) selected  @endif content="{!! $product->name !!}">{!! $product->name !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if($document->product_id == 1 && $plan != null)
                        <div class="form-group row plans">
                     
                            <label class="col-3 col-form-label">Product Plan :</label>
                            <div class="col-9">
                            <select class="form-control kt_selectpicker" name="plan_id" value="{{ old('plan') }}" id="plan">
                                    @foreach($plan as $pid)
                                        <option value="{!! $pid->id !!}" @if($pid->id == $document->plan_id) selected  @endif content="{!! $pid->name !!}">{!! $pid->name !!}</option>
                                    @endforeach
                        
                            </select>
                            </div>
                        
                        </div>
                       @else
                       <div class="form-group row plans plans2">
                     
                            <label class="col-3 col-form-label">Product Plan :</label>
                            <div class="col-9">
                            <select class="form-control kt_selectpicker" name="plan_id" value="{{ old('plan') }}" id="plan">
                            </select>
                            </div>
                        
                        </div>
                      @endif
                      
                       
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Document Category:</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="document_cat" name="document_cat" title="Please choose document category" data-live-search="true" required>
                                    @foreach($doc_types as $type)
                                        <option value="{!! $type->value !!}" @if($type->value == $document->category) selected @endif content="{!! $type->value !!}">{!! $type->value !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Document Name:</label>
                            <div class="col-9">
                                <input  class="form-control" value="{{ $document->name }}" name="document_name"  title="Document name is required" placeholder="Please enter document name">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Upload Document</label>
                            <div class="col-2">
                                <div class="kt-avatar" id="document" style="float: left; clear: left;">
                                    @if($document->link != null)
                                        <div class="kt-avatar__holder"
                                         style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($document->link) !!})"></div>
                                    @else
                                        <div class="kt-avatar__holder"
                                             style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @endif

                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' name="document"
                                        <?php echo config('app.accept_attr'); ?>
                                            <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                            <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="statusValue" value="1" type="checkbox" @if($document->status)
                                            checked="checked" @endif name="statusValue"
                                                   onchange="status()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($document->status)
                                                <h4 id="status"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue">
                                                Active</h4>
                                            @else
                                                <h4 id="status"
                                                    style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                Inactive</h4>
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
                                    <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.documents.index') }}" >Cancel</a>
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
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#docUpdate" ).validate({
// define validation rules
                rules: {
                    document_name: {
                        required: true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("accountCreate", -200);
                },

                submitHandler: function (form) {
                    $('#submitbtn').hide();
                    $('#loadBtn').show();
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

    function status(){
        var isChecked=document.getElementById("statusValue").checked;
        if (isChecked){
            document.getElementById("status").innerHTML="Active";
            document.getElementById("status").style.color="cornflowerblue";

        }
        else {
            document.getElementById("status").innerHTML="In-active";
            document.getElementById("status").style.color="#ff4d4d";
        }

    }

    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('document');

            }
        };
    }();
    jQuery(document).ready(function(){
        KTAvatarDemo.init();
    });
</script>
<script>
   var pid =  '{{ $document->product_id }}';
   console.log(pid);
   if(pid != 1){
    $('.plans2').hide();
   }

        $('#product').on('change',function(){
        var productId = $(this).val();
        if(productId == 1){
            ajaxRequest = setTimeout(function(sn) {
                $("#loader").show();
                $.ajax({
                    url: '{{ route("admin.ProductPlanDocs") }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "product_id": productId,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        $("#loader").hide();
                        var plans = data.productPlans;
                        var sel = "Select Product Plan";
                        $('#plan').empty();
                        if (plans != null){
                            $('#plan').append('<option value="">Please select product plan</option>');
                            $('.plans').show();
                            jQuery.each( plans, function( i, val ) {
                                $('#plan').append('<option value="' + val.id + '">' + val.slug + '</option>');
                            });
                        }else {
                            $('#plan').empty();
                            $('.plans').show();
                            $('#plan').append('<option value="-2">No Product plan Found</option>');
                        }
                    },
                });
            },200);
        }else{
            $('.plans').hide();
           
        }

    });

</script>

</body>
<!-- end::Body -->
</html>