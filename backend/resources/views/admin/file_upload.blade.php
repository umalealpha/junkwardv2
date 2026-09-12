<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
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
     <!-- check if is first time login -->
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create File
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}" class="kt-subheader__breadcrumbs-link"> File </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="fileupload" action="{{ route('uploadFile') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                   
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Version Number</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="version_number"  placeholder="Enter Version Number">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div> 

                        <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">File</label>
                                <div class="col-9">
                                    <input type="file" class="form-control-file" name="file"  placeholder="select file name" required>
                                    <span class="form-text text-muted"></span> 
                                    @if ($errors->has('file'))
                                        <span class="col-12 text text-danger">
                                            {{ $errors->first('file') }}
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
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.user.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>


                </form>
                <!--end::Form--> 
             
            </div>
            <!--end::Portlet--> 
            <div class="kt-portlet">  
                  <!--begin: Datatable -->  
                @if(isset($apkFiles))
               <table class="table table-striped table-bordered table-hover table-checkable" id="apkFiles">
                    <thead>
                    <tr>
                        <th>ID</th> 
                        <th>Created By</th>
                        <th>Version Number</th>
                        <th>File Path</th> 
                        <th>Last Modified</th>
                        <th>File Size</th>
                        <th>Created At</th> 
                       
                    </tr>
                    </thead>  

                   @foreach ($apkFiles as $file) 
                    <tbody> 
                        <tr> 
                            <td>{{ $file->id }}</td>  
                            <td>{{ $user->firstName }} {{ $user->lastName }}</td> 
                            <td>{{ $file->version_number }}</td> 
                           @if(str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->exists($file->filepath)))
                                <td><a href="{{ str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($file->filepath)) }}"><i class="la la-download"></i>{{ $file->filepath }}</a></td>
                                <td>{{  date("Y-m-d H:i:s", Storage::disk('s3')->lastModified($file->filepath)) }}</td> <!--convert UTC date format to normal date -->
                                <td>{{  number_format(str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->size($file->filepath)) / 1048576,2).' MB' }}</td> <!-- put disk s3 so it knows your want data from aws S3-->
                            @else 
                                 <td></td> 
                                 <td></td> 
                                 <td></td>
                            @endif 
                           
                            <td>{{ $file->created_at }}</td> 
                            

                        </tr>

                    </tbody>
                       
                   @endforeach
                </table> 
                @endif
                <!--end: Datatable -->
            </div>
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


<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>




</body>
<!-- end::Body -->
</html>