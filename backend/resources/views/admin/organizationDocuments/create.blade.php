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
   
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Organization Document Templates
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link">
                    Organization Document Templates </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="docForm" action="{{ route('organizationdocument.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">

                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <!-- CSRF Token -->
                    <div class="kt-portlet__body">

                    <div class="form-group row">
                        <label for="example-text-input" class="col-2 col-form-label">Region</label>
                        <div class="col-10">
                            <select class="form-control kt_selectpicker"
                                    title="Please choose Region" data-live-search="true"
                                    id="user_label"  name="region">
                                @if($regions->count() > 0)
                                    @foreach($regions as $region)
                                        <option value="{{$region->id}}">{{$region->name}}</option>
                                    @endforeach
                                @else
                                    <option value="">No records found</option>
                                @endif
                            </select> 
                            @if ($errors->has('region'))
                            <span class="col-12 text text-danger">
                                {{ $errors->first('region') }}
                            </span>
                            @endif
                        </div>
                    </div>

                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Enter Title</label>
                            <div class="col-10">
                                <input  class="form-control" name="title">
                                @if ($errors->has('title'))
                                <span class="col-12 text text-danger">
                                    {{ $errors->first('title') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Enter Slug</label>
                            <div class="col-10">
                                <input  class="form-control" name="slug">
                                @if ($errors->has('slug'))
                                <span class="col-12 text text-danger">
                                    {{ $errors->first('slug') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Text</label>
                            <div class="col-10">
                                <textarea class="form-control" id="summary-ckeditor"
                                name="text"></textarea> 
                                @if ($errors->has('text'))
                                <span class="col-12 text text-danger">
                                    {{ $errors->first('text') }}
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
                                    <button type="submit" style="margin-left: 190px;" value="Submit" class="btn btn-brand">Submit</button>
                                    <a class="btn btn-secondary" href="{{ route('organizationdocument.index') }}" >Cancel</a>
                                </div>


                            </div>
                        </div>
                    </div>
                </form>
                @if($errors->any())
                    <p>{{$errors->first()}}</p>
                @endif
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


<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('vendor/unisharp/laravel-ckeditor/ckeditor.js') }}"></script>

<script>
    //Insert Label
    $('#user_label').on('change',function(){
        var  user_label = $(this).val();
        var optionText = $("#user_label option:selected").text();
        if(user_label != -1){
            var dyanmic_field = '[['+optionText + '_' + user_label+ ']]';
            var $txt = jQuery("#summary-ckeditor");
            var caretPos = $txt[0].selectionStart;
            myValue = dyanmic_field.trim();
            CKEDITOR.instances['summary-ckeditor'].insertText(myValue);
        }

    });

    CKEDITOR.replace( 'summary-ckeditor', {
        toolbar: 'full',
        height: '200px',
    });

</script>



</body>
<!-- end::Body -->
</html>
