<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}" />
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
                    Edit Group
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ route('admin.companyname.index') }}" class="kt-subheader__breadcrumbs-link">Groups</a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                
                <form id="company_form" action="{{ route('admin.companyname.update',$companyname->id) }}"
                method="POST">
                    @csrf
                    @method('PUT')
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="name" class="col-3 col-form-label">Group Name</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="name" value="{{ $companyname->name }}" placeholder="Enter company name" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="address" class="col-3 col-form-label">Address</label>
                            <div class="col-8">
                                <textarea class="form-control" name="address" placeholder="Enter address">{{ $companyname->address }}</textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="email" class="col-3 col-form-label">Email</label>
                            <div class="col-8">
                                <input type="email" class="form-control" name="email" value="{{ $companyname->email }}" placeholder="Enter email">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="vat_no" class="col-3 col-form-label">VAT No</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="vat_no" value="{{ $companyname->vat_no }}" placeholder="Enter VAT number">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="status" class="col-3 col-form-label">Status</label>
                            <div class="col-8">
                                <select class="form-control" name="status">
                                    <option value="active" {{ $companyname->status == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ $companyname->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" class="btn btn-brand">Update</button>
                                    <a href="{{ route('admin.companyname.index') }}" class="btn btn-secondary">Cancel</a>
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
<!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')
</body>
<!-- end::Body -->
</html>
