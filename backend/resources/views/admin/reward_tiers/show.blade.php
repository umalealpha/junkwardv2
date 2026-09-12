<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">View Reward Tier</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('reward-tiers.index')}}" class="kt-subheader__breadcrumbs-link"> Reward Tiers </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet">
                <div class="kt-portlet__body">
                    <table class="table table-striped m-table">
                        <tbody>
                        <tr>
                            <th>Name:</th>
                            <td>{{ $tier->name }}</td>
                        </tr>
                        <tr>
                            <th>Label:</th>
                            <td>{{ $tier->label }}</td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td>{{ $tier->description }}</td>
                        </tr>
                        <tr>
                            <th>Number of Months (condition1)</th>
                            <td>{{ $tier->condition1 }}</td>
                        </tr>
                        <tr>
                            <th>Is Bundled (condition2)</th>
                            <td>{{ $tier->condition2 == 1 ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Is DomCom (condition3)</th>
                            <td>{{ $tier->condition3 == 1 ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Multi Policy Holder (condition4)</th>
                            <td>{{ $tier->condition4 == 1 ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Level Point:</th>
                            <td>{{ $tier->level_point }}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if($tier->status == 1)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Tier Image:</th>
                            <td>
                                @if($tier->image)
                                    <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($tier->image) !!}" alt="{{ $tier->name }} Image" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                                @else
                                    <span class="text-muted">No image uploaded</span>
                                @endif
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
</body>
</html> 