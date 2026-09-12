<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}"/>
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
        <!-- Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Pricing List</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Pricings</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('pricings.create') }}" class="btn btn-sm btn-elevate btn-brand" data-toggle="kt-tooltip" title="Add Pricing">
                        <span class="kt-opacity-11">Add Pricing</span>&nbsp;
                        <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="pricing_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gender</th>
                                <th>Age From</th>
                                <th>Age To</th>
                                <th>Product</th>
                                <th>Adult Dependent</th>
                                <th>Child Dependent</th>
                                <th>Main</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('includes.footer')
</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
    $(document).ready(function() {
        $('#pricing_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('pricings.index') }}",
            columns: [
                { data: 'id', name: 'id' },
                { data: 'gender', name: 'gender' },
                { data: 'age_from', name: 'age_from' },
                { data: 'age_to', name: 'age_to' },
                { data: 'product', name: 'product' },
                { data: 'adult_dependent', name: 'adult_dependent' },
                { data: 'child_dependent', name: 'child_dependent' },
                { data: 'main', name: 'main' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
</body>
</html>
