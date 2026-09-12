<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
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
<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Benefits List</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Benefits</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('benefits.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="kt-tooltip" title="Add Benefit" data-placement="left"> <span class="kt-opacity-11">Add Benefit</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="benefits_table">
                        <thead>
                        <tr>
                            <th>Image</th>
                            <th>Tag</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Point</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($benefits as $benefit)
                            <tr>
                                <td>
                                    @if($benefit->image)
                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($benefit->image) !!}" alt="{{ $benefit->tag }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    @else
                                        <div style="width: 50px; height: 50px; background-color: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999;">
                                            <i class="fa fa-image"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $benefit->tag }}</td>
                                <td>{{ $benefit->type }}</td>
                                <td>{{ $benefit->price }}</td>
                                <td>{{ $benefit->point }}</td>
                                <td>{{ $benefit->status == 1 ? 'Active' : 'Inactive' }}</td>
                                <td>
                                    <a href="{{ route('benefits.show', $benefit->id) }}" class="btn btn-info btn-sm">View</a>
                                    <a href="{{ route('benefits.edit', $benefit->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function() {
        $('#benefits_table').DataTable();
    });
</script>
</body>
</html> 