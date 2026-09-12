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
                    <div class="kt-portlet__body">

                        {{--<div class="form-group row">
                            <label for="example-text-input" class="col-2 col-form-label">Region</label>
                            <div class="col-10">
                                    @if($regions->count() > 0)
                                        @foreach($regions as $region)
                                            @if($document->region == $region->id)
                                                <p class="form-control">{{$region->name}}</p>
                                            @endif
                                        @endforeach
                                    @else
                                        <p class="form-control">No records found</p>
                                    @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Enter Title</label>
                            <div class="col-10">
                                <p class="form-control">{{$document->title}}</p>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Enter Slug</label>
                            <div class="col-10">
                                <p class="form-control">{{$document->slug}}</p>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Text</label>
                            <div class="col-10">
                                <p class="form-control">{{html_entity_decode($document->text)}}</p>
                            </div>
                        </div>--}}
                        <div class="kt-section">
                            <div class="kt-section__content">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Region:</th>
                                        @if($regions->count() > 0)
                                            @foreach($regions as $region)
                                                @if($document->region == $region->id)
                                                    <td>{{$region->name}}</td>
                                                @endif
                                            @endforeach
                                        @else
                                            <td>Unavailable</td>
                                        @endif

                                    </tr>

                                    <tr>
                                        <th>Title:</th>
                                        @if($document->title != null)
                                            <td>{{$document->title}}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>

                                    <tr>
                                        <th>Slug:</th>
                                        @if($document->slug != NULL)
                                            <td>{{$document->slug}}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif

                                    </tr>

                                    <tr>
                                        <th>Text:</th>
                                        @if($document->text != null)
                                            <td>{{ html_entity_decode($document->text) }}</td>
                                        @else
                                            <td>Unavailable</td>
                                        @endif
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <a class="btn btn-secondary" href="{{ route('organizationdocument.index') }}" >Back</a>
                                </div>


                            </div>
                        </div>
                    </div>
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
